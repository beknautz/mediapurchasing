<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin']);

// ── XLSX reader (ZipArchive only, no PhpSpreadsheet) ────────────────────────

function readXlsxSheets(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Cannot open XLSX file.');
    }

    // Shared strings
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $dom = new DOMDocument();
        @$dom->loadXML($ssXml);
        foreach ($dom->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $text .= $t->textContent;
            }
            $sharedStrings[] = $text;
        }
    }

    // Workbook sheet list
    $wbXml = $zip->getFromName('xl/workbook.xml');
    $wbDom = new DOMDocument();
    @$wbDom->loadXML($wbXml);
    $sheetNames = [];
    foreach ($wbDom->getElementsByTagName('sheet') as $sheet) {
        $sheetNames[$sheet->getAttribute('r:id')] = $sheet->getAttribute('name');
    }

    // Relationships → file paths
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    $relsDom = new DOMDocument();
    @$relsDom->loadXML($relsXml);
    $sheetFiles = [];
    foreach ($relsDom->getElementsByTagName('Relationship') as $rel) {
        $id = $rel->getAttribute('Id');
        $target = $rel->getAttribute('Target');
        if (isset($sheetNames[$id])) {
            $file = (str_starts_with($target, 'xl/') ? '' : 'xl/') . $target;
            $sheetFiles[$sheetNames[$id]] = $file;
        }
    }

    // Parse each sheet into array of rows
    $result = [];
    foreach ($sheetFiles as $sheetName => $sheetPath) {
        $wsXml = $zip->getFromName($sheetPath);
        if (!$wsXml) continue;
        $wsDom = new DOMDocument();
        @$wsDom->loadXML($wsXml);
        $rows = [];
        foreach ($wsDom->getElementsByTagName('row') as $rowEl) {
            $cells = [];
            $maxCol = 0;
            foreach ($rowEl->getElementsByTagName('c') as $c) {
                $ref  = $c->getAttribute('r');
                $type = $c->getAttribute('t');
                $vNode = $c->getElementsByTagName('v')->item(0);
                $tNode = $c->getElementsByTagName('t')->item(0);
                preg_match('/^([A-Z]+)/', $ref, $m);
                $colStr = $m[1] ?? 'A';
                $col = 0;
                for ($i = 0; $i < strlen($colStr); $i++) {
                    $col = $col * 26 + (ord($colStr[$i]) - 64);
                }
                $col--;
                if ($type === 's' && $vNode) {
                    $val = $sharedStrings[(int)$vNode->textContent] ?? '';
                } elseif ($type === 'inlineStr' && $tNode) {
                    $val = $tNode->textContent;
                } elseif ($vNode) {
                    $val = $vNode->textContent;
                } else {
                    $val = '';
                }
                $cells[$col] = trim((string)$val);
                $maxCol = max($maxCol, $col);
            }
            $rowArr = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $rowArr[] = $cells[$i] ?? '';
            }
            if (array_filter($rowArr)) {
                $rows[] = $rowArr;
            }
        }
        $result[$sheetName] = $rows;
    }
    $zip->close();
    return $result;
}

// ── Sheet parser ─────────────────────────────────────────────────────────────

function parseMediaSheet(string $market, array $rows): array
{
    $colMap = ['media' => 0, 'rep' => 1, 'email' => 2, 'phone' => 3, 'notes' => 4];
    $headerRow = -1;

    foreach ($rows as $i => $row) {
        foreach ($row as $j => $cell) {
            $low = strtolower(trim($cell));
            if ($low === 'media') { $colMap['media'] = $j; $headerRow = $i; }
            if ($low === 'rep')   { $colMap['rep']   = $j; $headerRow = $i; }
            if ($low === 'email') { $colMap['email'] = $j; $headerRow = $i; }
            if ($low === 'phone') { $colMap['phone'] = $j; $headerRow = $i; }
        }
        if ($headerRow === $i) break;
    }
    if ($headerRow === -1) return [];

    $catPat  = '/^(TV|Radio|Newspaper|Print|Online|Digital|Magazine|Outdoor|Cable|Streaming)\b/i';
    $addrPat = '/^\d|\b(St|Ave|Blvd|Dr|Rd|Way|Ln|Road|Street|Avenue|Drive)\b/i';

    $currentOutlet   = '';
    $currentCategory = '';
    $contacts = [];

    for ($i = $headerRow + 1; $i < count($rows); $i++) {
        $row   = $rows[$i];
        $media = trim($row[$colMap['media']] ?? '');
        $rep   = trim($row[$colMap['rep']]   ?? '');
        $email = strtolower(trim($row[$colMap['email']] ?? ''));
        $phone = trim($row[$colMap['phone']] ?? '');

        if ($media !== '') {
            if (preg_match($catPat, $media)) {
                $currentCategory = $media;
            } elseif (!preg_match($addrPat, $media)) {
                $currentOutlet = $media;
            }
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

        $contacts[] = [
            'outlet'   => $currentOutlet ?: 'Unknown',
            'category' => $currentCategory,
            'market'   => $market,
            'name'     => $rep,
            'email'    => $email,
            'phone'    => $phone,
        ];
    }
    return $contacts;
}

// ── Sheets to skip ───────────────────────────────────────────────────────────

const SKIP_SHEETS = ['Press Releases', 'HCC-Client Advertising Contact'];

// ── Handle requests ──────────────────────────────────────────────────────────

$errors  = [];
$step    = 'upload';   // upload | preview | done
$preview = [];         // parsed contacts for preview step
$results = [];         // insert results for done step

// ── STEP 3: Confirm + INSERT ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && !empty($_POST['payload'])) {
    $step = 'done';

    $contacts = json_decode(base64_decode($_POST['payload']), true);
    if (!is_array($contacts)) {
        $errors[] = 'Invalid payload — please re-upload the file.';
        $step = 'upload';
    } else {
        // Deduplicate by email (case-insensitive) — keep first occurrence
        $seen = [];
        $unique = [];
        foreach ($contacts as $c) {
            $key = strtolower($c['email']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $c;
            }
        }

        $outletsCreated   = 0;
        $contactsUpserted = 0;
        $contactsSkipped  = 0;

        // Grab PDO from a service instance via reflection (db is protected in BaseService)
        $svc  = new PressReleaseService();
        $ref  = new ReflectionObject($svc);
        $prop = $ref->getProperty('db');
        $prop->setAccessible(true);
        $pdo  = $prop->getValue($svc);

        try {
            $pdo->beginTransaction();

            // Group contacts by outlet key (market + outlet name)
            $outletMap = []; // "market||outletName" => outlet_id

            foreach ($unique as $c) {
                $outletKey = strtolower($c['market'] . '||' . $c['outlet']);

                if (!isset($outletMap[$outletKey])) {
                    // Try to find existing outlet
                    $chk = $pdo->prepare(
                        'SELECT id FROM media_outlets WHERE LOWER(market) = LOWER(:market) AND LOWER(name) = LOWER(:name) LIMIT 1'
                    );
                    $chk->execute([':market' => $c['market'], ':name' => $c['outlet']]);
                    $existingOutlet = $chk->fetchColumn();

                    if ($existingOutlet) {
                        $outletMap[$outletKey] = (int) $existingOutlet;
                    } else {
                        $ins = $pdo->prepare(
                            'INSERT INTO media_outlets (name, market, category, created_at, updated_at)
                             VALUES (:name, :market, :category, NOW(), NOW())'
                        );
                        $ins->execute([
                            ':name'     => $c['outlet'],
                            ':market'   => $c['market'],
                            ':category' => $c['category'],
                        ]);
                        $outletMap[$outletKey] = (int) $pdo->lastInsertId();
                        $outletsCreated++;
                    }
                }

                $outletId = $outletMap[$outletKey];

                // Upsert contact by email
                $upsert = $pdo->prepare(
                    'INSERT INTO media_contacts (outlet_id, name, email, phone, created_at, updated_at)
                     VALUES (:outlet_id, :name, :email, :phone, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE
                         name       = VALUES(name),
                         phone      = VALUES(phone),
                         updated_at = NOW()'
                );
                $upsert->execute([
                    ':outlet_id' => $outletId,
                    ':name'      => $c['name'],
                    ':email'     => $c['email'],
                    ':phone'     => $c['phone'],
                ]);
                $affected = $upsert->rowCount();
                // rowCount: 1 = inserted, 2 = updated, 0 = no change (duplicate exact data)
                if ($affected === 0) {
                    $contactsSkipped++;
                } else {
                    $contactsUpserted++;
                }
            }

            $pdo->commit();

            $results = [
                'outlets_created'   => $outletsCreated,
                'contacts_upserted' => $contactsUpserted,
                'contacts_skipped'  => $contactsSkipped,
                'total_parsed'      => count($unique),
            ];

        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
            $step = 'upload';
        }
    }
}

// ── STEP 2: Parse + Preview ──────────────────────────────────────────────────
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xlsx_file'])) {
    $step = 'upload';

    if ($_FILES['xlsx_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error (code ' . $_FILES['xlsx_file']['error'] . ').';
    } elseif (!preg_match('/\.xlsx$/i', $_FILES['xlsx_file']['name'])) {
        $errors[] = 'Only .xlsx files are accepted.';
    } else {
        try {
            $tmpPath = $_FILES['xlsx_file']['tmp_name'];
            $allSheets = readXlsxSheets($tmpPath);

            $allContacts = [];
            foreach ($allSheets as $sheetName => $rows) {
                if (in_array($sheetName, SKIP_SHEETS, true)) continue;
                $parsed = parseMediaSheet($sheetName, $rows);
                $allContacts = array_merge($allContacts, $parsed);
            }

            // Deduplicate by email before preview
            $seen    = [];
            $deduped = [];
            foreach ($allContacts as $c) {
                $key = strtolower($c['email']);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $deduped[] = $c;
                }
            }

            if (empty($deduped)) {
                $errors[] = 'No valid media contacts found in the uploaded file. Check that the sheets have MEDIA / REP / EMAIL / PHONE headers and valid email addresses.';
            } else {
                $preview = $deduped;
                $step    = 'preview';
            }

        } catch (Throwable $e) {
            $errors[] = 'Failed to parse XLSX: ' . $e->getMessage();
        }
    }
}

// ── Build preview summary (grouped by outlet for display) ───────────────────
$previewSummary = [];
if ($step === 'preview') {
    foreach ($preview as $c) {
        $key = $c['market'] . '||' . $c['outlet'];
        if (!isset($previewSummary[$key])) {
            $previewSummary[$key] = [
                'outlet'   => $c['outlet'],
                'market'   => $c['market'],
                'category' => $c['category'],
                'emails'   => [],
                'count'    => 0,
            ];
        }
        $previewSummary[$key]['count']++;
        if (count($previewSummary[$key]['emails']) < 3) {
            $previewSummary[$key]['emails'][] = $c['email'];
        }
    }
}

$pageTitle = 'Import Media Contacts — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-people me-2 text-primary"></i>Import Media Contacts
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/press-releases/index.php">Press Releases</a></li>
                <li class="breadcrumb-item active">Import Media Contacts</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Error:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($step === 'done'): ?>
<!-- ── Results ────────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-check-circle me-2 text-success"></i>Import Complete</h5>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 bg-light text-center py-3">
                    <div class="fs-3 fw-bold text-primary"><?= (int)$results['total_parsed'] ?></div>
                    <div class="small text-muted">Contacts Parsed</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 bg-light text-center py-3">
                    <div class="fs-3 fw-bold text-success"><?= (int)$results['outlets_created'] ?></div>
                    <div class="small text-muted">Outlets Created</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 bg-light text-center py-3">
                    <div class="fs-3 fw-bold text-info"><?= (int)$results['contacts_upserted'] ?></div>
                    <div class="small text-muted">Contacts Inserted/Updated</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 bg-light text-center py-3">
                    <div class="fs-3 fw-bold text-secondary"><?= (int)$results['contacts_skipped'] ?></div>
                    <div class="small text-muted">Skipped (no change)</div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="/press-releases/import-media-contacts.php" class="btn btn-outline-primary">
                <i class="bi bi-upload me-1"></i>Import Another File
            </a>
            <a href="/press-releases/compose.php" class="btn btn-primary">
                <i class="bi bi-send me-1"></i>Compose Press Release
            </a>
        </div>
    </div>
</div>

<?php elseif ($step === 'preview'): ?>
<!-- ── Preview ───────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-eye me-2 text-primary"></i>Preview — <?= count($preview) ?> contacts found
        </h5>
        <span class="badge bg-secondary"><?= count($previewSummary) ?> outlets</span>
    </div>
    <div class="card-body p-0" style="max-height:55vh;overflow-y:auto;">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light sticky-top">
                <tr>
                    <th>Outlet</th>
                    <th>Market</th>
                    <th>Category</th>
                    <th class="text-center">Contacts</th>
                    <th>Sample Emails</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($previewSummary as $row): ?>
            <tr>
                <td class="fw-semibold small"><?= h($row['outlet']) ?></td>
                <td class="small"><?= h($row['market']) ?></td>
                <td class="small text-muted"><?= h($row['category']) ?></td>
                <td class="text-center">
                    <span class="badge bg-primary"><?= (int)$row['count'] ?></span>
                </td>
                <td class="small text-muted">
                    <?= h(implode(', ', $row['emails'])) ?>
                    <?php if ($row['count'] > count($row['emails'])): ?>
                        <span class="text-secondary">+<?= $row['count'] - count($row['emails']) ?> more</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body d-flex gap-3 align-items-center">
        <form method="POST">
            <input type="hidden" name="confirm" value="1">
            <input type="hidden" name="payload" value="<?= h(base64_encode(json_encode($preview))) ?>">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-database-add me-1"></i>Confirm &amp; Import <?= count($preview) ?> Contacts
            </button>
        </form>
        <a href="/press-releases/import-media-contacts.php" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle me-1"></i>Cancel
        </a>
    </div>
</div>

<?php else: ?>
<!-- ── Upload form ────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm" style="max-width:600px;">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-upload me-2 text-primary"></i>Upload Media Contact List</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Upload an Excel (.xlsx) file where each sheet represents a market. Sheets named
            <strong>Press Releases</strong> and <strong>HCC-Client Advertising Contact</strong> are skipped.
            Each sheet should have columns: <strong>MEDIA, REP, EMAIL, PHONE</strong>.
        </p>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="xlsx_file" class="form-label fw-semibold">
                    Select XLSX File <span class="text-danger">*</span>
                </label>
                <input type="file" class="form-control" id="xlsx_file" name="xlsx_file"
                       accept=".xlsx" required>
                <div class="form-text">Accepted format: .xlsx only. Sheet names are used as market names.</div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-1"></i>Parse &amp; Preview
            </button>
        </form>
    </div>
    <div class="card-footer bg-light small text-muted">
        <strong>Sheet format:</strong> Row headers (MEDIA, REP, EMAIL, PHONE) anywhere in the sheet.
        Category rows start with TV, Radio, Newspaper, Print, Online, Digital, Magazine, Outdoor, Cable, or Streaming.
        Rows with no valid email are skipped. Duplicate emails are deduplicated before import.
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
