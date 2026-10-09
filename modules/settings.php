<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    require_post();
    $action = (string) ($_POST['action'] ?? 'create_user');

    if (in_array($action, ['approve_user', 'deactivate_user', 'activate_user'], true)) {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = $action === 'deactivate_user' ? 'Inactive' : 'Active';

        if ($targetId === (int) current_user()['id'] && $newStatus !== 'Active') {
            flash('danger', 'You cannot deactivate your own account.');
        } else {
            run_query('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $targetId]);
            audit_log('Account ' . strtolower($newStatus), 'User', $targetId, '');
            notify_user($targetId, 'Account ' . strtolower($newStatus), 'Your account is now ' . $newStatus . '.');
            flash('success', 'Account is now ' . $newStatus . '.');
        }
        redirect('modules/settings.php');
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $roleId = (int) ($_POST['role_id'] ?? 0);

    if ($fullName === '' || $email === '' || $password === '' || !$roleId) {
        flash('danger', 'Full name, email, password and role are required.');
    } elseif ($username !== '' && !preg_match('/^[A-Za-z0-9_]{4,30}$/', $username)) {
        flash('danger', 'Username must be 4 to 30 characters: letters, numbers and underscore only.');
    } else {
        try {
            run_query(
                'INSERT INTO users (role_id, full_name, username, email, position, password, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$roleId, $fullName, $username !== '' ? $username : null, $email, trim((string) ($_POST['position'] ?? '')) ?: null, password_hash($password, PASSWORD_DEFAULT), 'Active']
            );
            audit_log('Created user', 'User', (int) db()->lastInsertId(), $fullName);
            flash('success', 'User account created.');
        } catch (PDOException $error) {
            flash('danger', 'Could not create the account. The email or username may already be used.');
        }
        redirect('modules/settings.php');
    }
}

// Database backup (administrator only)
if (is_admin() && ($_GET['download'] ?? '') === 'sql') {
    $pdo = db();
    $out = "-- E-Barangay database backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $quoted = '`' . str_replace('`', '``', (string) $table) . '`';
        $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_NUM);
        $out .= 'DROP TABLE IF EXISTS ' . $quoted . ";\n" . $create[1] . ";\n\n";
        foreach ($pdo->query('SELECT * FROM ' . $quoted, PDO::FETCH_NUM) as $row) {
            $values = array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row);
            $out .= 'INSERT INTO ' . $quoted . ' VALUES (' . implode(',', $values) . ");\n";
        }
        $out .= "\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    audit_log('Downloaded backup', 'System', null, '');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="ebarangay_management_backup_' . date('Ymd_His') . '.sql"');
    echo $out;
    exit;
}

$roles = fetch_all('SELECT * FROM roles ORDER BY id');
$users = fetch_all(
    'SELECT users.*, roles.name AS role_name
     FROM users
     JOIN roles ON roles.id = users.role_id
     ORDER BY roles.id, users.full_name'
);

render_header('Settings');
page_header('Settings', 'Users, roles, permissions, system readiness and backup reminders.', is_admin() ? '<a class="btn btn-ghost" href="' . e(app_url('modules/contacts.php')) . '">Public contacts</a>' : '');
?>
<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>User Accounts</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>User</th><th>Email / username</th><th>Role</th><th>Status</th><?= is_admin() ? '<th></th>' : '' ?></tr></thead>
                <tbody>
                <?php foreach ($users as $account): ?>
                    <tr>
                        <td><?= e($account['full_name']) ?></td>
                        <td><?= e($account['email']) ?><?= $account['username'] ? '<br><small class="muted">@' . e($account['username']) . '</small>' : '' ?></td>
                        <td><?= e($account['role_name']) ?><?= $account['position'] ? '<br><small class="muted">' . e($account['position']) . '</small>' : '' ?></td>
                        <td><span class="<?= e(status_class($account['status'])) ?>"><?= e($account['status']) ?></span></td>
                        <?php if (is_admin()): ?>
                        <td>
                            <?php $accountActions = $account['status'] === 'Pending' ? ['approve_user' => 'Approve'] : ($account['status'] === 'Active' ? ['deactivate_user' => 'Deactivate'] : ['activate_user' => 'Activate']); ?>
                            <?php foreach ($accountActions as $act => $label): ?>
                                <form method="post" style="display:inline"><input type="hidden" name="action" value="<?= e($act) ?>"><input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>"><button class="btn btn-ghost" type="submit"><?= e($label) ?></button></form>
                            <?php endforeach; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Create Staff Account</h2></div>
        <div class="panel-body">
            <?php if (is_admin()): ?>
            <form method="post" class="form-grid">
                <div class="form-field full"><label>Full name</label><input class="form-control" name="full_name" required></div>
                <input type="hidden" name="action" value="create_user">
                <div class="form-field full"><label>Email</label><input class="form-control" type="email" name="email" required></div>
                <div class="form-field"><label>Username</label><input class="form-control" name="username" pattern="[A-Za-z0-9_]{4,30}"></div>
                <div class="form-field"><label>Position</label><input class="form-control" name="position" placeholder="e.g. Barangay Kagawad"></div>
                <div class="form-field full"><label>Password</label><input class="form-control" type="password" name="password" required></div>
                <div class="form-field full"><label>Role</label><select class="form-control" name="role_id" required><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-actions full"><button class="btn" type="submit">Create User</button></div>
            </form>
            <?php else: ?>
                <p class="muted">Only the System Administrator can create new user accounts.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Role Permissions</h2></div>
    <div class="panel-body grid grid-3">
        <?php foreach ($roles as $role): ?>
            <article class="module-card">
                <h3><?= e($role['name']) ?></h3>
                <p><?= e(implode(', ', role_permissions()[$role['slug']] ?? [])) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Backup & Data Management</h2></div>
    <div class="panel-body">
        <p>For Programming 3 defense, export the MySQL database from phpMyAdmin regularly. Include resident records, workflows, payments, notifications, audit logs and settings.</p>
        <p class="muted">Recommended filename: ebarangay_management_backup_<?= e(date('Ymd')) ?>.sql</p>
        <?php if (is_admin()): ?><p><a class="btn" href="<?= e(app_url('modules/settings.php?download=sql')) ?>">Download SQL backup now</a></p><?php endif; ?>
        <p class="muted">Uploaded files (storage/uploads and uploads/posts) are not part of the SQL file - copy those folders too.</p>
    </div>
</section>
<?php render_footer(); ?>

