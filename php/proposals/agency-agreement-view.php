<?php
/**
 * proposals/agency-agreement-view.php
 * Print-ready Agency Agreement document.
 * Use browser Print → Save as PDF for a clean output.
 * Budget categories are fully dynamic from budget_categories array.
 * Agency branding is loaded from workflow_settings via getBranding().
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc = new AgencyAgreementService();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/proposals/index.php');

$ag = $svc->get($id);
if (!$ag) { flash('error','Agreement not found.'); redirect('/proposals/index.php'); }

$branding = $svc->getBranding();

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    $svc->updateStatus($id, trim($_POST['new_status']));
    flash('success', 'Status updated.');
    redirect('/proposals/agency-agreement-view.php?id=' . $id);
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_confirm'])) {
    $svc->delete($id);
    flash('success', 'Agreement deleted.');
    redirect('/proposals/index.php');
}

$statusColor = AgencyAgreementService::STATUS_COLORS[$ag['status']] ?? 'secondary';
$statusLabel = AgencyAgreementService::STATUS_LABELS[$ag['status']] ?? $ag['status'];
$budgetTotal = $svc->budgetTotal($ag);

// Helpers
$money   = fn($v) => '$' . number_format((float)$v, 2);
$fmtDate = function(?string $d): string {
    if (!$d) return '';
    $ts = strtotime($d);
    return $ts ? date('F j, Y', $ts) : $d;
};

$pageTitle = h($ag['title']) . ' — Agency Agreement';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ════════════════════════════════════════════════════
   SCREEN TOOLBAR
   ════════════════════════════════════════════════════ */
.doc-toolbar { margin-bottom: 1.5rem; }

/* ════════════════════════════════════════════════════
   DOCUMENT BASE
   ════════════════════════════════════════════════════ */
.ag-doc {
    max-width: 780px;
    margin: 0 auto;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 10.5pt;
    color: #111;
    line-height: 1.6;
    background: #fff;
}

/* ════════════════════════════════════════════════════
   AGENCY LETTERHEAD HEADER
   ════════════════════════════════════════════════════ */
.agency-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    padding-bottom: 12px;
    margin-bottom: 18px;
    border-bottom: 3px solid #1a1a2e;
}
.agency-header .logo-wrap img {
    max-height: 72px;
    max-width: 240px;
    object-fit: contain;
}
.agency-header .agency-name-text {
    font-family: Georgia, serif;
    font-size: 20pt;
    font-weight: 700;
    color: #1a1a2e;
    letter-spacing: .01em;
    line-height: 1.1;
}
.agency-header .agency-dba {
    font-size: 9pt;
    color: #555;
    font-style: italic;
    margin-top: 2px;
}
.agency-header .agency-contact-block {
    text-align: right;
    font-size: 8.5pt;
    color: #444;
    line-height: 1.5;
}

/* ════════════════════════════════════════════════════
   DOCUMENT TITLE
   ════════════════════════════════════════════════════ */
.ag-doc h1.doc-title {
    font-size: 20pt;
    font-weight: 700;
    text-align: center;
    margin: 8px 0 2px;
    letter-spacing: .01em;
    color: #1a1a2e;
}
.ag-doc .doc-subtitle {
    text-align: center;
    font-style: italic;
    color: #555;
    margin-bottom: 16px;
    font-size: 9pt;
}

/* ════════════════════════════════════════════════════
   SECTION HEADINGS
   ════════════════════════════════════════════════════ */
.ag-doc .section-heading {
    font-size: 10.5pt;
    font-weight: 700;
    margin: 18px 0 5px;
    color: #1a1a2e;
    text-transform: uppercase;
    letter-spacing: .06em;
    border-bottom: 1.5px solid #1a1a2e;
    padding-bottom: 3px;
}

/* ════════════════════════════════════════════════════
   BODY TEXT
   ════════════════════════════════════════════════════ */
.ag-doc p  { margin-bottom: 7px; }
.ag-doc ol, .ag-doc ul { margin-bottom: 7px; padding-left: 22px; }
.ag-doc ol li, .ag-doc ul li { margin-bottom: 3px; }

/* ════════════════════════════════════════════════════
   PARTIES INFO BOX
   ════════════════════════════════════════════════════ */
.parties-box {
    display: flex;
    gap: 24px;
    background: #f4f6fb;
    border: 1px solid #dde2f0;
    border-radius: 6px;
    padding: 10px 14px;
    margin: 10px 0 14px;
    font-size: 9pt;
}
.parties-box .party-col { flex: 1; }
.parties-box .party-label {
    font-size: 7.5pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #888;
    margin-bottom: 2px;
}
.parties-box .party-name {
    font-weight: 700;
    font-size: 10pt;
    color: #1a1a2e;
}
.parties-box .party-detail { color: #555; line-height: 1.45; }

/* ════════════════════════════════════════════════════
   PRICING BOX
   ════════════════════════════════════════════════════ */
.pricing-box {
    display: flex;
    gap: 0;
    border: 1px solid #dde2f0;
    border-radius: 6px;
    overflow: hidden;
    margin: 10px 0;
}
.pricing-box .price-col {
    flex: 1;
    padding: 10px 14px;
    border-right: 1px solid #dde2f0;
}
.pricing-box .price-col:last-child { border-right: none; }
.pricing-box .price-label {
    font-size: 7.5pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #888;
    margin-bottom: 3px;
}
.pricing-box .price-amount {
    font-size: 14pt;
    font-weight: 700;
    color: #1a1a2e;
}
.pricing-box .price-due {
    font-size: 8.5pt;
    color: #555;
    margin-top: 2px;
}
.pricing-box .price-col.total-col {
    background: #1a1a2e;
    color: #fff;
}
.pricing-box .total-col .price-label { color: #aab; }
.pricing-box .total-col .price-amount { color: #fff; }

/* ════════════════════════════════════════════════════
   BUDGET TABLE
   ════════════════════════════════════════════════════ */
.budget-section { margin: 10px 0; }

/* Keep entire category group together */
.budget-cat-group {
    break-inside: avoid;
    page-break-inside: avoid;
    margin-bottom: 6px;
}
.budget-cat-label {
    font-weight: 700;
    font-size: 9pt;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #fff;
    background: #1a1a2e;
    padding: 4px 10px;
    display: flex;
    justify-content: space-between;
}
.budget-cat-rows { border: 1px solid #dde2f0; border-top: none; }
.budget-cat-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 10px;
    font-size: 9.5pt;
    border-bottom: 1px solid #eef0f5;
}
.budget-cat-row:last-child { border-bottom: none; }
.budget-cat-row:nth-child(even) { background: #f7f8fc; }
.budget-cat-subtotal {
    display: flex;
    justify-content: space-between;
    padding: 4px 10px;
    font-size: 9pt;
    font-weight: 700;
    background: #edf0f8;
    border: 1px solid #dde2f0;
    border-top: 1.5px solid #bbc;
}
.budget-grand-total {
    display: flex;
    justify-content: space-between;
    padding: 7px 10px;
    font-size: 11pt;
    font-weight: 700;
    background: #1a1a2e;
    color: #fff;
    margin-top: 4px;
    break-inside: avoid;
    page-break-inside: avoid;
}

/* ════════════════════════════════════════════════════
   SIGNATURE BLOCKS
   ════════════════════════════════════════════════════ */
.sig-block {
    margin-top: 24px;
    break-inside: avoid;
    page-break-inside: avoid;
}
.sig-row {
    display: flex;
    gap: 36px;
    margin-bottom: 18px;
}
.sig-col { flex: 1; }
.sig-line {
    border-bottom: 1.5px solid #333;
    margin-bottom: 4px;
    height: 28px;
}
.sig-label { font-size: 8.5pt; color: #555; }
.sig-prefill { font-size: 9.5pt; color: #111; font-weight: 600; }
.sig-info-row {
    display: flex;
    gap: 36px;
    font-size: 9pt;
    color: #444;
    border-top: 1px solid #ddd;
    padding-top: 10px;
    margin-top: 6px;
}
.sig-info-row .sig-col { line-height: 1.55; }

/* ════════════════════════════════════════════════════
   DIVIDERS & PAGE BREAKS
   ════════════════════════════════════════════════════ */
.doc-divider { border: none; border-top: 1.5px solid #ccc; margin: 16px 0; }
.page-break  { page-break-before: always; break-before: page; }

/* ════════════════════════════════════════════════════
   MOU PAGE
   ════════════════════════════════════════════════════ */
.mou-header {
    text-align: center;
    margin-bottom: 14px;
    break-inside: avoid;
    page-break-inside: avoid;
}
.mou-header .mou-title {
    font-size: 14pt;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 2px;
}
.mou-header .mou-subtitle {
    font-size: 9pt;
    color: #666;
    font-style: italic;
}

/* ════════════════════════════════════════════════════
   PRINT OVERRIDES
   ════════════════════════════════════════════════════ */
@media print {
    .doc-toolbar,
    nav, header, footer,
    .navbar, #site-header, aside,
    .d-print-none { display: none !important; }

    body { background: #fff !important; margin: 0; }

    .ag-doc {
        max-width: 100%;
        font-size: 9.5pt;
        padding: 0;
        margin: 0;
    }

    /* Prevent colour loss on printed backgrounds */
    .agency-header         { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .budget-cat-label      { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .budget-grand-total    { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .pricing-box .total-col{ -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .budget-cat-row:nth-child(even) { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .budget-cat-subtotal   { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .parties-box           { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    /* Orphan / widow control */
    p, li { orphans: 3; widows: 3; }

    /* Keep groups together */
    .sig-block, .budget-cat-group, .budget-grand-total, .mou-header,
    .parties-box, .pricing-box { break-inside: avoid; page-break-inside: avoid; }

    /* Section headings stay with following content */
    .section-heading { break-after: avoid; page-break-after: avoid; }

    /* Hard page breaks */
    .page-break { page-break-before: always; break-before: page; }

    /* Remove link decoration */
    a[href]:after { content: none !important; }

    /* Ensure page margins */
    @page { margin: 0.7in 0.75in; }
}
</style>

<!-- ── Screen toolbar ──────────────────────────────────────────────────── -->
<div class="doc-toolbar d-print-none">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="/proposals/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Proposals
        </a>
        <a href="/proposals/agency-agreement.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <button onclick="window.print()" class="btn btn-success btn-sm">
            <i class="bi bi-printer me-1"></i>Print / Save PDF
        </button>
        <span class="badge bg-<?= $statusColor ?> ms-1"><?= h($statusLabel) ?></span>

        <!-- Status change -->
        <form method="POST" class="d-inline ms-2">
            <div class="input-group input-group-sm">
                <select name="new_status" class="form-select form-select-sm" style="width:130px;">
                    <?php foreach (AgencyAgreementService::STATUS_LABELS as $val => $lbl): ?>
                    <option value="<?= h($val) ?>" <?= $ag['status'] === $val ? 'selected' : '' ?>><?= h($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm">Update</button>
            </div>
        </form>

        <!-- Delete -->
        <form method="POST" class="ms-auto"
              onsubmit="return confirm('Permanently delete this agreement?')">
            <input type="hidden" name="delete_confirm" value="1">
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i>Delete
            </button>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     DOCUMENT
     ════════════════════════════════════════════════════════════════════════ -->
<div class="ag-doc">

    <!-- ══════════════════ PAGE 1 — AGREEMENT COVER ════════════════════ -->

    <!-- Agency letterhead -->
    <div class="agency-header">
        <div class="logo-wrap">
            <?php if (!empty($branding['logo_url'])): ?>
                <img src="<?= h($branding['logo_url']) ?>"
                     alt="<?= h($branding['name']) ?>">
            <?php else: ?>
                <div class="agency-name-text"><?= h($branding['name']) ?></div>
                <?php if (!empty($branding['dba']) && $branding['dba'] !== $branding['name']): ?>
                <div class="agency-dba">DBA <?= h($branding['dba']) ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="agency-contact-block">
            <?= h($branding['address']) ?><br>
            <?= h($branding['city_state_zip']) ?><br>
            <?= h($branding['phone']) ?>
        </div>
    </div>

    <h1 class="doc-title">Agency Agreement</h1>
    <div class="doc-subtitle">Marketing Services Contract</div>

    <p>
        This agreement, by and between
        <strong><?= h($branding['name']) ?></strong>
        (&ldquo;Agency&rdquo;) and
        <strong><?= h($ag['client_name'] ?: 'CLIENT NAME') ?></strong>
        (&ldquo;Client&rdquo;), is a legally binding agreement. Agency agrees to provide
        marketing services described herein in exchange for payment from Client in accordance
        with the terms of this agreement.
    </p>

    <!-- Parties info box -->
    <div class="parties-box">
        <div class="party-col">
            <div class="party-label">Agency</div>
            <div class="party-name"><?= h($branding['name']) ?></div>
            <div class="party-detail">
                <?= h($branding['address']) ?><br>
                <?= h($branding['city_state_zip']) ?><br>
                <?= h($branding['phone']) ?>
            </div>
        </div>
        <div class="party-col">
            <div class="party-label">Client</div>
            <div class="party-name"><?= h($ag['client_name'] ?: '—') ?></div>
            <div class="party-detail">
                <?php if ($ag['client_representative']): ?>
                <?= h($ag['client_representative']) ?>
                <?php if ($ag['client_title']): ?>, <?= h($ag['client_title']) ?><?php endif; ?><br>
                <?php endif; ?>
                <?php if ($ag['client_address']): ?><?= h($ag['client_address']) ?><br><?php endif; ?>
                <?php if ($ag['client_city_state_zip']): ?><?= h($ag['client_city_state_zip']) ?><br><?php endif; ?>
                <?php if ($ag['client_phone']): ?><?= h($ag['client_phone']) ?><?php endif; ?>
            </div>
        </div>
        <?php if ($ag['contract_start'] || $ag['contract_end']): ?>
        <div class="party-col">
            <div class="party-label">Contract Period</div>
            <div class="party-detail">
                <strong>Start:</strong><br>
                <?= $ag['contract_start'] ? h($fmtDate($ag['contract_start'])) : 'Upon signing' ?><br>
                <strong>End:</strong><br>
                <?= $ag['contract_end'] ? h($fmtDate($ag['contract_end'])) : '12 months after signing' ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="section-heading">Appointment</div>
    <p>
        Client agrees to retain Agency as a provider of the marketing services provided below.
        Agency agrees to provide these services pursuant to the terms of this agreement.
    </p>
    <p><em>*Should Client require additional marketing and advertising services from Agency,
    both parties shall negotiate terms for those services, and attach such terms as an addendum
    to this agreement.</em></p>

    <div class="section-heading">Marketing Services</div>
    <ol>
        <?php foreach ($ag['services'] as $svcLine): ?>
        <li><?= h($svcLine) ?></li>
        <?php endforeach; ?>
    </ol>

    <div class="section-heading">Pricing</div>
    <?php if ($ag['deposit_amount'] > 0 || $ag['balance_amount'] > 0 || $ag['total_amount'] > 0): ?>
    <div class="pricing-box">
        <?php if ($ag['deposit_amount'] > 0): ?>
        <div class="price-col">
            <div class="price-label">Deposit</div>
            <div class="price-amount"><?= $money($ag['deposit_amount']) ?></div>
            <?php if ($ag['deposit_due_description']): ?>
            <div class="price-due"><?= h($ag['deposit_due_description']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($ag['balance_amount'] > 0): ?>
        <div class="price-col">
            <div class="price-label">Balance</div>
            <div class="price-amount"><?= $money($ag['balance_amount']) ?></div>
            <?php if ($ag['balance_due_description']): ?>
            <div class="price-due"><?= h($ag['balance_due_description']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="price-col total-col">
            <div class="price-label">Total Due</div>
            <div class="price-amount"><?= $money($ag['total_amount']) ?></div>
            <div class="price-due">All marketing services</div>
        </div>
    </div>
    <?php else: ?>
    <p>Pricing to be determined per addendum.</p>
    <?php endif; ?>

    <?php
    // Dynamic budget categories
    $budgetCats = $ag['budget_categories'] ?? [];
    $anyBudget  = false;
    foreach ($budgetCats as $cat) {
        if (!empty($cat['rows'])) { $anyBudget = true; break; }
    }
    ?>

    <?php if ($anyBudget): ?>
    <div class="section-heading">Budget Breakdown</div>
    <div class="budget-section">
        <?php foreach ($budgetCats as $cat):
            $catRows  = $cat['rows'] ?? [];
            if (empty($catRows)) continue;
            $catTotal = array_sum(array_column($catRows, 'amount'));
        ?>
        <div class="budget-cat-group">
            <div class="budget-cat-label">
                <span><?= h($cat['label']) ?></span>
                <span><?= $money($catTotal) ?></span>
            </div>
            <div class="budget-cat-rows">
                <?php foreach ($catRows as $row): ?>
                <div class="budget-cat-row">
                    <span><?= h($row['name'] ?: '—') ?></span>
                    <span><?= $money($row['amount'] ?? 0) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="budget-grand-total">
            <span>Total Budget</span>
            <span><?= $money($budgetTotal) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($ag['contingency_monthly']) && $ag['contingency_monthly'] > 0): ?>
    <p>
        <strong>Contingency Funds Recommendation:</strong>
        <?= $money($ag['contingency_monthly']) ?> per month be set aside for additional services
        or unforeseen expenses. These funds to be discussed prior to services rendered and agreed
        upon with Agency Managing Partner and Client.
    </p>
    <?php endif; ?>

    <p>
        <strong>Mileage:</strong> Travel to be paid at
        <?= $money($ag['mileage_rate']) ?> per mile if travel is necessary outside of
        Yakima city limits, billed starting from Agency&rsquo;s physical location.
        Travel time to be billed at half the hourly rate of
        <?= $money($ag['hourly_rate']) ?> per hour (Non-profit rate).
    </p>

    <?php if (!empty($ag['additional_notes'])): ?>
    <p><?= nl2br(h($ag['additional_notes'])) ?></p>
    <?php endif; ?>


    <!-- ══════════════════ PAGE 2 — TERMS & SIGNATURES ═════════════════ -->
    <div class="page-break"></div>

    <!-- Letterhead repeated on page 2 -->
    <div class="agency-header">
        <div class="logo-wrap">
            <?php if (!empty($branding['logo_url'])): ?>
                <img src="<?= h($branding['logo_url']) ?>" alt="<?= h($branding['name']) ?>">
            <?php else: ?>
                <div class="agency-name-text" style="font-size:14pt;"><?= h($branding['name']) ?></div>
            <?php endif; ?>
        </div>
        <div class="agency-contact-block">
            <?= h($branding['address']) ?><br><?= h($branding['city_state_zip']) ?>
        </div>
    </div>

    <div class="section-heading" style="margin-top:0;">Terms</div>

    <p><strong>Contract Duration:</strong></p>
    <ul>
        <li><strong>Start:</strong>
            <?= $ag['contract_start'] ? h($fmtDate($ag['contract_start'])) : 'Upon signing this document' ?></li>
        <li><strong>End:</strong>
            <?= $ag['contract_end'] ? h($fmtDate($ag['contract_end'])) : '12 months after signing' ?></li>
    </ul>
    <p><em>*Note: The contract can be updated with a specific date if required by the Client.
    At the end of the term, renegotiation or an addendum will be created for future services
    or partnership.</em></p>

    <p><strong>Payment &amp; Billing:</strong></p>
    <ul>
        <li>The Client acknowledges and agrees to the payment schedule outlined above.</li>
        <li>Additional services outside the original project scope, including extra page designs
            or additional features, will incur extra charges.</li>
        <li>An invoice will be provided to <?= h($ag['client_name'] ?: 'Client') ?>
            based on the aforementioned payment installments.</li>
        <li>Payment is due upon receipt.</li>
    </ul>

    <p><strong>Scope of Work &amp; Collaboration:</strong></p>
    <ul>
        <li>The scope provided by the Client serves as a guideline and does not limit Agency and
            <?= h($ag['client_name'] ?: 'Client') ?> from collaborating on other projects or
            materials as needed.</li>
        <li>Agency will receive partnership/sponsorship recognition on all printed and
            electronic materials created and maintained by Agency.</li>
    </ul>

    <p><strong>Exclusions &amp; Additional Costs:</strong></p>
    <ul>
        <li>Third-party costs (billed separately, estimates available upon request):
            Printing, media placements, travel expenses, and third-party production.</li>
        <li>Purchased images: $25 each.</li>
    </ul>

    <p><strong>Cancellation Policy:</strong></p>
    <p>
        If the project is canceled after the start, 50% of the total fee is non-refundable.
        If canceled after designs are delivered, the full amount is due.
    </p>
    <p>
        Upon cancellation, the Client owns all completed work in its current state, excluding
        native mechanical files, raw video, or raw photography, which remain the property of
        <?= h($branding['dba'] ?: $branding['name']) ?> unless transferred under a separate
        agreement with applicable fees.
    </p>

    <div class="section-heading">Acknowledgment and Agreement</div>
    <p>By signing below, I acknowledge that I have reviewed and agreed to the terms and
    conditions outlined in this contract.</p>

    <div class="sig-block">
        <div class="sig-row">
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-prefill"><?= h($branding['signer_name']) ?></div>
                <div class="sig-label">Name — <?= h($branding['name']) ?></div>
                <div class="sig-label mt-1">Title: <?= h($branding['signer_title']) ?></div>
                <div class="sig-label mt-1">Date: ______________________</div>
                <div class="sig-label mt-1">Signature: _________________</div>
            </div>
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-prefill"><?= h($ag['client_representative'] ?: '____________________________') ?></div>
                <div class="sig-label">Name — <?= h($ag['client_name'] ?: 'Client') ?></div>
                <div class="sig-label mt-1">Title: <?= h($ag['client_title'] ?: '______________________') ?></div>
                <div class="sig-label mt-1">Date: ______________________</div>
                <div class="sig-label mt-1">Signature: _________________</div>
            </div>
        </div>

        <div class="sig-info-row">
            <div class="sig-col">
                <strong><?= h($branding['name']) ?></strong><br>
                <?= h($branding['address']) ?><br>
                <?= h($branding['city_state_zip']) ?><br>
                <?= h($branding['phone']) ?>
            </div>
            <div class="sig-col">
                <strong><?= h($ag['client_name'] ?: 'Client') ?></strong><br>
                <?php if ($ag['client_address']): ?><?= h($ag['client_address']) ?><br><?php endif; ?>
                <?php if ($ag['client_city_state_zip']): ?><?= h($ag['client_city_state_zip']) ?><br><?php endif; ?>
                <?php if ($ag['client_phone']): ?><?= h($ag['client_phone']) ?><?php endif; ?>
            </div>
        </div>
    </div>


    <!-- ══════════════════ PAGE 3 — MEMORANDUM OF UNDERSTANDING ════════ -->
    <div class="page-break"></div>

    <!-- Letterhead repeated on page 3 -->
    <div class="agency-header">
        <div class="logo-wrap">
            <?php if (!empty($branding['logo_url'])): ?>
                <img src="<?= h($branding['logo_url']) ?>" alt="<?= h($branding['name']) ?>">
            <?php else: ?>
                <div class="agency-name-text" style="font-size:14pt;"><?= h($branding['name']) ?></div>
            <?php endif; ?>
        </div>
        <div class="agency-contact-block">
            <?= h($branding['address']) ?><br><?= h($branding['city_state_zip']) ?>
        </div>
    </div>

    <div class="mou-header">
        <div class="mou-title">Memorandum of Understanding</div>
        <div class="mou-subtitle"><?= h($branding['dba'] ?: $branding['name']) ?> &amp; <?= h($ag['client_name'] ?: 'Client') ?></div>
    </div>

    <p>
        <?= h($branding['dba'] ?: $branding['name']) ?>, an integrated marketing communications
        firm, agrees to complete all marketing services as listed above for
        <strong><?= h($ag['client_name'] ?: 'CLIENT NAME') ?></strong>.
        The services will include the following criteria:
    </p>

    <ul>
        <li>The contract shall be in effect
            <strong>Start:</strong>
            <?= $ag['contract_start'] ? h($fmtDate($ag['contract_start'])) : 'upon signing' ?>
            &nbsp;<strong>Ends:</strong>
            <?= $ag['contract_end'] ? h($fmtDate($ag['contract_end'])) : '12 months after signing' ?>.
            (Contract can be updated with a specific date if required by Client.)
        </li>
        <li>This contract can be canceled with 30 day written notice from either party.</li>
    </ul>

    <p>
        <strong>Contract does not include:</strong> All Third-Party Costs (examples: website
        hosting, purchased images — $25 each, printing, media placements, travel expenses).
        These elements can be estimated upon request.
    </p>

    <ul>
        <li>All invoices are payable within 15 days of receipt. Invoices that become 30 days
            past due are subject to 1&frac12;% monthly service charge. Accounts that become 60
            days past due can be sent to a collection agency and the Client will assume
            responsibility for all collection of legal fees necessitated by default in payment.</li>
        <li><?= h($branding['dba'] ?: $branding['name']) ?> will be responsible for all payroll,
            income, and unemployment tax deductions/payments due to services incurred by Agency.</li>
        <li>Agency warrants and represents that to the best of our knowledge any artwork designed
            by our firm is original, does not contain any scandalous, libelous, or unlawful matter
            and has not been previously published. We further assert that we have full authority to
            make this agreement. This warranty does not extend to any uses that the Client or others
            may make of the product that may infringe on the rights of others.</li>
        <li>The Client will make additional payments for changes requested in original assignment.
            However, no additional payment shall be made for changes required to conform to the
            original assignment description.</li>
        <li>Cancellation fees are due based on the amount of work completed. Fifty percent (50%)
            of the final fee is due within 30 days notification that for any reason the job is
            canceled or postponed before the final stage. One hundred percent (100%) of the total
            fee is due despite cancellation or postponement of the job if the art has been completed.
            Upon cancellation, both parties agree that the Client owns all artwork in the state it
            is in at the time of cancellation, except for the native mechanical files used to create
            said artwork. Either party can terminate this contract with thirty days written notice
            and no balance owing.</li>
        <li>Client acknowledges that the violation of any of the provisions of this agreement
            will cause irreparable loss and harm to Agency which cannot be reasonably or adequately
            compensated by damages in an action at law, and accordingly, that Agency will be
            entitled, without posting bond or other security, to injunctive and other equitable
            relief to enforce the provisions of this agreement and to prevent or cure any breach or
            threatened breach thereof; but an action for any such relief will not be deemed to be a
            waiver of the right to an action for damages.</li>
        <li>Both parties shall fully comply with all applicable federal, state, and local laws and
            regulations during this time. Both parties shall adhere to the PRSA Code of Ethics.</li>
        <li>All information about the business and this contract shall be kept confidential until
            both parties agree to the release of said information to the public forum, at which
            time only information permissible for release shall be released. All other information
            shall remain confidential.</li>
        <li>Both parties agree that the Client owns all artwork (finished project) except for the
            native mechanical files, raw video or raw photography used to create said artwork.
            Those files are retained by Agency unless and until a transfer of ownership agreement
            is made and transfer fee has been paid. Any alteration of original art is prohibited
            without the express consent of the artist.</li>
        <li>The Client will indemnify the individual artist, as well as Agency, against all claims
            and expenses arising from uses for which the Client does not have the rights to or
            authority to use. The Client will be responsible for payment of any special licensing
            or royalty fees resulting from the use of graphics programs that require such
            payments.</li>
        <li>Should any disagreement result over this contract, the prevailing party shall be
            entitled to receive reasonable attorney fees and costs set by the court. This agreement
            will be governed by the laws of the State of Washington. Venue regarding any dispute
            regarding this agreement shall lie in Yakima County.</li>
    </ul>

    <p>
        I, <span style="border-bottom:1px solid #444;display:inline-block;min-width:240px;padding-bottom:2px;">
            <?= h($ag['client_representative'] ?: '') ?>
        </span>
        a representing member of
        <strong><?= h($ag['client_name'] ?: 'CLIENT NAME') ?></strong>,
        agree that <?= h($branding['dba'] ?: $branding['name']) ?> will provide the service
        explained in this contract according to the terms of this contract.
    </p>

    <div class="sig-block">
        <div class="sig-row">
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-label"><?= h($ag['client_name'] ?: 'COMPANY NAME') ?> | Company Representative</div>
                <div class="sig-label mt-2">Date: ______________________</div>
            </div>
            <div class="sig-col" style="flex:.5;">
                <div class="sig-line"></div>
                <div class="sig-label"><?= h($branding['dba'] ?: $branding['name']) ?> | <?= h($branding['signer_title']) ?> | <?= h($branding['signer_name']) ?></div>
                <div class="sig-label mt-2">Date: ______________________</div>
            </div>
        </div>
    </div>

</div><!-- /.ag-doc -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
