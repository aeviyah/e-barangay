<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$reference = trim((string) ($_GET['reference'] ?? ''));
$record = null;
$history = [];
$notice = '';

if ($reference !== '') {
    try {
        // Public view shows status only: no resident name, no amounts, no notes.
        $record = fetch_one(
            'SELECT id, reference_no, module_slug, title, status, updated_at FROM service_records WHERE reference_no = ? AND deleted_at IS NULL',
            [$reference]
        );
        if ($record) {
            $history = fetch_all('SELECT status, created_at FROM record_history WHERE record_id = ? ORDER BY id ASC', [(int) $record['id']]);
        }
    } catch (Throwable $error) {
        $notice = 'The tracking service is not ready yet. Please try again later.';
    }
}

render_header('Track Request', ['body_class' => 'public-page', 'public' => true]);
?>
<section class="auth-card" style="width:min(100%, 720px);">
    <p class="eyebrow">Request Tracking</p>
    <h1>Track your request</h1>
    <p>Enter the reference number you received when you filed your request.</p>
    <form class="form-grid" method="get">
        <div class="form-field full">
            <label>Reference number</label>
            <input class="form-control" name="reference" value="<?= e($reference) ?>" placeholder="Example: DOC-20261006-A10001" required>
        </div>
        <div class="form-actions full">
            <button class="btn" type="submit">Track</button>
            <a class="btn btn-ghost" href="<?= e(app_url('public/index.php')) ?>">Public Portal</a>
        </div>
    </form>
    <?php if ($notice): ?><div class="alert alert-warning"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($reference !== '' && !$record && !$notice): ?><div class="alert alert-warning">No request found for that reference number.</div><?php endif; ?>
    <?php if ($record): ?>
        <div class="panel" style="margin-top:16px"><div class="panel-body">
            <h3><?= e($record['title']) ?></h3>
            <p>Reference <strong><?= e($record['reference_no']) ?></strong></p>
            <p>Current status: <span class="<?= e(status_class($record['status'])) ?>"><?= e($record['status']) ?></span></p>
            <small>Last updated <?= e($record['updated_at']) ?></small>
            <?php if ($history): ?>
                <ul class="timeline" style="margin-top:12px">
                    <?php foreach ($history as $step): ?><li><strong><?= e($step['status']) ?></strong><small><?= e($step['created_at']) ?></small></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
