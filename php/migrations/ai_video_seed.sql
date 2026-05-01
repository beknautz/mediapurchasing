-- ============================================================
-- AI Video Studio — Seed Data
-- Inserts one example campaign (client_id=1, status='script_generated')
-- with one script, one prompt, one mock completed job, and one review.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Example campaign
INSERT INTO `ai_video_campaigns`
    (`id`, `client_id`, `campaign_name`, `objective`, `target_audience`, `offer`,
     `brand_voice`, `platform`, `video_length`, `aspect_ratio`, `call_to_action`,
     `landing_page_url`, `notes`, `status`, `review_token`, `created_by`, `created_at`, `updated_at`)
VALUES
    (1, 1,
     'Summer Sale Launch 2025',
     'Drive awareness and conversions for our summer sale with up to 50% off select products.',
     'Women 25-44 interested in fashion, lifestyle, and deals. Middle income, mobile-first.',
     '50% off all summer styles — limited time only',
     'Energetic',
     'Instagram',
     30,
     '9:16',
     'Shop Now',
     'https://example.com/summer-sale',
     'Seed data example campaign. Not a real campaign.',
     'script_generated',
     'seed_token_abc123def456ghi789jkl012mno345pqr',
     1,
     NOW(),
     NOW()
    );

-- Example script (version 1)
INSERT INTO `ai_video_scripts`
    (`id`, `campaign_id`, `version_number`, `script_text`, `hook`, `scene_breakdown`,
     `voiceover_text`, `on_screen_text`, `cta_text`,
     `claude_model`, `claude_input_tokens`, `claude_output_tokens`, `claude_cost`, `created_at`)
VALUES
    (1, 1, 1,
     'HOOK: Summer is HERE — and so are our biggest deals of the year!\n\nSCENE 1: Fast montage of colorful summer outfits.\nSCENE 2: Price tags flipping to show 50% off.\nSCENE 3: Happy customer smiling with bags.\nSCENE 4: App/website on phone screen.\n\nVOICEOVER: This summer, style meets savings. Get up to 50% off our hottest looks — for a limited time only.\n\nCTA: Tap Shop Now and save big today!',
     'Summer is HERE — and so are our biggest deals of the year!',
     '[{"scene_number":1,"description":"Fast montage of colorful summer outfits","visual":"Bright, vibrant clothing on models","duration_seconds":6},{"scene_number":2,"description":"Price tags flipping to show 50% off","visual":"Animated price tags, bold red 50% graphic","duration_seconds":8},{"scene_number":3,"description":"Happy customer smiling with shopping bags","visual":"Lifestyle shot, warm sunlight","duration_seconds":8},{"scene_number":4,"description":"App and website on phone screen","visual":"Phone close-up showing sale page","duration_seconds":8}]',
     'This summer, style meets savings. Get up to 50% off our hottest looks — for a limited time only.',
     'UP TO 50% OFF\nLimited Time Only\nShop Now',
     'Tap Shop Now and save big today!',
     'claude-opus-4-5',
     1250,
     820,
     0.080250,
     NOW()
    );

-- Example Veo prompt (version 1)
INSERT INTO `ai_video_prompts`
    (`id`, `campaign_id`, `script_id`, `version_number`, `veo_prompt`, `negative_prompt`,
     `visual_style`, `camera_direction`, `lighting`, `pacing`,
     `aspect_ratio`, `duration_seconds`,
     `claude_model`, `claude_input_tokens`, `claude_output_tokens`, `claude_cost`, `created_at`)
VALUES
    (1, 1, 1, 1,
     'Vibrant summer fashion advertisement. Quick-cut montage of colorful summer outfits on diverse models in sunny outdoor settings. Bold animated price tags showing 50% off. Happy customer with shopping bags in golden sunlight. Close-up of smartphone displaying a summer sale website. Energetic, aspirational lifestyle aesthetic. Warm color palette with pops of coral, turquoise and yellow.',
     'Dark themes, winter clothing, indoor warehouse settings, negative emotions, blurry shots, text overlays (handled separately)',
     'Bright lifestyle commercial, vibrant and aspirational',
     'Mix of dynamic panning shots, close-ups, and wide establishing shots. Quick cuts every 6-8 seconds.',
     'Golden hour natural sunlight, bright studio lighting for product shots',
     'Fast-paced, energetic cuts synchronized to upbeat music',
     '9:16',
     30,
     'claude-opus-4-5',
     980,
     610,
     0.060450,
     NOW()
    );

-- Example mock completed job
INSERT INTO `ai_video_jobs`
    (`id`, `campaign_id`, `script_id`, `prompt_id`, `parent_job_id`,
     `provider`, `provider_job_id`, `job_status`, `progress_percent`,
     `requested_duration_seconds`, `requested_aspect_ratio`,
     `provider_request_json`, `provider_response_json`,
     `video_url`, `local_file_path`, `thumbnail_url`, `error_message`,
     `estimated_provider_cost`, `actual_provider_cost`, `total_ai_cost`,
     `created_by`, `created_at`, `queued_at`, `started_at`, `completed_at`, `failed_at`)
VALUES
    (1, 1, 1, 1, NULL,
     'veo', 'mock_seed_job_001', 'completed', 100,
     30, '9:16',
     '{"instances":[{"prompt":"Vibrant summer fashion advertisement..."}],"parameters":{"aspectRatio":"9:16","durationSeconds":30}}',
     '{"done":true,"response":{"videos":[{"uri":"gs://mock-bucket/mock_video.mp4"}]}}',
     '/uploads/ai-videos/mock_video.mp4', NULL, NULL, NULL,
     1.50, 1.50, 1.70,
     1, NOW(), NOW(), NOW(), NOW(), NULL
    );

-- Example review record
INSERT INTO `ai_video_reviews`
    (`id`, `campaign_id`, `job_id`, `client_id`, `review_status`,
     `reviewer_name`, `reviewer_email`, `comments`, `revision_notes`,
     `approved_at`, `revision_requested_at`, `created_at`)
VALUES
    (1, 1, 1, 1, 'pending',
     NULL, NULL, NULL, NULL,
     NULL, NULL, NOW()
    );

-- Log the costs
INSERT INTO `ai_video_costs`
    (`campaign_id`, `job_id`, `cost_type`, `provider`, `model`, `units`, `unit_cost`, `total_cost`, `notes`, `created_at`)
VALUES
    (1, NULL, 'script_generation', 'anthropic', 'claude-opus-4-5', 2070, 0.000038793, 0.080250, 'Script v1 — input 1250 + output 820 tokens', NOW()),
    (1, NULL, 'prompt_generation', 'anthropic', 'claude-opus-4-5', 1590, 0.000038019, 0.060450, 'Prompt v1 — input 980 + output 610 tokens', NOW()),
    (1, 1,    'video_generation',  'veo',       'veo-2.0-generate-001', 30, 0.050000, 1.500000, 'Mock video generation — 30 seconds', NOW());

SET FOREIGN_KEY_CHECKS = 1;
