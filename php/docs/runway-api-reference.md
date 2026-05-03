# Runway ML API Reference
> Base URL: `https://api.dev.runwayml.com/v1`
> Auth headers (every request):
> ```
> Authorization: Bearer {RUNWAY_API_KEY}
> X-Runway-Version: 2024-11-06
> Content-Type: application/json
> ```

---

## Polling Pattern
All generation endpoints return a task immediately. Poll until done:
```
GET /v1/tasks/{taskId}
→ { "id": "...", "status": "PENDING|RUNNING|SUCCEEDED|FAILED", "output": [...], "failure": "..." }
```
- `PENDING` → 10% progress
- `RUNNING` → 50% progress
- `SUCCEEDED` → `output[0]` = URL (expires 24–48 h — download immediately)
- `FAILED` → `failure` field has error message

---

## Text-to-Video
```
POST /v1/text_to_video
{
  "model":       "gen4.5",          // gen4.5 | gen4_turbo | gen4_aleph
  "promptText":  "...",             // max 1000 chars
  "ratio":       "720:1280",        // 1280:720 | 720:1280
  "duration":    5,                 // 5 or 10 (seconds)
  "watermark":   false,
  "promptImage": "https://..."      // optional: image-to-video first frame
}
→ { "id": "taskId", "status": "PENDING" }
```

---

## Image-to-Video
```
POST /v1/image_to_video
{
  "model":      "gen4_turbo",
  "promptImage": "https://..." | "runway://...",
  "promptText":  "...",
  "ratio":       "720:1280",
  "duration":    5
}
→ { "id": "taskId", "status": "PENDING" }
```

---

## Text-to-Speech (Voiceover)
```
POST /v1/text_to_speech
{
  "model": "eleven_multilingual_v2",
  "promptText": "...",              // max 1000 chars (UTF-16 code units)
  "voice": {
    "type": "runway-preset",
    "presetId": "Maya"              // see voice list below
  }
}
→ { "id": "taskId", "status": "PENDING" }
// output[0] = audio URL (mp3)
```

### Available Voice Presets
Female: Maya, Serene, Mabel, Leslie, Eleanor, Kylie, Lara, Lisa, Marlene, Miriam, Paula, Sandra, Maggie, Katie, Rina, Ella, Mariah, Claudia, Niki, Myrna, Wanda, Kiana, Rachel
Male: Arjun, Bernard, Billy, Mark, Clint, Chad, Elias, Elliot, Grungle, Brodie, Kirk, Malachi, Martin, Monster, Pip, Rusty, Ragnar, Xylar, Jack, Noah, James, Frank, Vincent, Kendrick, Tom, Benjamin

---

## Sound Effects
```
POST /v1/sound_effect   (model maps to eleven_text_to_sound_v2)
{
  "model":      "eleven_text_to_sound_v2",
  "promptText": "upbeat background music for a product ad",
  "duration":   15.0,              // 0.5–30 seconds (optional, auto if omitted)
  "loop":       true               // seamless loop output
}
→ { "id": "taskId", "status": "PENDING" }
// output[0] = audio URL
```

---

## Character Performance (Act Two / Lip Sync)
Drives a character image or video using a reference performance video.
```
POST /v1/character_performance
{
  "model": "act_two",
  "character": {
    "type": "image",               // "image" | "video"
    "uri":  "https://..."          // character face/body image or video
  },
  "reference": {
    "type": "video",
    "uri":  "https://..."          // driving performance video
  },
  "bodyControl":         true,     // enable non-facial movement/gestures
  "expressionIntensity": 3,        // 1–5 (1=subtle, 5=exaggerated)
  "ratio": "720:1280",             // 1280:720 | 720:1280 | 960:960 | 1104:832 | 832:1104 | 1584:672
  "contentModeration": {
    "publicFigureThreshold": "auto" // "auto" | "low"
  }
}
→ { "id": "taskId", "status": "PENDING" }
// output[0] = video URL
```

---

## Image Generation
```
POST /v1/text_to_image
{
  "model": "gen4_image",           // gen4_image | gen4_image_turbo | gemini_image3_pro
  "promptText": "...",
  "referenceImages": [             // optional style/content references
    { "url": "https://...", "tag": "style_ref" }
  ],
  "ratio": "1:1"                   // 1:1 | 16:9 | 9:16 | etc.
}
→ { "id": "taskId", "status": "PENDING" }
// output[0] = image URL
```

---

## File Uploads (get a runway:// URI)
Two-step upload to pass files to generation APIs:
```
Step 1 — Get upload URL:
POST /v1/uploads
{ "filename": "video.mp4", "type": "ephemeral" }
→ {
    "runwayUri":  "runway://asset/...",
    "uploadUrl":  "https://storage.googleapis.com/...",
    "fields":     { "key": "...", "policy": "...", ... }
  }

Step 2 — Upload the file:
POST {uploadUrl}   (multipart/form-data)
  fields.*  (spread all fields from Step 1 as form fields)
  file      = <binary file contents>
```
`runway://` URIs expire in 24 hours.

---

## Real-Time Avatar Sessions
Interactive conversational avatar (WebRTC-based, not batch):
```
POST /v1/realtime_sessions
{ "model": "gwm1_avatars", "avatar": { "type": "custom", "avatarId": "..." } }
→ { "id": "sessionId", "status": "PENDING" }

GET /v1/realtime_sessions/{sessionId}   — poll until status = "READY"

POST /v1/realtime_sessions/{sessionId}/consume
→ { "url": "...", "token": "...", "roomName": "..." }   (LiveKit WebRTC credentials)
```

---

## Pricing (approximate)
| Feature              | Model                      | Est. Cost          |
|----------------------|----------------------------|--------------------|
| Text-to-Video Gen-4  | gen4.5                     | ~$0.05/sec         |
| Text-to-Speech       | eleven_multilingual_v2     | ~$0.30/1k chars    |
| Sound Effects        | eleven_text_to_sound_v2    | ~$0.10/generation  |
| Character Perf.      | act_two                    | ~$0.05/sec output  |
| Image Generation     | gen4_image                 | ~$0.06/image       |
| File Upload          | ephemeral                  | free               |

---

## PHP Service Classes in This Project
| Class                     | File                           | Purpose                        |
|---------------------------|--------------------------------|--------------------------------|
| `RunwayVideoService`      | `src/RunwayVideoService.php`   | Text/image-to-video            |
| `RunwayAudioService`      | `src/RunwayAudioService.php`   | TTS + Sound Effects            |
| `RunwayCharacterService`  | `src/RunwayCharacterService.php` | Act Two character performance |
| `VeoVideoService`         | `src/VeoVideoService.php`      | Google Veo via Vertex AI       |
| `VideoServiceFactory`     | `src/VideoServiceFactory.php`  | Provider selection             |
