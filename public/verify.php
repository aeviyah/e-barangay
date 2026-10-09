<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$reference = trim((string) ($_GET['reference'] ?? ''));
$record = null;
$verifyNotice = '';

if ($reference !== '') {
    try {
        $record = fetch_one(
            "SELECT service_records.*, residents.first_name, residents.last_name
             FROM service_records
             LEFT JOIN residents ON residents.id = service_records.resident_id
             WHERE service_records.reference_no = ? AND service_records.module_slug IN ('documents', 'permits') AND service_records.deleted_at IS NULL",
            [$reference]
        );
    } catch (Throwable $error) {
        $verifyNotice = 'Database is not ready yet. Check config/database.php and follow the database setup or migration instructions in README.md.';
    }
}

render_header('Document Verification', ['body_class' => 'public-page', 'public' => true]);
?>
<section class="auth-card" style="width:min(100%, 720px);">
    <p class="eyebrow">QR / Reference Verification</p>
    <h1>Verify Document</h1>
    <p>Enter the document or permit reference number to check its release status.</p>
    <form class="form-grid" method="get">
        <div class="form-field full">
            <label>Reference number</label>
            <input class="form-control" name="reference" value="<?= e($reference) ?>" placeholder="Example: DOC-20261006-A10001" required>
        </div>
        <div class="form-actions full">
            <button class="btn" type="submit">Verify</button>
            <a class="btn btn-ghost" href="<?= e(app_url('public/index.php')) ?>">Public Portal</a>
        </div>
    </form>
</section>

<?php if ($reference !== ''): ?>
<section class="panel" style="width:min(100%, 720px); margin-top:18px;">
    <div class="panel-header"><h2>Verification Result</h2></div>
    <div class="panel-body">
        <?php if ($record): ?>
            <div class="grid grid-2">
                <p><strong>Reference</strong><br><?= e($record['reference_no']) ?></p>
                <p><strong>Status</strong><br><span class="<?= e(status_class($record['status'])) ?>"><?= e($record['status']) ?></span></p>
                <p><strong>Document</strong><br><?= e($record['title']) ?></p>
                <p><strong>Resident</strong><br><?= e(trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')) ?: 'Not linked') ?></p>
                <p><strong>Updated</strong><br><?= e($record['updated_at']) ?></p>
            </div>
            <p class="muted">A document is normally valid for release/download only when marked Ready for Release or Completed by authorized barangay personnel.</p>
        <?php elseif ($verifyNotice): ?>
            <div class="alert alert-warning"><?= e($verifyNotice) ?></div>
        <?php else: ?>
            <p>No matching public document or permit was found.</p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
<?php render_footer(); ?>
