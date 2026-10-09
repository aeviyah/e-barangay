<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$announcements = [];
$events = [];
$feedPosts = [];
$contacts = [];
$portalNotice = '';

try {
    $announcements = fetch_all(
        "SELECT * FROM service_records
         WHERE module_slug = 'announcements' AND status IN ('Ready for Release', 'Completed', 'Processing')
         ORDER BY due_date DESC, updated_at DESC
         LIMIT 6"
    );
    $events = fetch_all(
        "SELECT * FROM events
         WHERE status = 'Scheduled' AND event_date >= CURDATE()
         ORDER BY event_date ASC, event_time ASC
         LIMIT 6"
    );
    $feedPosts = fetch_all('SELECT * FROM posts ORDER BY created_at DESC, id DESC LIMIT 3');
    $contacts = fetch_all("SELECT * FROM public_contacts WHERE status = 'Active' ORDER BY id");
} catch (Throwable $error) {
    $portalNotice = 'Import database/schema.sql in phpMyAdmin to show live announcements and events.';
}

render_header('Public Portal', ['body_class' => 'public-page', 'public' => true]);
?>
<nav class="portal-nav">
    <?= brand_block('brand', 'public/index.php') ?>
    <a class="btn btn-ghost" href="<?= e(app_url('public/track.php')) ?>">Track request</a>
    <a class="btn btn-ghost" href="<?= e(app_url('public/verify.php')) ?>"><?= icon('qr') ?>Verify document</a>
    <a class="btn btn-secondary" href="<?= e(app_url('login.php')) ?>">Log in</a>
</nav>
<div class="portal-wrap">
<section class="portal-hero">
    <img class="brand-logo" src="<?= e(app_url('assets/img/logo.svg')) ?>" alt="Seal of <?= e(APP_BARANGAY) ?>">
    <span class="eyebrow">Opisyal na website ng barangay &middot; <?= e(APP_MUNICIPALITY) ?>, <?= e(APP_PROVINCE) ?></span>
    <h1>Welcome to <?= e(APP_BARANGAY) ?></h1>
    <p>Announcements, events, services, requirements and fees in one place. Private resident information is only available after login.</p>
    <?php if ($portalNotice): ?><div class="alert alert-warning"><?= e($portalNotice) ?></div><?php endif; ?>
    <div class="hero-actions">
        <a class="btn btn-secondary" href="<?= e(app_url('register.php')) ?>"><?= icon('users') ?>Register as resident</a>
        <a class="btn btn-ghost" href="<?= e(app_url('login.php')) ?>">Request a document</a>
    </div>
</section>

<section class="grid grid-3">
    <article class="module-card">
        <h3>Office Hours</h3>
        <p>Monday to Friday, 8:00 AM to 5:00 PM. Emergency concerns may be reported through the barangay contact desk.</p>
    </article>
    <article class="module-card">
        <h3>Services</h3>
        <p>Barangay clearance, residency, indigency, good moral, business clearance, assistance requests and facility reservations.</p>
    </article>
    <article class="module-card">
        <h3>Emergency Contacts</h3>
        <?php if ($contacts): ?>
            <?php foreach ($contacts as $contact): ?>
                <p><strong><?= e($contact['label']) ?>:</strong> <?= e($contact['value']) ?><?= $contact['availability'] ? ' <small>(' . e($contact['availability']) . ')</small>' : '' ?></p>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Barangay Hall: 0917-000-0000. Tanod Desk: 0917-111-1111. Email: info@barangay.test.</p>
        <?php endif; ?>
    </article>
</section>

<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>Announcements</h2></div>
        <div class="panel-body">
            <ul class="timeline">
                <?php foreach ($announcements as $item): ?>
                    <li><strong><?= e($item['title']) ?></strong><p><?= e($item['description']) ?></p><small><?= e($item['updated_at']) ?></small></li>
                <?php endforeach; ?>
                <?php if (!$announcements): ?><li>No announcements posted.</li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Events</h2></div>
        <div class="panel-body">
            <ul class="timeline">
                <?php foreach ($events as $item): ?>
                    <li><strong><?= e($item['title']) ?></strong><p><?= e($item['description']) ?></p><small><?= e(date('M j, Y', strtotime($item['event_date']))) ?><?= $item['event_time'] ? ' ' . e(date('g:i A', strtotime($item['event_time']))) : '' ?> - <?= e($item['location']) ?></small></li>
                <?php endforeach; ?>
                <?php if (!$events): ?><li>No events scheduled.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>About <?= e(APP_BARANGAY) ?></h2></div>
    <div class="panel-body">
        <p><?= e(APP_BARANGAY) ?> is in <?= e(APP_MUNICIPALITY) ?>, <?= e(APP_PROVINCE) ?>. The barangay seal shows <strong>niyog</strong> (coconut palms), the <strong>riles ng tren</strong> (the railway that runs through the community) and the <strong>gabi at palay</strong> that grow here.</p>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Public Requirements & Fees</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Service</th><th>Typical Requirements</th><th>Fee</th></tr></thead>
            <tbody>
                <tr><td>Barangay Clearance</td><td>Valid ID, resident verification, purpose</td><td><?= e(peso(document_fee('Barangay Clearance'))) ?></td></tr>
                <tr><td>Certificate of Residency</td><td>Valid ID, proof of address</td><td><?= e(peso(document_fee('Certificate of Residency'))) ?></td></tr>
                <tr><td>Certificate of Indigency</td><td>Valid ID, interview or eligibility review</td><td>Free / as assessed</td></tr>
                <tr><td>Business Clearance</td><td>Application, location, business details, assessment</td><td>As assessed</td></tr>
            </tbody>
        </table>
    </div>
</section>
</div>
<?php render_footer(); ?>

