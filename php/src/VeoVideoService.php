<?php
/**
 * src/VeoVideoService.php
 * AI Video Studio — Google Veo video generation service.
 * Supports mock mode (for development) and real Veo API calls.
 */

class VeoVideoService extends BaseService
{
    // -----------------------------------------------------------------------
    // queueVideoJob()
    // Creates a new video generation job.
    // If ENABLE_MOCK_VEO_MODE is true, returns a mock job immediately.
    // Returns the saved job row.
    // -----------------------------------------------------------------------
    public function queueVideoJob(
        array $promptData,
        int   $campaignId,
        int   $scriptId,
        int   $promptId,
        int   $createdBy
    ): array {
        $aspectRatio    = $promptData['aspect_ratio']      ?? '9:16';
        $durationSecs   = (int)($promptData['duration_seconds'] ?? 15);
        $estimatedCost  = DEFAULT_PROVIDER_COST_PER_GENERATION
                        + ($durationSecs * DEFAULT_PROVIDER_COST_PER_SECOND);

        if (ENABLE_MOCK_VEO_MODE) {
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
    // Fetches the current job state. In mock mode, auto-advances state.
    // Returns updated job row.
    // -----------------------------------------------------------------------
    public function getJobStatus(int $jobId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        $job = $stmt->fetch();

        if (!$job) {
            throw new RuntimeException('Job not found: ' . $jobId);
        }

        if (ENABLE_MOCK_VEO_MODE) {
            return $this->advanceMockJob($job);
        }

        return $this->pollRealJob($job);
    }

    // -----------------------------------------------------------------------
    // downloadOrStoreVideo()
    // Downloads the video from the provider URL to VIDEO_STORAGE_PATH.
    // Returns true on success.
    // -----------------------------------------------------------------------
    public function downloadOrStoreVideo(int $jobId): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        $job = $stmt->fetch();

        if (!$job || empty($job['video_url'])) {
            return false;
        }

        // In mock mode the video_url is already a local public path — no download needed
        if (ENABLE_MOCK_VEO_MODE || str_starts_with($job['video_url'], '/')) {
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
        $result  = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$result || $httpCode !== 200) {
            error_log('[VeoVideoService] Failed to download video for job ' . $jobId);
            @unlink($localPath);
            return false;
        }

        $upd = $this->db->prepare(
            'UPDATE ai_video_jobs SET local_file_path = :path, video_url = :url WHERE id = :id'
        );
        $upd->execute([':path' => $localPath, ':url' => $publicUrl, ':id' => $jobId]);

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
            'mock'      => true,
            'prompt'    => $promptData['veo_prompt'] ?? '',
            'aspect'    => $aspectRatio,
            'duration'  => $durationSecs,
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
            ':campaign_id'      => $campaignId,
            ':script_id'        => $scriptId ?: null,
            ':prompt_id'        => $promptId ?: null,
            ':provider'         => 'veo_mock',
            ':provider_job_id'  => 'mock_' . uniqid(),
            ':status'           => 'queued',
            ':duration'         => $durationSecs,
            ':aspect'           => $aspectRatio,
            ':req_json'         => $requestJson,
            ':est_cost'         => $estimatedCost,
            ':total_ai_cost'    => $estimatedCost,
            ':created_by'       => $createdBy,
        ]);
        $jobId = $this->lastInsertId();

        // Log estimated provider cost
        $costSvc = new AiVideoCostService();
        $costSvc->logProviderCost($campaignId, $jobId, $estimatedCost, 'veo_mock', 'Mock video generation queued');

        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    private function advanceMockJob(array $job): array
    {
        $jobId     = (int)$job['id'];
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
                // Use a publicly accessible sample video so mock mode works without
                // any local file. Swap this for your own URL once real Veo is live.
                $videoUrl = 'https://www.w3schools.com/html/mov_bbb.mp4';
                $this->db->prepare(
                    'UPDATE ai_video_jobs
                        SET job_status = "completed", progress_percent = 100,
                            video_url = :video_url, actual_provider_cost = estimated_provider_cost,
                            completed_at = NOW()
                      WHERE id = :id'
                )->execute([':video_url' => $videoUrl, ':id' => $jobId]);

                // Update campaign status to ready_for_review
                $this->db->prepare(
                    'UPDATE ai_video_campaigns SET status = "ready_for_review", updated_at = NOW()
                      WHERE id = :cid'
                )->execute([':cid' => $campaignId]);
                break;

            // completed / failed — no changes needed
        }

        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    // -----------------------------------------------------------------------
    // PRIVATE — Real Veo API
    // -----------------------------------------------------------------------
    private function queueRealJob(
        int   $campaignId, int $scriptId, int $promptId, int $createdBy,
        array $promptData, string $aspectRatio, int $durationSecs, float $estimatedCost
    ): array {
        if (VEO_API_KEY === '') {
            throw new RuntimeException('VEO_API_KEY is not configured.');
        }

        $veoPrompt   = $promptData['veo_prompt'] ?? '';
        $requestBody = [
            'prompt'           => ['text' => $veoPrompt],
            'generationConfig' => [
                'aspectRatio'     => $aspectRatio,
                'durationSeconds' => $durationSecs,
                'numberOfVideos'  => 1,
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . VEO_MODEL . ':predictLongRunning';

        $raw  = $this->callVeoApi('POST', $url, $requestBody);
        $data = json_decode($raw, true) ?? [];

        $operationName = $data['name'] ?? null;
        if (!$operationName) {
            throw new RuntimeException('Veo API did not return an operation name. Response: ' . substr($raw, 0, 300));
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
            ':campaign_id'    => $campaignId,
            ':script_id'      => $scriptId ?: null,
            ':prompt_id'      => $promptId ?: null,
            ':provider'       => 'veo',
            ':provider_job_id'=> $operationName,
            ':duration'       => $durationSecs,
            ':aspect'         => $aspectRatio,
            ':req_json'       => json_encode($requestBody),
            ':resp_json'      => $raw,
            ':est_cost'       => $estimatedCost,
            ':total_ai_cost'  => $estimatedCost,
            ':created_by'     => $createdBy,
        ]);
        $jobId = $this->lastInsertId();

        $costSvc = new AiVideoCostService();
        $costSvc->logProviderCost($campaignId, $jobId, $estimatedCost, 'veo', 'Veo video generation queued');

        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    private function pollRealJob(array $job): array
    {
        $jobId         = (int)$job['id'];
        $operationName = $job['provider_job_id'] ?? '';
        if (!$operationName) {
            return $job;
        }

        $pollUrl = 'https://generativelanguage.googleapis.com/v1beta/' . $operationName;
        $raw     = $this->callVeoApi('GET', $pollUrl);
        $data    = json_decode($raw, true) ?? [];

        if (!empty($data['done'])) {
            // Veo returns the video under response.generateVideoResponse.generatedSamples[0].video.uri
            $videoUrl = $data['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri']
                     ?? $data['response']['videos'][0]['uri']   // fallback for older format
                     ?? null;
            $hasError  = !empty($data['error']);
            $errMsg    = $hasError ? ($data['error']['message'] ?? 'Unknown Veo error') : null;

            if ($hasError || !$videoUrl) {
                $this->db->prepare(
                    'UPDATE ai_video_jobs
                        SET job_status = "failed", error_message = :err,
                            provider_response_json = :resp, failed_at = NOW()
                      WHERE id = :id'
                )->execute([':err' => $errMsg, ':resp' => $raw, ':id' => $jobId]);
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
            }
        } else {
            // Still running — update response
            $this->db->prepare(
                'UPDATE ai_video_jobs
                    SET job_status = "processing", progress_percent = 50, started_at = COALESCE(started_at, NOW()),
                        provider_response_json = :resp
                  WHERE id = :id'
            )->execute([':resp' => $raw, ':id' => $jobId]);
        }

        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    // -----------------------------------------------------------------------
    // callVeoApi() — internal cURL helper
    // -----------------------------------------------------------------------
    private function callVeoApi(string $method, string $url, array $body = []): string
    {
        // Google AI Studio API key auth (default) uses x-goog-api-key header.
        // Vertex AI uses OAuth Bearer token — set VEO_AUTH_TYPE = 'oauth' in config.
        $authType = defined('VEO_AUTH_TYPE') ? VEO_AUTH_TYPE : 'api_key';
        $authHeader = $authType === 'oauth'
            ? 'Authorization: Bearer ' . VEO_API_KEY
            : 'x-goog-api-key: '       . VEO_API_KEY;

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                $authHeader,
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
            throw new RuntimeException('cURL error calling Veo API: ' . $err);
        }
        if ($code >= 400) {
            throw new RuntimeException('Veo API returned HTTP ' . $code . ': ' . substr($raw, 0, 300));
        }

        return $raw;
    }
}
