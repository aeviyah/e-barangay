<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('search');

$q = trim((string) ($_GET['q'] ?? ''));
$results = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    if (can_access_module('residents')) {
        $results['Residents'] = fetch_all(
            'SELECT id, resident_no AS reference, CONCAT(first_name, " ", last_name) AS title, status, updated_at
             FROM residents
             WHERE resident_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
             ORDER BY updated_at DESC LIMIT 20',
            [$like, $like, $like, $like]
        );
    }

    $recordSql = 'SELECT id, module_slug, reference_no AS reference, title, status, updated_at
                  FROM service_records
                  WHERE (reference_no LIKE ? OR title LIKE ? OR requester_name LIKE ? OR category LIKE ?)';
    $params = [$like, $like, $like, $like];

    if (role_slug() === 'resident') {
        $recordSql .= ' AND (resident_id = ? OR (module_slug = "announcements" AND status IN ("Ready for Release", "Completed", "Processing")))';
        $params[] = current_resident_id();
    }

    $recordSql .= ' ORDER BY updated_at DESC LIMIT 40';
    $records = fetch_all($recordSql, $params);

    foreach ($records as $record) {
        if (can_access_module($record['module_slug'])) {
            $results[module_meta($record['module_slug'])['label'] ?? 'Records'][] = $record;
        }
    }

    if (can_access_module('events')) {
        $results['Events & Activities'] = fetch_all(
            "SELECT id, DATE_FORMAT(event_date, '%Y-%m-%d') AS reference, title, status, updated_at
             FROM events WHERE title LIKE ? OR location LIKE ? OR description LIKE ?
             ORDER BY event_date DESC LIMIT 20",
            [$like, $like, $like]
        );
    }

    if (can_access_module('facilities') && role_slug() !== 'resident') {
        $results['Facility Reservations'] = fetch_all(
            "SELECT facility_reservations.id, facility_reservations.reference_no AS reference,
                    CONCAT(facilities.name, ' - ', facility_reservations.applicant_name) AS title,
                    facility_reservations.status, facility_reservations.updated_at
             FROM facility_reservations JOIN facilities ON facilities.id = facility_reservations.facility_id
             WHERE facility_reservations.reference_no LIKE ? OR facility_reservations.applicant_name LIKE ? OR facilities.name LIKE ? OR facility_reservations.purpose LIKE ?
             ORDER BY facility_reservations.reservation_date DESC LIMIT 20",
            [$like, $like, $like, $like]
        );
    }

    if (can_access_module('treasury')) {
        $results['Payments'] = fetch_all(
            'SELECT id, receipt_no AS reference, purpose AS title, payment_status AS status, paid_at AS updated_at
             FROM payments
             WHERE receipt_no LIKE ? OR transaction_no LIKE ? OR payer_name LIKE ? OR purpose LIKE ?
             ORDER BY paid_at DESC LIMIT 20',
            [$like, $like, $like, $like]
        );
    }
}

render_header('Global Search');
page_header('Global Search', 'Permission-aware search across residents, households, requests, payments and authorized case records.');
?>
<section class="panel">
    <form class="filter-bar" method="get">
        <input name="q" placeholder="Enter resident, household, request, payment or case keyword" value="<?= e($q) ?>">
        <span></span>
        <button class="btn" type="submit">Search</button>
    </form>
    <div class="panel-body">
        <?php if ($q === ''): ?>
            <p class="muted">Start with a name, reference number, OR number, title, or keyword.</p>
        <?php elseif (!$results): ?>
            <p>No results found for <?= e($q) ?>.</p>
        <?php else: ?>
            <?php foreach ($results as $group => $items): ?>
                <h2><?= e($group) ?></h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Reference</th><th>Title</th><th>Status</th><th>Updated</th><th>Open</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $url = '#';
                            if ($group === 'Residents') {
                                $url = app_url('modules/residents.php?edit=' . (int) $item['id']);
                            } elseif ($group === 'Events & Activities') {
                                $url = app_url('modules/events.php?edit=' . (int) $item['id']);
                            } elseif ($group === 'Facility Reservations') {
                                $url = app_url('modules/facilities.php');
                            } elseif ($group === 'Payments') {
                                $url = app_url('modules/payments.php?receipt=' . (int) $item['id']);
                            } else {
                                $url = app_url('modules/records.php?module=' . urlencode($item['module_slug']) . '&id=' . (int) $item['id']);
                            }
                            ?>
                            <tr>
                                <td><?= e($item['reference']) ?></td>
                                <td><?= e($item['title']) ?></td>
                                <td><span class="<?= e(status_class($item['status'])) ?>"><?= e($item['status']) ?></span></td>
                                <td><?= e($item['updated_at']) ?></td>
                                <td><a class="btn btn-ghost" href="<?= e($url) ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>

