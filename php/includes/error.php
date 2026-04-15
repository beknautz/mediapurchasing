<?php
// error.php — include this to display a standardised error card
// Expected variables (set before including):
//   $errorMessage  (string) — required
//   $errorTitle    (string) — optional, defaults to 'An Error Occurred'
//   $exception     (Throwable) — optional, shows stack trace in dev mode

$errorTitle   = $errorTitle ?? 'An Error Occurred';
$errorMessage = $errorMessage ?? 'Something went wrong. Please try again.';
$devMode      = defined('APP_DEBUG') && APP_DEBUG === true;
?>
<div class="row justify-content-center mt-5">
    <div class="col-md-8 col-lg-6">
        <div class="card border-danger shadow-sm">
            <div class="card-header bg-danger text-white d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <strong><?= h($errorTitle) ?></strong>
            </div>
            <div class="card-body">
                <p class="mb-0 text-danger fw-semibold"><?= h($errorMessage) ?></p>

                <?php if ($devMode && isset($exception) && $exception instanceof Throwable): ?>
                    <hr>
                    <h6 class="text-muted">Debug Information <span class="badge bg-warning text-dark">Dev Mode</span></h6>
                    <dl class="row small">
                        <dt class="col-sm-3">Exception</dt>
                        <dd class="col-sm-9"><?= h(get_class($exception)) ?></dd>

                        <dt class="col-sm-3">Message</dt>
                        <dd class="col-sm-9"><?= h($exception->getMessage()) ?></dd>

                        <dt class="col-sm-3">File</dt>
                        <dd class="col-sm-9"><?= h($exception->getFile()) ?> (line <?= (int)$exception->getLine() ?>)</dd>
                    </dl>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                data-bs-toggle="collapse" data-bs-target="#stackTrace">
                            <i class="bi bi-terminal me-1"></i>Show Stack Trace
                        </button>
                        <div class="collapse mt-2" id="stackTrace">
                            <pre class="bg-dark text-light p-3 rounded small overflow-auto" style="max-height:400px;"><?= h($exception->getTraceAsString()) ?></pre>
                        </div>
                    </div>
                <?php elseif ($devMode && isset($errorDetail)): ?>
                    <hr>
                    <pre class="bg-dark text-light p-3 rounded small overflow-auto" style="max-height:400px;"><?= h($errorDetail) ?></pre>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-light d-flex gap-2">
                <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Go Back
                </a>
                <a href="/dashboard.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-house me-1"></i>Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
