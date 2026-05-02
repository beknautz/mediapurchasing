<?php
/**
 * src/RunwayVideoService.php
 * AI Video Studio — Runway ML video generation service.
 * Supports mock mode (for development) and real Runway Gen-3 API calls.
 *
 * API docs: https://docs.dev.runwayml.com/
 * Auth: Authorization: Bearer {RUNWAY_API_KEY}  +  X-Runway-Version: 2024-11-06
 */

class RunwayVideoService extends BaseService
{
    private const API_BASE    = 'https://api.dev.runwayml.com/v1';
    private const API_VERSION = '2024-11-06';  // keep updated per Runway changelog

    // Runway only supports 5 or 10 seconds for Gen-3
    private const SUPPORTED_DURATIONS = [5, 10];

    // Map campaign aspect_ratio strings → Runway gen4.5 ratio strings
    // gen4.5 only supports "1280:720" (landscape) and "720:1280" (portrait)
    private const RATIO_MAP = [
        '9:16'  => '720:1280',   // portrait — most common for ads
        '16:9'  => '1280:720',   // landscape
        '1:1'   => '1280:720',   // closest supported
        '4:3'   => '1280:720',   // closest supported
        '3:4'   => '720:1280',   // closest supported
        '21:9'  => '1280:720',   // closest supported
    ];

    // -----------------------------------------------------------------------
    // queueVideoJob()
    // -----------------------------------------------------------------------
    public function queueVideoJob(
        array $promptData,
        int   $campaignId,
        int   $scriptId,
        int   $promptId,
        int   $createdBy
    ): array {
        $aspectRatio  = $promptData['aspect_ratio'] ?? '9:16';
        $durationSecs = (int)($promptData['duration_seconds'] ?? 10);

        // Runway Gen-3 only supports 5 or 10 seconds — snap to nearest
        $durationSecs = $durationSecs <= 7 ? 5 : 10;

        // Runway pricing: ~$0.05/sec for Gen-3 Alpha Turbo
        $estimatedCost = $durationSecs * DEFAULT_PROVIDER_COST_PER_SECOND
                       + DEFAULT_PROVIDER_COST_PER_GENERATION;

        if (ENABLE_MOCK_RUNWAY_MODE) {
            return $this->queueMockJob(
                $campaignId, $scriptId, $promptId, $createdBy,
                $promptData, $aspectRatio, $durationSecs, $estimatedCost
            );
        }

        return $this->queueRealJob(
            $campaignId, $scriptId, $promptId, $createdBy,
            $promptData, $aspectRatio, $durationSecs, $estimatedCost
        );
    }

    // -----------------------------------------------------------------------
    // getJobStatus()
    // -----------------------------------------------------------------------
    public function getJobStatus(int $jobId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        $job = $stmt->fetch();

        if (!$job) {
            throw new RuntimeException('Job not found: ' . $jobId);
        }

        if (ENABLE_MOCK_RUNWAY_MODE) {
            return $this->advanceMockJob($job);
        }

        return $this->pollRealJob($job);
    }

    // -----------------------------------------------------------------------
    // downloadOrStoreVideo()
    // -----------------------------------------------------------------------
    public function downloadOrStoreVideo(int $jobId): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        $job = $stmt->fetch();

        if (!$job || empty($job['video_url'])) {
            return false;
        }

        // Already stored locally
        if (ENABLE_MOCK_RUNWAY_MODE || str_starts_with($job['video_url'], '/')) {
            return true;
        }

        if (!is_dir(VIDEO_STORAGE_PATH)) {
            mkdir(VIDEO_STORAGE_PATH, 0755, true);
        }

        $filename  = 'job_' . $jobId . '_' . time() . '.mp4';
        $localPath = VIDEO_STORAGE_PATH . '/' . $filename;
        $publicUrl = VIDEO_PUBLIC_URL_BASE . '/' . $filename;

        $ch = curl_init($job['video_url']);
        $fp = fopen($localPath, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 300,
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$result || $httpCode !== 200) {
            error_log('[RunwayVideoService] Failed to download video for job ' . $jobId);
            @unlink($localPath);
            return false;
        }

        $this->db->prepare(
            'UPDATE ai_video_jobs SET local_file_path = :path, video_url = :url WHERE id = :id'
        )->execute([':path' => $localPath, ':url' => $publicUrl, ':id' => $jobId]);

        return true;
    }

    // -----------------------------------------------------------------------
    // PRIVATE — Mock mode
    // -----------------------------------------------------------------------
    private function queueMockJob(
        int   $campaignId, int $scriptId, int $promptId, int $createdBy,
        array $promptData, string $aspectRatio, int $durationSecs, float $estimatedCost
    ): array {
        $requestJson = json_encode([
            'mock'     => true,
            'prompt'   => $promptData['veo_prompt'] ?? '',
            'ratio'    => $aspectRatio,
            'duration' => $durationSecs,
        ]);

        $ins = $this->db->prepare(
            'INSERT INTO ai_video_jobs
                (campaign_id, script_id, prompt_id, provider, provider_job_id,
                 job_status, progress_percent, requested_duration_seconds, requested_aspect_ratio,
                 provider_request_json, estimated_provider_cost, total_ai_cost,
                 created_by, created_at, queued_at)
             VALUES
                (:campaign_id, :script_id, :prompt_id, :provider, :provider_job_id,
                 :status, 0, :duration, :aspect,
                 :req_json, :est_cost, :total_ai_cost,
                 :created_by, NOW(), NOW())'
        );
        $ins->execute([
            ':campaign_id'     => $campaignId,
            ':script_id'       => $scriptId ?: null,
            ':prompt_id'       => $promptId ?: null,
            ':provider'        => 'runway_mock',
            ':provider_job_id' => 'mock_' . uniqid(),
            ':status'          => 'queued',
            ':duration'        => $durationSecs,
            ':aspect'          => $aspectRatio,
            ':req_json'        => $requestJson,
            ':est_cost'        => $estimatedCost,
            ':total_ai_cost'   => $estimatedCost,
            ':created_by'      => $createdBy,
        ]);
        $jobId = $this->lastInsertId();

        $costSvc = new AiVideoCostService();
        $costSvc->logProviderCost($campaignId, $jobId, $estimatedCost, 'runway_mock', 'Mock video generation queued');

        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    private function advanceMockJob(array $job): array
    {
        $jobId      = (int)$job['id'];
        $campaignId = (int)$job['campaign_id'];

        switch ($job['job_status']) {
            case 'queued':
                $this->db->prepare(
                    'UPDATE ai_video_jobs
                        SET job_status = "processing", progress_percent = 50, started_at = NOW()
                      WHERE id = :id'
                )->execute([':id' => $jobId]);
                break;

            case 'processing':
                $videoUrl = 'https://www.w3schools.com/html/mov_bbb.mp4';
                $this->db->prepare(
                    'UPDATE ai_video_jobs
                        SET job_status = "completed", progress_percent = 100,
                            video_url = :video_url, actual_provider_cost = estimated_provider_cost,
                            completed_at = NOW()
                      WHERE id = :id'
                )->execute([':video_url' => $videoUrl, ':id' => $jobId]);

                $this->db->prepare(
                    'UPDATE ai_video_campaigns SET status = "ready_for_review", updated_at = NOW()
                      WHERE id = :cid'
                )->execute([':cid' => $campaignId]);
                break;
        }

        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    // -----------------------------------------------------------------------
    // PRIVATE — Real Runway API
    // -----------------------------------------------------------------------
    private function queueRealJob(
        int   $campaignId, int $scriptId, int $promptId, int $createdBy,
        array $promptData, string $aspectRatio, int $durationSecs, float $estimatedCost
    ): array {
        if (RUNWAY_API_KEY === '') {
            throw new RuntimeException('RUNWAY_API_KEY is not configured.');
        }

        // Runway caps promptText at 1000 characters
        $textPrompt  = mb_substr($promptData['veo_prompt'] ?? '', 0, 1000);
        $runwayRatio = self::RATIO_MAP[$aspectRatio] ?? '720:1280';

        $requestBody = [
            'model'      => RUNWAY_MODEL,
            'promptText' => $textPrompt,
            'ratio'      => $runwayRatio,
            'duration'   => $durationSecs,
            'watermark'  => false,
        ];

        $raw  = $this->callRunwayApi('POST', self::API_BASE . '/text_to_video', $requestBody);
        $data = json_decode($raw, true) ?? [];

        $taskId = $data['id'] ?? null;
        if (!$taskId) {
            throw new RuntimeException(
                'Runway API did not return a task ID. Response: ' . substr($raw, 0, 300)
            );
        }

        $ins = $this->db->prepare(
            'INSERT INTO ai_video_jobs
                (campaign_id, script_id, prompt_id, provider, provider_job_id,
                 job_status, progress_percent, requested_duration_seconds, requested_aspect_ratio,
                 provider_request_json, provider_response_json,
                 estimated_provider_cost, total_ai_cost,
                 created_by, created_at, queued_at)
             VALUES
                (:campaign_id, :script_id, :prompt_id, :provider, :provider_job_id,
                 "queued", 0, :duration, :aspect,
                 :req_json, :resp_json,
                 :est_cost, :total_ai_cost,
                 :created_by, NOW(), NOW())'
        );
        $ins->execute([
            ':campaign_id'     => $campaignId,
            ':script_id'       => $scriptId ?: null,
            ':prompt_id'       => $promptId ?: null,
            ':provider'        => 'runway',
            ':provider_job_id' => $taskId,
            ':duration'        => $durationSecs,
            ':aspect'          => $aspectRatio,
            ':req_json'        => json_encode($requestBody),
            ':resp_json'       => $raw,
            ':est_cost'        => $estimatedCost,
            ':total_ai_cost'   => $estimatedCost,
            ':created_by'      => $createdBy,
        ]);
        $jobId = $this->lastInsertId();

        $costSvc = new AiVideoCostService();
        $costSvc->logProviderCost($campaignId, $jobId, $estimatedCost, 'runway', 'Runway video generation queued');

        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    private function pollRealJob(array $job): array
    {
        $jobId  = (int)$job['id'];
        $taskId = $job['provider_job_id'] ?? '';
        if (!$taskId) {
            return $job;
        }

        $raw  = $this->callRunwayApi('GET', self::API_BASE . '/tasks/' . $taskId);
        $data = json_decode($raw, true) ?? [];

        $status = $data['status'] ?? 'PENDING';

        if ($status === 'SUCCEEDED') {
            $videoUrl = $data['output'][0] ?? null;

            if (!$videoUrl) {
                $this->db->prepare(
                    'UPDATE ai_video_jobs
                        SET job_status = "failed", error_message = :err,
                            provider_response_json = :resp, failed_at = NOW()
                      WHERE id = :id'
                )->execute([
                    ':err'  => 'Runway SUCCEEDED but no output URL found. Response: ' . substr($raw, 0, 300),
                    ':resp' => $raw,
                    ':id'   => $jobId,
                ]);
            } else {
                $this->db->prepare(
                    'UPDATE ai_video_jobs
                        SET job_status = "completed", progress_percent = 100,
                            video_url = :url, actual_provider_cost = estimated_provider_cost,
                            provider_response_json = :resp, completed_at = NOW()
                      WHERE id = :id'
                )->execute([':url' => $videoUrl, ':resp' => $raw, ':id' => $jobId]);

                $this->db->prepare(
                    'UPDATE ai_video_campaigns SET status = "ready_for_review", updated_at = NOW()
                      WHERE id = :cid'
                )->execute([':cid' => $job['campaign_id']]);

                // Immediately download — Runway signed URLs expire
                $this->downloadOrStoreVideo($jobId);
            }

        } elseif ($status === 'FAILED') {
            $errMsg = $data['failure'] ?? ($data['failureCode'] ?? 'Unknown Runway error');
            $this->db->prepare(
                'UPDATE ai_video_jobs
                    SET job_status = "failed", error_message = :err,
                        provider_response_json = :resp, failed_at = NOW()
                  WHERE id = :id'
            )->execute([':err' => $errMsg, ':resp' => $raw, ':id' => $jobId]);

        } else {
            // PENDING or RUNNING
            $progress = $status === 'RUNNING' ? 50 : 10;
            $this->db->prepare(
                'UPDATE ai_video_jobs
                    SET job_status = "processing", progress_percent = :pct,
                        started_at = COALESCE(started_at, NOW()),
                        provider_response_json = :resp
                  WHERE id = :id'
            )->execute([':pct' => $progress, ':resp' => $raw, ':id' => $jobId]);
        }

        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    // -----------------------------------------------------------------------
    // callRunwayApi() — internal cURL helper
    // -----------------------------------------------------------------------
    private function callRunwayApi(string $method, string $url, array $body = []): string
    {
        $ch   = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . RUNWAY_API_KEY,
                'X-Runway-Version: '     . self::API_VERSION,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 60,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        } else {
            $opts[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $opts);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('cURL error calling Runway API: ' . $err);
        }
        if ($code >= 400) {
            throw new RuntimeException(
                'Runway API returned HTTP ' . $code . ': ' . substr($raw, 0, 500)
            );
        }

        return $raw;
    }
}
