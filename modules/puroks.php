<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('puroks');

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? fetch_one('SELECT * FROM puroks WHERE id = ?', [$editId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int) ($_POST['id'] ?? 0);
    $values = [
        trim((string) ($_POST['name'] ?? '')),
        trim((string) ($_POST['leader_name'] ?? '')),
        trim((string) ($_POST['assigned_area'] ?? '')),
        trim((string) ($_POST['notes'] ?? '')),
    ];

    if ($values[0] === '') {
        flash('danger', 'Purok / Sitio name is required.');
    } elseif ($id) {
        $values[] = $id;
        run_query('UPDATE puroks SET name = ?, leader_name = ?, assigned_area = ?, notes = ?, updated_at = NOW() WHERE id = ?', $values);
        audit_log('Updated purok', 'Purok', $id, $values[0]);
        flash('success', 'Purok updated.');
        redirect('modules/puroks.php');
    } else {
        run_query('INSERT INTO puroks (name, leader_name, assigned_area, notes, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())', $values);
        audit_log('Created purok', 'Purok', (int) db()->lastInsertId(), $values[0]);
        flash('success', 'Purok registered.');
        redirect('modules/puroks.php');
    }
}

$puroks = fetch_all(
    'SELECT puroks.*,
            (SELECT COUNT(*) FROM residents WHERE residents.purok_id = puroks.id) AS resident_count,
            (SELECT COUNT(*) FROM households WHERE households.purok_id = puroks.id) AS household_count
     FROM puroks
     ORDER BY name'
);

render_header('Purok / Sitio');
page_header('Purok / Sitio Management', 'Purok leaders, assigned areas, resident counts, household counts and local reports.', export_buttons('puroks'));
?>
<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2><?= $edit ? 'Edit Purok / Sitio' : 'Register Purok / Sitio' ?></h2></div>
        <div class="panel-body">
            <form method="post" class="form-grid">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="form-field full"><label>Name *</label><input class="form-control" name="name" value="<?= e($edit['name'] ?? '') ?>" required></div>
                <div class="form-field full"><label>Leader</label><input class="form-control" name="leader_name" value="<?= e($edit['leader_name'] ?? '') ?>"></div>
                <div class="form-field full"><label>Assigned area</label><input class="form-control" name="assigned_area" value="<?= e($edit['assigned_area'] ?? '') ?>"></div>
                <div class="form-field full"><label>Notes</label><textarea class="form-control" name="notes" rows="3"><?= e($edit['notes'] ?? '') ?></textarea></div>
                <div class="form-actions full"><button class="btn" type="submit"><?= $edit ? 'Update Purok' : 'Add Purok' ?></button><?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(app_url('modules/puroks.php')) ?>">Cancel</a><?php endif; ?></div>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Purok Reports</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Purok</th><th>Leader</th><th>Residents</th><th>Households</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($puroks as $purok): ?>
                    <tr>
                        <td><span class="record-title"><strong><?= e($purok['name']) ?></strong><small><?= e($purok['assigned_area']) ?></small></span></td>
                        <td><?= e($purok['leader_name']) ?></td>
                        <td><?= (int) $purok['resident_count'] ?></td>
                        <td><?= (int) $purok['household_count'] ?></td>
                        <td><a class="btn btn-ghost" href="<?= e(app_url('modules/puroks.php?edit=' . (int) $purok['id'])) ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$puroks): ?><tr><td colspan="5">No purok records found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php render_footer(); ?>

