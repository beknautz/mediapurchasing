# AI Video Studio — Setup Guide

AI Video Studio is a module for the MediaBuy PHP CMS/CRM that lets you:
1. Create campaign briefs
2. Generate video scripts with Claude AI
3. Generate Veo video prompts from scripts
4. Queue AI video generation via Google Veo
5. Manage client review + approval via a secure portal

---

## 1. SQL Migration

Run the migration file against your MySQL database:

```bash
mysql -u mediapurchasing -p mediapurchasing < migrations/ai_video_001.sql
```

Optionally load seed data (creates one example campaign):

```bash
mysql -u mediapurchasing -p mediapurchasing < migrations/ai_video_seed.sql
```

---

## 2. Configuration

Copy and configure `config/ai_video.php`. All values can be set via environment variables:

| Constant | Env Var | Description |
|---|---|---|
| `CLAUDE_API_KEY` | `CLAUDE_API_KEY` | Your Anthropic API key |
| `CLAUDE_MODEL` | `CLAUDE_MODEL` | Defaults to `claude-opus-4-5` |
| `VEO_API_KEY` | `VEO_API_KEY` | Your Google AI API key (for Veo) |
| `VEO_MODEL` | `VEO_MODEL` | Defaults to `veo-2.0-generate-001` |
| `VIDEO_STORAGE_PATH` | `VIDEO_STORAGE_PATH` | Local path to store downloaded videos |
| `VIDEO_PUBLIC_URL_BASE` | `VIDEO_PUBLIC_URL_BASE` | Public URL prefix for video files |
| `ENABLE_MOCK_VEO_MODE` | `ENABLE_MOCK_VEO_MODE` | `true` for dev (no real API calls) |
| `ADMIN_NOTIFICATION_EMAIL` | `ADMIN_NOTIFICATION_EMAIL` | Email for admin notifications |

---

## 3. CLAUDE_API_KEY Setup

Get your Anthropic API key at https://console.anthropic.com/

Set it as an environment variable or directly in `config/ai_video.php`:

```php
defined('CLAUDE_API_KEY') || define('CLAUDE_API_KEY', 'sk-ant-...');
```

**Never commit real API keys to version control.**

---

## 4. Mock Veo Mode (Development)

When `ENABLE_MOCK_VEO_MODE` is `true` (the default), no real Veo API calls are made:

- Jobs are created in the database with status `queued`
- Calling "Check Status" once advances the job to `processing`
- Calling again advances to `completed` with a mock video URL
- The video URL points to `/uploads/ai-videos/mock_video.mp4` — place a test `.mp4` there

To enable real Veo generation:

```php
define('ENABLE_MOCK_VEO_MODE', false);
define('VEO_API_KEY', 'your-google-ai-api-key');
```

---

## 5. Client Portal URL Pattern

Each campaign has a unique 64-character `review_token`. The client review portal URL is:

```
https://yourdomain.com/portal/video-review.php?token={review_token}
```

The portal requires no login — the token acts as the access credential. Share this URL with clients via the "Send to Client" button in the campaign view.

Portal actions (approve / request-revision) are in:
- `portal/actions/approve.php` — returns JSON `{"success":true}`
- `portal/actions/request-revision.php` — returns JSON `{"success":true}`

---

## 6. Directory Structure

```
admin/ai-video/
  index.php             — Campaign dashboard
  create-campaign.php   — New campaign brief form
  view-campaign.php     — Campaign hub (main page)
  script-editor.php     — Edit/regenerate scripts
  prompt-editor.php     — Edit/regenerate Veo prompts
  jobs.php              — Video job history
  reviews.php           — Client review history
  costs.php             — Cost breakdown + billing
  actions/              — HTMX fragment endpoints

src/
  ClaudeVideoService.php        — Claude API integration
  VeoVideoService.php           — Veo video generation
  AiVideoCostService.php        — Cost tracking
  AiVideoNotificationService.php — Email notifications

portal/
  video-review.php       — Client-facing review page
  actions/approve.php    — Client approval action
  actions/request-revision.php — Client revision action

config/ai_video.php       — Configuration constants
migrations/ai_video_001.sql — Database schema
migrations/ai_video_seed.sql — Example seed data
```

---

## 7. Workflow

```
Draft → Generate Script → Generate Veo Prompt → Queue Video
      → Video Processing → Ready for Review → Send to Client
      → Client Approves (done!) or Requests Revisions → repeat
```

Cost logging happens automatically at each AI step. View the full breakdown at `costs.php?campaign_id=N`.
