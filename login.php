<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $portal = ($_POST['portal'] ?? 'Resident') === 'Official' ? 'Official' : 'Resident';

    try {
        // Accept either the account email or the username.
        $user = fetch_one(
            'SELECT users.*, roles.slug AS role_slug, roles.name AS role_name
             FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE users.email = ? OR users.username = ?',
            [$identifier, $identifier]
        );

        $portalMatches = $user && (($portal === 'Resident') === ($user['role_slug'] === 'resident'));

        if ($user && password_verify($password, $user['password']) && $portalMatches) {
            if ($user['status'] === 'Pending') {
                flash('warning', 'Your official account is waiting for approval by the System Administrator.');
            } elseif ($user['status'] !== 'Active') {
                flash('danger', 'Your account is inactive. Please contact your barangay administrator.');
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                audit_log('Login', 'User', (int) $user['id'], 'User logged in successfully.');
                redirect('dashboard.php');
            }
        } else {
            audit_log('Failed login', 'User', null, 'Failed login attempt for ' . $identifier . ' (' . $portal . ' portal)');
            flash('danger', 'Wrong username/email, password, or portal. Check that you picked the right portal above.');
        }
    } catch (Throwable $error) {
        flash('danger', 'Database connection failed. Check config/database.php and follow the setup or safe migration instructions in README.md.');
    }
}

render_header('Login', ['body_class' => 'auth-page']);
?>
<div class="auth-split">
    <section class="auth-hero">
        <img class="brand-logo" src="<?= e(app_url('assets/img/logo.svg')) ?>" alt="Seal of <?= e(APP_BARANGAY) ?>">
        <p class="place-line">Barangay Bigaan &middot; Calauag, Quezon</p>
        <h1>Serbisyo ng barangay, abot-kamay.</h1>
        <p><?= e(APP_BARANGAY) ?> online: request certificates, track your applications and stay updated on what is happening in your community.</p>
        <ul class="auth-points">
            <li><?= icon('file') ?>Request clearances and certificates without the queue</li>
            <li><?= icon('check') ?>See the status of every request, step by step</li>
            <li><?= icon('qr') ?>Verify any barangay document with its QR code</li>
        </ul>
        <div class="about-seal">
            <h3>About Barangay Bigaan</h3>
            <p>Barangay Bigaan is in Calauag, Quezon. E-Barangay brings its everyday services online, so residents can request documents and follow their applications without waiting in line.</p>
            <dl>
                <div><dt>Niyog</dt><dd>Coconut palms on the barangay seal.</dd></div>
                <div><dt>Riles ng tren</dt><dd>The railway that runs through the seal.</dd></div>
                <div><dt>Gabi at palay</dt><dd>Crops that grow in the community.</dd></div>
            </dl>
        </div>
    </section>

    <section class="auth-card">
        <h2>Welcome back</h2>
        <p class="login-portal-title">Barangay Officials &amp; Admin Portal</p>
        <p>Sign in to your barangay account.</p>
        <form method="post" class="form-grid">
            <div class="form-field full">
                <label>Sign in to</label>
                <div class="split-actions">
                    <label><input type="radio" name="portal" value="Resident" <?= checked(old('portal', 'Resident') !== 'Official') ?>> Resident portal</label>
                    <label><input type="radio" name="portal" value="Official" <?= checked(old('portal', 'Resident') === 'Official') ?>> Barangay officials</label>
                </div>
            </div>
            <div class="form-field full">
                <label for="identifier">Username</label>
                <input class="form-control" id="identifier" type="text" name="identifier" placeholder="Enter your username" autocomplete="username" required>
            </div>
            <div class="form-field full">
                <label for="password">Password</label>
                <input class="form-control" id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
            </div>
            <div class="form-actions full"><button class="btn" type="submit">Log in</button></div>
        </form>
        <div class="auth-links">
            <a class="btn btn-ghost" href="<?= e(app_url('register.php')) ?>">Create an account</a>
            <a class="btn btn-ghost" href="<?= e(app_url('public/index.php')) ?>">Public portal</a>
        </div>
        <?php if (APP_DEMO): ?>
        <details class="demo-box">
            <summary>Demo / Test Credentials (Click to Autofill)</summary>
            <div class="demo-chips">
                <?php foreach (['admin' => 'Administrator', 'secretary' => 'Secretary', 'treasurer' => 'Treasurer', 'captain' => 'Punong Barangay', 'staff' => 'Staff', 'juan' => 'Resident'] as $uname => $label): ?>
                    <button type="button" data-demo-email="<?= e($uname) ?>" data-demo-portal="<?= $uname === 'juan' ? 'Resident' : 'Official' ?>" data-demo-password="password"><?= e($label) ?></button>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
    </section>
</div>
<?php render_footer(); ?>
