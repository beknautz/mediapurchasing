<?php
/**
 * campaigns/rfp-template.php
 * Generates and auto-downloads a pre-filled XLSX proposal template
 * for vendors to complete and return.
 *
 * Access modes:
 *   ?token=<rfp_reply_token>               — public (vendor portal, no login)
 *   ?campaign_id=X&vendor_id=Y             — internal (requires session)
 */
require_once __DIR__ . '/../bootstrap.php';

$svc   = new CampaignService();
$token = trim($_GET['token'] ?? '');

if ($token) {
    // ── Public / vendor access ─────────────────────────────────────────────
    $ctx      = $svc->getRfpChannelByToken($token);
    if (empty($ctx)) { http_response_code(404); die('Invalid or expired link.'); }
    $ch       = $ctx['channel'];
    $channels = $ctx['channels'];
    $vendorName  = $ch['vendor_name'] ?: ($ch['vendor_email'] ?? 'Vendor');
    $campaignTitle = $ch['campaign_title'];
    $market      = $ch['campaign_market'] ?? '';
    $flightStart = $ch['campaign_start'] ?? '';
    $flightEnd   = $ch['campaign_end']   ?? '';
} else {
    // ── Internal access ────────────────────────────────────────────────────
    requireRole(['admin', 'buyer']);
    $campaignId = (int)($_GET['campaign_id'] ?? 0);
    $vendorId   = (int)($_GET['vendor_id']   ?? 0);
    if (!$campaignId || !$vendorId) { http_response_code(400); die('Missing parameters.'); }

    $data = $svc->getCampaign($campaignId);
    if (empty($data)) { http_response_code(404); die('Campaign not found.'); }

    $campaign      = $data['campaign'];
    $allChannels   = $data['channels'];
    $channels      = array_values(array_filter($allChannels, fn($c) => (int)$c['vendor_id'] === $vendorId));
    if (empty($channels)) { http_response_code(404); die('Vendor not found in this campaign.'); }

    $ch          = $channels[0];
    $vendorName  = $ch['vendor_name'] ?? 'Vendor';
    $campaignTitle = $campaign['title'];
    $market      = $campaign['market'] ?? '';
    $flightStart = $campaign['flight_start'] ?? '';
    $flightEnd   = $campaign['flight_end']   ?? '';
}

$flightLabel = ($flightStart ? date('M j, Y', strtotime($flightStart)) : 'TBD')
             . ' – '
             . ($flightEnd   ? date('M j, Y', strtotime($flightEnd))   : 'TBD');

// Build category rows for the template header section
$categoryRows = [];
foreach ($channels as $c) {
    $categoryRows[] = [$c['media_category'], number_format((float)$c['budget_allocated'], 0)];
}

$templateData = [
    'campaign'     => $campaignTitle,
    'vendor'       => $vendorName,
    'market'       => $market,
    'flight'       => $flightLabel,
    'flight_start' => $flightStart,
    'flight_end'   => $flightEnd,
    'categories'   => $categoryRows,
];
$safeTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $campaignTitle);
$filename  = 'RFP_Template_' . $safeTitle . '_' . date('Ymd') . '.xlsx';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generating Template…</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 3rem; color: #444; }
        .spinner { width: 2rem; height: 2rem; border: 3px solid #ddd; border-top-color: #0d6efd; border-radius: 50%; animation: spin .7s linear infinite; margin: 1rem auto; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="spinner"></div>
    <p>Generating your proposal template…</p>
    <p id="done" style="display:none;color:#198754;font-weight:bold;">
        ✓ Download started. You can close this window.
    </p>

    <!-- SheetJS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
    (function() {
        var data = <?= json_encode($templateData, JSON_UNESCAPED_UNICODE) ?>;
        var filename = <?= json_encode($filename) ?>;

        var wb = XLSX.utils.book_new();

        // ── Sheet 1: Proposal ──────────────────────────────────────────────
        var rows = [];

        // Title block
        rows.push(['RFP PROPOSAL TEMPLATE']);
        rows.push(['']);
        rows.push(['Campaign:', data.campaign]);
        rows.push(['Vendor:', data.vendor]);
        rows.push(['Market:', data.market || '—']);
        rows.push(['Flight Dates:', data.flight]);
        rows.push(['']);

        // Requested items summary
        rows.push(['REQUESTED MEDIA ITEMS']);
        rows.push(['Media Category', 'Budget Allocated']);
        data.categories.forEach(function(cat) {
            rows.push([cat[0], '$' + cat[1]]);
        });
        rows.push(['']);

        // Instructions
        rows.push(['PROPOSAL INSTRUCTIONS']);
        rows.push(['Please complete all rows in the "Ad Schedule" section below.']);
        rows.push(['Add as many rows as needed for each media placement.']);
        rows.push(['Return this file via the online portal or reply to the RFP email.']);
        rows.push(['']);

        // ── Ad Schedule header ─────────────────────────────────────────────
        rows.push(['AD SCHEDULE']);
        var scheduleHeaderRow = rows.length; // 0-indexed after push
        rows.push([
            'Media Category',
            'Placement / Daypart / Program',
            'Unit Type',
            'Flight Start',
            'Flight End',
            '# Spots / Units / Impressions',
            'Rate per Unit ($)',
            'Total Cost ($)',
            'Notes / Availabilities'
        ]);

        // Pre-fill one blank row per requested category
        var flightStart = data.flight_start || '';
        var flightEnd   = data.flight_end   || '';
        data.categories.forEach(function(cat) {
            rows.push([
                cat[0],     // Media Category
                '',         // Placement
                '',         // Unit Type
                flightStart,// Flight Start
                flightEnd,  // Flight End
                '',         // Quantity
                '',         // Unit Rate
                '',         // Total
                ''          // Notes
            ]);
        });

        // Extra blank rows for additional placements
        for (var i = 0; i < 10; i++) {
            rows.push(['', '', '', '', '', '', '', '', '']);
        }

        var ws = XLSX.utils.aoa_to_sheet(rows);

        // ── Column widths ──────────────────────────────────────────────────
        ws['!cols'] = [
            {wch: 22}, // A: Media Category
            {wch: 32}, // B: Placement
            {wch: 18}, // C: Unit Type
            {wch: 14}, // D: Flight Start
            {wch: 14}, // E: Flight End
            {wch: 14}, // F: Quantity
            {wch: 16}, // G: Rate
            {wch: 16}, // H: Total
            {wch: 30}, // I: Notes
        ];

        XLSX.utils.book_append_sheet(wb, ws, 'Proposal');

        // ── Sheet 2: Production Notes ──────────────────────────────────────
        var prodRows = [];
        prodRows.push(['PRODUCTION SPECIFICATIONS']);
        prodRows.push(['']);
        prodRows.push(['If you require specific production specifications or materials, list them below.']);
        prodRows.push(['']);
        prodRows.push([
            'Media Type',
            'Ad / Spot Name',
            'Unit Specs (size, length, format)',
            'Material Due Date',
            'Notes'
        ]);
        data.categories.forEach(function(cat) {
            prodRows.push([cat[0], '', '', '', '']);
        });
        for (var j = 0; j < 5; j++) {
            prodRows.push(['', '', '', '', '']);
        }

        var wsProd = XLSX.utils.aoa_to_sheet(prodRows);
        wsProd['!cols'] = [
            {wch: 20}, {wch: 28}, {wch: 36}, {wch: 18}, {wch: 30}
        ];
        XLSX.utils.book_append_sheet(wb, wsProd, 'Production Notes');

        // ── Download ───────────────────────────────────────────────────────
        XLSX.writeFile(wb, filename);

        document.getElementById('done').style.display = 'block';
        document.querySelector('.spinner').style.display = 'none';
        document.querySelector('p:not(#done)').style.display = 'none';

        // Auto-close after 2 seconds if opened in a popup
        if (window.opener) {
            setTimeout(function() { window.close(); }, 2000);
        }
    })();
    </script>
</body>
</html>
