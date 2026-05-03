<?php
/**
 * admin/ai-video/audio-editor.php
 * Runway audio production: TTS voiceover + Sound Effects for the campaign.
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';
requireRole(['admin', 'buyer']);

$campaignId = (int)($_GET['campaign_id'] ?? 0);
if (!$campaignId) redirect('/admin/ai-video/index.php');

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$campaign = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE id = :id');
$campaign->execute([':id' => $campaignId]);
$campaign = $campaign->fetch();
if (!$campaign) redirect('/admin/ai-video/index.php');

$latestScript = $pdo->prepare(
    'SELECT * FROM ai_video_scripts WHERE campaign_id = :cid ORDER BY version_number DESC LIMIT 1'
);
$latestScript->execute([':cid' => $campaignId]);
$latestScript = $latestScript->fetch();

$audioJobs = $pdo->prepare(
    'SELECT * FROM ai_video_audio_jobs WHERE campaign_id = :cid ORDER BY created_at DESC'
);
$audioJobs->execute([':cid' => $campaignId]);
$audioJobs = $audioJobs->fetchAll();

$isMock = ENABLE_MOCK_RUNWAY_MODE;

$pageTitle = 'Audio Editor — ' . h($campaign['campaign_name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-music-note-list me-2 text-primary"></i>Audio Editor</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/index.php">AI Video Studio</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>"><?= h($campaign['campaign_name']) ?></a></li>
            <li class="breadcrumb-item active">Audio Editor</li>
        </ol>
    </nav>
</div>

<?php if ($isMock): ?>
<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Mock mode active.</strong> Audio jobs will simulate generation with a sample file.
    Set <code>ENABLE_MOCK_RUNWAY_MODE=false</code> to use the real Runway API.
</div>
<?php endif; ?>

<div class="row g-3">

    <!-- LEFT — TTS + SFX forms -->
    <div class="col-lg-7">

        <!-- ── Voiceover (TTS) ────────────────────────────────────────── -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-mic-fill me-2 text-danger"></i>Voiceover (Text-to-Speech)
                <span class="badge bg-secondary ms-1">eleven_multilingual_v2</span>
            </div>
            <div class="card-body">
                <form hx-post="/admin/ai-video/actions/queue-audio-job.php"
                      hx-vals='{"job_type":"tts"}'
                      hx-target="#tts-result"
                      hx-swap="innerHTML"
                      hx-indicator="#tts-spinner">
                    <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
                    <input type="hidden" name="job_type" value="tts">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Script Text</label>
                        <textarea name="tts_text" class="form-control" rows="6"
                                  placeholder="Enter the voiceover script…"><?= h($latestScript['script_text'] ?? '') ?></textarea>
                        <div class="form-text">Pulled from your latest script. Edit freely — max 1,000 characters sent per job.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Voice</label>
                        <div class="row g-2">
                            <?php
                            $voices = RunwayAudioService::VOICE_PRESETS;
                            sort($voices);
                            ?>
                            <div class="col-md-6">
                                <select name="voice_preset" class="form-select">
                                    <?php foreach ($voices as $v): ?>
                                    <option value="<?= h($v) ?>" <?= $v === 'Maya' ? 'selected' : '' ?>><?= h($v) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-text pt-1">
                                    <?= count($voices) ?> preset voices available.
                                    <a href="https://docs.dev.runwayml.com" target="_blank" class="small">Preview voices →</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-mic me-1"></i>Generate Voiceover
                        </button>
                        <span id="tts-spinner" class="htmx-indicator">
                            <span class="spinner-border spinner-border-sm text-danger me-1"></span>Generating…
                        </span>
                    </div>
                </form>
                <div id="tts-result" class="mt-3"></div>
            </div>
        </div>

        <!-- ── Sound Effects (SFX) ───────────────────────────────────── -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-soundwave me-2 text-warning"></i>Sound Effects
                <span class="badge bg-secondary ms-1">eleven_text_to_sound_v2</span>
            </div>
            <div class="card-body">
                <form hx-post="/admin/ai-video/actions/queue-audio-job.php"
                      hx-vals='{"job_type":"sfx"}'
                      hx-target="#sfx-result"
                      hx-swap="innerHTML"
                      hx-indicator="#sfx-spinner">
                    <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
                    <input type="hidden" name="job_type" value="sfx">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sound Description</label>
                        <input type="text" name="sfx_prompt" class="form-control"
                               placeholder="e.g. upbeat background music for a product ad, energetic and modern">
                        <div class="form-text">Describe the audio feel you want — music, ambience, or sound effect.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Duration (seconds)</label>
                            <input type="number" name="sfx_duration" class="form-control"
                                   value="15" min="1" max="30" step="0.5">
                            <div class="form-text">0.5 – 30 seconds.</div>
                        </div>
                        <div class="col-md-7 d-flex align-items-center pt-3">
                            <div class="form-check">
                                <input type="checkbox" name="sfx_loop" id="sfx_loop" class="form-check-input" value="1" checked>
                                <label class="form-check-label" for="sfx_loop">
                                    Seamless loop <span class="text-muted small">(designed to repeat)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-warning text-dark">
                            <i class="bi bi-soundwave me-1"></i>Generate Sound Effect
                        </button>
                        <span id="sfx-spinner" class="htmx-indicator">
                            <span class="spinner-border spinner-border-sm text-warning me-1"></span>Generating…
                        </span>
                    </div>
                </form>
                <div id="sfx-result" class="mt-3"></div>
            </div>
        </div>

    </div>

    <!-- RIGHT — Job History -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2 text-secondary"></i>Audio History</span>
                <span class="badge bg-secondary"><?= count($audioJobs) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($audioJobs)): ?>
                <div class="text-muted text-center py-4 small">
                    <i class="bi bi-music-note display-5 d-block mb-2"></i>
                    No audio jobs yet.
                </div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($audioJobs as $j): ?>
                    <?php $isComplete = $j['job_status'] === 'completed'; ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="small">
                                <span class="badge bg-<?= $j['job_type'] === 'tts' ? 'danger' : 'warning text-dark' ?> me-1">
                                    <?= strtoupper(h($j['job_type'])) ?>
                                </span>
                                <strong>#<?= (int)$j['id'] ?></strong>
                                <?php if ($j['job_type'] === 'tts' && $j['voice_preset']): ?>
                                <span class="text-muted ms-1"><?= h($j['voice_preset']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-<?= $j['job_status'] === 'completed' ? 'success' : ($j['job_status'] === 'failed' ? 'danger' : 'warning') ?> ms-1">
                                <?= h(ucfirst($j['job_status'])) ?>
                            </span>
                        </div>

                        <?php if ($j['prompt_text']): ?>
                        <div class="small text-muted mt-1" style="max-height:40px;overflow:hidden;">
                            <?= h(mb_substr($j['prompt_text'], 0, 80)) ?><?= mb_strlen($j['prompt_text']) > 80 ? '…' : '' ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($isComplete && $j['audio_url']): ?>
                        <div class="mt-2">
                            <audio controls class="w-100" style="height:32px;">
                                <source src="<?= h($j['audio_url']) ?>" type="audio/mpeg">
                            </audio>
                        </div>
                        <?php elseif ($j['job_status'] !== 'failed' && $j['job_status'] !== 'completed'): ?>
                        <div class="mt-1">
                            <button class="btn btn-xs btn-outline-secondary btn-sm"
                                    hx-get="/admin/ai-video/actions/poll-audio-status.php?job_id=<?= (int)$j['id'] ?>"
                                    hx-target="#audio-inline-<?= (int)$j['id'] ?>"
                                    hx-swap="outerHTML">
                                <i class="bi bi-arrow-repeat me-1"></i>Check
                            </button>
                            <span id="audio-inline-<?= (int)$j['id'] ?>"></span>
                        </div>
                        <?php endif; ?>

                        <div class="small text-muted mt-1">
                            <?= h(date('M j, g:ia', strtotime($j['created_at']))) ?>
                            &middot; $<?= number_format((float)($j['actual_cost'] ?? $j['estimated_cost']), 4) ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-info-circle me-2 text-info"></i>How it works
            </div>
            <div class="card-body small text-muted">
                <ol class="ps-3 mb-0">
                    <li class="mb-1"><strong>Voiceover</strong> — generates spoken narration from your script using Runway's ElevenLabs TTS engine.</li>
                    <li class="mb-1"><strong>Sound Effects</strong> — generates music or ambient audio from a text description.</li>
                    <li class="mb-1">Download both, then combine with your generated video in the <a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>#tab-character">Character</a> tab or your own editing tool.</li>
                </ol>
            </div>
        </div>
    </div>

</div>

<div class="mt-3">
    <a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Campaign
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
