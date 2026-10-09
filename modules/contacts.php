<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('settings');

if (!is_admin()) {
    flash('danger', 'Only the administrator can manage public contacts.');
    redirect('modules/settings.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? fetch_one('SELECT * FROM public_contacts WHERE id = ?', [$editId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        run_query('DELETE FROM public_contacts WHERE id = ?', [$id]);
        audit_log('Deleted public contact', 'Public Contact', $id, '');
        flash('success', 'Contact removed.');
    } else {
        $label = trim((string) ($_POST['label'] ?? ''));
        $value = trim((string) ($_POST['value'] ?? ''));
        $type = trim((string) ($_POST['contact_type'] ?? '')) ?: 'Office';
        $availability = trim((string) ($_POST['availability'] ?? ''));
        $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

        if ($label === '' || $value === '') {
            flash('danger', 'Label and value are required.');
            redirect('modules/contacts.php' . ($id ? '?edit=' . $id : ''));
        }
        if ($id) {
            run_query('UPDATE public_contacts SET label = ?, contact_type = ?, value = ?, availability = ?, status = ? WHERE id = ?', [$label, $type, $value, $availability, $status, $id]);
            audit_log('Updated public contact', 'Public Contact', $id, $label);
        } else {
            run_query('INSERT INTO public_contacts (label, contact_type, value, availability, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [$label, $type, $value, $availability, $status]);
            audit_log('Created public contact', 'Public Contact', (int) db()->lastInsertId(), $label);
        }
        flash('success', 'Public contact saved.');
    }
    redirect('modules/contacts.php');
}

$contacts = fetch_all('SELECT * FROM public_contacts ORDER BY status ASC, id DESC');

render_header('Public Contacts');
page_header('Public Contacts', 'Contact details shown on the public portal. Only Active entries are visible.', '<a class="btn btn-ghost" href="' . e(app_url('modules/settings.php')) . '">Back to Settings</a>');
?>
<section class="panel">
    <div class="panel-header"><h2><?= $edit ? 'Edit Contact' : 'Add Contact' ?></h2></div>
    <div class="panel-body">
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-field"><label>Label *</label><input class="form-control" name="label" value="<?= e($edit['label'] ?? '') ?>" required></div>
            <div class="form-field"><label>Type</label><input class="form-control" name="contact_type" value="<?= e($edit['contact_type'] ?? 'Office') ?>" placeholder="Phone, Email, Facebook..."></div>
            <div class="form-field"><label>Value *</label><input class="form-control" name="value" value="<?= e($edit['value'] ?? '') ?>" required></div>
            <div class="form-field"><label>Availability</label><input class="form-control" name="availability" value="<?= e($edit['availability'] ?? '') ?>"></div>
            <div class="form-field"><label>Status</label><select class="form-control" name="status"><option <?= selected($edit['status'] ?? 'Active', 'Active') ?>>Active</option><option <?= selected($edit['status'] ?? 'Active', 'Inactive') ?>>Inactive</option></select></div>
            <div class="form-actions full"><button class="btn" type="submit"><?= $edit ? 'Update Contact' : 'Add Contact' ?></button><?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(app_url('modules/contacts.php')) ?>">Cancel</a><?php endif; ?></div>
        </form>
    </div>
</section>
<section class="panel">
    <div class="panel-header"><h2>All Contacts</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Label</th><th>Type</th><th>Value</th><th>Availability</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($contacts as $row): ?>
                <tr>
                    <td><?= e($row['label']) ?></td><td><?= e($row['contact_type']) ?></td><td><?= e($row['value']) ?></td><td><?= e($row['availability']) ?></td>
                    <td><span class="<?= e(status_class($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                    <td>
                        <a class="btn btn-ghost" href="<?= e(app_url('modules/contacts.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Burahin ang contact na ito?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-ghost" type="submit">Delete</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$contacts): ?><tr><td colspan="6">No contacts yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
