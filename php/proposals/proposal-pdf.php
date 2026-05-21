<?php
/**
 * proposals/proposal-pdf.php
 * Generate a downloadable PDF of a Proposal via Dompdf.
 *
 * Dompdf is loaded from lib/dompdf/ via a custom autoloader — no Composer required.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

// ── Load Dompdf from bundled zip extraction ───────────────────────────────────
$dompdfAutoload = __DIR__ . '/../lib/dompdf-autoload.php';
if (file_exists($dompdfAutoload)) {
    require_once $dompdfAutoload;
}

if (!class_exists('Dompdf\Dompdf')) {
    flash('error', 'Dompdf not found. Ensure lib/dompdf/ exists (extracted from dompdf-master.zip).');
    redirect('/proposals/index.php');
}

$proposalService = new ProposalService();
$agSvc           = new AgencyAgreementService();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    redirect('/proposals/index.php');
}

$data = $proposalService->getProposal($id);
if (!$data) {
    flash('error', 'Proposal not found.');
    redirect('/proposals/index.php');
}

$proposal = $data['proposal'];
$blocks   = $data['blocks'];
$branding = $agSvc->getBranding();

// ── Helpers ───────────────────────────────────────────────────────────────────
$h     = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$money = fn($v) => '$' . number_format((float) $v, 2);

// ── Embed logos as base64 data URIs — avoids ALL Dompdf path/SVG issues ────────
// Dompdf's file-path resolution and the optional svg-lib dependency both cause
// crashes when images can't be located. Data URIs sidestep both problems.

$logoToDataUri = function (?string $url): string {
    if (empty($url)) return '';

    // Resolve URL → absolute filesystem path
    if (str_starts_with($url, '/')) {
        $webRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/..'), '/\\');
        $path    = $webRoot . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $url), DIRECTORY_SEPARATOR);
    } else {
        $path = $url; // already absolute or relative local path
    }

    if (!file_exists($path) || !is_readable($path)) return '';

    $raw  = file_get_contents($path);
    if ($raw === false) return '';

    // Detect MIME from extension (mime_content_type can misfire on some hosts)
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'gif'         => 'image/gif',
        'webp'        => 'image/webp',
        'svg'         => 'image/svg+xml',
        default       => (function_exists('mime_content_type') ? mime_content_type($path) : 'image/png'),
    };

    return 'data:' . $mime . ';base64,' . base64_encode($raw);
};

$agencyLogoUri = $logoToDataUri($branding['logo_url'] ?? '');
$clientLogoUri = $logoToDataUri($proposal['client_logo_url'] ?? '');

$agencyLogoHtml = $agencyLogoUri
    ? '<img src="' . $agencyLogoUri . '" style="max-height:48px;max-width:180px;">'
    : '';

$clientLogoHtml = $clientLogoUri
    ? '<img src="' . $clientLogoUri . '" style="max-height:44px;max-width:160px;display:block;margin-bottom:6px;">'
    : '';

// ── Compute grand total from item blocks ──────────────────────────────────────
$grandTotal = 0;
foreach ($blocks as $b) {
    if ($b['block_type'] === 'item') {
        $grandTotal += (float) $b['quantity'] * (float) $b['unit_price'];
    }
}

// ── Status label ──────────────────────────────────────────────────────────────
$statusLabel = ProposalService::STATUS_LABELS[$proposal['status']] ?? $proposal['status'];

// ── Build HTML document ───────────────────────────────────────────────────────

$html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
$html .= '<title>' . $h($proposal['title']) . '</title>';
$html .= '<style>';
$html .= 'body { font-family: helvetica, Arial, sans-serif; font-size: 10pt; color: #1e293b; line-height: 1.6; margin: 0; padding: 0; }';
$html .= 'p { margin: 0 0 7px; }';
$html .= 'table { border-spacing: 0; }';
$html .= 'strong { color: #0f172a; }';
$html .= '</style>';
$html .= '</head><body>';

// ── HEADER — dark navy bar ────────────────────────────────────────────────────
$html .= '<table style="width:100%;border-collapse:collapse;background:#0f172a;">';
$html .= '<tr>';
$html .= '<td style="padding:20px 24px;vertical-align:middle;">';
if ($agencyLogoHtml) {
    $html .= $agencyLogoHtml;
} else {
    $html .= '<span style="color:#ffffff;font-size:14pt;font-weight:700;">' . $h($branding['name']) . '</span>';
}
$html .= '</td>';
$html .= '<td style="padding:20px 24px;text-align:right;vertical-align:middle;">';
$html .= '<span style="color:#ffffff;font-size:22pt;font-weight:800;letter-spacing:.1em;opacity:.9;">PROPOSAL</span>';
$html .= '</td>';
$html .= '</tr>';
$html .= '</table>';

// ── SUBHEADER — Prepared For / Prepared By ────────────────────────────────────
$html .= '<table style="width:100%;border-collapse:collapse;border:1px solid #e2e8f0;">';
$html .= '<tr>';

// Prepared For (left)
$html .= '<td style="width:50%;padding:16px 20px;vertical-align:top;border-right:1px solid #e2e8f0;">';
$html .= '<div style="font-size:7pt;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:6px;">Prepared For</div>';
if ($clientLogoHtml) {
    $html .= $clientLogoHtml;
}
$html .= '<div style="font-weight:700;font-size:12pt;color:#0f172a;">' . $h($proposal['client_name'] ?? '—') . '</div>';
$html .= '</td>';

// Prepared By (right)
$html .= '<td style="width:50%;padding:16px 20px;vertical-align:top;">';
$html .= '<div style="font-size:7pt;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:6px;">Prepared By</div>';
$html .= '<div style="font-weight:700;font-size:11pt;color:#0f172a;">' . $h($branding['name']) . '</div>';
$html .= '<div style="font-size:9pt;color:#475569;">' . $h($branding['address']) . ', ' . $h($branding['city_state_zip']) . '</div>';
$html .= '<div style="font-size:9pt;color:#475569;">' . $h($branding['phone']) . '</div>';
$html .= '</td>';

$html .= '</tr>';
$html .= '</table>';

// ── META BAR ──────────────────────────────────────────────────────────────────
$html .= '<table style="width:100%;border-collapse:collapse;background:#f1f5f9;border-bottom:1px solid #e2e8f0;">';
$html .= '<tr>';

// Proposal title
$html .= '<td style="padding:10px 20px;vertical-align:top;">';
$html .= '<div style="font-size:7pt;text-transform:uppercase;letter-spacing:.07em;color:#64748b;">Proposal</div>';
$html .= '<div style="font-weight:700;font-size:10pt;color:#0f172a;">' . $h($proposal['title']) . '</div>';
$html .= '</td>';

// Valid Until
if ($proposal['valid_until']) {
    $html .= '<td style="padding:10px 20px;vertical-align:top;text-align:right;">';
    $html .= '<div style="font-size:7pt;text-transform:uppercase;letter-spacing:.07em;color:#64748b;">Valid Until</div>';
    $isExpired = strtotime($proposal['valid_until']) < time();
    $dateColor = $isExpired ? '#dc2626' : '#0f172a';
    $html .= '<div style="font-weight:600;color:' . $dateColor . ';">' . $h(date('M j, Y', strtotime($proposal['valid_until']))) . '</div>';
    $html .= '</td>';
}

// Date Prepared
$html .= '<td style="padding:10px 20px;vertical-align:top;text-align:right;">';
$html .= '<div style="font-size:7pt;text-transform:uppercase;letter-spacing:.07em;color:#64748b;">Date Prepared</div>';
$html .= '<div style="font-weight:600;color:#0f172a;">' . $h(date('M j, Y', strtotime($proposal['created_at']))) . '</div>';
$html .= '</td>';

// Proposal ID
$html .= '<td style="padding:10px 20px;vertical-align:top;text-align:right;">';
$html .= '<div style="font-size:7pt;text-transform:uppercase;letter-spacing:.07em;color:#64748b;">Proposal #</div>';
$html .= '<div style="font-weight:600;color:#0f172a;">' . $h($proposal['id']) . '</div>';
$html .= '</td>';

$html .= '</tr>';
$html .= '</table>';

// ── ACCENT DIVIDER ────────────────────────────────────────────────────────────
$html .= '<div style="height:3px;background:#2563eb;margin-bottom:20px;"></div>';

// ── CONTENT BLOCKS ────────────────────────────────────────────────────────────
$itemBuffer = [];

foreach ($blocks as $b) {
    if ($b['block_type'] === 'item') {
        $itemBuffer[] = $b;
    } else {
        // Flush accumulated item blocks first
        if (!empty($itemBuffer)) {
            // Render item table
            $subtotal = array_sum(array_map(fn($i) => (float) $i['total_price'], $itemBuffer));
            $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:16px;">';
            $html .= '<tr style="background:#0f172a;">';
            $html .= '<th style="padding:7px 10px;text-align:left;color:#ffffff;font-size:9pt;">Description</th>';
            $html .= '<th style="padding:7px 10px;text-align:center;color:#ffffff;font-size:9pt;width:60px;">Qty</th>';
            $html .= '<th style="padding:7px 10px;text-align:right;color:#ffffff;font-size:9pt;width:100px;">Unit Price</th>';
            $html .= '<th style="padding:7px 10px;text-align:right;color:#ffffff;font-size:9pt;width:100px;">Total</th>';
            $html .= '</tr>';
            foreach ($itemBuffer as $ri => $item) {
                $bg = $ri % 2 === 0 ? '#ffffff' : '#f8fafc';
                $html .= '<tr style="background:' . $bg . ';">';
                $html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;">' . $h($item['description']) . '</td>';
                $qty = rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.');
                $html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center;">' . $h($qty) . '</td>';
                $html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:right;">' . $money($item['unit_price']) . '</td>';
                $html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">' . $money($item['total_price']) . '</td>';
                $html .= '</tr>';
            }
            if (count($itemBuffer) > 1) {
                $html .= '<tr style="background:#f1f5f9;">';
                $html .= '<td colspan="3" style="padding:6px 10px;text-align:right;color:#64748b;font-size:9pt;">Subtotal</td>';
                $html .= '<td style="padding:6px 10px;text-align:right;font-weight:600;">' . $money($subtotal) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $itemBuffer = [];
        }

        if ($b['block_type'] === 'text') {
            // Render Summernote HTML directly
            $html .= '<div style="margin-bottom:16px;line-height:1.8;">';
            $html .= $b['content'];
            $html .= '</div>';
        } elseif ($b['block_type'] === 'signature') {
            $label = $b['sig_label'] ?: 'Authorized Signature';
            $html .= '<table style="width:100%;border-collapse:collapse;margin:16px 0 20px;">';
            $html .= '<tr>';
            $html .= '<td style="width:55%;vertical-align:top;padding-right:20px;">';
            $html .= '<div style="border-bottom:1px solid #334155;height:32px;margin-bottom:5px;"></div>';
            $html .= '<table style="width:100%;border-collapse:collapse;">';
            $html .= '<tr>';
            $html .= '<td style="font-size:8.5pt;color:#475569;">' . $h($label) . '</td>';
            $html .= '<td style="text-align:right;font-size:8.5pt;color:#475569;">Date</td>';
            $html .= '</tr>';
            $html .= '</table>';
            $html .= '</td>';
            $html .= '<td style="width:45%;"></td>';
            $html .= '</tr>';
            $html .= '</table>';
        }
    }
}

// Flush remaining item buffer
if (!empty($itemBuffer)) {
    $subtotal = array_sum(array_map(fn($i) => (float) $i['total_price'], $itemBuffer));
    $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:16px;">';
    $html .= '<tr style="background:#0f172a;">';
    $html .= '<th style="padding:7px 10px;text-align:left;color:#ffffff;font-size:9pt;">Description</th>';
    $html .= '<th style="padding:7px 10px;text-align:center;color:#ffffff;font-size:9pt;width:60px;">Qty</th>';
    $html .= '<th style="padding:7px 10px;text-align:right;color:#ffffff;font-size:9pt;width:100px;">Unit Price</th>';
    $html .= '<th style="padding:7px 10px;text-align:right;color:#ffffff;font-size:9pt;width:100px;">Total</th>';
    $html .= '</tr>';
    foreach ($itemBuffer as $ri => $item) {
        $bg = $ri % 2 === 0 ? '#ffffff' : '#f8fafc';
        $html .= '<tr style="background:' . $bg . ';">';
        $html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;">' . $h($item['description']) . '</td>';
        $qty = rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.');
        $html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center;">' . $h($qty) . '</td>';
        $html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:right;">' . $money($item['unit_price']) . '</td>';
        $html .= '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">' . $money($item['total_price']) . '</td>';
        $html .= '</tr>';
    }
    if (count($itemBuffer) > 1) {
        $html .= '<tr style="background:#f1f5f9;">';
        $html .= '<td colspan="3" style="padding:6px 10px;text-align:right;color:#64748b;font-size:9pt;">Subtotal</td>';
        $html .= '<td style="padding:6px 10px;text-align:right;font-weight:600;">' . $money($subtotal) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
}

// ── GRAND TOTAL ───────────────────────────────────────────────────────────────
if ($grandTotal > 0) {
    $html .= '<table style="width:100%;border-collapse:collapse;margin-top:16px;">';
    $html .= '<tr>';
    $html .= '<td style="padding:0;"></td>';
    $html .= '<td style="width:260px;background:#0f172a;padding:14px 20px;text-align:right;">';
    $html .= '<span style="color:#94a3b8;font-size:8.5pt;text-transform:uppercase;letter-spacing:.06em;">Grand Total</span>';
    $html .= '<span style="color:#ffffff;font-size:16pt;font-weight:800;margin-left:16px;">' . $money($grandTotal) . '</span>';
    $html .= '</td>';
    $html .= '</tr>';
    $html .= '</table>';
}

$html .= '</body></html>';

// ── Render via Dompdf ─────────────────────────────────────────────────────────
use Dompdf\Dompdf;
use Dompdf\Options;

// Use 'helvetica' — a PDF core font built into every PDF viewer.
// Requires NO font files on disk, so it works on any server regardless of
// what's in lib/dompdf/lib/fonts/. Custom fonts (DejaVu etc.) need .ufm
// metric files that may not be present in this bundled install.
$options = new Options();
$options->setIsRemoteEnabled(false);       // not needed — logos are base64
$options->setIsHtml5ParserEnabled(false);  // use DOMDocument parser
$options->setDefaultFont('helvetica');     // PDF core font — zero file deps
$options->setDpi(96);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();

$slug     = preg_replace('/[^A-Za-z0-9_-]/', '-', $proposal['title']);
$slug     = trim($slug, '-');
$filename = 'Proposal-' . $slug . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
