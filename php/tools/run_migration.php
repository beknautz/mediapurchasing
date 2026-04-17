<?php
/**
 * tools/run_migration.php
 * Applies sql/migrate_php.sql against the configured database.
 * DELETE THIS FILE after the migration succeeds.
 */
require_once __DIR__ . '/../bootstrap.php';

define('TOOL_SECRET', 'mb-migrate-2024');

$ran     = false;
$results = [];
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['secret'] ?? '') !== TOOL_SECRET) {
        $error = 'Wrong secret key.';
    } else {
        $sqlFile = __DIR__ . '/../../sql/migrate_php.sql';

        if (!file_exists($sqlFile)) {
            $error = 'Migration file not found: ' . $sqlFile;
        } else {
            try {
                $pdo = new PDO(
                    sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
                    DB_USER, DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $sql = file_get_contents($sqlFile);

                // Split on statement delimiters, skip comments and blank lines
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    fn($s) => $s !== '' && !preg_match('/^--/', $s)
                );

                foreach ($statements as $stmt) {
                    if (trim($stmt) === '') continue;
                    try {
                        $pdo->exec($stmt);
                        // Extract first meaningful line for display
                        $preview = trim(preg_replace('/\s+/', ' ', strtok($stmt, "\n")));
                        $results[] = ['ok' => true,  'sql' => substr($preview, 0, 100)];
                    } catch (PDOException $e) {
                        $results[] = ['ok' => false, 'sql' => substr(trim($stmt), 0, 100), 'err' => $e->getMessage()];
                    }
                }

                $ran = true;

            } catch (Throwable $e) {
                $error = 'Connection error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DB Migration Runner</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light py-4">
<div class="container" style="max-width:760px;">

    <div class="alert alert-danger fw-bold">
        &#9888; Delete <code>tools/run_migration.php</code> after the migration succeeds.
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white"><strong>Database Migration Runner</strong></div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Runs <code>sql/migrate_php.sql</code> — adds missing tables and columns.
                Safe to run multiple times.
            </p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (!$ran): ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Secret Key</label>
                    <input type="password" name="secret" class="form-control" style="max-width:280px;" required autofocus>
                    <div class="form-text">Default: <code>mb-migrate-2024</code></div>
                </div>
                <button type="submit" class="btn btn-dark">Run Migration</button>
            </form>
            <?php else: ?>
                <?php
                $ok  = count(array_filter($results, fn($r) => $r['ok']));
                $bad = count(array_filter($results, fn($r) => !$r['ok']));
                ?>
                <div class="alert alert-<?= $bad === 0 ? 'success' : 'warning' ?>">
                    <strong><?= $ok ?> statement(s) OK</strong><?= $bad > 0 ? ", <strong>{$bad} failed</strong>" : '' ?>.
                    <?= $bad === 0 ? 'Migration complete — delete this file now.' : 'Check failures below.' ?>
                </div>
                <table class="table table-sm table-bordered small">
                    <thead class="table-light"><tr><th style="width:60px">Status</th><th>Statement</th></tr></thead>
                    <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr class="<?= $r['ok'] ? '' : 'table-danger' ?>">
                            <td class="text-center"><?= $r['ok'] ? '✓' : '✗' ?></td>
                            <td>
                                <code><?= htmlspecialchars($r['sql'], ENT_QUOTES, 'UTF-8') ?></code>
                                <?php if (!$r['ok']): ?>
                                    <div class="text-danger mt-1"><?= htmlspecialchars($r['err'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>
