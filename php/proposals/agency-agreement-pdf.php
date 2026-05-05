<?php
/**
 * proposals/agency-agreement-pdf.php
 * Generate a downloadable PDF of an Agency Agreement via Dompdf.
 *
 * Dompdf is loaded from lib/dompdf/ (extracted from dompdf-master.zip)
 * via a custom autoloader — no Composer required.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

// ── Load Dompdf from bundled zip extraction ───────────────────────────────────
$dompdfAutoload = __DIR__ . '/../lib/dompdf-autoload.php';
if (file_exists($dompdfAutoload)) {
    require_once $dompdfAutoload;
}

$svc = new AgencyAgreementService();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/proposals/index.php');

$ag = $svc->get($id);
if (!$ag) { flash('error', 'Agreement not found.'); redirect('/proposals/index.php'); }

$branding    = $svc->getBranding();
$budgetTotal = $svc->budgetTotal($ag);

// ── Helpers ──────────────────────────────────────────────────────────────────
$money   = fn($v) => '$' . number_format((float)$v, 2);
$h       = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$fmtDate = function(?string $d): string {
    if (!$d) return '';
    $ts = strtotime($d);
    return $ts ? date('F j, Y', $ts) : $d;
};

// ── Check for Dompdf ─────────────────────────────────────────────────────────
if (!class_exists('Dompdf\Dompdf')) {
    flash('error', 'Dompdf not found. Ensure lib/dompdf/ exists (extracted from dompdf-master.zip).');
    redirect('/proposals/agency-agreement-view.php?id=' . $id);
}

// ── Build self-contained HTML ─────────────────────────────────────────────────
// Dompdf needs inline CSS and absolute image paths.
$logoHtml = '';
if (!empty($branding['logo_url'])) {
    $logoSrc = $branding['logo_url'];
    // Convert relative path to absolute filesystem path for Dompdf
    if (str_starts_with($logoSrc, '/')) {
        $logoSrc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..', '/') . $logoSrc;
        $logoHtml = '<img src="' . $h($logoSrc) . '" style="max-height:60px;max-width:200px;">';
    } else {
        $logoHtml = '<img src="' . $h($logoSrc) . '" style="max-height:60px;max-width:200px;">';
    }
}

// Payment schedule rows HTML
$paySchedule    = $ag['payment_schedule'] ?? [];
$payScheduleHtml = '';
if (!empty($paySchedule)) {
    $payScheduleHtml .= '<table style="width:100%;border-collapse:collapse;margin:8px 0;">';
    $payScheduleHtml .= '<tr style="background:#1a1a2e;color:#fff;">'
        . '<th style="padding:5px 8px;text-align:left;font-size:8pt;">Payment</th>'
        . '<th style="padding:5px 8px;text-align:right;font-size:8pt;">Amount</th>'
        . '<th style="padding:5px 8px;text-align:left;font-size:8pt;">Due</th>'
        . '</tr>';
    foreach ($paySchedule as $pi => $pitem) {
        $bg   = $pi % 2 === 0 ? '#ffffff' : '#f7f8fc';
        $payScheduleHtml .= '<tr style="background:' . $bg . ';">'
            . '<td style="padding:4px 8px;border-bottom:1px solid #e5e5e5;">' . $h($pitem['label'] ?? 'Payment ' . ($pi+1)) . '</td>'
            . '<td style="padding:4px 8px;border-bottom:1px solid #e5e5e5;text-align:right;font-weight:bold;">' . $money($pitem['amount'] ?? 0) . '</td>'
            . '<td style="padding:4px 8px;border-bottom:1px solid #e5e5e5;">' . $h($pitem['due'] ?? '') . '</td>'
            . '</tr>';
    }
    $payScheduleHtml .= '<tr style="background:#1a1a2e;color:#fff;">'
        . '<td style="padding:5px 8px;font-weight:bold;">Total</td>'
        . '<td style="padding:5px 8px;text-align:right;font-weight:bold;">' . $money($ag['total_amount']) . '</td>'
        . '<td style="padding:5px 8px;"></td>'
        . '</tr>';
    $payScheduleHtml .= '</table>';
} elseif ($ag['total_amount'] > 0) {
    $payScheduleHtml = '<p>Client agrees to pay <strong>' . $money($ag['total_amount']) . '</strong> for the marketing services provided.</p>';
} else {
    $payScheduleHtml = '<p>Pricing to be determined per addendum.</p>';
}

// Budget table HTML
$budgetHtml = '';
$budgetCats = $ag['budget_categories'] ?? [];
$anyBudget  = false;
foreach ($budgetCats as $cat) {
    if (!empty($cat['rows'])) { $anyBudget = true; break; }
}
if ($anyBudget) {
    $budgetHtml .= '<table style="width:100%;border-collapse:collapse;margin:8px 0;font-size:9pt;">';
    $budgetHtml .= '<tr style="background:#1a1a2e;color:#fff;">'
        . '<th style="padding:5px 10px;text-align:left;">Category</th>'
        . '<th style="padding:5px 10px;text-align:left;">Outlet / Platform</th>'
        . '<th style="padding:5px 10px;text-align:right;">Amount</th></tr>';
    foreach ($budgetCats as $cat) {
        $catRows = $cat['rows'] ?? [];
        if (empty($catRows)) continue;
        $catTotal = array_sum(array_column($catRows, 'amount'));
        $ri = 0;
        foreach ($catRows as $row) {
            $bg = $ri % 2 === 0 ? '#ffffff' : '#f7f8fc';
            $budgetHtml .= '<tr style="background:' . $bg . ';">';
            if ($ri === 0) {
                $budgetHtml .= '<td rowspan="' . count($catRows) . '" style="padding:4px 10px;border-bottom:1px solid #e5e5e5;font-weight:600;vertical-align:top;">'
                    . $h($cat['label']) . '</td>';
            }
            $budgetHtml .= '<td style="padding:4px 10px;border-bottom:1px solid #e5e5e5;">' . $h($row['name'] ?? '') . '</td>'
                . '<td style="padding:4px 10px;border-bottom:1px solid #e5e5e5;text-align:right;">' . $money($row['amount'] ?? 0) . '</td>'
                . '</tr>';
            $ri++;
        }
        $budgetHtml .= '<tr style="background:#edf0f8;">'
            . '<td colspan="2" style="padding:4px 10px;font-weight:700;border-top:1px solid #bbc;">' . $h($cat['label']) . ' Subtotal</td>'
            . '<td style="padding:4px 10px;font-weight:700;text-align:right;border-top:1px solid #bbc;">' . $money($catTotal) . '</td>'
            . '</tr>';
    }
    $budgetHtml .= '<tr style="background:#1a1a2e;color:#fff;">'
        . '<td colspan="2" style="padding:6px 10px;font-weight:700;">Total Budget</td>'
        . '<td style="padding:6px 10px;font-weight:700;text-align:right;">' . $money($budgetTotal) . '</td>'
        . '</tr></table>';
}

// Services list HTML
$servicesHtml = '<ol style="margin:6px 0;padding-left:20px;">';
foreach ($ag['services'] as $svcLine) {
    $servicesHtml .= '<li style="margin-bottom:3px;">' . $h($svcLine) . '</li>';
}
$servicesHtml .= '</ol>';

// Signature block helper
function sigBlock(string $nameLeft, string $titleLeft, string $orgLeft,
                  string $nameRight, string $titleRight, string $orgRight): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $line = '<div style="border-bottom:1px solid #444;height:30px;margin-bottom:4px;"></div>';
    return '
    <table style="width:100%;margin-top:24px;border-collapse:collapse;">
    <tr>
      <td style="width:48%;vertical-align:top;padding-right:20px;">'
        . $line
        . '<div style="font-weight:600;font-size:9.5pt;">' . $h($nameLeft) . '</div>'
        . '<div style="font-size:8.5pt;color:#555;">' . $h($orgLeft) . '</div>'
        . '<div style="font-size:8.5pt;color:#555;">Title: ' . $h($titleLeft) . '</div>'
        . '<div style="font-size:8.5pt;color:#555;margin-top:6px;">Date: ______________________</div>'
        . '<div style="font-size:8.5pt;color:#555;margin-top:4px;">Signature: _________________</div>'
      . '</td>
      <td style="width:4%;"></td>
      <td style="width:48%;vertical-align:top;padding-left:20px;">'
        . $line
        . '<div style="font-weight:600;font-size:9.5pt;">' . $h($nameRight) . '</div>'
        . '<div style="font-size:8.5pt;color:#555;">' . $h($orgRight) . '</div>'
        . '<div style="font-size:8.5pt;color:#555;">Title: ' . $h($titleRight) . '</div>'
        . '<div style="font-size:8.5pt;color:#555;margin-top:6px;">Date: ______________________</div>'
        . '<div style="font-size:8.5pt;color:#555;margin-top:4px;">Signature: _________________</div>'
      . '</td>
    </tr></table>';
}

// ── Build full HTML document ──────────────────────────────────────────────────
$clientName  = $ag['client_name'] ?: 'CLIENT NAME';
$clientRep   = $ag['client_representative'] ?: '____________________________';
$clientTitle = $ag['client_title']          ?: '';

$contractStart = $ag['contract_start'] ? $fmtDate($ag['contract_start']) : 'Upon signing';
$contractEnd   = $ag['contract_end']   ? $fmtDate($ag['contract_end'])   : '12 months after signing';

$headingCss = 'font-size:10.5pt;font-weight:700;color:#1a1a2e;text-transform:uppercase;letter-spacing:.06em;
               border-bottom:1.5px solid #1a1a2e;padding-bottom:3px;margin:16px 0 6px;';

$pageBreak = '<div style="page-break-before:always;"></div>';

// Repeated letterhead for each page
function letterhead(string $logoHtml, array $b): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $logo = $logoHtml ?: '<span style="font-size:18pt;font-weight:700;color:#1a1a2e;">' . $h($b['name']) . '</span>';
    return '
    <table style="width:100%;border-collapse:collapse;border-bottom:3px solid #1a1a2e;padding-bottom:10px;margin-bottom:16px;">
    <tr>
      <td style="vertical-align:bottom;">' . $logo . '</td>
      <td style="text-align:right;font-size:8.5pt;color:#444;line-height:1.5;vertical-align:bottom;">'
        . $h($b['address']) . '<br>'
        . $h($b['city_state_zip']) . '<br>'
        . $h($b['phone'])
      . '</td>
    </tr></table>';
}

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>' . $h($ag['title']) . ' — Agency Agreement</title>
<style>
  body { font-family: Georgia, "Times New Roman", serif; font-size:10.5pt; color:#111; line-height:1.6; margin:0; padding:0; }
  p  { margin:0 0 7px; }
  ol, ul { margin:0 0 7px; padding-left:22px; }
  li { margin-bottom:3px; }
  strong { color:#1a1a2e; }
  table { border-spacing:0; }
</style>
</head><body>';

// ── PAGE 1 — AGREEMENT COVER ─────────────────────────────────────────────────
$html .= letterhead($logoHtml, $branding);

$html .= '<h1 style="font-size:20pt;font-weight:700;text-align:center;margin:8px 0 2px;color:#1a1a2e;">Agency Agreement</h1>';
$html .= '<p style="text-align:center;font-style:italic;color:#555;font-size:9pt;margin-bottom:14px;">Marketing Services Contract</p>';

$html .= '<p>This agreement, by and between <strong>' . $h($branding['name']) . '</strong> (&ldquo;Agency&rdquo;) and <strong>'
       . $h($clientName) . '</strong> (&ldquo;Client&rdquo;), is a legally binding agreement. Agency agrees to provide marketing services described herein in exchange for payment from Client in accordance with the terms of this agreement.</p>';

// Parties box
$html .= '<table style="width:100%;border:1px solid #dde2f0;border-radius:6px;background:#f4f6fb;font-size:9pt;margin:10px 0 14px;border-collapse:collapse;">
<tr>
  <td style="padding:10px 12px;vertical-align:top;border-right:1px solid #dde2f0;">
    <div style="font-size:7pt;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#888;margin-bottom:3px;">Agency</div>
    <div style="font-weight:700;font-size:10pt;color:#1a1a2e;">' . $h($branding['name']) . '</div>
    <div style="color:#555;">' . $h($branding['address']) . '<br>' . $h($branding['city_state_zip']) . '<br>' . $h($branding['phone']) . '</div>
  </td>
  <td style="padding:10px 12px;vertical-align:top;border-right:1px solid #dde2f0;">
    <div style="font-size:7pt;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#888;margin-bottom:3px;">Client</div>
    <div style="font-weight:700;font-size:10pt;color:#1a1a2e;">' . $h($clientName) . '</div>
    <div style="color:#555;">'
        . ($ag['client_representative'] ? $h($ag['client_representative']) . ($ag['client_title'] ? ', ' . $h($ag['client_title']) : '') . '<br>' : '')
        . ($ag['client_address'] ? $h($ag['client_address']) . '<br>' : '')
        . ($ag['client_city_state_zip'] ? $h($ag['client_city_state_zip']) . '<br>' : '')
        . $h($ag['client_phone'])
    . '</div>
  </td>
  <td style="padding:10px 12px;vertical-align:top;">
    <div style="font-size:7pt;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#888;margin-bottom:3px;">Contract Period</div>
    <div style="color:#555;"><strong>Start:</strong><br>' . $h($contractStart) . '<br><strong>End:</strong><br>' . $h($contractEnd) . '</div>
  </td>
</tr></table>';

$html .= '<div style="' . $headingCss . '">Appointment</div>';
$html .= '<p>Client agrees to retain Agency as a provider of the marketing services provided below. Agency agrees to provide these services pursuant to the terms of this agreement.</p>';
$html .= '<p><em>*Should Client require additional marketing and advertising services from Agency, both parties shall negotiate terms for those services, and attach such terms as an addendum to this agreement.</em></p>';

$html .= '<div style="' . $headingCss . '">Marketing Services</div>';
$html .= $servicesHtml;

$html .= '<div style="' . $headingCss . '">Pricing</div>';
$html .= $payScheduleHtml;

if (!empty($budgetHtml)) {
    $html .= '<div style="' . $headingCss . '">Budget Breakdown</div>';
    $html .= $budgetHtml;
}

if (!empty($ag['contingency_monthly']) && $ag['contingency_monthly'] > 0) {
    $html .= '<p><strong>Contingency Funds Recommendation:</strong> ' . $money($ag['contingency_monthly']) . ' per month be set aside for additional services or unforeseen expenses. These funds to be discussed prior to services rendered and agreed upon with Agency Managing Partner and Client.</p>';
}

$html .= '<p><strong>Mileage:</strong> Travel to be paid at ' . $money($ag['mileage_rate']) . ' per mile if travel is necessary outside of Yakima city limits, billed starting from Agency\'s physical location. Travel time to be billed at half the hourly rate of ' . $money($ag['hourly_rate']) . ' per hour (Non-profit rate).</p>';

if (!empty($ag['additional_notes'])) {
    $html .= '<p>' . nl2br($h($ag['additional_notes'])) . '</p>';
}

// ── PAGE 2 — TERMS ───────────────────────────────────────────────────────────
$html .= $pageBreak;
$html .= letterhead($logoHtml, $branding);

$html .= '<div style="' . $headingCss . 'margin-top:0;">Terms</div>';

$html .= '<p><strong>Contract Duration:</strong></p>
<ul>
  <li><strong>Start:</strong> ' . $h($contractStart) . '</li>
  <li><strong>End:</strong> ' . $h($contractEnd) . '</li>
</ul>
<p><em>*Note: The contract can be updated with a specific date if required by the Client. At the end of the term, renegotiation or an addendum will be created for future services or partnership.</em></p>';

$html .= '<p><strong>Payment &amp; Billing:</strong></p>
<ul>
  <li>The Client acknowledges and agrees to the payment schedule outlined above.</li>
  <li>Additional services outside the original project scope, including extra page designs or additional features, will incur extra charges.</li>
  <li>An invoice will be provided to ' . $h($clientName) . ' based on the aforementioned payment installments.</li>
  <li>Payment is due upon receipt.</li>
</ul>';

$html .= '<p><strong>Scope of Work &amp; Collaboration:</strong></p>
<ul>
  <li>The scope provided by the Client serves as a guideline and does not limit Agency and ' . $h($clientName) . ' from collaborating on other projects or materials as needed.</li>
  <li>Agency will receive partnership/sponsorship recognition on all printed and electronic materials created and maintained by Agency.</li>
</ul>';

$html .= '<p><strong>Exclusions &amp; Additional Costs:</strong></p>
<ul>
  <li>Third-party costs (billed separately, estimates available upon request): Printing, media placements, travel expenses, and third-party production.</li>
  <li>Purchased images: $25 each.</li>
</ul>';

$html .= '<p><strong>Cancellation Policy:</strong></p>
<p>If the project is canceled after the start, 50% of the total fee is non-refundable. If canceled after designs are delivered, the full amount is due.</p>
<p>Upon cancellation, the Client owns all completed work in its current state, excluding native mechanical files, raw video, or raw photography, which remain the property of ' . $h($branding['dba'] ?: $branding['name']) . ' unless transferred under a separate agreement with applicable fees.</p>';

$html .= '<div style="' . $headingCss . '">Acknowledgment and Agreement</div>';
$html .= '<p>By signing below, I acknowledge that I have reviewed and agreed to the terms and conditions outlined in this contract.</p>';

$html .= sigBlock(
    $branding['signer_name'], $branding['signer_title'], $branding['name'],
    $ag['client_representative'] ?: '____________________________',
    $ag['client_title'] ?: '______________________',
    $ag['client_name'] ?: 'Client'
);

// Address info below signatures
$html .= '<table style="width:100%;font-size:9pt;color:#444;border-top:1px solid #ddd;margin-top:10px;padding-top:10px;border-collapse:collapse;">
<tr>
  <td style="padding:8px 0 0;vertical-align:top;width:50%;">
    <strong>' . $h($branding['name']) . '</strong><br>'
    . $h($branding['address']) . '<br>'
    . $h($branding['city_state_zip']) . '<br>'
    . $h($branding['phone'])
  . '</td>
  <td style="padding:8px 0 0;vertical-align:top;width:50%;">
    <strong>' . $h($clientName) . '</strong><br>'
    . ($ag['client_address'] ? $h($ag['client_address']) . '<br>' : '')
    . ($ag['client_city_state_zip'] ? $h($ag['client_city_state_zip']) . '<br>' : '')
    . $h($ag['client_phone'])
  . '</td>
</tr></table>';

// ── PAGE 3 — MOU ─────────────────────────────────────────────────────────────
$html .= $pageBreak;
$html .= letterhead($logoHtml, $branding);

$dba = $branding['dba'] ?: $branding['name'];

$html .= '<h2 style="font-size:14pt;font-weight:700;color:#1a1a2e;text-align:center;margin:0 0 2px;">Memorandum of Understanding</h2>';
$html .= '<p style="text-align:center;font-size:9pt;color:#666;font-style:italic;margin-bottom:14px;">' . $h($dba) . ' &amp; ' . $h($clientName) . '</p>';

$html .= '<p>' . $h($dba) . ', an integrated marketing communications firm, agrees to complete all marketing services as listed above for <strong>' . $h($clientName) . '</strong>. The services will include the following criteria:</p>';

$html .= '<ul>
  <li>The contract shall be in effect <strong>Start:</strong> ' . $h($contractStart) . ' &nbsp;<strong>Ends:</strong> ' . $h($contractEnd) . '. (Contract can be updated with a specific date if required by Client.)</li>
  <li>This contract can be canceled with 30 day written notice from either party.</li>
</ul>';

$html .= '<p><strong>Contract does not include:</strong> All Third-Party Costs (examples: website hosting, purchased images — $25 each, printing, media placements, travel expenses). These elements can be estimated upon request.</p>';

$html .= '<ul>
  <li>All invoices are payable within 15 days of receipt. Invoices that become 30 days past due are subject to 1&frac12;% monthly service charge. Accounts that become 60 days past due can be sent to a collection agency and the Client will assume responsibility for all collection of legal fees necessitated by default in payment.</li>
  <li>' . $h($dba) . ' will be responsible for all payroll, income, and unemployment tax deductions/payments due to services incurred by Agency.</li>
  <li>Agency warrants and represents that to the best of our knowledge any artwork designed by our firm is original, does not contain any scandalous, libelous, or unlawful matter and has not been previously published.</li>
  <li>The Client will make additional payments for changes requested in original assignment. However, no additional payment shall be made for changes required to conform to the original assignment description.</li>
  <li>Cancellation fees are due based on the amount of work completed. Fifty percent (50%) of the final fee is due within 30 days notification that for any reason the job is canceled or postponed before the final stage. One hundred percent (100%) of the total fee is due despite cancellation or postponement of the job if the art has been completed. Upon cancellation, both parties agree that the Client owns all artwork in the state it is in at the time of cancellation, except for the native mechanical files used to create said artwork.</li>
  <li>Both parties shall fully comply with all applicable federal, state, and local laws and regulations during this time. Both parties shall adhere to the PRSA Code of Ethics.</li>
  <li>All information about the business and this contract shall be kept confidential until both parties agree to the release of said information to the public forum.</li>
  <li>Both parties agree that the Client owns all artwork (finished project) except for the native mechanical files, raw video or raw photography used to create said artwork. Those files are retained by Agency unless and until a transfer of ownership agreement is made and transfer fee has been paid.</li>
  <li>The Client will indemnify the individual artist, as well as Agency, against all claims and expenses arising from uses for which the Client does not have the rights to or authority to use.</li>
  <li>Should any disagreement result over this contract, the prevailing party shall be entitled to receive reasonable attorney fees and costs set by the court. This agreement will be governed by the laws of the State of Washington. Venue regarding any dispute regarding this agreement shall lie in Yakima County.</li>
</ul>';

$html .= '<p>I, <span style="border-bottom:1px solid #444;display:inline-block;min-width:240px;padding-bottom:2px;">'
       . $h($ag['client_representative'] ?: '') . '</span>'
       . ' a representing member of <strong>' . $h($clientName) . '</strong>,'
       . ' agree that ' . $h($dba) . ' will provide the service explained in this contract according to the terms of this contract.</p>';

$html .= '<table style="width:100%;margin-top:24px;border-collapse:collapse;">
<tr>
  <td style="width:55%;vertical-align:top;padding-right:20px;">
    <div style="border-bottom:1px solid #444;height:30px;margin-bottom:4px;"></div>
    <div style="font-size:8.5pt;color:#555;">' . $h($clientName) . ' | Company Representative</div>
    <div style="font-size:8.5pt;color:#555;margin-top:8px;">Date: ______________________</div>
  </td>
  <td style="width:45%;vertical-align:top;padding-left:20px;">
    <div style="border-bottom:1px solid #444;height:30px;margin-bottom:4px;"></div>
    <div style="font-size:8.5pt;color:#555;">' . $h($dba) . ' | ' . $h($branding['signer_title']) . ' | ' . $h($branding['signer_name']) . '</div>
    <div style="font-size:8.5pt;color:#555;margin-top:8px;">Date: ______________________</div>
  </td>
</tr></table>';

$html .= '</body></html>';

// ── Render via Dompdf ─────────────────────────────────────────────────────────
use Dompdf\Dompdf;
use Dompdf\Options;

// Bundled fonts directory (DejaVu, Helvetica AFM files pre-packaged in the zip)
$dompdfFontDir = realpath(__DIR__ . '/../lib/dompdf/lib/fonts');

$options = new Options();
$options->setIsRemoteEnabled(true);          // allow logo img from URL
$options->setIsHtml5ParserEnabled(false);    // use DOMDocument — avoids Masterminds dependency
$options->setDefaultFont('dejavu serif');    // built-in TTF font from bundled lib/fonts/
$options->setDpi(96);

// Tell Dompdf where bundled fonts live so it finds the .ufm metric files
if ($dompdfFontDir && is_dir($dompdfFontDir)) {
    $options->setFontDir($dompdfFontDir);
    $options->setFontCache($dompdfFontDir);
}

// Allow Dompdf to read local logo files (e.g. /uploads/logos/...)
$webRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..');
if ($webRoot) {
    $options->setChroot($webRoot);
}

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();

$filename = 'Agency-Agreement-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $ag['title']) . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
