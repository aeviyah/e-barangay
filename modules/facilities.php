<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('facilities');

$user = current_user();
$isResident = role_slug() === 'resident';
$canManage = !$isResident;
$activeStatuses = "('Pending', 'Approved')";

function normalize_time(string $value): ?string
{
    return preg_match('/^\d{2}:\d{2}$/', $value) ? $value . ':00' : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $action = (string) ($_POST['action'] ?? '');

    // ---- Officials: add / delete a facility -------------------------------------------------
    if ($action === 'add_facility' && $canManage) {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('danger', 'Facility name is required.');
        } elseif (fetch_value('SELECT COUNT(*) FROM facilities WHERE name = ?', [$name])) {
            flash('danger', 'A facility with that name already exists.');
        } else {
            run_query('INSERT INTO facilities (name, description, capacity, fee, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [
                $name,
                trim((string) ($_POST['description'] ?? '')),
                max(0, (int) ($_POST['capacity'] ?? 0)),
                max(0, (float) ($_POST['fee'] ?? 0)),
                'Active',
            ]);
            audit_log('Added facility', 'Facilities', (int) db()->lastInsertId(), $name);
            flash('success', 'Facility added.');
        }
        redirect('modules/facilities.php');
    }

    if ($action === 'delete_facility' && $canManage) {
        $facilityId = (int) ($_POST['facility_id'] ?? 0);
        $active = (int) fetch_value("SELECT COUNT(*) FROM facility_reservations WHERE facility_id = ? AND status IN $activeStatuses", [$facilityId]);
        if ($active > 0) {
            flash('danger', "Cannot delete: this facility still has $active active reservation(s).");
        } else {
            run_query('DELETE FROM facilities WHERE id = ?', [$facilityId]);
            audit_log('Deleted facility', 'Facilities', $facilityId, '');
            flash('success', 'Facility deleted.');
        }
        redirect('modules/facilities.php');
    }

    // ---- Anyone: request a reservation ------------------------------------------------------
    if ($action === 'reserve') {
        $facilityId = (int) ($_POST['facility_id'] ?? 0);
        $facility = fetch_one("SELECT * FROM facilities WHERE id = ? AND status = 'Active'", [$facilityId]);
        $date = (string) ($_POST['reservation_date'] ?? '');
        $start = normalize_time((string) ($_POST['start_time'] ?? ''));
        $end = normalize_time((string) ($_POST['end_time'] ?? ''));
        $purpose = trim((string) ($_POST['purpose'] ?? ''));
        $residentId = $isResident ? current_resident_id() : (($_POST['resident_id'] ?? '') !== '' ? (int) $_POST['resident_id'] : null);

        if (!$facility || $purpose === '' || !$start || !$end || !strtotime($date)) {
            flash('danger', 'Choose a facility, a date, a start and end time, and enter the purpose.');
        } elseif ($date < date('Y-m-d')) {
            flash('danger', 'The reservation date cannot be in the past.');
        } elseif ($end <= $start) {
            flash('danger', 'The end time must be later than the start time.');
        } elseif ($isResident && !$residentId) {
            flash('danger', 'Your account is not linked to a resident profile yet. Complete your profile first.');
        } elseif ((int) fetch_value(
            "SELECT COUNT(*) FROM facility_reservations
             WHERE facility_id = ? AND reservation_date = ? AND status IN $activeStatuses AND start_time < ? AND end_time > ?",
            [$facilityId, $date, $end, $start]
        ) > 0) {
            flash('danger', 'Double booking blocked: that facility is already reserved for an overlapping time on the selected date.');
        } else {
            $applicant = $user['full_name'];
            if (!$isResident && $residentId) {
                $row = fetch_one('SELECT first_name, last_name FROM residents WHERE id = ?', [$residentId]);
                $applicant = $row ? trim($row['first_name'] . ' ' . $row['last_name']) : $applicant;
            }

            run_query(
                'INSERT INTO facility_reservations (reference_no, facility_id, resident_id, user_id, applicant_name, purpose, reservation_date, start_time, end_time, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [generate_reference('FAC'), $facilityId, $residentId, (int) $user['id'], $applicant, $purpose, $date, $start, $end, 'Pending']
            );
            audit_log('Requested reservation', 'Facilities', (int) db()->lastInsertId(), $facility['name'] . ' on ' . $date);
            flash('success', 'Reservation request submitted. It is now pending approval.' . ((float) $facility['fee'] > 0 ? ' Facility fee: ' . peso($facility['fee']) . '.' : ''));
        }
        redirect('modules/facilities.php');
    }

    // ---- Officials: approve / reject / complete; owner: cancel ------------------------------
    if (in_array($action, ['approve', 'reject', 'complete', 'cancel'], true)) {
        $reservation = fetch_one('SELECT * FROM facility_reservations WHERE id = ?', [(int) ($_POST['reservation_id'] ?? 0)]);
        $isOwner = $reservation && (int) $reservation['user_id'] === (int) $user['id'];
        $allowed = false;
        $newStatus = '';

        if ($reservation) {
            if ($canManage && $action === 'approve' && $reservation['status'] === 'Pending') { $allowed = true; $newStatus = 'Approved'; }
            if ($canManage && $action === 'reject' && $reservation['status'] === 'Pending') { $allowed = true; $newStatus = 'Rejected'; }
            if ($canManage && $action === 'complete' && $reservation['status'] === 'Approved') { $allowed = true; $newStatus = 'Completed'; }
            if ($action === 'cancel' && ($canManage || $isOwner) && in_array($reservation['status'], ['Pending', 'Approved'], true)) { $allowed = true; $newStatus = 'Cancelled'; }
        }

        if ($allowed) {
            run_query('UPDATE facility_reservations SET status = ?, updated_at = NOW() WHERE id = ?', [$newStatus, (int) $reservation['id']]);
            audit_log('Reservation ' . strtolower($newStatus), 'Facilities', (int) $reservation['id'], $reservation['reference_no']);
            notify_user($reservation['user_id'] ? (int) $reservation['user_id'] : null, 'Reservation ' . strtolower($newStatus), $reservation['reference_no'] . ' is now ' . $newStatus . '.');
            flash('success', 'Reservation ' . strtolower($newStatus) . '.');
        } else {
            flash('danger', 'That action is not allowed for this reservation.');
        }
        redirect('modules/facilities.php');
    }
}

$facilities = fetch_all('SELECT * FROM facilities ORDER BY name');
$residents = $isResident ? [] : fetch_all("SELECT id, resident_no, first_name, last_name FROM residents WHERE status != 'Archived' ORDER BY last_name, first_name");

$reservationSql = 'SELECT facility_reservations.*, facilities.name AS facility_name, facilities.fee
                   FROM facility_reservations JOIN facilities ON facilities.id = facility_reservations.facility_id';
$reservations = $isResident
    ? fetch_all($reservationSql . ' WHERE facility_reservations.user_id = ? ORDER BY reservation_date DESC, start_time DESC', [(int) $user['id']])
    : fetch_all($reservationSql . ' ORDER BY reservation_date DESC, start_time DESC LIMIT 100');

// Booked slots (no personal details) so everyone can see availability.
$booked = fetch_all("SELECT facility_reservations.reservation_date, start_time, end_time, status, facilities.name AS facility_name
                     FROM facility_reservations JOIN facilities ON facilities.id = facility_reservations.facility_id
                     WHERE reservation_date >= CURDATE() AND status IN $activeStatuses
                     ORDER BY reservation_date, start_time LIMIT 30");

render_header('Facilities & Reservations');
page_header('Facilities & Reservations', 'Reserve barangay facilities by date and time. Overlapping bookings are blocked automatically.', export_buttons('reservations'));
?>

<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>Reserve a facility</h2></div>
        <div class="panel-body">
            <?php if (!$facilities): ?>
                <p class="muted">No facilities are available yet.</p>
            <?php else: ?>
            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="reserve">
                <div class="form-field full">
                    <label>Facility *</label>
                    <select class="form-control" name="facility_id" required>
                        <option value="">Select</option>
                        <?php foreach ($facilities as $facility): if ($facility['status'] !== 'Active') { continue; } ?>
                            <option value="<?= (int) $facility['id'] ?>"><?= e($facility['name']) ?><?= (float) $facility['fee'] > 0 ? ' - ' . e(peso($facility['fee'])) : ' - free' ?><?= $facility['capacity'] ? ' (capacity ' . (int) $facility['capacity'] . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!$isResident): ?>
                <div class="form-field full">
                    <label>On behalf of resident</label>
                    <select class="form-control" name="resident_id">
                        <option value="">Walk-in / no linked resident</option>
                        <?php foreach ($residents as $resident): ?><option value="<?= (int) $resident['id'] ?>"><?= e($resident['resident_no'] . ' - ' . $resident['last_name'] . ', ' . $resident['first_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-field"><label>Date *</label><input class="form-control" type="date" name="reservation_date" min="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="form-field"><label>Start *</label><input class="form-control" type="time" name="start_time" required></div>
                <div class="form-field"><label>End *</label><input class="form-control" type="time" name="end_time" required></div>
                <div class="form-field full"><label>Purpose *</label><input class="form-control" name="purpose" required></div>
                <div class="form-actions full"><button class="btn" type="submit">Submit request</button></div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Upcoming booked slots</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Facility</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($booked as $slot): ?>
                    <tr>
                        <td><?= e($slot['facility_name']) ?></td>
                        <td><?= e(date('M j, Y', strtotime($slot['reservation_date']))) ?></td>
                        <td><?= e(date('g:i A', strtotime($slot['start_time']))) ?> - <?= e(date('g:i A', strtotime($slot['end_time']))) ?></td>
                        <td><span class="<?= e(status_class($slot['status'])) ?>"><?= e($slot['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$booked): ?><tr><td colspan="4">Everything is open.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2><?= $isResident ? 'My reservations' : 'All reservations' ?></h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>Facility</th><?= $isResident ? '' : '<th>Applicant</th>' ?><th>When</th><th>Purpose</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($reservations as $row): ?>
                <tr>
                    <td><strong><?= e($row['reference_no']) ?></strong></td>
                    <td><?= e($row['facility_name']) ?><?= (float) $row['fee'] > 0 ? '<br><small class="muted">Fee ' . e(peso($row['fee'])) . '</small>' : '' ?></td>
                    <?php if (!$isResident): ?><td><?= e($row['applicant_name']) ?></td><?php endif; ?>
                    <td><?= e(date('M j, Y', strtotime($row['reservation_date']))) ?><br><small><?= e(date('g:i A', strtotime($row['start_time']))) ?> - <?= e(date('g:i A', strtotime($row['end_time']))) ?></small></td>
                    <td><?= e($row['purpose']) ?></td>
                    <td><span class="<?= e(status_class($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                    <td>
                        <?php
                        $buttons = [];
                        if ($canManage && $row['status'] === 'Pending') { $buttons = ['approve' => 'Approve', 'reject' => 'Reject']; }
                        if ($canManage && $row['status'] === 'Approved') { $buttons = ['complete' => 'Mark done', 'cancel' => 'Cancel']; }
                        if (!$canManage && in_array($row['status'], ['Pending', 'Approved'], true) && (int) $row['user_id'] === (int) $user['id']) { $buttons = ['cancel' => 'Cancel']; }
                        if ($canManage && $row['status'] === 'Pending') { $buttons['cancel'] = 'Cancel'; }
                        foreach ($buttons as $act => $label): ?>
                            <form method="post" style="display:inline" <?= in_array($act, ['reject', 'cancel'], true) ? 'data-confirm="Are you sure?"' : '' ?>>
                                <input type="hidden" name="action" value="<?= e($act) ?>"><input type="hidden" name="reservation_id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-ghost" type="submit"><?= e($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$reservations): ?><tr><td colspan="7">No reservations yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canManage): ?>
<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>Facilities</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Capacity</th><th>Fee</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($facilities as $facility): ?>
                    <tr>
                        <td><strong><?= e($facility['name']) ?></strong><br><small class="muted"><?= e($facility['description']) ?></small></td>
                        <td><?= (int) $facility['capacity'] ?></td>
                        <td><?= e(peso($facility['fee'])) ?></td>
                        <td>
                            <form method="post" data-confirm="Delete this facility? Its reservation history will be removed too.">
                                <input type="hidden" name="action" value="delete_facility"><input type="hidden" name="facility_id" value="<?= (int) $facility['id'] ?>">
                                <button class="btn btn-ghost" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Add a facility</h2></div>
        <div class="panel-body">
            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="add_facility">
                <div class="form-field full"><label>Name *</label><input class="form-control" name="name" required></div>
                <div class="form-field"><label>Capacity</label><input class="form-control" type="number" min="0" name="capacity" value="0"></div>
                <div class="form-field"><label>Reservation fee (PHP)</label><input class="form-control" type="number" min="0" step="0.01" name="fee" value="0"></div>
                <div class="form-field full"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                <div class="form-actions full"><button class="btn" type="submit">Add facility</button></div>
            </form>
        </div>
    </div>
</section>
<?php endif; ?>

<?php render_footer(); ?>
