<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('residents');

$puroks = fetch_all('SELECT id, name FROM puroks ORDER BY name');
$householdList = fetch_all('SELECT id, household_no, address FROM households ORDER BY id DESC LIMIT 300');
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? fetch_one('SELECT * FROM residents WHERE id = ?', [$editId]) : null;
$editHousehold = $editId ? (int) fetch_value('SELECT household_id FROM household_members WHERE resident_id = ? ORDER BY household_id LIMIT 1', [$editId]) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $action = (string) ($_POST['action'] ?? '');

    if (in_array($action, ['change_status', 'restore', 'delete', 'assign_household', 'create_household'], true)) {
        $rid = (int) ($_POST['resident_id'] ?? 0);
        $target = $rid ? fetch_one('SELECT * FROM residents WHERE id = ?', [$rid]) : null;
        $keep = (string) ($_POST['return_status'] ?? '');
        $back = 'modules/residents.php' . ($keep !== '' ? '?status=' . urlencode($keep) : '');

        if (!$target) {
            flash('danger', 'Resident not found.');
            redirect($back);
        }
        $name = resident_full_name($target);

        if ($action === 'change_status') {
            $new = (string) ($_POST['new_status'] ?? '');
            if (!in_array($new, resident_statuses(), true)) {
                flash('danger', 'Choose a valid status.');
            } else {
                run_query('UPDATE residents SET status = ?, status_notes = ?, status_changed_at = NOW(), updated_at = NOW() WHERE id = ?', [$new, trim((string) ($_POST['status_notes'] ?? '')) ?: null, $rid]);
                audit_log('Changed resident status', 'Resident', $rid, $name . ': ' . $target['status'] . ' to ' . $new);
                flash('success', $name . ' is now ' . $new . '.');
            }
        } elseif ($action === 'restore') {
            run_query("UPDATE residents SET status = 'Active', status_notes = NULL, status_changed_at = NOW(), updated_at = NOW() WHERE id = ?", [$rid]);
            audit_log('Restored resident', 'Resident', $rid, $name);
            flash('success', $name . ' restored to Active.');
        } elseif ($action === 'delete') {
            if (!is_admin()) {
                flash('danger', 'Only the administrator can permanently delete a resident. Use Change status to Archived instead.');
            } else {
                try {
                    run_query('DELETE FROM residents WHERE id = ?', [$rid]);
                    audit_log('Deleted resident', 'Resident', $rid, $name);
                    flash('success', $name . ' was permanently deleted.');
                } catch (PDOException $error) {
                    flash('danger', 'This resident has linked records (requests, payments or cases) and cannot be deleted. Change the status to Archived instead.');
                }
            }
        } elseif ($action === 'assign_household') {
            $hid = (int) ($_POST['household_id'] ?? 0);
            if (!$hid || !fetch_one('SELECT id FROM households WHERE id = ?', [$hid])) {
                flash('danger', 'Choose a household.');
            } else {
                run_query('DELETE FROM household_members WHERE resident_id = ? AND household_id <> ?', [$rid, $hid]);
                run_query(
                    'INSERT INTO household_members (household_id, resident_id, relationship, created_at) VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE relationship = VALUES(relationship)',
                    [$hid, $rid, trim((string) ($_POST['relationship'] ?? '')) ?: 'Member']
                );
                audit_log('Assigned resident to household', 'Resident', $rid, $name . ' to household #' . $hid);
                flash('success', $name . ' added to the household.');
            }
        } else { // create_household
            run_query(
                "INSERT INTO households (household_no, household_head_id, address, purok_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'Active', NOW(), NOW())",
                [generate_reference('HH'), $rid, $target['address'], $target['purok_id']]
            );
            $hid = (int) db()->lastInsertId();
            run_query('DELETE FROM household_members WHERE resident_id = ?', [$rid]);
            run_query("INSERT INTO household_members (household_id, resident_id, relationship, created_at) VALUES (?, ?, 'Household Head', NOW())", [$hid, $rid]);
            run_query('UPDATE residents SET is_head_of_family = 1, updated_at = NOW() WHERE id = ?', [$rid]);
            audit_log('Created household', 'Household', $hid, 'Head: ' . $name);
            flash('success', 'New household created with ' . $name . ' as head.');
        }
        redirect($back);
    }
    $data = [
        trim((string) ($_POST['first_name'] ?? '')),
        trim((string) ($_POST['middle_name'] ?? '')),
        trim((string) ($_POST['last_name'] ?? '')),
        trim((string) ($_POST['suffix'] ?? '')),
        trim((string) ($_POST['gender'] ?? '')),
        $_POST['birth_date'] ?: null,
        trim((string) ($_POST['civil_status'] ?? '')),
        trim((string) ($_POST['contact_no'] ?? '')),
        trim((string) ($_POST['email'] ?? '')),
        trim((string) ($_POST['address'] ?? '')),
        $_POST['purok_id'] ?: null,
        trim((string) ($_POST['occupation'] ?? '')),
        trim((string) ($_POST['education'] ?? '')),
        trim((string) ($_POST['voter_status'] ?? '')),
        trim((string) ($_POST['residency_status'] ?? 'Resident')),
        isset($_POST['is_pwd']) ? 1 : 0,
        isset($_POST['is_senior']) ? 1 : 0,
        isset($_POST['is_solo_parent']) ? 1 : 0,
        isset($_POST['is_4ps']) ? 1 : 0,
        isset($_POST['is_ofw']) ? 1 : 0,
        isset($_POST['is_other']) ? 1 : 0,
        isset($_POST['is_youth']) ? 1 : 0,
        isset($_POST['is_head_of_family']) ? 1 : 0,
        trim((string) ($_POST['pwd_id_number'] ?? '')) ?: null,
        trim((string) ($_POST['solo_parent_id_number'] ?? '')) ?: null,
        trim((string) ($_POST['special_classification'] ?? '')) ?: null,
        (string) ($_POST['status'] ?? 'Pending Verification'),
        trim((string) ($_POST['notes'] ?? '')),
    ];

    $requestedHousehold = (int) ($_POST['household_id'] ?? 0);
    if ($data[0] === '' || $data[2] === '' || $data[9] === '') {
        flash('danger', 'First name, last name, and address are required.');
    } elseif ($requestedHousehold && !fetch_one('SELECT id FROM households WHERE id = ?', [$requestedHousehold])) {
        flash('danger', 'Choose a valid household.');
    } elseif ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo = db();
        try {
            $pdo->beginTransaction();
            if ($id) {
                $data[] = $id;
                run_query(
                    'UPDATE residents SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, gender = ?, birth_date = ?, civil_status = ?, contact_no = ?, email = ?, address = ?, purok_id = ?, occupation = ?, education = ?, voter_status = ?, residency_status = ?, is_pwd = ?, is_senior = ?, is_solo_parent = ?, is_4ps = ?, is_ofw = ?, is_other = ?, is_youth = ?, is_head_of_family = ?, pwd_id_number = ?, solo_parent_id_number = ?, special_classification = ?, status = ?, notes = ?, updated_at = NOW() WHERE id = ?',
                    $data
                );
                audit_log('Updated resident', 'Resident', $id, $data[0] . ' ' . $data[2]);
            } else {
                array_unshift($data, generate_reference('RES'));
                run_query(
                    'INSERT INTO residents (resident_no, first_name, middle_name, last_name, suffix, gender, birth_date, civil_status, contact_no, email, address, purok_id, occupation, education, voter_status, residency_status, is_pwd, is_senior, is_solo_parent, is_4ps, is_ofw, is_other, is_youth, is_head_of_family, pwd_id_number, solo_parent_id_number, special_classification, status, notes, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    $data
                );
                $id = (int) $pdo->lastInsertId();
                audit_log('Created resident', 'Resident', $id, $data[1] . ' ' . $data[3]);
            }

            if ($requestedHousehold) {
                $relationship = !empty($data[22]) ? 'Household Head' : (trim((string) ($_POST['relationship'] ?? '')) ?: 'Member');
                run_query('DELETE FROM household_members WHERE resident_id = ? AND household_id <> ?', [$id, $requestedHousehold]);
                run_query('INSERT INTO household_members (household_id, resident_id, relationship, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE relationship = VALUES(relationship)', [$requestedHousehold, $id, $relationship]);
                if (!empty($data[22])) {
                    run_query('UPDATE households SET household_head_id = ?, updated_at = NOW() WHERE id = ?', [$id, $requestedHousehold]);
                }
            } else {
                run_query('DELETE FROM household_members WHERE resident_id = ?', [$id]);
            }
            $pdo->commit();
            flash('success', 'Classification & Household information successfully saved to Admin and Official databases.');
            redirect('modules/residents.php');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Resident, classification, and household changes were not saved. Check the database migration and try again.');
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$params = [];
$where = 'WHERE 1=1';
if ($search !== '') {
    $where .= ' AND (resident_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR contact_no LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($status !== '') {
    $where .= ' AND residents.status = ?';
    $params[] = $status;
}

$residents = fetch_all(
    'SELECT residents.*, puroks.name AS purok_name
     FROM residents
     LEFT JOIN puroks ON puroks.id = residents.purok_id
     ' . $where . '
     ORDER BY residents.updated_at DESC
     LIMIT 120',
    $params
);

$counts = ['' => 0];
foreach (fetch_all('SELECT status, COUNT(*) AS total FROM residents GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['total'];
    $counts[''] += (int) $row['total'];
}

render_header('Residents');
page_header('Resident Management', 'Registration, verification, classifications, profile information and archive status.', export_buttons('residents', $status !== '' ? ['status' => $status] : []));
?>
<section class="panel">
    <div class="panel-header"><h2><?= $edit ? 'Edit Resident' : 'Register Resident' ?></h2></div>
    <div class="panel-body">
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-field"><label>First name *</label><input class="form-control" name="first_name" value="<?= e($edit['first_name'] ?? '') ?>" required></div>
            <div class="form-field"><label>Last name *</label><input class="form-control" name="last_name" value="<?= e($edit['last_name'] ?? '') ?>" required></div>
            <div class="form-field"><label>Middle name</label><input class="form-control" name="middle_name" value="<?= e($edit['middle_name'] ?? '') ?>"></div>
            <div class="form-field"><label>Suffix</label><input class="form-control" name="suffix" value="<?= e($edit['suffix'] ?? '') ?>"></div>
            <div class="form-field"><label>Gender</label><select class="form-control" name="gender"><option value="">Select</option><option <?= selected($edit['gender'] ?? '', 'Female') ?>>Female</option><option <?= selected($edit['gender'] ?? '', 'Male') ?>>Male</option></select></div>
            <div class="form-field"><label>Birth date</label><input class="form-control" type="date" name="birth_date" value="<?= e($edit['birth_date'] ?? '') ?>"></div>
            <div class="form-field"><label>Civil status</label><input class="form-control" name="civil_status" value="<?= e($edit['civil_status'] ?? '') ?>"></div>
            <div class="form-field"><label>Contact no.</label><input class="form-control" name="contact_no" value="<?= e($edit['contact_no'] ?? '') ?>"></div>
            <div class="form-field"><label>Email</label><input class="form-control" type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
            <div class="form-field"><label>Purok / Sitio</label><select class="form-control" name="purok_id"><option value="">Select</option><?php foreach ($puroks as $purok): ?><option value="<?= (int) $purok['id'] ?>" <?= selected($edit['purok_id'] ?? '', $purok['id']) ?>><?= e($purok['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-field full"><label>Address *</label><textarea class="form-control" name="address" rows="2" required><?= e($edit['address'] ?? '') ?></textarea></div>
            <div class="form-field"><label>Occupation</label><input class="form-control" name="occupation" value="<?= e($edit['occupation'] ?? '') ?>"></div>
            <div class="form-field"><label>Education</label><input class="form-control" name="education" value="<?= e($edit['education'] ?? '') ?>"></div>
            <div class="form-field"><label>Voter status</label><input class="form-control" name="voter_status" value="<?= e($edit['voter_status'] ?? '') ?>"></div>
            <div class="form-field"><label>Residency status</label><input class="form-control" name="residency_status" value="<?= e($edit['residency_status'] ?? 'Resident') ?>"></div>
            <div class="form-field"><label>Record status</label><select class="form-control" name="status"><?php foreach (resident_statuses() as $value): ?><option <?= selected($edit['status'] ?? 'Pending Verification', $value) ?>><?= e($value) ?></option><?php endforeach; ?></select></div>
            <div class="form-field full"><label>Classifications</label><div class="split-actions"><label><input type="checkbox" name="is_4ps" <?= checked((bool) ($edit['is_4ps'] ?? false)) ?>> 4Ps</label><label><input type="checkbox" name="is_pwd" <?= checked((bool) ($edit['is_pwd'] ?? false)) ?>> PWD</label><label><input type="checkbox" name="is_senior" <?= checked((bool) ($edit['is_senior'] ?? false)) ?>> Senior Citizen</label><label><input type="checkbox" name="is_ofw" <?= checked((bool) ($edit['is_ofw'] ?? false)) ?>> OFW</label><label><input type="checkbox" name="is_other" <?= checked((bool) ($edit['is_other'] ?? false)) ?>> Other</label><label><input type="checkbox" name="is_solo_parent" <?= checked((bool) ($edit['is_solo_parent'] ?? false)) ?>> Solo Parent</label><label><input type="checkbox" name="is_youth" <?= checked((bool) ($edit['is_youth'] ?? false)) ?>> Youth</label><label><input type="checkbox" name="is_head_of_family" <?= checked((bool) ($edit['is_head_of_family'] ?? false)) ?>> Head of Family</label></div></div>
            <div class="form-field"><label>Household</label><select class="form-control" name="household_id"><option value="">No household assigned</option><?php foreach ($householdList as $household): ?><option value="<?= (int) $household['id'] ?>" <?= selected($editHousehold, $household['id']) ?>><?= e($household['household_no'] . ' - ' . substr((string) $household['address'], 0, 55)) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label>Relationship to household head</label><input class="form-control" name="relationship" value="<?= e($editHousehold ? (string) fetch_value('SELECT relationship FROM household_members WHERE resident_id = ? AND household_id = ?', [$editId, $editHousehold]) : '') ?>" placeholder="Member, Spouse, Child..."></div>
            <div class="form-field"><label>PWD ID no.</label><input class="form-control" name="pwd_id_number" value="<?= e($edit['pwd_id_number'] ?? '') ?>"></div>
            <div class="form-field"><label>Solo Parent ID no.</label><input class="form-control" name="solo_parent_id_number" value="<?= e($edit['solo_parent_id_number'] ?? '') ?>"></div>
            <div class="form-field"><label>Other classification</label><input class="form-control" name="special_classification" placeholder="e.g. Indigenous, OFW family" value="<?= e($edit['special_classification'] ?? '') ?>"></div>
            <div class="form-field full"><label>Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($edit['notes'] ?? '') ?></textarea></div>
            <div class="form-actions full"><button class="btn" type="submit"><?= $edit ? 'Update Resident' : 'Add Resident' ?></button><?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(app_url('modules/residents.php')) ?>">Cancel</a><?php endif; ?></div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Resident List</h2></div>
    <div class="split-actions" style="padding:12px 16px 0;flex-wrap:wrap;gap:8px">
        <a class="btn <?= $status === '' ? '' : 'btn-ghost' ?>" href="<?= e(app_url('modules/residents.php')) ?>">All (<?= (int) $counts[''] ?>)</a>
        <?php foreach (resident_statuses() as $value): ?>
            <a class="btn <?= $status === $value ? '' : 'btn-ghost' ?>" href="<?= e(app_url('modules/residents.php?status=' . urlencode($value))) ?>"><?= e($value) ?> (<?= (int) ($counts[$value] ?? 0) ?>)</a>
        <?php endforeach; ?>
    </div>
    <form class="filter-bar" method="get">
        <input name="q" placeholder="Search resident no., name, email or contact" value="<?= e($search) ?>">
        <select name="status" data-autosubmit><option value="">All statuses</option><?php foreach (resident_statuses() as $value): ?><option value="<?= e($value) ?>" <?= selected($status, $value) ?>><?= e($value) ?></option><?php endforeach; ?></select>
        <button class="btn" type="submit">Filter</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Resident</th><th>Contact</th><th>Purok</th><th>Classifications</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($residents as $resident): ?>
                <tr>
                    <td><span class="record-title"><strong><?= e(resident_full_name($resident)) ?></strong><small><?= e($resident['resident_no']) ?></small></span></td>
                    <td><?= e($resident['contact_no'] ?: 'No contact') ?><br><small><?= e($resident['email']) ?></small></td>
                    <td><?= e($resident['purok_name'] ?: 'Unassigned') ?></td>
                    <td><?= e(trim(($resident['is_4ps'] ? '4Ps ' : '') . ($resident['is_pwd'] ? 'PWD ' : '') . ($resident['is_senior'] ? 'Senior ' : '') . ($resident['is_ofw'] ? 'OFW ' : '') . ($resident['is_other'] ? 'Other ' : '') . ($resident['is_solo_parent'] ? 'Solo Parent ' : '') . ($resident['is_youth'] ? 'Youth ' : '') . ($resident['is_head_of_family'] ? 'Head of Family' : '')) ?: 'None') ?></td>
                    <td><span class="<?= e(status_class($resident['status'])) ?>"><?= e($resident['status']) ?></span></td>
                    <td>
                        <a class="btn btn-ghost" href="<?= e(app_url('modules/residents.php?edit=' . (int) $resident['id'])) ?>">Edit</a>
                        <?php if (in_array($resident['status'], ['Inactive', 'Transferred', 'Moved Out', 'Abroad', 'Deceased', 'Archived'], true)): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Ibalik sa Active?');"><input type="hidden" name="action" value="restore"><input type="hidden" name="resident_id" value="<?= (int) $resident['id'] ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>"><button class="btn btn-ghost" type="submit">Restore</button></form>
                        <?php endif; ?>
                        <details style="margin-top:6px">
                            <summary style="cursor:pointer">More</summary>
                            <form method="post" class="form-grid" style="margin-top:8px;min-width:240px">
                                <input type="hidden" name="action" value="change_status"><input type="hidden" name="resident_id" value="<?= (int) $resident['id'] ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>">
                                <div class="form-field full"><label>Change status</label><select class="form-control" name="new_status"><?php foreach (resident_statuses() as $value): ?><option <?= selected($resident['status'], $value) ?>><?= e($value) ?></option><?php endforeach; ?></select></div>
                                <div class="form-field full"><label>Notes / reason</label><textarea class="form-control" name="status_notes" rows="2"><?= e($resident['status_notes'] ?? '') ?></textarea></div>
                                <div class="form-actions full"><button class="btn" type="submit">Save status</button></div>
                            </form>
                            <form method="post" class="form-grid" style="margin-top:8px;min-width:240px">
                                <input type="hidden" name="action" value="assign_household"><input type="hidden" name="resident_id" value="<?= (int) $resident['id'] ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>">
                                <div class="form-field full"><label>Add to household</label><select class="form-control" name="household_id"><option value="">Select household</option><?php foreach ($householdList as $hh): ?><option value="<?= (int) $hh['id'] ?>"><?= e($hh['household_no'] . ' - ' . substr((string) $hh['address'], 0, 40)) ?></option><?php endforeach; ?></select></div>
                                <div class="form-field full"><label>Relationship</label><input class="form-control" name="relationship" placeholder="Spouse, Child, Parent..."></div>
                                <div class="form-actions full"><button class="btn" type="submit">Assign</button></div>
                            </form>
                            <form method="post" style="margin-top:8px" onsubmit="return confirm('Gumawa ng bagong household na head ang residenteng ito?');"><input type="hidden" name="action" value="create_household"><input type="hidden" name="resident_id" value="<?= (int) $resident['id'] ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>"><button class="btn btn-ghost" type="submit">Make head of new household</button></form>
                            <?php if (is_admin()): ?>
                                <form method="post" style="margin-top:8px" onsubmit="return confirm('PERMANENTENG burahin ang residenteng ito? Hindi na ito mababawi.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="resident_id" value="<?= (int) $resident['id'] ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>"><button class="btn btn-ghost" type="submit" style="color:#a11a22">Delete permanently</button></form>
                            <?php endif; ?>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$residents): ?><tr><td colspan="6">No residents found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
