<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$user = current_user();
$residentFilter = role_slug() === 'resident' ? ' AND resident_id = ' . (int) current_resident_id() : '';

$stats = [
    'Residents' => (int) fetch_value('SELECT COUNT(*) FROM residents'),
    'Households' => (int) fetch_value('SELECT COUNT(*) FROM households'),
    'Pending Requests' => (int) fetch_value("SELECT COUNT(*) FROM service_records WHERE service_records.status IN ('Pending', 'Under Review', 'For Payment', 'For Approval', 'Processing')" . $residentFilter),
    'Open Cases' => (int) fetch_value("SELECT COUNT(*) FROM service_records WHERE module_slug IN ('complaints', 'katarungang', 'protection') AND service_records.status NOT IN ('Completed', 'Rejected', 'Cancelled', 'Archived')" . $residentFilter),
    'Today Collections' => (float) fetch_value('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(paid_at) = CURDATE()'),
    'Active Projects' => (int) fetch_value("SELECT COUNT(*) FROM service_records WHERE module_slug = 'projects' AND service_records.status NOT IN ('Completed', 'Archived', 'Cancelled')"),
];

$queueSql = "SELECT service_records.*, residents.first_name, residents.last_name
             FROM service_records
             LEFT JOIN residents ON residents.id = service_records.resident_id
             WHERE service_records.status IN ('Pending', 'Under Review', 'For Payment', 'For Approval', 'Processing', 'Ready for Release')";
$queueParams = [];
if (role_slug() === 'resident') {
    $queueSql .= ' AND service_records.resident_id = ?';
    $queueParams[] = current_resident_id();
}
$queueSql .= ' ORDER BY service_records.updated_at DESC LIMIT 8';
$queues = fetch_all($queueSql, $queueParams);

$notifications = fetch_all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6', [(int) $user['id']]);
$recentAudit = fetch_all('SELECT audit_logs.*, users.full_name FROM audit_logs LEFT JOIN users ON users.id = audit_logs.user_id ORDER BY audit_logs.created_at DESC LIMIT 8');

$isStaff = role_slug() !== 'resident';
$statusCounts = $isStaff ? fetch_all("SELECT service_records.status, COUNT(*) AS total FROM service_records GROUP BY service_records.status ORDER BY total DESC") : [];
$maxStatus = max(1, ...array_map(fn ($r) => (int) $r['total'], $statusCounts ?: [['total' => 1]]));
$purokCounts = $isStaff ? fetch_all("SELECT COALESCE(puroks.name, 'Unassigned') AS name, COUNT(residents.id) AS total FROM residents LEFT JOIN puroks ON puroks.id = residents.purok_id GROUP BY puroks.name ORDER BY total DESC") : [];
$maxPurok = max(1, ...array_map(fn ($r) => (int) $r['total'], $purokCounts ?: [['total' => 1]]));
$upcoming = fetch_all("(SELECT title, event_date AS due_date, 'events' AS module_slug FROM events WHERE event_date >= CURDATE() AND status = 'Scheduled')
                      UNION ALL
                      (SELECT CONCAT(facilities.name, ' reservation') AS title, reservation_date AS due_date, 'facilities' AS module_slug FROM facility_reservations JOIN facilities ON facilities.id = facility_reservations.facility_id WHERE reservation_date >= CURDATE() AND facility_reservations.status = 'Approved')
                      UNION ALL
                      (SELECT title, due_date, module_slug FROM service_records WHERE module_slug = 'assembly_meetings' AND due_date >= CURDATE() AND status NOT IN ('Cancelled','Archived','Rejected'))
                      ORDER BY due_date ASC LIMIT 5");
$feedPosts = fetch_all('SELECT * FROM posts ORDER BY created_at DESC, id DESC LIMIT 5');
$announcements = fetch_all("SELECT id, title, description, updated_at FROM service_records WHERE module_slug = 'announcements' AND status IN ('Ready for Release', 'Completed', 'Processing') ORDER BY updated_at DESC LIMIT 4");
$myProfile = (!$isStaff && current_resident_id())
    ? fetch_one('SELECT residents.*, puroks.name AS purok_name FROM residents LEFT JOIN puroks ON puroks.id = residents.purok_id WHERE residents.id = ?', [current_resident_id()])
    : null;
$firstName = explode(' ', trim($user['full_name']))[0];
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Magandang umaga' : ($hour < 18 ? 'Magandang hapon' : 'Magandang gabi');

render_header('Dashboard');
?>
<section class="hero">
    <span class="eyebrow"><?= e(APP_BARANGAY) ?> - <?= e($user['role_name']) ?></span>
    <h1><?= e($greeting) ?>, <?= e($firstName) ?>.</h1>
    <p><?= $isStaff ? 'Here is what needs attention in the barangay today: pending requests, open cases, collections and upcoming activities.' : 'Request documents, follow the status of your applications and read the latest barangay announcements.' ?></p>
    <span class="hero-date"><?= icon('calendar') ?>Ngayon ay <?= e(date('F j, Y')) ?></span>
    <div class="hero-actions">
        <?php if (can_access_module('documents')): ?><a class="btn btn-secondary" href="<?= e(app_url('modules/records.php?module=documents')) ?>"><?= icon('file') ?><?= $isStaff ? 'Process documents' : 'Request a document' ?></a><?php endif; ?>
        <a class="btn btn-ghost" href="#notifications"><?= icon('bell') ?>Notifications</a>
    </div>
</section>

<?php if ($isStaff): ?>
<section class="grid grid-3">
    <?php $metricIcons = ['users', 'home', 'file', 'alert', 'receipt', 'trending']; $i = 0; foreach ($stats as $label => $value): ?>
        <article class="metric-card">
            <span class="metric-icon"><?= icon($metricIcons[$i++ % 6]) ?></span>
            <span><?= e($label) ?></span>
            <strong><?= $label === 'Today Collections' ? e(peso($value)) : e((string) $value) ?></strong>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><h2>Quick actions</h2><span class="muted">Only actions allowed for your role are shown.</span></div>
    <div class="panel-body quick-grid">
        <?php foreach ([
            ['residents', 'modules/residents.php', 'users', 'Register resident'],
            ['documents', 'modules/records.php?module=documents', 'file', $isStaff ? 'Process document' : 'Request document'],
            ['services', 'modules/records.php?module=services', 'heart', $isStaff ? 'Review services' : 'Request a service'],
            ['treasury', 'modules/payments.php', 'receipt', 'Record payment'],
            ['complaints', 'modules/records.php?module=complaints', 'alert', $isStaff ? 'File / review case' : 'File a complaint'],
            ['announcements', 'modules/records.php?module=announcements', 'megaphone', $isStaff ? 'Create announcement' : 'Announcements'],
            ['facilities', 'modules/facilities.php', 'building', 'Reserve a facility'],
            ['events', 'modules/events.php', 'calendar', $isStaff ? 'Post an event' : 'See events'],
            ['feed', 'modules/feed.php', 'megaphone', $isStaff ? 'Post to feed' : 'Community feed'],
        ] as [$slug, $href, $ico, $label]): if (!can_access_module($slug)) { continue; } ?>
            <a class="quick-tile" href="<?= e(app_url($href)) ?>"><span class="tile-icon"><?= icon($ico) ?></span><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</section>

<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2><?= $isStaff ? 'Needs attention' : 'My requests' ?></h2><a href="<?= e(app_url('modules/records.php?module=documents')) ?>">Open records</a></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Reference</th><th>Module</th><th>Status</th><th>Due</th></tr></thead>
                <tbody>
                <?php foreach ($queues as $row): ?>
                    <tr>
                        <td><a class="record-title" href="<?= e(app_url('modules/records.php?module=' . urlencode($row['module_slug']) . '&id=' . (int) $row['id'])) ?>"><strong><?= e($row['reference_no']) ?></strong><small><?= e($row['title']) ?></small></a></td>
                        <td><?= e(module_meta($row['module_slug'])['label'] ?? $row['module_slug']) ?></td>
                        <td><span class="<?= e(status_class($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                        <td><?= e($row['due_date'] ?: 'Not set') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$queues): ?><tr><td colspan="4">Nothing pending. Magaling!</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel" id="notifications">
        <div class="panel-header"><h2>Notifications</h2><span class="muted">In-app</span></div>
        <div class="panel-body">
            <ul class="timeline">
                <?php foreach ($notifications as $notice): ?>
                    <li><strong><?= e($notice['title']) ?></strong><p><?= e($notice['message']) ?></p><small><?= e($notice['created_at']) ?></small></li>
                <?php endforeach; ?>
                <?php if (!$notifications): ?><li><strong>You are all caught up</strong><p>New updates about your requests will show here.</p></li><?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Latest announcements</h2><?php if (can_access_module('announcements')): ?><a href="<?= e(app_url('modules/records.php?module=announcements')) ?>">Tingnan ang lahat</a><?php endif; ?></div>
    <div class="panel-body">
        <ul class="announce-list">
            <?php foreach ($announcements as $item): ?>
                <li><strong><?= e($item['title']) ?></strong><p><?= e($item['description']) ?></p><small class="muted"><?= e(date('M j, Y g:i A', strtotime($item['updated_at']))) ?></small></li>
            <?php endforeach; ?>
            <?php if (!$announcements): ?><li>Walang opisyal na anunsyo sa ngayon.</li><?php endif; ?>
        </ul>
    </div>
</section>

<?php if ($myProfile): $pf = fn ($label, $value) => '<div class="pf"><span>' . e($label) . '</span><b' . (($value === null || $value === '') ? ' class="muted"' : '') . '>' . e(($value === null || $value === '') ? 'Wala' : (string) $value) . '</b></div>'; ?>
<section class="panel">
    <div class="panel-header"><h2>Personal profile record</h2><a href="<?= e(app_url('modules/profile.php')) ?>">Edit profile</a></div>
    <div class="panel-body profile-record">
        <?= $pf('Pangalan', resident_full_name($myProfile)) ?>
        <?= $pf('Birthdate', $myProfile['birth_date'] ? date('F j, Y', strtotime($myProfile['birth_date'])) : null) ?>
        <?= $pf('Gender', $myProfile['gender']) ?>
        <?= $pf('Civil status', $myProfile['civil_status']) ?>
        <?= $pf('Occupation', $myProfile['occupation']) ?>
        <?= $pf('Contact no.', $myProfile['contact_no']) ?>
        <?= $pf('Email', $myProfile['email']) ?>
        <?= $pf('Purok', $myProfile['purok_name']) ?>
        <?= $pf('Address', $myProfile['address']) ?>
        <?= $pf('Record status', $myProfile['status']) ?>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header"><h2>Community feed</h2><?php if (can_access_module('feed')): ?><a href="<?= e(app_url('modules/feed.php')) ?>">Open feed</a><?php endif; ?></div>
    <div class="panel-body">
        <?php foreach ($feedPosts as $post): ?>
            <article style="margin-bottom:18px">
                <strong><?= e($post['author_name']) ?></strong> <small class="muted">- <?= e($post['author_role']) ?> - <?= e(date('M j, Y g:i A', strtotime($post['created_at']))) ?></small>
                <p><?= nl2br(e($post['content'])) ?></p>
                <?php if ($post['image_path']): ?><img src="<?= e(app_url('uploads/posts/' . rawurlencode($post['image_path']))) ?>" alt="Post photo" style="max-width:100%;max-height:340px;border-radius:12px"><?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$feedPosts): ?><p class="muted">No posts yet.</p><?php endif; ?>
    </div>
</section>

<?php if (!$isStaff): ?>
<section class="panel">
    <div class="panel-header"><h2>Upcoming events</h2><a href="<?= e(app_url('modules/events.php')) ?>">All events</a></div>
    <div class="panel-body">
        <ul class="timeline">
            <?php foreach ($upcoming as $item): if ($item['module_slug'] !== 'events') { continue; } ?>
                <li><strong><?= e($item['title']) ?></strong><small><?= e(date('M j, Y', strtotime($item['due_date']))) ?></small></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php if ($isStaff): ?>
<section class="grid grid-3">
    <div class="panel">
        <div class="panel-header"><h2>Requests by status</h2></div>
        <div class="panel-body">
            <?php foreach ($statusCounts as $row): ?>
                <div class="bar-row"><span><?= e($row['status']) ?></span><div class="bar-track"><div class="bar-fill" style="width:<?= (int) round($row['total'] / $maxStatus * 100) ?>%"></div></div><b><?= (int) $row['total'] ?></b></div>
            <?php endforeach; ?>
            <?php if (!$statusCounts): ?><p class="muted">No records yet.</p><?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Residents per Purok</h2></div>
        <div class="panel-body">
            <?php foreach ($purokCounts as $row): ?>
                <div class="bar-row"><span><?= e($row['name']) ?></span><div class="bar-track"><div class="bar-fill" style="width:<?= (int) round($row['total'] / $maxPurok * 100) ?>%"></div></div><b><?= (int) $row['total'] ?></b></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Upcoming</h2></div>
        <div class="panel-body">
            <ul class="timeline">
                <?php foreach ($upcoming as $item): ?>
                    <li><strong><?= e($item['title']) ?></strong><small><?= e(date('M j, Y', strtotime($item['due_date']))) ?> - <?= e(module_meta($item['module_slug'])['label'] ?? '') ?></small></li>
                <?php endforeach; ?>
                <?php if (!$upcoming): ?><li><strong>No upcoming events</strong></li><?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Modules</h2><span class="muted">Mapped to the approved master scope</span></div>
    <div class="panel-body grid grid-3">
        <?php foreach (app_modules() as $slug => $module): if (!can_access_module($slug)) { continue; } ?>
            <article class="module-card">
                <span class="module-icon"><?= module_icon($slug) ?></span>
                <h3><?= e($module['label']) ?></h3>
                <p><?= e($module['description']) ?></p>
                <a class="btn btn-ghost" href="<?= e(app_url(module_url($slug))) ?>">Open</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>Recent activity</h2></div>
        <div class="panel-body">
            <ul class="timeline">
                <?php foreach ($recentAudit as $activity): ?>
                    <li><strong><?= e($activity['action']) ?></strong><p><?= e($activity['details']) ?></p><small><?= e($activity['full_name'] ?? 'System') ?> - <?= e($activity['created_at']) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Future modules</h2></div>
        <div class="panel-body grid grid-2">
            <?php foreach (future_modules() as $label): ?>
                <article class="module-card is-locked">
                    <span class="locked-label"><?= icon('lock') ?>Future module</span>
                    <h3><?= e($label) ?></h3>
                    <p>Planned for a later release. Not active in this scope.</p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php render_footer(); ?>
