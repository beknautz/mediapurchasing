<?php
/**
 * src/RunwayAudioService.php
 * AI Video Studio — Runway ML audio generation.
 *
 * Supports:
 *   - Text-to-Speech (TTS)  via eleven_multilingual_v2
 *   - Sound Effects (SFX)   via eleven_text_to_sound_v2
 *
 * Both use the same async task polling pattern as video generation.
 * Outputs are downloaded and stored locally because Runway URLs expire in 24–48 h.
 *
 * API ref: php/docs/runway-api-reference.md
 */

class RunwayAudioService extends BaseService
{
    private const API_BASE    = 'https://api.dev.runwayml.com/v1';
    private const API_VERSION = '2024-11-06';

    // All preset voice IDs available in eleven_multilingual_v2
    public const VOICE_PRESETS = [
        // Female
        'Maya', 'Serene', 'Mabel', 'Leslie', 'Eleanor', 'Kylie', 'Lara', 'Lisa',
        'Marlene', 'Miriam', 'Paula', 'Sandra', 'Maggie', 'Katie', 'Rina', 'Ella',
        'Mariah', 'Claudia', 'Niki', 'Myrna', 'Wanda', 'Kiana', 'Rachel',
        // Male
        'Arjun', 'Bernard', 'Billy', 'Mark', 'Clint', 'Chad', 'Elias', 'Elliot',
        'Grungle', 'Brodie', 'Kirk', 'Malachi', 'Martin', 'Monster', 'Pip', 'Rusty',
        'Ragnar', 'Xylar', 'Jack', 'Noah', 'James', 'Frank', 'Vincent', 'Kendrick',
        'Tom', 'Benjamin',
    ];

    // Approximate cost per 1000 UTF-16 code units
    private const TTS_COST_PER_1K_CHARS = 0.30;
    private const SFX_COST_PER_GEN      = 0.10;

    // -----------------------------------------------------------------------
    // queueTtsJob()
    // -----------------------------------------------------------------------
    public function queueTtsJob(
        int    $campaignId,
        int    $scriptId,
        string $text,
        string $voicePreset,
        int    $createdBy
    ): array {
        if (!in_array($voicePreset, self::VOICE_PRESETS, true)) {
            $voicePreset = 'Maya';
        }

        $textLen       = mb_strlen($text, 'UTF-8');
        $estimatedCost = ($textLen / 1000) * self::TTS_COST_PER_1K_CHARS;

        if (ENABLE_MOCK_RUNWAY_MODE) {
            return $this->queueMockJob(
                $campaignId, $scriptId, 'tts', $text, $voicePreset,
                null, null, $createdBy, $estimatedCost
            );
        }

        return $this->queueRealTtsJob(
            $campaignId, $scriptId, $text, $voicePreset, $createdBy, $estimatedCost
        );
    }

    // -----------------------------------------------------------------------
    // queueSfxJob()
    // -----------------------------------------------------------------------
    public function queueSfxJob(
        int    $campaignId,
        string $promptText,
        float  $duration,
        bool   $loop,
        int    $createdBy
    ): array {
        $duration = max(0.5, min(30.0, $duration));

        if (ENABLE_MOCK_RUNWAY_MODE) {
            return $this->queueMockJob(
                $campaignId, 0, 'sfx', $promptText, null,
                $duration, $loop, $createdBy, self::SFX_COST_PER_GEN
            );
        }

        return $this->queueRealSfxJob(
            $campaignId, $promptText, $duration, $loop, $createdBy
        );
    }

    // -----------------------------------------------------------------------
    // getJobStatus()
    // -----------------------------------------------------------------------
    public function getJobStatus(int $jobId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_video_audio_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        $job = $stmt->fetch();
        if (!$job) {
            throw new RuntimeException('Audio job not found: ' . $jobId);
        }

        if (ENABLE_MOCK_RUNWAY_MODE) {
            return $this->advanceMockJob($job);
        }

        return $this->pollRealJob($job);
    }

    // -----------------------------------------------------------------------
    // PRIVATE — Mock mode
    // -----------------------------------------------------------------------
    private function queueMockJob(
        int    $campaignId,
        int    $scriptId,
        string $jobType,
        string $promptText,
        ?string $voicePreset,
        ?float $sfxDuration,
        ?bool  $sfxLoop,
        int    $createdBy,
        float  $estimatedCost
    ): array {
        $ins = $this->db->prepare(
            'INSERT INTO ai_video_audio_jobs
                (campaign_id, script_id, job_type, provider, provider_job_id,
                 job_status, voice_preset, prompt_text, sfx_duration, sfx_loop,
                 estimated_cost, created_by, created_at, queued_at)
             VALUES
                (:campaign_id, :script_id, :job_type, :provider, :provider_job_id,
                 "queued", :voice, :text, :sfx_dur, :sfx_loop,
                 :est_cost, :created_by, NOW(), NOW())'
        );
        $ins->execute([
            ':campaign_id'     => $campaignId,
            ':script_id'       => $scriptId ?: null,
            ':job_type'        => $jobType,
            ':provider'        => 'runway_mock',
            ':provider_job_id' => 'mock_audio_' . uniqid(),
            ':voice'           => $voicePreset,
            ':text'            => $promptText,
            ':sfx_dur'         => $sfxDuration,
            ':sfx_loop'        => $sfxLoop ? 1 : 0,
            ':est_cost'        => $estimatedCost,
            ':created_by'      => $createdBy,
        ]);
        $jobId = $this->lastInsertId();

        $costSvc = new AiVideoCostService();
        $costSvc->logProviderCost($campaignId, null, $estimatedCost, 'runway_mock', ucfirst($jobType) . ' audio job queued (mock)');

        $stmt = $this->db->prepare('SELECT * FROM ai_video_audio_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    private function advanceMockJob(array $job): array
    {
        $jobId = (int)$job['id'];

        switch ($job['job_status']) {
            case 'queued':
                $this->db->prepare(
                    'UPDATE ai_video_audio_jobs
                        SET job_status = "processing", started_at = NOW()
                      WHERE id = :id'
                )->execute([':id' => $jobId]);
                break;

            case 'processing':
                // Use a public domain audio sample as mock output
                $audioUrl = 'https://www.w3schools.com/html/horse.mp3';
                $this->db->prepare(
                    'UPDATE ai_video_audio_jobs
                        SET job_status = "completed",
                            audio_url = :url,
                            actual_cost = estimated_cost,
                            completed_at = NOW()
                      WHERE id = :id'
                )->execute([':url' => $audioUrl, ':id' => $jobId]);
                break;
        }

        $stmt = $this->db->prepare('SELECT * FROM ai_video_audio_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    // -----------------------------------------------------------------------
    // PRIVATE — Real Runway API
    // -----------------------------------------------------------------------
    private function queueRealTtsJob(
        int    $campaignId,
        int    $scriptId,
        string $text,
        string $voicePreset,
        int    $createdBy,
        float  $estimatedCost
    ): array {
        if (RUNWAY_API_KEY === '') {
            throw new RuntimeException('RUNWAY_API_KEY is not configured.');
        }

        // Runway TTS caps promptText at 1000 UTF-16 code units
        $text = mb_substr($text, 0, 1000);

        $requestBody = [
            'model'      => 'eleven_multilingual_v2',
            'promptText' => $text,
            'voice'      => [
                'type'     => 'runway-preset',
                'presetId' => $voicePreset,
            ],
        ];

        $raw  = $this->callRunwayApi('POST', self::API_BASE . '/text_to_speech', $requestBody);
        $data = json_decode($raw, true) ?? [];

        $taskId = $data['id'] ?? null;
        if (!$taskId) {
            throw new RuntimeException(
                'Runway TTS API did not return a task ID. Response: ' . substr($raw, 0, 300)
            );
        }

        $ins = $this->db->prepare(
            'INSERT INTO ai_video_audio_jobs
                (campaign_id, script_id, job_type, provider, provider_job_id,
                 job_status, voice_preset, prompt_text,
                 provider_request_json, provider_response_json,
                 estimated_cost, created_by, created_at, queued_at)
             VALUES
                (:cid, :sid, "tts", "runway", :task_id,
                 "queued", :voice, :text,
                 :req, :resp,
                 :cost, :by, NOW(), NOW())'
        );
        $ins->execute([
            ':cid'     => $campaignId,
            ':sid'     => $scriptId ?: null,
            ':task_id' => $taskId,
            ':voice'   => $voicePreset,
            ':text'    => $text,
            ':req'     => json_encode($requestBody),
            ':resp'    => $raw,
            ':cost'    => $estimatedCost,
            ':by'      => $createdBy,
        ]);
        $jobId = $this->lastInsertId();

        $costSvc = new AiVideoCostService();
        $costSvc->logProviderCost($campaignId, null, $estimatedCost, 'runway', 'TTS voiceover queued');

        $stmt = $this->db->prepare('SELECT * FROM ai_video_audio_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    private function queueRealSfxJob(
        int    $campaignId,
        string $promptText,
        float  $duration,
        bool   $loop,
        int    $createdBy
    ): array {
        if (RUNWAY_API_KEY === '') {
            throw new RuntimeException('RUNWAY_API_KEY is not configured.');
        }

        $requestBody = [
            'model'      => 'eleven_text_to_sound_v2',
            'promptText' => mb_substr($promptText, 0, 500),
            'duration'   => $duration,
            'loop'       => $loop,
        ];

        $raw  = $this->callRunwayApi('POST', self::API_BASE . '/sound_effect', $requestBody);
        $data = json_decode($raw, true) ?? [];

        $taskId = $data['id'] ?? null;
        if (!$taskId) {
            throw new RuntimeException(
                'Runway SFX API did not return a task ID. Response: ' . substr($raw, 0, 300)
            );
        }

        $ins = $this->db->prepare(
            'INSERT INTO ai_video_audio_jobs
                (campaign_id, script_id, job_type, provider, provider_job_id,
                 job_status, prompt_text, sfx_duration, sfx_loop,
                 provider_request_json, provider_response_json,
                 estimated_cost, created_by, created_at, queued_at)
             VALUES
                (:cid, NULL, "sfx", "runway", :task_id,
                 "queued", :text, :dur, :loop,
                 :req, :resp,
                 :cost, :by, NOW(), NOW())'
        );
        $ins->execute([
            ':cid'     => $campaignId,
            ':task_id' => $taskId,
            ':text'    => $promptText,
            ':dur'     => $duration,
            ':loop'    => $loop ? 1 : 0,
            ':req'     => json_encode($requestBody),
            ':resp'    => $raw,
            ':cost'    => self::SFX_COST_PER_GEN,
            ':by'      => $createdBy,
        ]);
        $jobId = $this->lastInsertId();

        $costSvc = new AiVideoCostService();
        $costSvc->logProviderCost($campaignId, null, self::SFX_COST_PER_GEN, 'runway', 'Sound effect queued');

        $stmt = $this->db->prepare('SELECT * FROM ai_video_audio_jobs WHERE id = :id');
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
            $audioUrl = $data['output'][0] ?? null;
            if (!$audioUrl) {
                $this->db->prepare(
                    'UPDATE ai_video_audio_jobs
                        SET job_status = "failed", error_message = :err, failed_at = NOW()
                      WHERE id = :id'
                )->execute([':err' => 'Succeeded but no output URL', ':id' => $jobId]);
            } else {
                // Download and store locally — Runway URLs expire in 24-48h
                $localUrl = $this->downloadAudio($jobId, $audioUrl);

                $this->db->prepare(
                    'UPDATE ai_video_audio_jobs
                        SET job_status = "completed",
                            audio_url = :url,
                            actual_cost = estimated_cost,
                            provider_response_json = :resp,
                            completed_at = NOW()
                      WHERE id = :id'
                )->execute([':url' => $localUrl ?: $audioUrl, ':resp' => $raw, ':id' => $jobId]);
            }

        } elseif ($status === 'FAILED') {
            $errMsg = $data['failure'] ?? ($data['failureCode'] ?? 'Unknown error');
            $this->db->prepare(
                'UPDATE ai_video_audio_jobs
                    SET job_status = "failed", error_message = :err,
                        provider_response_json = :resp, failed_at = NOW()
                  WHERE id = :id'
            )->execute([':err' => $errMsg, ':resp' => $raw, ':id' => $jobId]);

        } else {
            $this->db->prepare(
                'UPDATE ai_video_audio_jobs
                    SET job_status = "processing",
                        started_at = COALESCE(started_at, NOW()),
                        provider_response_json = :resp
                  WHERE id = :id'
            )->execute([':resp' => $raw, ':id' => $jobId]);
        }

        $stmt = $this->db->prepare('SELECT * FROM ai_video_audio_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        return $stmt->fetch() ?: [];
    }

    private function downloadAudio(int $jobId, string $url): ?string
    {
        $audioDir = VIDEO_STORAGE_PATH . '/audio';
        if (!is_dir($audioDir)) {
            mkdir($audioDir, 0755, true);
        }

        $filename  = 'audio_' . $jobId . '_' . time() . '.mp3';
        $localPath = $audioDir . '/' . $filename;
        $publicUrl = VIDEO_PUBLIC_URL_BASE . '/audio/' . $filename;

        $ch = curl_init($url);
        $fp = fopen($localPath, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$result || $httpCode !== 200) {
            @unlink($localPath);
            return null;
        }

        $this->db->prepare(
            'UPDATE ai_video_audio_jobs SET local_file_path = :path WHERE id = :id'
        )->execute([':path' => $localPath, ':id' => $jobId]);

        return $publicUrl;
    }

    // -----------------------------------------------------------------------
    // callRunwayApi() — same helper as RunwayVideoService
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
