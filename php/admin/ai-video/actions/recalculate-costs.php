<?php
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger">POST method required.</div>';
    exit;
}

$campaignId = (int)($_POST['campaign_id'] ?? 0);
$markupPct  = (float)($_POST['markup_pct'] ?? 30.0);

if (!$campaignId) {
    echo '<div class="alert alert-danger">Campaign ID required.</div>';
    exit;
}

try {
    $costSvc = new AiVideoCostService();
    $summary = $costSvc->getCostSummary($campaignId, $markupPct);

    echo '<div id="cost-summary-section">';
    echo '<div class="row g-3 mb-4">';

    $cards = [
        ['val' => $summary['claude_cost'],    'fmt' => 4, 'prefix' => '$', 'label' => 'Claude AI Cost',    'color' => 'info'],
        ['val' => $summary['provider_cost'],  'fmt' => 4, 'prefix' => '$', 'label' => 'Video Generation',  'color' => 'warning'],
        ['val' => $summary['internal_cost'],  'fmt' => 4, 'prefix' => '$', 'label' => 'Internal Total',    'color' => 'primary'],
        ['val' => $summary['markup_amount'],  'fmt' => 2, 'prefix' => '$', 'label' => 'Markup (' . number_format($markupPct,0) . '%)', 'color' => 'success'],
        ['val' => $summary['billable_fee'],   'fmt' => 2, 'prefix' => '$', 'label' => 'Billable Fee',      'color' => 'success', 'bg' => true],
        ['val' => $summary['margin'],         'fmt' => 1, 'prefix' => '',  'suffix' => '%', 'label' => 'Margin', 'color' => 'secondary'],
    ];

    foreach ($cards as $card) {
        $formatted = ($card['prefix'] ?? '') . number_format((float)$card['val'], $card['fmt']) . ($card['suffix'] ?? '');
        $bg = !empty($card['bg']) ? ' bg-success bg-opacity-10' : '';
        echo '<div class="col-6 col-md-2">';
        echo '<div class="card border-0 shadow-sm text-center py-3 h-100' . $bg . '">';
        echo '<div class="fs-4 fw-bold text-' . $card['color'] . '">' . $formatted . '</div>';
        echo '<div class="small text-muted">' . h($card['label']) . '</div>';
        echo '</div>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

} catch (Throwable $e) {
    error_log('[AI Video] recalculate-costs error: ' . $e->getMessage());
    echo '<div class="alert alert-danger">' . h($e->getMessage()) . '</div>';
}
