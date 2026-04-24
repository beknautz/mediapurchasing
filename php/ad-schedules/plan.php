<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc   = new AdScheduleService();
$errors   = [];
$flashMsg = flash('success');
$errorMsg = flash('error');

$planId = (int) ($_GET['id'] ?? 0);
if (!$planId) redirect('/ad-schedules/index.php');

$plan = $svc->getPlan($planId);
if (!$plan) {
    flash('error', 'Plan not found.');
    redirect('/ad-schedules/index.php');
}

// ── POST: save placement (add or edit) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_placement') {
        $pid = (int) ($_POST['placement_id'] ?? 0);
        if (trim($_POST['publication'] ?? '') === '') {
            $errors[] = 'Publication is required.';
        }
        if (empty($errors)) {
            try {
                $svc->savePlacement([
                    'id'                        => $pid,
                    'plan_id'                   => $planId,
                    'client_id'                 => (int)$plan['client_id'],
                    'publication'               => $_POST['publication']               ?? '',
                    'contact_name'              => $_POST['contact_name']              ?? '',
                    'editorial'                 => $_POST['editorial']                 ?? '',
                    'ad_number'                 => $_POST['ad_number']                ?? '',
                    'run_date'                  => $_POST['run_date']                  ?? '',
                    'artwork_deadline'          => $_POST['artwork_deadline']          ?? '',
                    'client_approval_deadline'  => $_POST['client_approval_deadline'] ?? '',
                    'ad_size'                   => $_POST['ad_size']                   ?? '',
                    'circulation'               => $_POST['circulation']               ?? '',
                    'num_ads'                   => $_POST['num_ads']                   ?? 1,
                    'cost_to_agency'            => $_POST['cost_to_agency']            ?? '',
                    'cost_to_client'            => $_POST['cost_to_client']            ?? '',
                    'markup_pct'                => $_POST['markup_pct']                ?? '',
                    'notes'                     => $_POST['notes']                     ?? '',
                    'sort_order'                => $_POST['sort_order']                ?? 0,
                ]);
                flash('success', $pid ? 'Placement updated.' : 'Placement added.');
                redirect('/ad-schedules/plan.php?id=' . $planId);
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }

    } elseif ($action === 'delete_placement') {
        $pid = (int) ($_POST['placement_id'] ?? 0);
        if ($pid > 0) {
            try {
                $svc->deletePlacement($pid);
                flash('success', 'Placement removed.');
            } catch (Exception $e) {
                flash('error', 'Could not delete: ' . $e->getMessage());
            }
        }
        redirect('/ad-schedules/plan.php?id=' . $planId);

    } elseif ($action === 'delete_plan') {
        try {
            $svc->deletePlan($planId);
            flash('success', 'Plan deleted.');
            redirect('/ad-schedules/index.php');
        } catch (Exception $e) {
            flash('error', 'Could not delete plan: ' . $e->getMessage());
            redirect('/ad-schedules/plan.php?id=' . $planId);
        }

    } elseif ($action === 'import_placements') {
        $rows = json_decode($_POST['rows_json'] ?? '[]', true);
        if (!is_array($rows)) {
            flash('error', 'Invalid import data.');
            redirect('/ad-schedules/plan.php?id=' . $planId);
        }
        $imported = 0;
        $skipped  = 0;
        foreach ($rows as $row) {
            if (empty(trim($row['publication'] ?? ''))) { $skipped++; continue; }
            try {
                $svc->savePlacement([
                    'id'                        => 0,
                    'plan_id'                   => $planId,
                    'client_id'                 => (int)$plan['client_id'],
                    'publication'               => $row['publication']              ?? '',
                    'contact_name'              => $row['contact_name']             ?? '',
                    'editorial'                 => $row['editorial']                ?? '',
                    'ad_number'                 => $row['ad_number']                ?? '',
                    'run_date'                  => $row['run_date']                 ?? '',
                    'artwork_deadline'          => $row['artwork_deadline']         ?? '',
                    'client_approval_deadline'  => $row['client_approval_deadline'] ?? '',
                    'ad_size'                   => $row['ad_size']                  ?? '',
                    'circulation'               => $row['circulation']              ?? '',
                    'num_ads'                   => $row['num_ads']                  ?? 1,
                    'cost_to_agency'            => $row['cost_to_agency']           ?? '',
                    'cost_to_client'            => $row['cost_to_client']           ?? '',
                    'markup_pct'                => $row['markup_pct']               ?? '',
                    'notes'                     => $row['notes']                    ?? '',
                    'sort_order'                => 0,
                ]);
                $imported++;
            } catch (Exception $e) {
                $skipped++;
            }
        }
        $msg = 'Imported ' . $imported . ' placement' . ($imported !== 1 ? 's' : '');
        if ($skipped > 0) $msg .= ' (' . $skipped . ' skipped)';
        flash('success', $msg . '.');
        redirect('/ad-schedules/plan.php?id=' . $planId);
    }
}

$placements = $svc->getPlacements($planId);
$stats      = $svc->getPlanStats($planId);
$overdue    = array_filter($placements, fn($p) =>
    $p['run_date'] && strtotime($p['run_date']) < strtotime('-7 days') && !$p['placement_invoiced']
);

// For edit modal: which placement to pre-load
$editId      = (int) ($_GET['edit'] ?? 0);
$editRecord  = $editId ? $svc->getPlacement($editId) : null;

$pageTitle = h($plan['company_name']) . ' — ' . $plan['year'] . ' Ad Schedule — MediaBuy';
require_once __DIR__ . '/../includes/header.php';

// Helper: render a workflow / billing checkbox cell (toggles via AJAX)
function checkCell(int $id, string $field, int $value, string $title = ''): void {
    $checked = $value ? 'text-success' : 'text-muted opacity-50';
    $icon    = $value ? 'bi-check-circle-fill' : 'bi-circle';
    echo '<td class="text-center" style="width:40px;" title="' . h($title) . '">'
       . '<button type="button" class="btn btn-link p-0 toggle-btn ' . $checked . '" '
       . 'data-id="' . $id . '" data-field="' . $field . '" style="font-size:1.1rem;">'
       . '<i class="bi ' . $icon . '"></i>'
       . '</button></td>';
}
?>

<?php if ($flashMsg): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i><?= h($flashMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errorMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Header ──────────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
    <div>
        <h1 class="h3 mb-1 fw-bold">
            <i class="bi bi-calendar3 me-2 text-primary"></i>
            <?= h($plan['company_name']) ?>
            <span class="badge bg-secondary ms-2"><?= (int)$plan['year'] ?></span>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/ad-schedules/index.php">Ad Schedules</a></li>
                <li class="breadcrumb-item active"><?= h($plan['company_name']) ?> <?= $plan['year'] ?></li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-primary btn-sm"
                onclick="openAddModal()">
            <i class="bi bi-plus-circle me-1"></i>Add Placement
        </button>
        <button type="button" class="btn btn-outline-success btn-sm"
                data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Import Excel
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm"
                onclick="confirmDeletePlan()">
            <i class="bi bi-trash me-1"></i>Delete Plan
        </button>
    </div>
</div>

<!-- ── Stats row ───────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-4 fw-bold text-primary"><?= (int)$stats['total'] ?></div>
            <div class="small text-muted">Placements</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-4 fw-bold text-success">
                $<?= number_format((float)($stats['total_client_cost'] ?? 0), 0) ?>
            </div>
            <div class="small text-muted">Client Spend</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-4 fw-bold <?= $stats['billed_count'] == $stats['total'] && $stats['total'] > 0 ? 'text-success' : 'text-warning' ?>">
                <?= (int)$stats['billed_count'] ?> / <?= (int)$stats['total'] ?>
            </div>
            <div class="small text-muted">Placement Billed</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-4 fw-bold <?= $stats['overdue_count'] > 0 ? 'text-danger' : 'text-success' ?>">
                <?php if ($stats['overdue_count'] > 0): ?>
                <i class="bi bi-alarm me-1"></i><?= (int)$stats['overdue_count'] ?>
                <?php else: ?>
                <i class="bi bi-check-circle"></i>
                <?php endif; ?>
            </div>
            <div class="small text-muted">Billing Alerts</div>
        </div>
    </div>
</div>

<!-- ── Overdue banner ──────────────────────────────────────────────────────── -->
<?php if (!empty($overdue)): ?>
<div class="alert alert-danger d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-alarm-fill fs-5 flex-shrink-0 mt-1"></i>
    <div>
        <strong><?= count($overdue) ?> placement<?= count($overdue) !== 1 ? 's' : '' ?> need billing</strong>
        — run date passed 7+ days ago. Rows are highlighted below.
    </div>
</div>
<?php endif; ?>

<!-- ── Placements Table ────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-semibold">
                <i class="bi bi-table me-2 text-primary"></i>Publication Schedule
            </h5>
            <div class="d-flex gap-3 small text-muted align-items-center">
                <span><i class="bi bi-circle-fill text-success me-1" style="font-size:.6rem;"></i>Checked</span>
                <span><i class="bi bi-circle text-muted me-1" style="font-size:.6rem;"></i>Unchecked</span>
                <span><i class="bi bi-circle-fill text-danger me-1" style="font-size:.6rem;"></i>Billing alert</span>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($placements)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-50"></i>
            <div>No placements yet.</div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-3"
                    onclick="openAddModal()">
                <i class="bi bi-plus-circle me-1"></i>Add First Placement
            </button>
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="min-width:1300px;">
            <thead class="table-light" style="font-size:.75rem;">
                <tr>
                    <th style="width:200px;">Publication</th>
                    <th style="width:180px;">Editorial</th>
                    <th style="width:90px;">Run Date</th>
                    <th style="width:90px;">Art Due</th>
                    <th style="width:90px;">Approval Due</th>
                    <th style="width:70px;">Ad #</th>
                    <th class="text-end" style="width:85px;">Agency $</th>
                    <th class="text-end" style="width:85px;">Client $</th>
                    <!-- Workflow -->
                    <th class="text-center" style="width:40px;" title="Reserved/Ordered">Res</th>
                    <th class="text-center" style="width:40px;" title="Designed">Des</th>
                    <th class="text-center" style="width:40px;" title="Sent to Client">→HC</th>
                    <th class="text-center" style="width:40px;" title="Sent to Publication">→Pub</th>
                    <!-- Billing -->
                    <th class="text-center" style="width:48px;" title="Design Invoiced">D-Bill</th>
                    <th class="text-center" style="width:55px;" title="Placement Invoiced">P-Bill</th>
                    <th style="width:65px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $prevPub = null;
            foreach ($placements as $p):
                $isOverdue = $p['run_date']
                    && strtotime($p['run_date']) < strtotime('-7 days')
                    && !$p['placement_invoiced'];
                $rowClass  = $isOverdue ? 'table-danger' : '';
                $daysAgo   = $p['run_date'] ? (int)((time() - strtotime($p['run_date'])) / 86400) : null;

                // Publication group divider
                if ($prevPub !== null && $p['publication'] !== $prevPub):
            ?>
            <tr class="table-secondary" style="font-size:.7rem;">
                <td colspan="15" class="py-1 px-3 text-muted fw-semibold" style="border-top:2px solid #dee2e6;">
                    &nbsp;
                </td>
            </tr>
            <?php
                endif;
                $prevPub = $p['publication'];
            ?>
            <tr id="row-<?= (int)$p['id'] ?>" class="<?= $rowClass ?>">
                <td style="font-size:.8rem;">
                    <div class="fw-semibold"><?= h($p['publication']) ?></div>
                    <?php if ($p['contact_name']): ?>
                    <div class="text-muted" style="font-size:.7rem;"><?= h($p['contact_name']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:.8rem;"><?= h($p['editorial'] ?? '') ?></td>
                <td style="font-size:.8rem;" class="text-nowrap">
                    <?php if ($p['run_date']): ?>
                        <?= h(date('M j, Y', strtotime($p['run_date']))) ?>
                        <?php if ($isOverdue): ?>
                        <div class="text-danger fw-semibold" style="font-size:.7rem;">
                            <i class="bi bi-alarm"></i> <?= $daysAgo ?>d ago
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.8rem;" class="text-nowrap text-muted">
                    <?= $p['artwork_deadline'] ? h(date('M j', strtotime($p['artwork_deadline']))) : '—' ?>
                </td>
                <td style="font-size:.8rem;" class="text-nowrap text-muted">
                    <?= $p['client_approval_deadline'] ? h(date('M j', strtotime($p['client_approval_deadline']))) : '—' ?>
                </td>
                <td style="font-size:.75rem;" class="text-muted"><?= h($p['ad_number'] ?? '') ?></td>
                <td class="text-end" style="font-size:.8rem;">
                    <?= $p['cost_to_agency'] !== null ? '$'.number_format((float)$p['cost_to_agency'], 2) : '—' ?>
                </td>
                <td class="text-end fw-semibold" style="font-size:.8rem;">
                    <?= $p['cost_to_client'] !== null ? '$'.number_format((float)$p['cost_to_client'], 2) : '—' ?>
                </td>

                <?php /* Workflow checkboxes */ ?>
                <?php checkCell((int)$p['id'], 'is_reserved',            (int)$p['is_reserved'],            'Reserved/Ordered') ?>
                <?php checkCell((int)$p['id'], 'is_designed',            (int)$p['is_designed'],            'Designed') ?>
                <?php checkCell((int)$p['id'], 'is_sent_to_client',      (int)$p['is_sent_to_client'],      'Sent to Client') ?>
                <?php checkCell((int)$p['id'], 'is_sent_to_publication', (int)$p['is_sent_to_publication'], 'Sent to Publication') ?>

                <?php /* Billing checkboxes */ ?>
                <td class="text-center" style="width:48px;">
                    <button type="button"
                            class="btn btn-link p-0 toggle-btn <?= $p['design_invoiced'] ? 'text-success' : 'text-muted opacity-50' ?>"
                            data-id="<?= (int)$p['id'] ?>" data-field="design_invoiced"
                            style="font-size:1.1rem;" title="Design Invoiced">
                        <i class="bi <?= $p['design_invoiced'] ? 'bi-check-circle-fill' : 'bi-circle' ?>"></i>
                    </button>
                    <?php if ($p['design_invoiced_at']): ?>
                    <div class="text-muted" style="font-size:.65rem;">
                        <?= date('M j', strtotime($p['design_invoiced_at'])) ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td class="text-center" style="width:55px;">
                    <button type="button"
                            class="btn btn-link p-0 toggle-btn <?= $p['placement_invoiced'] ? 'text-success' : ($isOverdue ? 'text-danger' : 'text-muted opacity-50') ?>"
                            data-id="<?= (int)$p['id'] ?>" data-field="placement_invoiced"
                            style="font-size:1.1rem;" title="Placement Invoiced">
                        <i class="bi <?= $p['placement_invoiced'] ? 'bi-check-circle-fill' : 'bi-circle' ?>"></i>
                    </button>
                    <?php if ($p['placement_invoiced_at']): ?>
                    <div class="text-muted" style="font-size:.65rem;">
                        <?= date('M j', strtotime($p['placement_invoiced_at'])) ?>
                    </div>
                    <?php endif; ?>
                </td>

                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary me-2"
                            onclick='openEditModal(<?= json_encode($p) ?>)' title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger"
                            onclick="confirmDeletePlacement(<?= (int)$p['id'] ?>, <?= json_encode($p['publication'].' — '.($p['editorial']??'')) ?>)"
                            title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold" style="font-size:.8rem;">
                <tr>
                    <td colspan="6" class="text-end pe-2">Totals</td>
                    <td class="text-end">
                        $<?= number_format(array_sum(array_column($placements, 'cost_to_agency')), 2) ?>
                    </td>
                    <td class="text-end">
                        $<?= number_format(array_sum(array_column($placements, 'cost_to_client')), 2) ?>
                    </td>
                    <td colspan="7"></td>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Add / Edit Placement Modal ─────────────────────────────────────────── -->
<div class="modal fade" id="placementModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="/ad-schedules/plan.php?id=<?= $planId ?>" id="placementForm">
                <input type="hidden" name="action" value="save_placement">
                <input type="hidden" name="placement_id" id="placementId" value="0">
                <input type="hidden" name="sort_order" id="placementSort" value="0">

                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="placementModalTitle">
                        <i class="bi bi-plus-circle me-2 text-primary"></i>Add Placement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Publication -->
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Publication <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="publication" id="fPub"
                                   placeholder="e.g. Capital Press" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Contact Name</label>
                            <input type="text" class="form-control" name="contact_name" id="fContact"
                                   placeholder="e.g. Patty Gilbert">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Ad Number</label>
                            <input type="text" class="form-control" name="ad_number" id="fAdNum"
                                   placeholder="e.g. CP424">
                        </div>

                        <!-- Editorial / Edition -->
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Editorial / Edition</label>
                            <input type="text" class="form-control" name="editorial" id="fEditorial"
                                   placeholder="e.g. Orchards, Nuts &amp; Vines">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ad Size</label>
                            <input type="text" class="form-control" name="ad_size" id="fSize"
                                   placeholder='e.g. 1/4 page 5.125" x 5"'>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Circulation</label>
                            <input type="number" class="form-control" name="circulation" id="fCirc"
                                   min="0" placeholder="32000">
                        </div>

                        <!-- Dates -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Run / Publication Date</label>
                            <input type="date" class="form-control" name="run_date" id="fRunDate">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Artwork Deadline</label>
                            <input type="date" class="form-control" name="artwork_deadline" id="fArtwork">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Client Approval Due</label>
                            <input type="date" class="form-control" name="client_approval_deadline" id="fApproval">
                        </div>

                        <!-- Financials -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Cost to Agency</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="cost_to_agency" id="fAgencyCost"
                                       min="0" step="0.01" placeholder="0.00"
                                       oninput="autoMarkup()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Markup %</label>
                            <input type="number" class="form-control" name="markup_pct" id="fMarkup"
                                   min="0" step="0.1" placeholder="20"
                                   oninput="autoMarkup()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Cost to Client</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="cost_to_client" id="fClientCost"
                                       min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold"># Ads</label>
                            <input type="number" class="form-control" name="num_ads" id="fNumAds"
                                   min="1" value="1">
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" name="notes" id="fNotes" rows="2"
                                      placeholder="Optional notes…"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>Save Placement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Delete Placement Modal ─────────────────────────────────────────────── -->
<div class="modal fade" id="deletePlacementModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="/ad-schedules/plan.php?id=<?= $planId ?>">
                <input type="hidden" name="action" value="delete_placement">
                <input type="hidden" name="placement_id" id="deletePlacementId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title">Remove Placement?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    Remove <strong id="deletePlacementName"></strong>? This cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Delete Plan Modal ──────────────────────────────────────────────────── -->
<div class="modal fade" id="deletePlanModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="/ad-schedules/plan.php?id=<?= $planId ?>">
                <input type="hidden" name="action" value="delete_plan">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Plan?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    Delete the entire <strong><?= h($plan['company_name']) ?> <?= $plan['year'] ?></strong>
                    plan and all <?= count($placements) ?> placement<?= count($placements) !== 1 ? 's' : '' ?>?
                    This cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ── Placement modal helpers ───────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('placementModalTitle').innerHTML =
        '<i class="bi bi-plus-circle me-2 text-primary"></i>Add Placement';
    document.getElementById('placementId').value = '0';
    document.getElementById('placementForm').reset();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('placementModal')).show();
}

function openEditModal(p) {
    document.getElementById('placementModalTitle').innerHTML =
        '<i class="bi bi-pencil me-2 text-primary"></i>Edit Placement';
    document.getElementById('placementId').value  = p.id;
    document.getElementById('placementSort').value = p.sort_order ?? 0;
    document.getElementById('fPub').value          = p.publication     || '';
    document.getElementById('fContact').value      = p.contact_name    || '';
    document.getElementById('fAdNum').value        = p.ad_number       || '';
    document.getElementById('fEditorial').value    = p.editorial       || '';
    document.getElementById('fSize').value         = p.ad_size         || '';
    document.getElementById('fCirc').value         = p.circulation     || '';
    document.getElementById('fRunDate').value      = p.run_date        || '';
    document.getElementById('fArtwork').value      = p.artwork_deadline || '';
    document.getElementById('fApproval').value     = p.client_approval_deadline || '';
    document.getElementById('fAgencyCost').value   = p.cost_to_agency  ?? '';
    document.getElementById('fMarkup').value       = p.markup_pct      ?? '';
    document.getElementById('fClientCost').value   = p.cost_to_client  ?? '';
    document.getElementById('fNumAds').value       = p.num_ads         || 1;
    document.getElementById('fNotes').value        = p.notes           || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('placementModal')).show();
}

function autoMarkup() {
    const agency  = parseFloat(document.getElementById('fAgencyCost').value) || 0;
    const markup  = parseFloat(document.getElementById('fMarkup').value)     || 0;
    if (agency > 0 && markup > 0) {
        document.getElementById('fClientCost').value = (agency * (1 + markup / 100)).toFixed(2);
    }
}

function confirmDeletePlacement(id, name) {
    document.getElementById('deletePlacementId').value       = id;
    document.getElementById('deletePlacementName').textContent = name;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deletePlacementModal')).show();
}

function confirmDeletePlan() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deletePlanModal')).show();
}

// ── AJAX checkbox toggles ─────────────────────────────────────────────────────
document.querySelectorAll('.toggle-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const id    = this.dataset.id;
        const field = this.dataset.field;
        const icon  = this.querySelector('i');
        const btn   = this;

        btn.disabled = true;
        fetch('/ad-schedules/api.php', {
            method:  'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body:    'action=toggle&id=' + encodeURIComponent(id) + '&field=' + encodeURIComponent(field),
        })
        .then(r => r.json())
        .then(function (data) {
            if (data.success) {
                const on = data.new_value === 1;
                icon.className = on ? 'bi bi-check-circle-fill' : 'bi bi-circle';

                const isPlacementBill = field === 'placement_invoiced';
                if (on) {
                    btn.classList.replace('text-muted', 'text-success');
                    btn.classList.replace('text-danger', 'text-success');
                    btn.classList.remove('opacity-50');
                } else {
                    btn.classList.replace('text-success', isPlacementBill ? 'text-muted' : 'text-muted');
                    if (!isPlacementBill) btn.classList.add('opacity-50');
                }

                // If billing a placement, de-highlight the overdue row
                if (isPlacementBill && on) {
                    const row = document.getElementById('row-' + id);
                    if (row) row.classList.remove('table-danger');
                }
            }
        })
        .catch(function () { /* silent */ })
        .finally(function () { btn.disabled = false; });
    });
});

<?php if ($editRecord): ?>
// Auto-open edit modal for ?edit= parameter
window.addEventListener('load', function () { openEditModal(<?= json_encode($editRecord) ?>); });
<?php endif; ?>
</script>

<!-- ── Import Excel Modal ─────────────────────────────────────────────────── -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold">
                    <i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Import from Excel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Drop zone -->
                <div id="importDropZone"
                     class="border border-2 border-dashed rounded-3 text-center py-5 px-3 mb-3"
                     style="border-color:#6c757d!important; cursor:pointer; transition:background .15s;"
                     ondragover="importDragOver(event)" ondragleave="importDragLeave(event)"
                     ondrop="importDrop(event)" onclick="document.getElementById('importFileInput').click()">
                    <i class="bi bi-cloud-upload fs-2 text-muted d-block mb-2"></i>
                    <div class="fw-semibold">Drag &amp; drop an Excel file here</div>
                    <div class="small text-muted mt-1">or click to browse — .xlsx / .xls / .csv</div>
                    <input type="file" id="importFileInput" accept=".xlsx,.xls,.csv"
                           class="d-none" onchange="importFileSelected(this)">
                </div>

                <!-- Column mapping hint -->
                <div id="importMappingHint" class="alert alert-info small py-2 d-none">
                    <i class="bi bi-info-circle me-1"></i>
                    <span id="importMappingText"></span>
                </div>

                <!-- Preview -->
                <div id="importPreviewWrap" class="d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-semibold small">
                            Preview — <span id="importRowCount">0</span> row(s) detected
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="importReset()">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </button>
                    </div>
                    <div class="table-responsive" style="max-height:340px; overflow-y:auto;">
                        <table class="table table-sm table-bordered mb-0 align-middle"
                               style="font-size:.78rem; min-width:1100px;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Publication</th>
                                    <th>Contact</th>
                                    <th>Editorial</th>
                                    <th>Ad #</th>
                                    <th>Run Date</th>
                                    <th>Art Due</th>
                                    <th>Approval Due</th>
                                    <th>Ad Size</th>
                                    <th class="text-end">Agency $</th>
                                    <th class="text-end">Client $</th>
                                    <th class="text-end">Markup %</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="importPreviewBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="/ad-schedules/plan.php?id=<?= $planId ?>" id="importForm">
                    <input type="hidden" name="action" value="import_placements">
                    <input type="hidden" name="rows_json" id="importRowsJson" value="[]">
                    <button type="submit" class="btn btn-success d-none" id="importSubmitBtn">
                        <i class="bi bi-cloud-download me-1"></i>
                        Import <span id="importSubmitCount">0</span> Placement(s)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
// ── Excel Import ──────────────────────────────────────────────────────────────

// Excel header (lowercase) → form field name
const IMPORT_COL_MAP = {
    'publication':       'publication',
    'editorial':         'editorial',
    'publication month': 'run_date',
    'artwork deadline':  'artwork_deadline',
    'client approval':   'client_approval_deadline',
    'size':              'ad_size',
    'circulation':       'circulation',
    '# of ads':          'num_ads',
    'ad #':              'ad_number',
    'client cost':       'cost_to_client',
    'our cost':          'cost_to_agency',
    'markup %':          'markup_pct',
};

let importParsedRows = [];

function importDragOver(e) {
    e.preventDefault();
    document.getElementById('importDropZone').style.background = '#e8f5e9';
}
function importDragLeave(e) {
    document.getElementById('importDropZone').style.background = '';
}
function importDrop(e) {
    e.preventDefault();
    importDragLeave(e);
    const file = e.dataTransfer.files[0];
    if (file) importParseFile(file);
}
function importFileSelected(input) {
    if (input.files[0]) importParseFile(input.files[0]);
}

function importParseFile(file) {
    const reader = new FileReader();
    reader.onload = function (e) {
        try {
            const wb = XLSX.read(e.target.result, { type: 'array', cellDates: true, dateNF: 'yyyy-mm-dd' });
            const ws = wb.Sheets[wb.SheetNames[0]];
            const raw = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, dateNF: 'yyyy-mm-dd' });
            importProcess(raw);
        } catch (err) {
            alert('Could not read file: ' + err.message);
        }
    };
    reader.readAsArrayBuffer(file);
}

function importProcess(raw) {
    // Find first non-empty row as header
    let headerRowIdx = -1;
    for (let i = 0; i < Math.min(raw.length, 10); i++) {
        if (raw[i] && raw[i].filter(Boolean).length >= 2) { headerRowIdx = i; break; }
    }
    if (headerRowIdx === -1) { alert('Could not detect a header row.'); return; }

    const headers = raw[headerRowIdx].map(h => (h || '').toString().trim().toLowerCase());
    const colIndex = {};  // fieldName → column index
    const unmapped = [];

    headers.forEach(function (h, idx) {
        const field = IMPORT_COL_MAP[h];
        if (field && !(field in colIndex)) {
            colIndex[field] = idx;
        } else if (h) {
            unmapped.push(h);
        }
    });

    if (!('publication' in colIndex)) {
        alert('Could not find a "Publication" column. Check that your Excel headers match exactly.');
        return;
    }

    // Parse data rows
    importParsedRows = [];
    for (let i = headerRowIdx + 1; i < raw.length; i++) {
        const row = raw[i];
        if (!row || !row.filter(Boolean).length) continue;

        const pub = (row[colIndex['publication']] || '').toString().trim();
        if (!pub) continue;  // skip blank publication rows

        const r = {};
        Object.keys(colIndex).forEach(function (field) {
            let val = (row[colIndex[field]] || '').toString().trim();
            // Normalise date values that come as JS Date serialised strings
            if (['run_date', 'artwork_deadline', 'client_approval_deadline'].includes(field)) {
                val = importNormaliseDate(val);
            }
            r[field] = val;
        });
        importParsedRows.push(r);
    }

    // Show mapping hint
    const hintEl = document.getElementById('importMappingHint');
    const mappedFields = Object.keys(colIndex).length;
    let hintText = 'Matched ' + mappedFields + ' column(s): ' + Object.keys(colIndex).join(', ') + '.';
    if (unmapped.length) hintText += '  Ignored (no matching field): ' + unmapped.join(', ') + '.';
    document.getElementById('importMappingText').textContent = hintText;
    hintEl.classList.remove('d-none');

    importRenderPreview();
}

function importNormaliseDate(val) {
    if (!val) return '';
    // Already YYYY-MM-DD
    if (/^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
    // Try parsing
    const d = new Date(val);
    if (!isNaN(d)) return d.toISOString().slice(0, 10);
    return val;
}

function importRenderPreview() {
    const tbody = document.getElementById('importPreviewBody');
    tbody.innerHTML = '';

    importParsedRows.forEach(function (r) {
        const tr = document.createElement('tr');
        [
            r.publication, r.contact_name, r.editorial, r.ad_number,
            r.run_date, r.artwork_deadline, r.client_approval_deadline,
            r.ad_size,
        ].forEach(function (val) {
            const td = document.createElement('td');
            td.textContent = val || '';
            tr.appendChild(td);
        });
        ['cost_to_agency', 'cost_to_client', 'markup_pct'].forEach(function (f) {
            const td = document.createElement('td');
            td.className = 'text-end';
            td.textContent = r[f] || '';
            tr.appendChild(td);
        });
        const tdNotes = document.createElement('td');
        tdNotes.textContent = r.notes || '';
        tr.appendChild(tdNotes);
        tbody.appendChild(tr);
    });

    const count = importParsedRows.length;
    document.getElementById('importRowCount').textContent = count;
    document.getElementById('importSubmitCount').textContent = count;
    document.getElementById('importRowsJson').value = JSON.stringify(importParsedRows);

    const previewWrap = document.getElementById('importPreviewWrap');
    const submitBtn   = document.getElementById('importSubmitBtn');
    if (count > 0) {
        previewWrap.classList.remove('d-none');
        submitBtn.classList.remove('d-none');
    } else {
        previewWrap.classList.add('d-none');
        submitBtn.classList.add('d-none');
        alert('No importable rows found. Check that the Publication column is filled.');
    }
}

function importReset() {
    importParsedRows = [];
    document.getElementById('importFileInput').value = '';
    document.getElementById('importPreviewBody').innerHTML = '';
    document.getElementById('importPreviewWrap').classList.add('d-none');
    document.getElementById('importSubmitBtn').classList.add('d-none');
    document.getElementById('importMappingHint').classList.add('d-none');
    document.getElementById('importDropZone').style.background = '';
}

// Reset state when modal is closed
document.getElementById('importModal').addEventListener('hidden.bs.modal', importReset);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
