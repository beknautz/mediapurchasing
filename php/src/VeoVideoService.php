<?php
/**
 * src/VeoVideoService.php
 * AI Video Studio — Google Veo 2 video generation via Vertex AI.
 *
 * Auth:     OAuth2 service account Bearer token (NOT an API key)
 * Endpoint: https://us-central1-aiplatform.googleapis.com/v1/
 *
 * WHY Vertex AI and not Gemini API (generativelanguage.googleapis.com):
 *   Veo is a Vertex AI product. API keys are rejected. The Gemini API
 *   endpoint routes through Google's org-level infrastructure project and
 *   is blocked by Google Workspace org policies. Vertex AI bypasses this
 *   entirely — it uses your own GCP project with OAuth2 service account auth.
 *
 * Video output: Veo returns video inline as Base64-encoded bytes
 *   (response.videos[0].bytesBase64Encoded). This MUST be decoded and saved
 *   to disk. Never log, echo, or store the raw Base64 payload in the database.
 */

class VeoVideoService extends BaseService
{
    private const API_BASE = 'https://us-central1-aiplatform.googleapis.com/v1';
    private const LOCATION = 'us-central1';

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
        $aspectRatio  = $promptData['aspect_ratio']      ?? '9:16';
        $durationSecs = (int)($promptData['duration_seconds'] ?? 8);
        // Veo 2 supports 5–8 seconds
        $durationSecs = max(5, min(8, $durationSecs));

        $estimatedCost = DEFAULT_PROVIDER_COST_PER_GENERATION
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
    // For Veo, videos are already saved to disk during pollRealJob().
    // This is a no-op if the file already exists locally.
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
        if (str_starts_with($job['video_url'], '/') || ENABLE_MOCK_VEO_MODE) {
            return true;
        }

        // Remote URL fallback — download if somehow not yet local
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
            error_log('[VeoVideoService] Failed to download video for job ' . $jobId);
            @unlink($localPath);
            return false;
        }

        $this->db->prepare(
            'UPDATE ai_video_jobs SET local_file_path = :path, video_url = :url WHERE id = :id'
        )->execute([':path' => $localPath, ':url' => $publicUrl, ':id' => $jobId]);

        return true;
    }

    // -----------------------------------------------------------------------
    // saveCompletedVideo()
    // Decodes and saves video from a completed Veo operation response.
    //
    // Veo Vertex AI may return the generated video inline as Base64 bytes.
    // This must be decoded and written to an MP4 file.
    // Do not display or log the raw Base64 video payload.
    //
    // Handles three possible response shapes:
    //   1. videos[0].bytesBase64Encoded — inline Base64 (most common)
    //   2. videos[0].gcsUri             — Google Cloud Storage URI
    //   3. videos[0].uri                — hosted download URL
    // -----------------------------------------------------------------------
    public function saveCompletedVideo(array $operation, string $outputDir, string $publicBaseUrl): array
    {
        if (empty($operation['done'])) {
            throw new RuntimeException('saveCompletedVideo called but operation is not done yet.');
        }

        $videos = $operation['response']['videos'] ?? [];
        if (empty($videos)) {
            throw new RuntimeException(
                'Veo operation is done but response contains no videos array. ' .
                'Keys present: ' . implode(', ', array_keys($operation['response'] ?? []))
            );
        }

        $video = $videos[0];

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $fileName  = 'veo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.mp4';
        $filePath  = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
        $publicUrl = rtrim($publicBaseUrl, '/') . '/' . $fileName;

        // ── Shape 1: inline Base64 ──────────────────────────────────────────
        if (!empty($video['bytesBase64Encoded'])) {
            $videoData = base64_decode($video['bytesBase64Encoded'], true);
            if ($videoData === false) {
                throw new RuntimeException('Failed to base64_decode Veo video payload.');
            }
            if (file_put_contents($filePath, $videoData) === false) {
                throw new RuntimeException('Failed to write Veo video to disk: ' . $filePath);
            }
            return ['file_path' => $filePath, 'public_url' => $publicUrl, 'source' => 'base64'];
        }

        // ── Shape 2: GCS URI ────────────────────────────────────────────────
        // GCS URIs require authenticated download via the Storage API.
        // Store the URI as video_url for now — a future job can download it.
        if (!empty($video['gcsUri'])) {
            return ['file_path' => null, 'public_url' => $video['gcsUri'], 'source' => 'gcs_uri'];
        }

        // ── Shape 3: hosted URI ─────────────────────────────────────────────
        if (!empty($video['uri'])) {
            $ch = curl_init($video['uri']);
            $fp = fopen($filePath, 'wb');
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 300,
            ]);
            $ok   = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if (!$ok || $code !== 200) {
                @unlink($filePath);
                throw new RuntimeException('Failed to download Veo video from URI (HTTP ' . $code . ')');
            }
            return ['file_path' => $filePath, 'public_url' => $publicUrl, 'source' => 'uri'];
        }

        throw new RuntimeException(
            'Veo operation completed but no video output found. ' .
            'Expected bytesBase64Encoded, gcsUri, or uri in response.videos[0]. ' .
            'Keys present: ' . implode(', ', array_keys($video))
        );
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
            'aspect'   => $aspectRatio,
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
            ':provider'        => 'veo_mock',
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
        $costSvc->logProviderCost($campaignId, $jobId, $estimatedCost, 'veo_mock', 'Mock Veo job queued');

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
                            video_url = :url, actual_provider_cost = estimated_provider_cost,
                            completed_at = NOW()
                      WHERE id = :id'
                )->execute([':url' => $videoUrl, ':id' => $jobId]);

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
    // PRIVATE — Real Vertex AI
    // -----------------------------------------------------------------------
    private function queueRealJob(
        int   $campaignId, int $scriptId, int $promptId, int $createdBy,
        array $promptData, string $aspectRatio, int $durationSecs, float $estimatedCost
    ): array {
        $projectId = defined('VEO_PROJECT_ID') ? VEO_PROJECT_ID : '';
        if (!$projectId) {
            throw new RuntimeException('VEO_PROJECT_ID is not configured.');
        }

        $model    = VEO_MODEL; // e.g. veo-2.0-generate-001
        $endpoint = self::API_BASE
                  . '/projects/' . $projectId
                  . '/locations/' . self::LOCATION
                  . '/publishers/google/models/' . $model
                  . ':predictLongRunning';

        $requestBody = [
            'instances'  => [[
                'prompt' => $promptData['veo_prompt'] ?? '',
            ]],
            'parameters' => [
                'aspectRatio'     => $aspectRatio,
                'sampleCount'     => 1,
                'durationSeconds' => $durationSecs,
            ],
        ];

        if (!empty($promptData['negative_prompt'])) {
            $requestBody['instances'][0]['negativePrompt'] = $promptData['negative_prompt'];
        }

        $raw  = $this->callVertexApi('POST', $endpoint, $requestBody);
        $data = json_decode($raw, true) ?? [];

        $operationName = $data['name'] ?? null;
        if (!$operationName) {
            throw new RuntimeException(
                'Veo Vertex AI did not return an operation name. Response: ' . substr($raw, 0, 300)
            );
        }

        // Confirm Vertex returned a full operation path, not a short/mock ID
        if (!str_starts_with($operationName, 'projects/')) {
            throw new RuntimeException(
                'Veo Vertex AI returned an unexpected operation name format: "' . $operationName . '". ' .
                'Expected: projects/{PROJECT}/locations/.../operations/{ID}'
            );
        }

        error_log('[VeoVideoService] Veo create job response: ' . $raw);

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
            ':provider'        => 'veo',
            ':provider_job_id' => $operationName,
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
        $costSvc->logProviderCost($campaignId, $jobId, $estimatedCost, 'veo', 'Veo Vertex AI video generation queued');

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

        // Guard: mock job IDs cannot be polled against live Vertex AI
        if (str_starts_with($operationName, 'mock_')) {
            throw new RuntimeException(
                'Job #' . $jobId . ' has a mock Veo ID ("' . $operationName . '") and cannot be polled against live Vertex AI. ' .
                'Create a new Veo job with mock mode disabled.'
            );
        }

        // Guard: live Veo operation names must start with "projects/"
        if (!str_starts_with($operationName, 'projects/')) {
            throw new RuntimeException(
                'Invalid Veo operation name for job #' . $jobId . '. ' .
                'Expected full Vertex operation name beginning with "projects/". Got: ' . $operationName
            );
        }

        // Poll: POST :fetchPredictOperation with operationName in the body.
        // Do NOT use GET /v1/{operationName} — that returns a 404 HTML page.
        // Do NOT append the operation name to the URL path.
        $projectId = defined('VEO_PROJECT_ID') ? VEO_PROJECT_ID : '';
        $model     = VEO_MODEL;
        $pollUrl   = self::API_BASE
                   . '/projects/' . rawurlencode($projectId)
                   . '/locations/' . self::LOCATION
                   . '/publishers/google/models/' . rawurlencode($model)
                   . ':fetchPredictOperation';

        $pollBody  = ['operationName' => $operationName];

        // Retry once on 401 (expired OAuth token)
        $retried = false;
        retry:
        try {
            $raw = $this->callVertexApi('POST', $pollUrl, $pollBody);
        } catch (RuntimeException $e) {
            if (!$retried && str_contains($e->getMessage(), 'HTTP 401')) {
                $this->makeOAuth()->clearCache();
                $retried = true;
                goto retry;
            }
            throw $e;
        }

        $data = json_decode($raw, true) ?? [];

        // ── Error response ──────────────────────────────────────────────────
        if (!empty($data['error'])) {
            $errMsg = $data['error']['message'] ?? 'Unknown Veo error';
            $this->db->prepare(
                'UPDATE ai_video_jobs
                    SET job_status = "failed", error_message = :err,
                        provider_response_json = :resp, failed_at = NOW()
                  WHERE id = :id'
            )->execute([':err' => $errMsg, ':resp' => substr($raw, 0, 2000), ':id' => $jobId]);

        // ── Operation complete ──────────────────────────────────────────────
        } elseif (!empty($data['done'])) {
            try {
                $saved = $this->saveCompletedVideo($data, VIDEO_STORAGE_PATH, VIDEO_PUBLIC_URL_BASE);
            } catch (RuntimeException $e) {
                $this->db->prepare(
                    'UPDATE ai_video_jobs
                        SET job_status = "failed", error_message = :err,
                            provider_response_json = :resp, failed_at = NOW()
                      WHERE id = :id'
                )->execute([
                    ':err'  => 'Video save failed: ' . $e->getMessage(),
                    ':resp' => $this->stripBase64FromResponse($raw),
                    ':id'   => $jobId,
                ]);

                $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
                $stmt->execute([':id' => $jobId]);
                return $stmt->fetch() ?: [];
            }

            // Store path + public URL — NEVER the raw Base64 in the DB
            $this->db->prepare(
                'UPDATE ai_video_jobs
                    SET job_status = "completed", progress_percent = 100,
                        video_url = :url, local_file_path = :path,
                        actual_provider_cost = estimated_provider_cost,
                        provider_response_json = :resp,
                        completed_at = NOW()
                  WHERE id = :id'
            )->execute([
                ':url'  => $saved['public_url'],
                ':path' => $saved['file_path'] ?? '',
                ':resp' => $this->stripBase64FromResponse($raw),
                ':id'   => $jobId,
            ]);

            $this->db->prepare(
                'UPDATE ai_video_campaigns SET status = "ready_for_review", updated_at = NOW()
                  WHERE id = :cid'
            )->execute([':cid' => $job['campaign_id']]);

        // ── Still running ───────────────────────────────────────────────────
        } else {
            $this->db->prepare(
                'UPDATE ai_video_jobs
                    SET job_status = "processing", progress_percent = 50,
                        started_at = COALESCE(started_at, NOW()),
                        provider_response_json = :resp
                  WHERE id = :id'
            )->execute([':resp' => substr($raw, 0, 2000), ':id' => $jobId]);
        }

        $stmt = $this->db->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    // -----------------------------------------------------------------------
    // stripBase64FromResponse()
    // Replaces bytesBase64Encoded with a placeholder before storing in MySQL.
    // The raw video payload can be tens of MB — never put it in the DB.
    // -----------------------------------------------------------------------
    private function stripBase64FromResponse(string $raw): string
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return substr($raw, 0, 2000);
        }
        foreach ($data['response']['videos'] ?? [] as &$v) {
            if (!empty($v['bytesBase64Encoded'])) {
                $v['bytesBase64Encoded'] = '[base64_video_stripped_saved_to_disk]';
            }
        }
        unset($v);
        return json_encode($data);
    }

    // -----------------------------------------------------------------------
    // makeOAuth() — returns a GoogleOAuthService instance
    // -----------------------------------------------------------------------
    private function makeOAuth(): GoogleOAuthService
    {
        $jsonPath = defined('VEO_SERVICE_ACCOUNT_JSON_PATH') ? VEO_SERVICE_ACCOUNT_JSON_PATH : '';
        if (!$jsonPath) {
            throw new RuntimeException(
                'VEO_SERVICE_ACCOUNT_JSON_PATH is not configured. ' .
                'Download a service account JSON key from GCP Console and set this constant.'
            );
        }
        return new GoogleOAuthService($jsonPath);
    }

    // -----------------------------------------------------------------------
    // callVertexApi() — internal cURL helper using OAuth2 Bearer token
    // -----------------------------------------------------------------------
    private function callVertexApi(string $method, string $url, array $body = []): string
    {
        $token = $this->makeOAuth()->getAccessToken();

        $ch   = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 120,
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
            throw new RuntimeException('cURL error calling Vertex AI: ' . $err);
        }

        // Safety net: detect wrong-endpoint org routing
        if (str_contains((string)$raw, '542708778979')) {
            throw new RuntimeException(
                'Request routed through org infrastructure project 542708778979. ' .
                'Verify VEO_PROJECT_ID and VEO_SERVICE_ACCOUNT_JSON_PATH are correct.'
            );
        }

        if ($code >= 400) {
            throw new RuntimeException(
                'Vertex AI returned HTTP ' . $code . ': ' . substr($raw, 0, 500)
            );
        }

        return $raw;
    }
}
