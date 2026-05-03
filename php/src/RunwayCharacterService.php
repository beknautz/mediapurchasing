<?php
/**
 * src/RunwayCharacterService.php
 * AI Video Studio — Runway Act Two character performance.
 *
 * Takes a character image/video and a reference performance video, then
 * outputs a new video of the character performing the same motion.
 *
 * Typical ad use-case:
 *   1. Upload a product spokesperson image (character)
 *   2. Record or generate a reference video of the desired performance
 *   3. Act Two animates the character to match
 *
 * API ref: php/docs/runway-api-reference.md#character-performance
 */

class RunwayCharacterService extends BaseService
{
    private const API_BASE    = 'https://api.dev.runwayml.com/v1';
    private const API_VERSION = '2024-11-06';

    private const SUPPORTED_RATIOS = [
        '720:1280',   // portrait  9:16
        '1280:720',   // landscape 16:9
        '960:960',    // square    1:1
        '1104:832',   // wide portrait
        '832:1104',
        '1584:672',   // ultra-wide
    ];

    // Map standard aspect ratio strings to Runway Act Two ratios
    private const RATIO_MAP = [
        '9:16'  => '720:1280',
        '16:9'  => '1280:720',
        '1:1'   => '960:960',
        '4:3'   => '1104:832',
        '3:4'   => '832:1104',
    ];

    // Approximate cost per second of output video
    private const COST_PER_SECOND = 0.05;

    // -----------------------------------------------------------------------
    // queueCharacterJob()
    // -----------------------------------------------------------------------
    public function queueCharacterJob(
        int    $campaignId,
        int    $videoJobId,
        string $characterUrl,
        string $characterType,   // 'image' | 'video'
        string $referenceVideoUrl,
        int    $expressionIntensity,
        bool   $bodyControl,
        string $aspectRatio,
        int    $createdBy
    ): array {
        $expressionIntensity = max(1, min(5, $expressionIntensity));
        $ratio = self::RATIO_MAP[$aspectRatio] ?? '720:1280';
        $estimatedCost = 5 * self::COST_PER_SECOND; // ~5 sec output estimate

        if (ENABLE_MOCK_RUNWAY_MODE) {
            return $this->queueMockJob(
                $campaignId, $videoJobId, $characterUrl, $characterType,
                $referenceVideoUrl, $expressionIntensity, $bodyControl,
                $ratio, $createdBy, $estimatedCost
            );
        }

        return $this->queueRealJob(
            $campaignId, $videoJobId, $characterUrl, $characterType,
            $referenceVideoUrl, $expressionIntensity, $bodyControl,
            $ratio, $createdBy, $estimatedCost
        );
    }

    // -----------------------------------------------------------------------
    // getJobStatus()
    // -----------------------------------------------------------------------
    public function getJobStatus(int $jobId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_video_character_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        $job = $stmt->fetch();
        if (!$job) {
            throw new RuntimeException('Character job not found: ' . $jobId);
        }

        if (ENABLE_MOCK_RUNWAY_MODE) {
            return $this->advanceMockJob($job);
        }

        return $this->pollRealJob($job);
    }

    // -----------------------------------------------------------------------
    // uploadFile()
    // Upload a local file to Runway ephemeral storage and return the runway:// URI.
    // Used to pass character images / reference videos to the Act Two API.
    // -----------------------------------------------------------------------
    public function uploadFile(string $localPath, string $filename): string
    {
        if (RUNWAY_API_KEY === '') {
            throw new RuntimeException('RUNWAY_API_KEY is not configured.');
        }

        // Step 1: Get signed upload URL
        $initRaw  = $this->callRunwayApi('POST', self::API_BASE . '/uploads', [
            'filename' => $filename,
            'type'     => 'ephemeral',
        ]);
        $initData = json_decode($initRaw, true) ?? [];

        $runwayUri = $initData['runwayUri'] ?? null;
        $uploadUrl = $initData['uploadUrl'] ?? null;
        $fields    = $initData['fields']    ?? [];

        if (!$runwayUri || !$uploadUrl) {
            throw new RuntimeException(
                'Runway uploads API did not return expected fields. Response: ' . substr($initRaw, 0, 300)
            );
        }

        // Step 2: POST file as multipart to the signed URL
        $postFields = $fields; // spread the pre-signed policy fields
        $postFields['file'] = new CURLFile($localPath, $this->guessMime($filename), $filename);

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new RuntimeException(
                'Runway file upload to storage failed with HTTP ' . $httpCode . ': ' . substr($result, 0, 300)
            );
        }

        return $runwayUri;
    }

    private function guessMime(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'mp4'         => 'video/mp4',
            'mov'         => 'video/quicktime',
            default       => 'application/octet-stream',
        };
    }

    // -----------------------------------------------------------------------
    // PRIVATE — Mock mode
    // -----------------------------------------------------------------------
    private function queueMockJob(
        int    $campaignId,
        int    $videoJobId,
        string $characterUrl,
        string $characterType,
        string $referenceVideoUrl,
        int    $expressionIntensity,
        bool   $bodyControl,
        string $ratio,
        int    $createdBy,
        float  $estimatedCost
    ): array {
        $ins = $this->db->prepare(
            'INSERT INTO ai_video_character_jobs
                (campaign_id, video_job_id, provider, model, provider_job_id,
                 job_status, character_url, character_type, reference_video_url,
                 expression_intensity, body_control, ratio,
                 estimated_cost, created_by, created_at, queued_at)
             VALUES
                (:cid, :vjid, "runway_mock", "act_two", :task_id,
                 "queued", :char_url, :char_type, :ref_url,
                 :expr, :body, :ratio,
                 :cost, :by, NOW(), NOW())'
        );
        $ins->execute([
            ':cid'       => $campaignId,
            ':vjid'      => $videoJobId ?: null,
            ':task_id'   => 'mock_char_' . uniqid(),
            ':char_url'  => $characterUrl,
            ':char_type' => $characterType,
            ':ref_url'   => $referenceVideoUrl,
            ':expr'      => $expressionIntensity,
            ':body'      => $bodyControl ? 1 : 0,
            ':ratio'     => $ratio,
            ':cost'      => $estimatedCost,
            ':by'        => $createdBy,
        ]);
        $jobId = $this->lastInsertId();

        $costSvc = new AiVideoCostService();
        $costSvc->logProviderCost($campaignId, null, $estimatedCost, 'runway_mock', 'Character performance queued (mock)');

        $stmt = $this->db->prepare('SELECT * FROM ai_video_character_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    private function advanceMockJob(array $job): array
    {
        $jobId = (int)$job['id'];

        switch ($job['job_status']) {
            case 'queued':
                $this->db->prepare(
                    'UPDATE ai_video_character_jobs
                        SET job_status = "processing", started_at = NOW()
                      WHERE id = :id'
                )->execute([':id' => $jobId]);
                break;

            case 'processing':
                // Use the W3Schools sample video as mock output
                $videoUrl = 'https://www.w3schools.com/html/mov_bbb.mp4';
                $this->db->prepare(
                    'UPDATE ai_video_character_jobs
                        SET job_status = "completed",
                            output_video_url = :url,
                            actual_cost = estimated_cost,
                            completed_at = NOW()
                      WHERE id = :id'
                )->execute([':url' => $videoUrl, ':id' => $jobId]);
                break;
        }

        $stmt = $this->db->prepare('SELECT * FROM ai_video_character_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    // -----------------------------------------------------------------------
    // PRIVATE — Real Runway API
    // -----------------------------------------------------------------------
    private function queueRealJob(
        int    $campaignId,
        int    $videoJobId,
        string $characterUrl,
        string $characterType,
        string $referenceVideoUrl,
        int    $expressionIntensity,
        bool   $bodyControl,
        string $ratio,
        int    $createdBy,
        float  $estimatedCost
    ): array {
        if (RUNWAY_API_KEY === '') {
            throw new RuntimeException('RUNWAY_API_KEY is not configured.');
        }

        $requestBody = [
            'model'     => 'act_two',
            'character' => [
                'type' => $characterType,
                'uri'  => $characterUrl,
            ],
            'reference' => [
                'type' => 'video',
                'uri'  => $referenceVideoUrl,
            ],
            'bodyControl'         => $bodyControl,
            'expressionIntensity' => $expressionIntensity,
            'ratio'               => $ratio,
        ];

        $raw  = $this->callRunwayApi('POST', self::API_BASE . '/character_performance', $requestBody);
        $data = json_decode($raw, true) ?? [];

        $taskId = $data['id'] ?? null;
        if (!$taskId) {
            throw new RuntimeException(
                'Runway character_performance API did not return a task ID. Response: ' . substr($raw, 0, 300)
            );
        }

        $ins = $this->db->prepare(
            'INSERT INTO ai_video_character_jobs
                (campaign_id, video_job_id, provider, model, provider_job_id,
                 job_status, character_url, character_type, reference_video_url,
                 expression_intensity, body_control, ratio,
                 provider_request_json, provider_response_json,
                 estimated_cost, created_by, created_at, queued_at)
             VALUES
                (:cid, :vjid, "runway", "act_two", :task_id,
                 "queued", :char_url, :char_type, :ref_url,
                 :expr, :body, :ratio,
                 :req, :resp,
                 :cost, :by, NOW(), NOW())'
        );
        $ins->execute([
            ':cid'       => $campaignId,
            ':vjid'      => $videoJobId ?: null,
            ':task_id'   => $taskId,
            ':char_url'  => $characterUrl,
            ':char_type' => $characterType,
            ':ref_url'   => $referenceVideoUrl,
            ':expr'      => $expressionIntensity,
            ':body'      => $bodyControl ? 1 : 0,
            ':ratio'     => $ratio,
            ':req'       => json_encode($requestBody),
            ':resp'      => $raw,
            ':cost'      => $estimatedCost,
            ':by'        => $createdBy,
        ]);
        $jobId = $this->lastInsertId();

        $costSvc = new AiVideoCostService();
        $costSvc->logProviderCost($campaignId, null, $estimatedCost, 'runway', 'Character performance queued');

        $stmt = $this->db->prepare('SELECT * FROM ai_video_character_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    private function pollRealJob(array $job): array
    {
        $jobId  = (int)$job['id'];
        $taskId = $job['provider_job_id'] ?? '';
        if (!$taskId) return $job;

        $raw  = $this->callRunwayApi('GET', self::API_BASE . '/tasks/' . $taskId);
        $data = json_decode($raw, true) ?? [];

        $status = $data['status'] ?? 'PENDING';

        if ($status === 'SUCCEEDED') {
            $videoUrl = $data['output'][0] ?? null;
            if (!$videoUrl) {
                $this->db->prepare(
                    'UPDATE ai_video_character_jobs
                        SET job_status = "failed", error_message = :err, failed_at = NOW()
                      WHERE id = :id'
                )->execute([':err' => 'Succeeded but no output URL', ':id' => $jobId]);
            } else {
                $this->db->prepare(
                    'UPDATE ai_video_character_jobs
                        SET job_status = "completed",
                            output_video_url = :url,
                            actual_cost = estimated_cost,
                            provider_response_json = :resp,
                            completed_at = NOW()
                      WHERE id = :id'
                )->execute([':url' => $videoUrl, ':resp' => $raw, ':id' => $jobId]);
            }

        } elseif ($status === 'FAILED') {
            $errMsg = $data['failure'] ?? ($data['failureCode'] ?? 'Unknown error');
            $this->db->prepare(
                'UPDATE ai_video_character_jobs
                    SET job_status = "failed", error_message = :err,
                        provider_response_json = :resp, failed_at = NOW()
                  WHERE id = :id'
            )->execute([':err' => $errMsg, ':resp' => $raw, ':id' => $jobId]);

        } else {
            $this->db->prepare(
                'UPDATE ai_video_character_jobs
                    SET job_status = "processing",
                        started_at = COALESCE(started_at, NOW()),
                        provider_response_json = :resp
                  WHERE id = :id'
            )->execute([':resp' => $raw, ':id' => $jobId]);
        }

        $stmt = $this->db->prepare('SELECT * FROM ai_video_character_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    // -----------------------------------------------------------------------
    // callRunwayApi()
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
