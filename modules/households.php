<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('households');

$puroks = fetch_all('SELECT id, name FROM puroks ORDER BY name');
$residents = fetch_all("SELECT id, resident_no, first_name, last_name FROM residents WHERE status != 'Archived' ORDER BY last_name, first_name");
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? fetch_one('SELECT * FROM households WHERE id = ?', [$editId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int) ($_POST['id'] ?? 0);
    $values = [
        $_POST['household_head_id'] ?: null,
        trim((string) ($_POST['address'] ?? '')),
        $_POST['purok_id'] ?: null,
        trim((string) ($_POST['classification'] ?? '')),
        trim((string) ($_POST['income_bracket'] ?? '')),
        trim((string) ($_POST['housing_type'] ?? '')),
        trim((string) ($_POST['utilities'] ?? '')),
        trim((string) ($_POST['status'] ?? 'Active')),
    ];

    if ($values[1] === '') {
        flash('danger', 'Household address is required.');
    } elseif ($id) {
        $values[] = $id;
        run_query(
            'UPDATE households SET household_head_id = ?, address = ?, purok_id = ?, classification = ?, income_bracket = ?, housing_type = ?, utilities = ?, status = ?, updated_at = NOW() WHERE id = ?',
            $values
        );
        audit_log('Updated household', 'Household', $id, 'Household profile updated.');
        flash('success', 'Household updated.');
        redirect('modules/households.php');
    } else {
        array_unshift($values, generate_reference('HH'));
        run_query(
            'INSERT INTO households (household_no, household_head_id, address, purok_id, classification, income_bracket, housing_type, utilities, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            $values
        );
        $newId = (int) db()->lastInsertId();
        if (!empty($_POST['household_head_id'])) {
            run_query(
                'INSERT IGNORE INTO household_members (household_id, resident_id, relationship, created_at) VALUES (?, ?, ?, NOW())',
                [$newId, (int) $_POST['household_head_id'], 'Household Head']
            );
        }
        audit_log('Created household', 'Household', $newId, 'Household registered.');
        flash('success', 'Household registered.');
        redirect('modules/households.php');
    }
}

$households = fetch_all(
    'SELECT households.*, puroks.name AS purok_name, residents.first_name, residents.last_name,
            (SELECT COUNT(*) FROM household_members WHERE household_members.household_id = households.id) AS member_count
     FROM households
     LEFT JOIN puroks ON puroks.id = households.purok_id
     LEFT JOIN residents ON residents.id = households.household_head_id
     ORDER BY households.updated_at DESC'
);

render_header('Households');
page_header('Household Management', 'Household head, address, family member count, classification, income bracket and utilities.', export_buttons('households'));
?>
<section class="panel">
    <div class="panel-header"><h2><?= $edit ? 'Edit Household' : 'Register Household' ?></h2></div>
    <div class="panel-body">
        <form method="post" class="form-grid">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-field">
                <label>Household head</label>
                <select class="form-control" name="household_head_id">
                    <option value="">Select</option>
                    <?php foreach ($residents as $resident): ?>
                        <option value="<?= (int) $resident['id'] ?>" <?= selected($edit['household_head_id'] ?? '', $resident['id']) ?>><?= e($resident['resident_no'] . ' - ' . $resident['last_name'] . ', ' . $resident['first_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Purok / Sitio</label>
                <select class="form-control" name="purok_id"><option value="">Select</option><?php foreach ($puroks as $purok): ?><option value="<?= (int) $purok['id'] ?>" <?= selected($edit['purok_id'] ?? '', $purok['id']) ?>><?= e($purok['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-field full"><label>Address *</label><textarea class="form-control" name="address" rows="2" required><?= e($edit['address'] ?? '') ?></textarea></div>
            <div class="form-field"><label>Classification</label><input class="form-control" name="classification" value="<?= e($edit['classification'] ?? '') ?>"></div>
            <div class="form-field"><label>Income bracket</label><input class="form-control" name="income_bracket" value="<?= e($edit['income_bracket'] ?? '') ?>"></div>
            <div class="form-field"><label>Housing type</label><input class="form-control" name="housing_type" value="<?= e($edit['housing_type'] ?? '') ?>"></div>
            <div class="form-field"><label>Status</label><select class="form-control" name="status"><option <?= selected($edit['status'] ?? 'Active', 'Active') ?>>Active</option><option <?= selected($edit['status'] ?? '', 'Inactive') ?>>Inactive</option><option <?= selected($edit['status'] ?? '', 'Archived') ?>>Archived</option></select></div>
            <div class="form-field full"><label>Utilities</label><textarea class="form-control" name="utilities" rows="2"><?= e($edit['utilities'] ?? '') ?></textarea></div>
            <div class="form-actions full"><button class="btn" type="submit"><?= $edit ? 'Update Household' : 'Add Household' ?></button><?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(app_url('modules/households.php')) ?>">Cancel</a><?php endif; ?></div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Household List</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Household</th><th>Head</th><th>Purok</th><th>Members</th><th>Income</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($households as $household): ?>
                <tr>
                    <td><span class="record-title"><strong><?= e($household['household_no']) ?></strong><small><?= e($household['address']) ?></small></span></td>
                    <td><?= e(trim(($household['first_name'] ?? '') . ' ' . ($household['last_name'] ?? '')) ?: 'Not assigned') ?></td>
                    <td><?= e($household['purok_name'] ?: 'Unassigned') ?></td>
                    <td><?= (int) $household['member_count'] ?></td>
                    <td><?= e($household['income_bracket']) ?></td>
                    <td><span class="<?= e(status_class($household['status'])) ?>"><?= e($household['status']) ?></span></td>
                    <td><a class="btn btn-ghost" href="<?= e(app_url('modules/households.php?edit=' . (int) $household['id'])) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$households): ?><tr><td colspan="7">No households found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>

