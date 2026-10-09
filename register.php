<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountType = ($_POST['account_type'] ?? 'Resident') === 'Barangay Official' ? 'Barangay Official' : 'Resident';
    $isOfficial = $accountType === 'Barangay Official';
    $position = (string) ($_POST['position'] ?? '');
    $username = trim((string) ($_POST['username'] ?? ''));
    $required = $isOfficial ? ['first_name', 'last_name', 'email', 'username', 'password'] : ['first_name', 'last_name', 'email', 'address', 'password'];
    $missing = [];

    foreach ($required as $field) {
        if (trim((string) ($_POST[$field] ?? '')) === '') {
            $missing[] = $field;
        }
    }

    if ($missing) {
        flash('danger', 'Please complete all required fields.');
    } elseif (strlen((string) $_POST['password']) < 6) {
        flash('danger', 'Password must be at least 6 characters.');
    } elseif (($username !== '' || $isOfficial) && !preg_match('/^[A-Za-z0-9_]{4,30}$/', $username)) {
        flash('danger', 'Username must be 4 to 30 characters: letters, numbers and underscore only.');
    } elseif ($isOfficial && !array_key_exists($position, official_positions())) {
        flash('danger', 'Please choose a valid position.');
    } elseif ($isOfficial) {
        // Barangay officials are active immediately after successful registration.
        try {
            $roleId = (int) fetch_value('SELECT id FROM roles WHERE slug = ?', [official_positions()[$position]]);
            $fullName = trim((string) $_POST['first_name'] . ' ' . (string) $_POST['last_name']);
            run_query(
                'INSERT INTO users (role_id, resident_id, full_name, username, email, position, password, status, created_at, updated_at)
                 VALUES (?, NULL, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$roleId, $fullName, $username, trim((string) $_POST['email']), $position, password_hash((string) $_POST['password'], PASSWORD_DEFAULT), 'Active']
            );
            audit_log('Official sign-up requested', 'User', (int) db()->lastInsertId(), $fullName . ' as ' . $position);
            flash('success', 'Official account created. You can log in now.');
            redirect('login.php');
        } catch (PDOException $error) {
            flash('danger', 'Registration failed. The email or username may already be used.');
        }
    } else {
        $pdo = null;
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $residentNo = generate_reference('RES');
            run_query(
                'INSERT INTO residents
                 (resident_no, first_name, middle_name, last_name, suffix, gender, birth_date, civil_status, contact_no, email, address, purok_id, occupation, education, voter_status, residency_status, is_pwd, is_senior, is_solo_parent, is_4ps, is_ofw, is_other, special_classification, status, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $residentNo,
                    trim((string) $_POST['first_name']),
                    trim((string) ($_POST['middle_name'] ?? '')),
                    trim((string) $_POST['last_name']),
                    trim((string) ($_POST['suffix'] ?? '')),
                    trim((string) ($_POST['gender'] ?? '')),
                    $_POST['birth_date'] ?: null,
                    trim((string) ($_POST['civil_status'] ?? '')),
                    trim((string) ($_POST['contact_no'] ?? '')),
                    trim((string) $_POST['email']),
                    trim((string) $_POST['address']),
                    $_POST['purok_id'] ?: null,
                    trim((string) ($_POST['occupation'] ?? '')),
                    trim((string) ($_POST['education'] ?? '')),
                    trim((string) ($_POST['voter_status'] ?? '')),
                    'Resident',
                    isset($_POST['is_pwd']) ? 1 : 0,
                    isset($_POST['is_senior']) ? 1 : 0,
                    isset($_POST['is_solo_parent']) ? 1 : 0,
                    isset($_POST['is_4ps']) ? 1 : 0,
                    isset($_POST['is_ofw']) ? 1 : 0,
                    isset($_POST['is_other']) ? 1 : 0,
                    trim((string) ($_POST['special_classification'] ?? '')) ?: null,
                    'Pending Verification',
                    'Registered through resident portal.',
                ]
            );

            $residentId = (int) $pdo->lastInsertId();
            $roleId = (int) fetch_value('SELECT id FROM roles WHERE slug = ?', ['resident']);
            $fullName = trim((string) $_POST['first_name'] . ' ' . (string) $_POST['last_name']);

            run_query(
                'INSERT INTO users (role_id, resident_id, full_name, username, email, password, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$roleId, $residentId, $fullName, $username !== '' ? $username : null, trim((string) $_POST['email']), password_hash((string) $_POST['password'], PASSWORD_DEFAULT), 'Active']
            );

            $pdo->commit();
            flash('success', 'Registration submitted. You can log in while your resident record is pending verification.');
            redirect('login.php');
        } catch (Throwable $error) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Registration failed. The email or username may already be used, or the database is not ready.');
        }
    }
}

$puroks = [];
try {
    $puroks = fetch_all('SELECT id, name FROM puroks ORDER BY name');
} catch (Throwable $error) {
    $puroks = [];
}

render_header('Resident Registration', ['body_class' => 'auth-page']);
?>
<section class="auth-card">
    <p class="eyebrow">Resident Portal</p>
    <h1>Resident Registration</h1>
    <p>Create an account and submit your information for barangay verification.</p>

    <form method="post" class="form-grid">
        <div class="form-field full">
            <label>I am registering as</label>
            <div class="split-actions">
                <label><input type="radio" name="account_type" value="Resident" data-account-type <?= checked(old('account_type', 'Resident') !== 'Barangay Official') ?>> Resident</label>
                        <label><input type="radio" name="account_type" value="Barangay Official" data-account-type <?= checked(old('account_type', 'Resident') === 'Barangay Official') ?>> Barangay official (active immediately)</label>
            </div>
        </div>
        <div class="form-field">
            <label>First name *</label>
            <input class="form-control" name="first_name" value="<?= e(old('first_name')) ?>" required>
        </div>
        <div class="form-field">
            <label>Last name *</label>
            <input class="form-control" name="last_name" value="<?= e(old('last_name')) ?>" required>
        </div>
        <div class="form-field">
            <label>Middle name</label>
            <input class="form-control" name="middle_name" value="<?= e(old('middle_name')) ?>">
        </div>
        <div class="form-field">
            <label>Suffix</label>
            <input class="form-control" name="suffix" value="<?= e(old('suffix')) ?>">
        </div>
        <div class="form-field">
            <label>Email *</label>
            <input class="form-control" type="email" name="email" value="<?= e(old('email')) ?>" required>
        </div>
        <div class="form-field">
            <label>Username <small class="muted">(required for officials)</small></label>
            <input class="form-control" name="username" value="<?= e(old('username')) ?>" pattern="[A-Za-z0-9_]{4,30}" autocomplete="username">
        </div>
        <div class="form-field" data-official-only>
            <label>Position (officials only)</label>
            <select class="form-control" name="position">
                <?php foreach (array_keys(official_positions()) as $positionName): ?>
                    <option <?= selected(old('position'), $positionName) ?>><?= e($positionName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>Password *</label>
            <input class="form-control" type="password" name="password" required>
        </div>
        <div class="form-field">
            <label>Gender</label>
            <select class="form-control" name="gender">
                <option value="">Select</option>
                <option <?= selected(old('gender'), 'Female') ?>>Female</option>
                <option <?= selected(old('gender'), 'Male') ?>>Male</option>
            </select>
        </div>
        <div class="form-field">
            <label>Birth date</label>
            <input class="form-control" type="date" name="birth_date" value="<?= e(old('birth_date')) ?>">
        </div>
        <div class="form-field">
            <label>Contact no.</label>
            <input class="form-control" name="contact_no" value="<?= e(old('contact_no')) ?>">
        </div>
        <div class="form-field">
            <label>Purok / Sitio</label>
            <select class="form-control" name="purok_id">
                <option value="">Select</option>
                <?php foreach ($puroks as $purok): ?>
                    <option value="<?= (int) $purok['id'] ?>" <?= selected(old('purok_id'), $purok['id']) ?>><?= e($purok['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field full">
            <label>Address * <small class="muted">(residents)</small></label>
            <textarea class="form-control" name="address" rows="3"><?= e(old('address')) ?></textarea>
        </div>
        <div class="form-field">
            <label>Occupation</label>
            <input class="form-control" name="occupation" value="<?= e(old('occupation')) ?>">
        </div>
        <div class="form-field">
            <label>Education</label>
            <input class="form-control" name="education" value="<?= e(old('education')) ?>">
        </div>
        <div class="form-field full">
            <label>Classifications</label>
            <div class="split-actions">
                <label><input type="checkbox" name="is_pwd" <?= checked(isset($_POST['is_pwd'])) ?>> PWD</label>
                <label><input type="checkbox" name="is_senior" <?= checked(isset($_POST['is_senior'])) ?>> Senior Citizen</label>
                <label><input type="checkbox" name="is_solo_parent" <?= checked(isset($_POST['is_solo_parent'])) ?>> Solo Parent</label>
                <label><input type="checkbox" name="is_4ps" <?= checked(isset($_POST['is_4ps'])) ?>> 4Ps</label>
                <label><input type="checkbox" name="is_ofw" <?= checked(isset($_POST['is_ofw'])) ?>> OFW</label>
                <label><input type="checkbox" name="is_other" <?= checked(isset($_POST['is_other'])) ?>> Other</label>
            </div>
            <input class="form-control" name="special_classification" placeholder="If Other, describe classification" value="<?= e(old('special_classification')) ?>">
        </div>
        <div class="form-actions full">
            <button class="btn" type="submit">Submit Registration</button>
            <a class="btn btn-ghost" href="<?= e(app_url('login.php')) ?>">Back to Login</a>
        </div>
    </form>
</section>
<?php render_footer(); ?>
