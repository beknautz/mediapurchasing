-- Add Anthropic API key setting for AI ad copy generation
INSERT IGNORE INTO workflow_settings (setting_key, label, description, setting_group, setting_value)
VALUES ('anthropic_api_key', 'Anthropic API Key', 'Claude API key for AI ad copy generation (keep secret)', 'ai', '');
