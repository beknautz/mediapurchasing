<?php
/**
 * proposals/agency-agreement-view.php
 * Print-ready Agency Agreement document.
 * Use browser Print → Save as PDF for a clean output.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc = new AgencyAgreementService();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/proposals/index.php');

$ag = $svc->get($id);
if (!$ag) { flash('error','Agreement not found.'); redirect('/proposals/index.php'); }

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

// Format currency
$money = fn($v) => '$' . number_format((float)$v, 2);

// Format date
$fmtDate = function(?string $d): string {
    if (!$d) return '';
    $ts = strtotime($d);
    return $ts ? date('F j, Y', $ts) : $d;
};

$pageTitle = h($ag['title']) . ' — Agency Agreement';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Screen toolbar ─────────────────────────────────── */
.doc-toolbar { margin-bottom: 1.5rem; }

/* ── Document styling ───────────────────────────────── */
.ag-doc {
    max-width: 780px;
    margin: 0 auto;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 10.5pt;
    color: #111;
    line-height: 1.55;
}
.ag-doc h1.doc-title {
    font-size: 22pt;
    font-weight: 700;
    text-align: center;
    margin-bottom: .25rem;
    letter-spacing: .01em;
}
.ag-doc .doc-subtitle {
    text-align: center;
    font-style: italic;
    color: #555;
    margin-bottom: 1.5rem;
    font-size: 9.5pt;
}
.ag-doc .section-heading {
    font-size: 11pt;
    font-weight: 700;
    margin: 1.4rem 0 .4rem;
    text-decoration: underline;
    text-underline-offset: 3px;
}
.ag-doc p { margin-bottom: .6rem; }
.ag-doc ol, .ag-doc ul { margin-bottom: .6rem; padding-left: 1.5rem; }
.ag-doc ol li, .ag-doc ul li { margin-bottom: .2rem; }

/* Budget table */
.budget-tbl { width: 100%; border-collapse: collapse; margin: .75rem 0; font-size: 9.5pt; }
.budget-tbl th {
    background: #1a1a2e;
    color: #fff;
    padding: 5px 10px;
    text-align: left;
    font-size: 9pt;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.budget-tbl td { padding: 4px 10px; border-bottom: 1px solid #e5e5e5; }
.budget-tbl tr:nth-child(even) td { background: #f7f7f7; }
.budget-tbl .cat-subtotal td {
    font-weight: 700;
    border-top: 1px solid #bbb;
    background: #f0f4ff;
}
.budget-tbl .grand-total td {
    font-weight: 700;
    font-size: 10pt;
    border-top: 2px solid #333;
    background: #e8f0fe;
}
.budget-tbl td:last-child { text-align: right; }
.budget-tbl th:last-child { text-align: right; }

/* Signature block */
.sig-block { margin-top: 2rem; }
.sig-row { display: flex; gap: 3rem; margin-bottom: 1.5rem; }
.sig-col { flex: 1; }
.sig-line {
    border-bottom: 1px solid #444;
    margin-bottom: .2rem;
    height: 1.8rem;
}
.sig-label { font-size: 8.5pt; color: #555; }
.sig-prefill { font-size: 9pt; color: #222; }

/* Page breaks for print */
.page-break { page-break-before: always; break-before: page; }

/* Section divider */
.doc-divider { border: none; border-top: 1.5px solid #ccc; margin: 1.5rem 0; }

/* ── Print overrides ────────────────────────────────── */
@media print {
    .doc-toolbar, nav, header, footer,
    .navbar, #site-header, aside { display: none !important; }

    body { background: #fff !important; }
    .ag-doc { max-width: 100%; font-size: 10pt; }
    .budget-tbl th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .budget-tbl .cat-subtotal td,
    .budget-tbl .grand-total td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .page-break { page-break-before: always; }
    a[href]:after { content: none !important; }
}
</style>

<!-- ── Screen toolbar ───────────────────────────────────────────────────── -->
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

<!-- ── DOCUMENT ─────────────────────────────────────────────────────────── -->
<div class="ag-doc">

    <!-- ═══════════════════════ PAGE 1 ════════════════════════════════ -->
    <h1 class="doc-title">Agency Agreement</h1>
    <div class="doc-subtitle">Enigma, Inc. DBA Enigma Marketing</div>

    <p>
        This agreement, by and between <strong>Enigma, Inc. DBA Enigma Marketing</strong>
        (&ldquo;Agency&rdquo;) and <strong><?= h($ag['client_name'] ?: 'CLIENT NAME') ?></strong>
        (&ldquo;Client&rdquo;), is a legally binding agreement. Agency agrees to provide marketing
        services described herein in exchange for payment from Client in accordance with the terms
        of this agreement.
    </p>

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
        <?php foreach ($ag['services'] as $i => $svcLine): ?>
        <li><?= h($svcLine) ?></li>
        <?php endforeach; ?>
    </ol>

    <div class="section-heading">Pricing</div>
    <p>
        Client agrees to pay for the marketing services provided by Agency in accordance with
        the following:
    </p>
    <?php if ($ag['deposit_amount'] > 0 || $ag['balance_amount'] > 0): ?>
    <p>
        <?php if ($ag['deposit_amount'] > 0): ?>
        <strong><?= $money($ag['deposit_amount']) ?> deposit</strong>
        <?= $ag['deposit_due_description'] ? h($ag['deposit_due_description']) : '' ?>
        <?php if ($ag['balance_amount'] > 0): ?> and the <?php endif; ?>
        <?php endif; ?>
        <?php if ($ag['balance_amount'] > 0): ?>
        <strong>remaining balance of <?= $money($ag['balance_amount']) ?></strong>
        <?= $ag['balance_due_description'] ? h($ag['balance_due_description']) : '' ?>
        <?php endif; ?>.
    </p>
    <?php endif; ?>
    <p><strong>Total <?= $money($ag['total_amount']) ?></strong></p>

    <?php
    // Build combined budget table
    $cats = [
        'budget_tv'    => 'TV',
        'budget_radio' => 'Radio',
        'budget_news'  => 'Newspaper',
        'budget_social'=> 'Social Media',
    ];
    $anyBudget = false;
    foreach ($cats as $k => $l) { if (!empty($ag[$k])) { $anyBudget = true; break; } }
    ?>

    <?php if ($anyBudget): ?>
    <div class="section-heading">Budget Breakdown</div>
    <table class="budget-tbl">
        <thead>
            <tr>
                <th>Category</th>
                <th>Outlet / Platform</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cats as $key => $label):
            $rows = $ag[$key] ?? [];
            if (empty($rows)) continue;
            $catTotal = array_sum(array_column($rows, 'amount'));
        ?>
            <?php foreach ($rows as $ri => $row): ?>
            <tr>
                <?php if ($ri === 0): ?>
                <td rowspan="<?= count($rows) ?>" style="font-weight:600;vertical-align:top;padding-top:6px;">
                    <?= h($label) ?>
                </td>
                <?php endif; ?>
                <td><?= h($row['name'] ?? '') ?></td>
                <td><?= $money($row['amount'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="cat-subtotal">
                <td colspan="2"><?= h($label) ?> Subtotal</td>
                <td><?= $money($catTotal) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="grand-total">
                <td colspan="2">Total Budget</td>
                <td><?= $money($budgetTotal) ?></td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($ag['contingency_monthly']) && $ag['contingency_monthly'] > 0): ?>
    <p>
        <strong>Contingency Funds Recommendation:</strong>
        <?= $money($ag['contingency_monthly']) ?> per month be set aside for additional services
        or unforeseen expenses. These funds to be discussed prior to services rendered and agreed
        upon with Enigma Managing Partner and Client.
    </p>
    <?php endif; ?>

    <p>
        <strong>Mileage:</strong> Travel to be paid @ <?= $money($ag['mileage_rate']) ?> per mile
        if travel is necessary outside of Yakima city limits and will be billed starting from
        Enigma&rsquo;s physical location. Travel time to be billed at half the hourly rate of
        <?= $money($ag['hourly_rate']) ?> per hour (Non-profit rate).
    </p>

    <?php if (!empty($ag['additional_notes'])): ?>
    <p><?= nl2br(h($ag['additional_notes'])) ?></p>
    <?php endif; ?>

    <!-- ═══════════════════════ PAGE 2 — TERMS ════════════════════════ -->
    <div class="page-break"></div>

    <div class="section-heading" style="margin-top:0;">Terms</div>

    <p><strong>Contract Duration:</strong></p>
    <ul>
        <li>Start: <?= $ag['contract_start'] ? h($fmtDate($ag['contract_start'])) : 'Upon signing this document' ?></li>
        <li>End:&nbsp;&nbsp;<?= $ag['contract_end']
            ? h($fmtDate($ag['contract_end']))
            : '12 months after signing' ?></li>
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
        <li>The scope provided by the Client serves as a guideline and does not limit Enigma and
            <?= h($ag['client_name'] ?: 'Client') ?> from collaborating on other projects or
            materials as needed.</li>
        <li>Enigma Marketing will receive partnership/sponsorship recognition on all printed and
            electronic materials created and maintained by Enigma.</li>
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
        Enigma Marketing unless transferred under a separate agreement with applicable fees.
    </p>

    <div class="section-heading">Acknowledgment and Agreement</div>
    <p>By signing below, I acknowledge that I have reviewed and agreed to the terms and
    conditions outlined in this contract.</p>

    <div class="sig-block">
        <div class="sig-row">
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-prefill fw-bold">Duane Gordon</div>
                <div class="sig-label">Name — Enigma Inc. DBA Enigma Marketing</div>
                <div class="sig-label mt-1">Title: Managing Partner</div>
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

        <div class="sig-row" style="font-size:9pt;color:#444;border-top:1px solid #ddd;padding-top:.75rem;">
            <div class="sig-col">
                <strong>Enigma Inc. &ndash; DBA Enigma Marketing</strong><br>
                3601 W Washington STE 130<br>
                Yakima, WA 98903<br>
                P: 509-452-3733
            </div>
            <div class="sig-col">
                <strong><?= h($ag['client_name'] ?: 'Client') ?></strong><br>
                <?= h($ag['client_address']) ?><br>
                <?= h($ag['client_city_state_zip']) ?><br>
                <?= h($ag['client_phone']) ?>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════ PAGE 3 — MOU ══════════════════════════ -->
    <div class="page-break"></div>

    <div class="section-heading" style="margin-top:0;">Memorandum of Understanding</div>

    <p>
        Enigma Marketing, an integrated marketing communications firm, agrees to complete all
        marketing services as listed above for
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
            responsibility for all collection of legal fees necessitated by default in payment.
        </li>
        <li>Enigma will be responsible for all payroll, income, and unemployment tax
            deductions/payments due to services incurred by Enigma.</li>
        <li>Enigma Marketing warrants and represents that to the best of our knowledge any
            artwork designed by our firm is original, does not contain any scandalous, libelous,
            or unlawful matter and has not been previously published. We further assert that we
            have full authority to make this agreement. This warranty does not extend to any uses
            that the Client or others may make of the product that may infringe on the rights of
            others.</li>
        <li>The Client will make additional payments for changes requested in original
            assignment. However, no additional payment shall be made for changes required to
            conform to the original assignment description.</li>
        <li>Cancellation fees are due based on the amount of work completed. Fifty percent (50%)
            of the final fee is due within 30 days notification that for any reason the job is
            canceled or postponed before the final stage. One hundred percent (100%) of the total
            fee is due despite cancellation or postponement of the job if the art has been
            completed. Upon cancellation, both parties agree that the Client owns all artwork in
            the state it is in at the time of cancellation, except for the native mechanical files
            used to create said artwork. Either party can terminate this contract with thirty days
            written notice and no balance owing.</li>
        <li>Client acknowledges that the violation of any of the provisions of this agreement
            will cause irreparable loss and harm to Enigma Marketing which cannot be reasonably
            or adequately compensated by damages in an action at law, and accordingly, that Enigma
            Marketing will be entitled, without posting bond or other security, to injunctive and
            other equitable relief to enforce the provisions of this agreement and to prevent or
            cure any breach or threatened breach thereof; but an action for any such relief will
            not be deemed to be a waiver of the right to an action for damages.</li>
        <li>Both parties shall fully comply with all applicable federal, state, and local laws and
            regulations during this time. Both parties shall adhere to the PRSA Code of Ethics.</li>
        <li>All information about the business and this contract shall be kept confidential until
            both parties agree to the release of said information to the public forum, at which
            time only information permissible for release shall be released. All other information
            shall remain confidential.</li>
        <li>Both parties agree that the Client owns all artwork (finished project) except for the
            native mechanical files, raw video or raw photography used to create said artwork.
            Those files are retained by Enigma Marketing unless and until a transfer of ownership
            agreement is made and transfer fee has been paid. Any alteration of original art is
            prohibited without the express consent of the artist.</li>
        <li>The Client will indemnify the individual artist, as well as Enigma Marketing, against
            all claims and expenses arising from uses for which the Client does not have the rights
            to or authority to use. The Client will be responsible for payment of any special
            licensing or royalty fees resulting from the use of graphics programs that require such
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
        agree that Enigma Marketing will provide the service explained in this contract
        according to the terms of this contract.
    </p>

    <div class="sig-block">
        <div class="sig-row">
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-label"><?= h($ag['client_name'] ?: 'COMPANY NAME') ?> | Company Representative</div>
                <div class="sig-label mt-2">Date: ______________________</div>
            </div>
            <div class="sig-col" style="flex:.45;">
                <div class="sig-line"></div>
                <div class="sig-label">Enigma Marketing Managing Partner | Duane Gordon</div>
                <div class="sig-label mt-2">Date: ______________________</div>
            </div>
        </div>
    </div>

</div><!-- /.ag-doc -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
