<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$user = current_user();
$residentId = current_resident_id();
$resident = $residentId ? fetch_one('SELECT * FROM residents WHERE id = ?', [$residentId]) : null;
$puroks = fetch_all('SELECT id, name FROM puroks ORDER BY name');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'password') {
        $old = (string) ($_POST['old_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($old, (string) $user['password'])) {
            flash('danger', 'Your current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash('danger', 'The new password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            flash('danger', 'The two new passwords do not match.');
        } else {
            run_query('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
            audit_log('Changed password', 'User', (int) $user['id'], '');
            flash('success', 'Password changed.');
        }
        redirect('modules/profile.php');
    }

    if ($action === 'details' && $resident) {
        // Classifications (PWD, senior, solo parent, 4Ps) and status stay staff-controlled because they need verification.
        run_query(
            'UPDATE residents SET gender = ?, birth_date = ?, civil_status = ?, contact_no = ?, address = ?, purok_id = ?, occupation = ?, education = ?, updated_at = NOW() WHERE id = ?',
            [
                trim((string) ($_POST['gender'] ?? '')),
                ($_POST['birth_date'] ?? '') ?: null,
                trim((string) ($_POST['civil_status'] ?? '')),
                trim((string) ($_POST['contact_no'] ?? '')),
                trim((string) ($_POST['address'] ?? '')) ?: (string) $resident['address'],
                ($_POST['purok_id'] ?? '') ?: null,
                trim((string) ($_POST['occupation'] ?? '')),
                trim((string) ($_POST['education'] ?? '')),
                $residentId,
            ]
        );
        audit_log('Updated own profile', 'Resident', $residentId, '');
        flash('success', 'Your profile was updated.');
        redirect('modules/profile.php');
    }
}

render_header('My Profile');
page_header('My Profile', 'Your account and personal details.');
?>

<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>Account</h2><span class="status status-active"><?= e($user['role_name']) ?></span></div>
        <div class="panel-body">
            <p><strong>Name</strong><br><?= e($user['full_name']) ?></p>
            <p><strong>Email</strong><br><?= e($user['email']) ?></p>
            <p><strong>Username</strong><br><?= e($user['username'] ?: 'Not set') ?></p>
            <?php if ($user['position']): ?><p><strong>Position</strong><br><?= e($user['position']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Change password</h2></div>
        <div class="panel-body">
            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="password">
                <div class="form-field full"><label>Current password</label><input class="form-control" type="password" name="old_password" autocomplete="current-password" required></div>
                <div class="form-field"><label>New password</label><input class="form-control" type="password" name="new_password" autocomplete="new-password" required></div>
                <div class="form-field"><label>Confirm new password</label><input class="form-control" type="password" name="confirm_password" autocomplete="new-password" required></div>
                <div class="form-actions full"><button class="btn" type="submit">Change password</button></div>
            </form>
        </div>
    </div>
</section>

<?php if ($resident): ?>
<section class="panel">
    <div class="panel-header">
        <h2>Resident details</h2>
        <span class="<?= e(status_class($resident['status'])) ?>"><?= e($resident['status']) ?></span>
    </div>
    <div class="panel-body">
        <p class="muted">Resident No. <?= e($resident['resident_no']) ?>. Your name, classifications and verification status can only be changed by barangay staff.</p>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="details">
            <div class="form-field"><label>Gender</label>
                <select class="form-control" name="gender"><option value="">Select</option><?php foreach (['Female', 'Male'] as $g): ?><option <?= selected($resident['gender'], $g) ?>><?= e($g) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-field"><label>Birth date</label><input class="form-control" type="date" name="birth_date" value="<?= e($resident['birth_date']) ?>"></div>
            <div class="form-field"><label>Civil status</label>
                <select class="form-control" name="civil_status"><option value="">Select</option><?php foreach (['Single', 'Married', 'Widowed', 'Separated'] as $c): ?><option <?= selected($resident['civil_status'], $c) ?>><?= e($c) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-field"><label>Contact no.</label><input class="form-control" name="contact_no" value="<?= e($resident['contact_no']) ?>"></div>
            <div class="form-field"><label>Purok / Sitio</label>
                <select class="form-control" name="purok_id"><option value="">Select</option><?php foreach ($puroks as $purok): ?><option value="<?= (int) $purok['id'] ?>" <?= selected($resident['purok_id'], $purok['id']) ?>><?= e($purok['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-field"><label>Occupation</label><input class="form-control" name="occupation" value="<?= e($resident['occupation']) ?>"></div>
            <div class="form-field"><label>Education</label><input class="form-control" name="education" value="<?= e($resident['education']) ?>"></div>
            <div class="form-field full"><label>Address</label><textarea class="form-control" name="address" rows="2"><?= e($resident['address']) ?></textarea></div>
            <div class="form-actions full"><button class="btn" type="submit">Save details</button></div>
        </form>
    </div>
</section>
<?php endif; ?>

<?php render_footer(); ?>
