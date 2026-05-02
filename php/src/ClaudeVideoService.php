<?php
/**
 * src/ClaudeVideoService.php
 * AI Video Studio — Claude API service for script and Veo prompt generation.
 * Pure cURL, no Composer SDK.
 */

class ClaudeVideoService extends BaseService
{
    // -----------------------------------------------------------------------
    // Claude cost rates per 1M tokens
    // -----------------------------------------------------------------------
    private static array $RATES = [
        'claude-opus-4-5'    => ['input' => 15.00, 'output' => 75.00],
        'claude-sonnet-4-5'  => ['input' =>  3.00, 'output' => 15.00],
        'claude-haiku-3'     => ['input' =>  0.25, 'output' =>  1.25],
    ];

    // -----------------------------------------------------------------------
    // generateScript()
    // Calls Claude to produce a structured video script from campaign brief.
    // Saves to ai_video_scripts, logs cost to ai_video_costs.
    // Returns saved row array including claude_* cost fields.
    // -----------------------------------------------------------------------
    public function generateScript(array $campaignData): array
    {
        $prompt = $this->buildScriptPrompt($campaignData);
        [$content, $inputTokens, $outputTokens] = $this->callClaude($prompt);

        $parsed = $this->parseJson($content);
        if ($parsed === null) {
            throw new RuntimeException('Claude returned invalid JSON for script generation. Raw: ' . substr($content, 0, 500));
        }

        $model    = $this->getSetting('anthropic_model') ?: (defined('CLAUDE_MODEL') ? CLAUDE_MODEL : 'claude-opus-4-5');
        $cost     = $this->estimateClaudeCost($inputTokens, $outputTokens, $model);

        // Calculate next version number
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(version_number), 0) + 1 AS next_ver
               FROM ai_video_scripts WHERE campaign_id = :cid'
        );
        $stmt->execute([':cid' => $campaignData['id']]);
        $nextVer = (int)($stmt->fetchColumn() ?: 1);

        $sceneBreakdown = isset($parsed['scenes']) ? json_encode($parsed['scenes']) : null;
        $fullScript     = $parsed['full_script'] ?? $parsed['voiceover_text'] ?? '';

        $ins = $this->db->prepare(
            'INSERT INTO ai_video_scripts
                (campaign_id, version_number, script_text, hook, scene_breakdown,
                 voiceover_text, on_screen_text, cta_text,
                 claude_model, claude_input_tokens, claude_output_tokens, claude_cost, created_at)
             VALUES
                (:campaign_id, :ver, :script_text, :hook, :scene_breakdown,
                 :voiceover_text, :on_screen_text, :cta_text,
                 :model, :input_tokens, :output_tokens, :cost, NOW())'
        );
        $ins->execute([
            ':campaign_id'    => $campaignData['id'],
            ':ver'            => $nextVer,
            ':script_text'    => $fullScript,
            ':hook'           => $parsed['hook'] ?? null,
            ':scene_breakdown'=> $sceneBreakdown,
            ':voiceover_text' => $parsed['voiceover_text'] ?? null,
            ':on_screen_text' => $parsed['on_screen_text'] ?? null,
            ':cta_text'       => $parsed['cta_text'] ?? null,
            ':model'          => $model,
            ':input_tokens'   => $inputTokens,
            ':output_tokens'  => $outputTokens,
            ':cost'           => $cost,
        ]);
        $scriptId = $this->lastInsertId();

        // Log cost
        $costSvc = new AiVideoCostService();
        $costSvc->logClaudeCost(
            (int)$campaignData['id'], 0, 'script_generation', $model, $inputTokens, $outputTokens
        );

        // Return the saved row
        $row = $this->db->prepare('SELECT * FROM ai_video_scripts WHERE id = :id');
        $row->execute([':id' => $scriptId]);
        return $row->fetch() ?: [];
    }

    // -----------------------------------------------------------------------
    // generateVeoPrompt()
    // Calls Claude to translate a script into a Veo video generation prompt.
    // Saves to ai_video_prompts, logs cost. Returns saved row.
    // -----------------------------------------------------------------------
    public function generateVeoPrompt(array $scriptData, array $campaignData): array
    {
        $prompt = $this->buildVeoPromptPrompt($scriptData, $campaignData);
        [$content, $inputTokens, $outputTokens] = $this->callClaude($prompt);

        $parsed = $this->parseJson($content);
        if ($parsed === null) {
            throw new RuntimeException('Claude returned invalid JSON for Veo prompt generation. Raw: ' . substr($content, 0, 500));
        }

        $model = CLAUDE_MODEL;
        $cost  = $this->estimateClaudeCost($inputTokens, $outputTokens, $model);

        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(version_number), 0) + 1 AS next_ver
               FROM ai_video_prompts WHERE campaign_id = :cid'
        );
        $stmt->execute([':cid' => $campaignData['id']]);
        $nextVer = (int)($stmt->fetchColumn() ?: 1);

        $ins = $this->db->prepare(
            'INSERT INTO ai_video_prompts
                (campaign_id, script_id, version_number, veo_prompt, negative_prompt,
                 visual_style, camera_direction, lighting, pacing,
                 aspect_ratio, duration_seconds,
                 claude_model, claude_input_tokens, claude_output_tokens, claude_cost, created_at)
             VALUES
                (:campaign_id, :script_id, :ver, :veo_prompt, :negative_prompt,
                 :visual_style, :camera_direction, :lighting, :pacing,
                 :aspect_ratio, :duration_seconds,
                 :model, :input_tokens, :output_tokens, :cost, NOW())'
        );
        $ins->execute([
            ':campaign_id'     => $campaignData['id'],
            ':script_id'       => $scriptData['id'] ?? null,
            ':ver'             => $nextVer,
            ':veo_prompt'      => $parsed['veo_prompt'] ?? null,
            ':negative_prompt' => $parsed['negative_prompt'] ?? null,
            ':visual_style'    => $parsed['visual_style'] ?? null,
            ':camera_direction'=> $parsed['camera_direction'] ?? null,
            ':lighting'        => $parsed['lighting'] ?? null,
            ':pacing'          => $parsed['pacing'] ?? null,
            ':aspect_ratio'    => $parsed['aspect_ratio'] ?? ($campaignData['aspect_ratio'] ?? null),
            ':duration_seconds'=> $parsed['duration_seconds'] ?? ($campaignData['video_length'] ?? null),
            ':model'           => $model,
            ':input_tokens'    => $inputTokens,
            ':output_tokens'   => $outputTokens,
            ':cost'            => $cost,
        ]);
        $promptId = $this->lastInsertId();

        // Log cost
        $costSvc = new AiVideoCostService();
        $costSvc->logClaudeCost(
            (int)$campaignData['id'], 0, 'prompt_generation', $model, $inputTokens, $outputTokens
        );

        $row = $this->db->prepare('SELECT * FROM ai_video_prompts WHERE id = :id');
        $row->execute([':id' => $promptId]);
        return $row->fetch() ?: [];
    }

    // -----------------------------------------------------------------------
    // estimateClaudeCost()
    // Returns estimated USD cost for a given token usage + model.
    // -----------------------------------------------------------------------
    public function estimateClaudeCost(int $inputTokens, int $outputTokens, string $model): float
    {
        $rates = self::$RATES[$model] ?? self::$RATES['claude-sonnet-4-5'];
        $inputCost  = ($inputTokens  / 1_000_000) * $rates['input'];
        $outputCost = ($outputTokens / 1_000_000) * $rates['output'];
        return round($inputCost + $outputCost, 6);
    }

    // -----------------------------------------------------------------------
    // callClaude() — internal
    // Calls Anthropic /v1/messages endpoint.
    // Returns [responseText, inputTokens, outputTokens]
    // -----------------------------------------------------------------------
    private function callClaude(string $userPrompt): array
    {
        // Uses the same key as Budget Planner — stored in workflow_settings.
        $apiKey = $this->getSetting('anthropic_api_key');
        if ($apiKey === '') {
            throw new RuntimeException(
                'Claude API key is not set. Go to Admin → Settings and add anthropic_api_key ' .
                '(the same key already used by Budget Planner).'
            );
        }

        $model = $this->getSetting('anthropic_model') ?: 'claude-opus-4-5';

        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => 4096,
            'messages'   => [['role' => 'user', 'content' => $userPrompt]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: '          . $apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT    => 120,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('cURL error calling Claude API: ' . $err);
        }
        if ($code !== 200) {
            throw new RuntimeException('Claude API returned HTTP ' . $code . ': ' . substr($raw, 0, 300));
        }

        $data         = json_decode($raw, true) ?? [];
        $content      = $data['content'][0]['text'] ?? '';
        $inputTokens  = (int)($data['usage']['input_tokens']  ?? 0);
        $outputTokens = (int)($data['usage']['output_tokens'] ?? 0);

        return [$content, $inputTokens, $outputTokens];
    }

    // -----------------------------------------------------------------------
    // parseJson() — strips markdown fences and decodes JSON
    // -----------------------------------------------------------------------
    private function parseJson(string $raw): ?array
    {
        // Strip ```json ... ``` fences
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Try to find a JSON object within the string
        if (preg_match('/\{[\s\S]+\}/s', $cleaned, $m)) {
            $decoded = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // buildScriptPrompt()
    // -----------------------------------------------------------------------
    private function buildScriptPrompt(array $c): string
    {
        return <<<PROMPT
You are an expert video ad scriptwriter. Create a compelling video advertisement script based on the campaign brief below.

CAMPAIGN BRIEF:
- Campaign Name: {$c['campaign_name']}
- Objective: {$c['objective']}
- Target Audience: {$c['target_audience']}
- Offer/Product: {$c['offer']}
- Brand Voice: {$c['brand_voice']}
- Platform: {$c['platform']}
- Video Length: {$c['video_length']} seconds
- Aspect Ratio: {$c['aspect_ratio']}
- Call to Action: {$c['call_to_action']}
- Landing Page: {$c['landing_page_url']}
- Additional Notes: {$c['notes']}

Return ONLY a single valid JSON object (no markdown, no explanation, no extra text) with this exact schema:
{
  "hook": "Opening line or visual concept that grabs attention in the first 2 seconds",
  "scenes": [
    {"scene_number": 1, "description": "What happens in this scene", "visual": "Visual description for the videographer", "duration_seconds": 5}
  ],
  "voiceover_text": "Full voiceover script that runs throughout the video",
  "on_screen_text": "Text overlays and titles that appear on screen",
  "cta_text": "Exact call-to-action text at the end",
  "music_suggestion": "Music style/mood recommendation",
  "full_script": "Complete formatted script combining all elements"
}

Ensure scenes sum to approximately {$c['video_length']} seconds total. Make it emotionally engaging and platform-appropriate for {$c['platform']}.
PROMPT;
    }

    // -----------------------------------------------------------------------
    // buildVeoPromptPrompt()
    // -----------------------------------------------------------------------
    private function buildVeoPromptPrompt(array $script, array $campaign): string
    {
        $sceneText = '';
        if (!empty($script['scene_breakdown'])) {
            $scenes = json_decode($script['scene_breakdown'], true);
            if (is_array($scenes)) {
                foreach ($scenes as $s) {
                    $sceneText .= "\n  Scene {$s['scene_number']}: {$s['visual']} ({$s['duration_seconds']}s)";
                }
            }
        }

        return <<<PROMPT
You are an expert AI video prompt engineer specializing in Google Veo video generation.
Convert the following video script into an optimized Veo generation prompt.

CAMPAIGN:
- Name: {$campaign['campaign_name']}
- Platform: {$campaign['platform']}
- Brand Voice: {$campaign['brand_voice']}
- Video Length: {$campaign['video_length']} seconds
- Aspect Ratio: {$campaign['aspect_ratio']}

SCRIPT HOOK: {$script['hook']}
VOICEOVER: {$script['voiceover_text']}
CTA: {$script['cta_text']}

SCENE BREAKDOWN:{$sceneText}

Return ONLY a single valid JSON object (no markdown, no explanation) with this exact schema:
{
  "veo_prompt": "Detailed, vivid, cinematographic description for Veo. Include subjects, settings, actions, emotions, colors, and composition. Be specific and visual.",
  "negative_prompt": "What to avoid in the video (bad quality, unwanted elements, etc.)",
  "visual_style": "Overall visual style description",
  "camera_direction": "Camera movements, angles, and shot types",
  "lighting": "Lighting description and mood",
  "pacing": "Edit pacing and rhythm description",
  "aspect_ratio": "{$campaign['aspect_ratio']}",
  "duration_seconds": {$campaign['video_length']}
}

Make the veo_prompt highly descriptive (150-300 words) to guide precise video generation.
PROMPT;
    }
}
