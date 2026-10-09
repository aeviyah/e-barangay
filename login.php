<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

/* ------------------------------------------------------------------
 * Page-local helpers (prefixed lg_ so they never clash with yours)
 * ------------------------------------------------------------------ */

/** Inline SVG icon (stroke style). */
function lg_icon(string $name): string
{
    static $paths = [
        'file'    => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
        'check'   => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'lock'    => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'eye'     => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M3 3l18 18"/><path d="M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4M6.5 6.6C3.7 8.3 2 12 2 12s3.5 7 10 7c1.6 0 3-.4 4.3-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'home'    => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'heart'   => '<path d="M12 20s-8-4.7-8-10.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 8 2.5C20 15.3 12 20 12 20z"/>',
    ];

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
}

/**
 * Pull messages that other pages left in the session (e.g. after registering).
 * If your flash() helper uses a different session key, add it to the list.
 */
function lg_take_flashes(): array
{
    $allowed = ['success', 'warning', 'danger', 'info'];
    $out = [];

    foreach (['flashes', 'flash', 'flash_messages'] as $key) {
        if (empty($_SESSION[$key]) || !is_array($_SESSION[$key])) {
            continue;
        }
        foreach ($_SESSION[$key] as $k => $item) {
            if (is_array($item) && isset($item['message'])) {
                $type = (string) ($item['type'] ?? 'info');
                $out[] = ['type' => in_array($type, $allowed, true) ? $type : 'info', 'message' => (string) $item['message']];
            } elseif (is_string($item) && is_string($k)) {
                $out[] = ['type' => in_array($k, $allowed, true) ? $k : 'info', 'message' => $item];
            }
        }
        unset($_SESSION[$key]);
    }

    return $out;
}

/* ------------------------------------------------------------------
 * Login handling (same logic as before)
 * ------------------------------------------------------------------ */

$alerts = lg_take_flashes();

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
                $alerts[] = ['type' => 'warning', 'message' => 'Your official account is waiting for approval by the System Administrator.'];
            } elseif ($user['status'] !== 'Active') {
                $alerts[] = ['type' => 'danger', 'message' => 'Your account is inactive. Please contact your barangay administrator.'];
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                audit_log('Login', 'User', (int) $user['id'], 'User logged in successfully.');
                redirect('dashboard.php');
            }
        } else {
            audit_log('Failed login', 'User', null, 'Failed login attempt for ' . $identifier . ' (' . $portal . ' portal)');
            $alerts[] = ['type' => 'danger', 'message' => 'Wrong username/email, password, or portal. Check that you picked the right portal above.'];
        }
    } catch (Throwable $error) {
        $alerts[] = ['type' => 'danger', 'message' => 'Database connection failed. Check config/database.php and follow the setup or safe migration instructions in README.md.'];
    }
}

$logo = app_url('assets/img/logo.svg');

$points = [
    ['file',  'Request documents online', 'Clearance, residency and indigency certificates without the queue.'],
    ['check', 'Track every request',      'See each step from submitted to ready for pickup.'],
    ['chart', 'Open barangay budget',     'Read how public funds and projects are being used.'],
];

$services = [
    ['file',  'Barangay clearance',       'For jobs, IDs, and other official transactions.',          'Sign in to request'],
    ['home',  'Certificate of residency', 'Proof that you live in Barangay Bigaan.',                  'Sign in to request'],
    ['heart', 'Certificate of indigency', 'For assistance programs, scholarships, and medical help.', 'Sign in to request'],
    ['check', 'Track my request',         'Follow each step until your document is ready for pickup.', 'Sign in to track'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · E-Barangay Bigaan</title>
    <link rel="icon" href="<?= e($logo) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <style>
:root {
    --nav-h: 76px;
    --panel: rgba(10, 30, 42, .9);
    --panel-line: rgba(255, 255, 255, .12);
    --text: #eaf2f2;
    --muted: #b9c9cc;
    --gold: #f5b73b;
    --gold-ink: #2d1f05;
    --teal: #17596d;
    --green: #2b8a50;
    --green-dark: #237043;
    --cream: #faf8f1;
    --cream-line: #e3dfd0;
    --field: #f4f2e9;
    --ink: #14272f;
    --ink-muted: #5b6b72;
    --font: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body { margin: 0; min-height: 100vh; font-family: var(--font); color: var(--text); background: #0d2a3d; line-height: 1.55; -webkit-font-smoothing: antialiased; }
img, svg { display: block; max-width: 100%; }
:focus-visible { outline: 3px solid var(--gold); outline-offset: 2px; }
.sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

/* Background scene */
.scene { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
.scene svg { width: 100%; height: 100%; max-width: none; }
.train { animation: drive 90s linear infinite; }
@keyframes drive { from { transform: translateX(-1100px); } to { transform: translateX(2100px); } }

/* Navigation */
.site-nav { position: fixed; inset: 0 0 auto 0; height: var(--nav-h); z-index: 50; background: rgba(7, 22, 33, .88); -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, .08); }
.nav-inner { height: 100%; max-width: 1840px; margin: 0 auto; padding: 0 clamp(16px, 3vw, 56px); display: flex; align-items: center; justify-content: space-between; gap: 24px; }
.brand { display: flex; align-items: center; gap: 12px; color: #fff; text-decoration: none; }
.brand img { width: 54px; height: 54px; border-radius: 50%; }
.brand strong { display: block; font-size: 1.05rem; font-weight: 800; line-height: 1.2; }
.brand small { display: block; font-size: .72rem; color: #d3dde0; }
.nav-links { display: flex; align-items: center; gap: 6px; }
.nav-links a { color: #fff; font-size: .82rem; font-weight: 700; text-decoration: none; padding: 9px 15px; border-radius: 999px; transition: background-color .2s ease; }
.nav-links a:hover { background: rgba(255, 255, 255, .1); }
.nav-links a.is-active { background: rgba(255, 255, 255, .17); }
.nav-links .nav-cta { margin-left: 8px; background: var(--gold); color: var(--gold-ink); }
.nav-links .nav-cta:hover { background: #ffc85a; }
.nav-toggle { display: none; width: 44px; height: 44px; padding: 0; border: 1px solid rgba(255, 255, 255, .2); border-radius: 12px; background: transparent; cursor: pointer; flex-direction: column; align-items: center; justify-content: center; gap: 5px; }
.nav-toggle span { width: 20px; height: 2px; background: #fff; border-radius: 2px; }

/* Sections */
.page-section { min-height: 100vh; min-height: 100svh; display: flex; align-items: center; justify-content: center; padding: calc(var(--nav-h) + 36px) 24px 48px; }
.page-section--end { flex-direction: column; }
.container { width: 100%; max-width: 1072px; margin: 0 auto; }

/* Home */
.hero-grid { display: grid; grid-template-columns: minmax(0, 1fr) 431px; gap: 56px; align-items: center; }
.auth-hero .brand-logo { width: 188px; height: 188px; border-radius: 50%; outline: 2px dotted rgba(245, 183, 59, .6); outline-offset: 8px; margin: 8px 0 22px 8px; filter: drop-shadow(0 10px 24px rgba(0, 0, 0, .35)); }
.place-line { margin: 0 0 6px; color: var(--gold); font-size: .85rem; font-weight: 700; }
.auth-hero h1 { margin: 0 0 18px; max-width: 11.5em; font-size: clamp(2.4rem, 5.2vw, 3.9rem); font-weight: 800; line-height: 1.04; letter-spacing: -.025em; color: #fff; text-shadow: 0 2px 18px rgba(0, 0, 0, .35); }
.auth-hero .lead { max-width: 52ch; margin: 0 0 22px; font-size: .95rem; color: #f2f7f7; }
.auth-points { list-style: none; margin: 0; padding: 0; max-width: 430px; display: grid; gap: 10px; }
.auth-points li { display: flex; align-items: center; gap: 14px; padding: 12px 16px; background: rgba(9, 28, 40, .82); border: 1px solid var(--panel-line); border-radius: 14px; }
.auth-points strong { display: block; font-size: .84rem; font-weight: 800; color: #fff; }
.auth-points span.sub { display: block; font-size: .76rem; color: var(--muted); line-height: 1.4; }
.tile { flex: none; width: 38px; height: 38px; display: grid; place-items: center; border-radius: 11px; color: var(--gold); background: rgba(245, 183, 59, .16); border: 1px solid rgba(245, 183, 59, .28); }
.tile svg { width: 19px; height: 19px; }

/* Login card */
.auth-card { position: relative; overflow: hidden; padding: 36px 32px 26px; background: var(--cream); color: var(--ink); border-radius: 22px; box-shadow: 0 34px 70px -24px rgba(0, 0, 0, .65); }
.auth-card::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 5px; background: repeating-linear-gradient(90deg, var(--gold) 0 12px, transparent 12px 18px); }
.auth-card h2 { margin: 0 0 4px; font-size: 1.75rem; font-weight: 800; letter-spacing: -.02em; }
.card-sub { margin: 0 0 18px; font-size: .84rem; color: var(--ink-muted); }
.login-form { display: grid; gap: 14px; }
.portal-field { margin: 0; padding: 0; border: 0; min-width: 0; }
.portal-switch { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; padding: 4px; background: #eceff1; border-radius: 14px; }
.portal-option { position: relative; cursor: pointer; }
.portal-option input { position: absolute; opacity: 0; inset: 0; margin: 0; cursor: pointer; }
.portal-option span { display: block; padding: 11px 8px; border-radius: 11px; text-align: center; font-size: .8rem; font-weight: 700; color: #4b5b63; transition: background-color .2s ease, color .2s ease; }
.portal-option input:checked + span { background: var(--teal); color: #fff; box-shadow: 0 6px 14px -6px rgba(23, 89, 109, .7); }
.portal-option input:focus-visible + span { outline: 3px solid var(--gold); outline-offset: 2px; }
.field label { display: block; margin-bottom: 6px; font-size: .78rem; font-weight: 700; }
.input-wrap { position: relative; }
.lead-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #7d8b91; pointer-events: none; }
.lead-icon svg { width: 18px; height: 18px; }
.form-control { width: 100%; height: 46px; padding: 0 46px 0 42px; font: inherit; font-size: .88rem; color: var(--ink); background: var(--field); border: 1px solid var(--cream-line); border-radius: 12px; transition: border-color .2s ease, background-color .2s ease, box-shadow .2s ease; }
.form-control::placeholder { color: #8b979c; }
.form-control:focus { outline: none; background: #fff; border-color: var(--teal); box-shadow: 0 0 0 3px rgba(23, 89, 109, .22); }
.toggle-pass { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; display: grid; place-items: center; padding: 0; border: 0; border-radius: 9px; background: transparent; color: #5b6b72; cursor: pointer; }
.toggle-pass:hover { background: rgba(0, 0, 0, .06); }
.toggle-pass svg { width: 19px; height: 19px; }
.toggle-pass .icon-off { display: none; }
.toggle-pass[aria-pressed="true"] .icon-on { display: none; }
.toggle-pass[aria-pressed="true"] .icon-off { display: block; }
.btn { display: flex; align-items: center; justify-content: center; width: 100%; height: 48px; padding: 0 18px; font: inherit; font-size: .9rem; font-weight: 800; color: #fff; text-decoration: none; background: var(--green); border: 0; border-radius: 12px; cursor: pointer; box-shadow: 0 10px 20px -10px rgba(43, 138, 80, .8); transition: background-color .2s ease, transform .15s ease; }
.btn:hover { background: var(--green-dark); }
.btn:active { transform: translateY(1px); }
.card-foot { margin: 16px 0 0; text-align: center; font-size: .8rem; color: var(--ink-muted); }
.card-foot a { color: var(--teal); font-weight: 800; text-decoration: none; }
.card-foot a:hover { text-decoration: underline; }
.alert { margin: 0 0 14px; padding: 11px 14px; border-radius: 12px; font-size: .8rem; font-weight: 600; line-height: 1.45; }
.alert-danger { background: #fde8e6; color: #8a2018; }
.alert-warning { background: #fff3d6; color: #6b4a00; }
.alert-success { background: #e3f4ea; color: #1d5a35; }
.alert-info { background: #e4eef3; color: #17475a; }
.demo-box { margin-top: 14px; padding-top: 12px; border-top: 1px dashed #d8d3c0; font-size: .8rem; }
.demo-box summary { cursor: pointer; font-weight: 700; color: var(--teal); }
.demo-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.demo-chips button { padding: 6px 12px; font: inherit; font-size: .76rem; font-weight: 700; color: var(--teal); background: #fff; border: 1px solid #cfd8d6; border-radius: 999px; cursor: pointer; }
.demo-chips button:hover { background: rgba(23, 89, 109, .08); }

/* Panels */
.panel { padding: 44px; background: var(--panel); border: 1px solid var(--panel-line); border-radius: 26px; box-shadow: 0 30px 70px -30px rgba(0, 0, 0, .65); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); }
.panel h2 { margin: 0 0 12px; font-size: clamp(2rem, 4vw, 2.9rem); font-weight: 800; line-height: 1.05; letter-spacing: -.025em; color: #fff; }
.panel-lead { margin: 0; max-width: 62ch; font-size: .92rem; color: #e3ecec; }
.service-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: 30px; }
.service-card { display: flex; flex-direction: column; padding: 20px; color: inherit; text-decoration: none; background: rgba(255, 255, 255, .05); border: 1px solid var(--panel-line); border-radius: 18px; transition: border-color .2s ease, background-color .2s ease; }
.service-card:hover { border-color: rgba(245, 183, 59, .55); background: rgba(255, 255, 255, .08); }
.service-card h3 { margin: 22px 0 6px; font-size: 1rem; font-weight: 800; color: #fff; }
.service-card p { margin: 0; font-size: .8rem; color: var(--muted); }
.card-link { margin-top: auto; padding-top: 16px; font-size: .8rem; font-weight: 800; color: var(--gold); }
.about-panel { display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 40px; align-items: center; }
.about-seal-img { width: 220px; height: 220px; border-radius: 50%; box-shadow: 0 16px 40px -12px rgba(0, 0, 0, .55); }
.seal-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 22px 0 0; }
.seal-facts div { padding: 12px 14px; background: rgba(255, 255, 255, .05); border: 1px solid var(--panel-line); border-radius: 14px; }
.seal-facts dt { font-size: .84rem; font-weight: 800; color: var(--gold); }
.seal-facts dd { margin: 2px 0 0; font-size: .76rem; color: var(--muted); }
.site-footer { margin-top: 40px; text-align: center; font-size: .76rem; color: #eef5ef; text-shadow: 0 1px 6px rgba(0, 0, 0, .5); }

/* Responsive */
@media (max-width: 980px) {
    .hero-grid { grid-template-columns: minmax(0, 1fr); gap: 36px; justify-items: start; }
    .auth-card { width: 100%; max-width: 520px; }
    .service-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 860px) {
    .nav-toggle { display: flex; }
    .nav-links { position: absolute; top: calc(var(--nav-h) - 4px); left: 12px; right: 12px; flex-direction: column; align-items: stretch; gap: 4px; padding: 10px; background: rgba(7, 22, 33, .97); border: 1px solid var(--panel-line); border-radius: 16px; display: none; }
    .nav-links.is-open { display: flex; }
    .nav-links a { padding: 12px 14px; }
    .nav-links .nav-cta { margin: 4px 0 0; text-align: center; }
}
@media (max-width: 720px) {
    .panel { padding: 28px 22px; }
    .about-panel { grid-template-columns: 1fr; justify-items: center; text-align: center; gap: 24px; }
    .about-seal-img { width: 160px; height: 160px; }
    .seal-facts { grid-template-columns: 1fr; text-align: left; }
    .auth-hero .brand-logo { width: 140px; height: 140px; }
}
@media (max-width: 560px) {
    .page-section { padding-left: 16px; padding-right: 16px; }
    .service-grid { grid-template-columns: 1fr; }
    .auth-card { padding: 32px 22px 22px; }
}
@media (prefers-reduced-motion: reduce) {
    html { scroll-behavior: auto; }
    .train { animation: none; transform: translateX(900px); }
    * { transition-duration: .01ms !important; }
}
    </style>
</head>
<body class="auth-page">

<!-- Illustrated background (decorative) -->
<div class="scene" aria-hidden="true">
    <svg viewBox="0 0 1920 1080" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg" focusable="false">
        <defs>
            <linearGradient id="sc-sky" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#0a2236"/><stop offset=".45" stop-color="#14495a"/><stop offset=".74" stop-color="#2d6a63"/><stop offset="1" stop-color="#b98a3c"/>
            </linearGradient>
            <radialGradient id="sc-sun" cx=".5" cy=".5" r=".5">
                <stop offset="0" stop-color="#ffd27a" stop-opacity=".9"/><stop offset=".5" stop-color="#f0a948" stop-opacity=".35"/><stop offset="1" stop-color="#f0a948" stop-opacity="0"/>
            </radialGradient>
            <linearGradient id="sc-ray" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#fff" stop-opacity=".16"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
            </linearGradient>
            <linearGradient id="sc-sand" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#e0ac49"/><stop offset="1" stop-color="#b9822f"/>
            </linearGradient>
            <linearGradient id="sc-grass" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#2f8a4e"/><stop offset="1" stop-color="#1a5434"/>
            </linearGradient>
            <g id="sc-frond">
                <path d="M0 0C50-70 170-80 250 5C175-28 85-26 0 0Z" fill="currentColor"/>
                <path d="M0 0C60-40 160-50 250 5" fill="none" stroke="#0f4a31" stroke-width="3" opacity=".7"/>
            </g>
            <g id="sc-palm">
                <path d="M-15 0C-24 220-8 450-22 740H22C8 450 22 220 15 0Z" fill="#6a3a24"/>
                <path d="M0 10C-6 220 6 450 0 740" fill="none" stroke="#3f2214" stroke-width="30" stroke-dasharray="2 22" opacity=".45"/>
                <use href="#sc-frond" transform="scale(-1 1) rotate(-95)" style="color:#176a40"/>
                <use href="#sc-frond" transform="rotate(-95)" style="color:#176a40"/>
                <use href="#sc-frond" transform="scale(-1 1) rotate(-55)" style="color:#1d7a47"/>
                <use href="#sc-frond" transform="rotate(-55)" style="color:#1d7a47"/>
                <use href="#sc-frond" transform="scale(-1 1) rotate(-15)" style="color:#23864f"/>
                <use href="#sc-frond" transform="rotate(-15)" style="color:#23864f"/>
                <use href="#sc-frond" transform="scale(-1 1) rotate(25)" style="color:#2b9658"/>
                <use href="#sc-frond" transform="rotate(25)" style="color:#2b9658"/>
                <use href="#sc-frond" transform="scale(-1 1) rotate(60)" style="color:#33a562"/>
                <use href="#sc-frond" transform="rotate(60)" style="color:#33a562"/>
                <circle cx="-13" cy="16" r="14" fill="#7a4a26"/>
                <circle cx="11" cy="20" r="14" fill="#8a5630"/>
                <circle cx="-1" cy="32" r="14" fill="#6c4020"/>
            </g>
            <g id="sc-car">
                <rect width="200" height="70" rx="8" fill="#f1f4f0"/>
                <rect y="52" width="200" height="8" fill="#2f8a57"/>
                <rect x="18" y="14" width="44" height="26" rx="4" fill="#4aa8e0"/>
                <rect x="78" y="14" width="44" height="26" rx="4" fill="#4aa8e0"/>
                <rect x="138" y="14" width="44" height="26" rx="4" fill="#4aa8e0"/>
            </g>
            <g id="sc-engine">
                <path d="M0 70V10a8 8 0 0 1 8-8h160l50 28v40z" fill="#eeb03e"/>
                <rect y="52" width="218" height="8" fill="#d6372d"/>
                <rect x="24" y="14" width="40" height="26" rx="4" fill="#173b57"/>
                <rect x="80" y="14" width="40" height="26" rx="4" fill="#173b57"/>
            </g>
        </defs>

        <rect width="1920" height="1080" fill="url(#sc-sky)"/>
        <polygon points="820,0 1020,0 1560,800 560,800" fill="url(#sc-ray)"/>
        <polygon points="260,0 380,0 940,760 480,760" fill="url(#sc-ray)" opacity=".7"/>
        <polygon points="1300,0 1420,0 1700,720 1180,720" fill="url(#sc-ray)" opacity=".5"/>
        <circle cx="960" cy="650" r="540" fill="url(#sc-sun)"/>
        <ellipse cx="1180" cy="330" rx="190" ry="40" fill="#fff" opacity=".13"/>
        <ellipse cx="1290" cy="312" rx="110" ry="30" fill="#fff" opacity=".1"/>
        <path d="M0 790 L260 560 L520 730 L820 520 L1120 710 L1420 540 L1700 700 L1920 600 V920 H0Z" fill="#1b5a60" opacity=".95"/>
        <path d="M0 840 L330 680 L640 800 L980 640 L1340 790 L1640 660 L1920 780 V920 H0Z" fill="#16494c"/>
        <g transform="translate(1010 668)">
            <rect width="66" height="50" fill="#e9dcc4"/>
            <polygon points="-8,0 33,-30 74,0" fill="#c2433a"/>
            <rect x="12" y="14" width="16" height="16" fill="#4aa8e0"/>
            <rect x="40" y="14" width="16" height="16" fill="#4aa8e0"/>
        </g>
        <rect y="770" width="1920" height="170" fill="url(#sc-sand)"/>
        <path d="M0 805H1920M0 835H1920M0 865H1920" stroke="#fff" stroke-width="2" opacity=".1"/>
        <rect y="900" width="1920" height="180" fill="url(#sc-grass)"/>
        <line x1="0" x2="1920" y1="942" y2="942" stroke="#8c5446" stroke-width="12" stroke-dasharray="6 26"/>
        <rect y="924" width="1920" height="5" fill="#e8dcc6"/>
        <rect y="936" width="1920" height="4" fill="#d7c9ae"/>
        <g class="train">
            <g transform="translate(0 842)">
                <use href="#sc-car" x="0"/><use href="#sc-car" x="210"/><use href="#sc-car" x="420"/><use href="#sc-engine" x="630"/>
                <rect x="198" y="46" width="14" height="6" fill="#2a2a2a"/>
                <rect x="408" y="46" width="14" height="6" fill="#2a2a2a"/>
                <rect x="618" y="46" width="14" height="6" fill="#2a2a2a"/>
                <?php foreach ([0, 210, 420, 630] as $x): ?>
                    <circle cx="<?= $x + 34 ?>" cy="71" r="12" fill="#1c2b33" stroke="#e8e4da" stroke-width="3"/>
                    <circle cx="<?= $x + 166 ?>" cy="71" r="12" fill="#1c2b33" stroke="#e8e4da" stroke-width="3"/>
                <?php endforeach; ?>
            </g>
        </g>
        <use href="#sc-palm" transform="translate(190 300) scale(1.1)"/>
        <use href="#sc-palm" transform="translate(410 470) scale(.7)"/>
        <use href="#sc-palm" transform="translate(1730 330) scale(-1.15 1.15)"/>
        <use href="#sc-palm" transform="translate(1560 520) scale(-.75 .75)"/>
    </svg>
</div>

<!-- Navigation -->
<header class="site-nav">
    <div class="nav-inner">
        <a class="brand" href="#home" aria-label="E-Barangay, back to top">
            <img src="<?= e($logo) ?>" alt="Seal of <?= e(APP_BARANGAY) ?>">
            <span>
                <strong>E-Barangay</strong>
                <small>Bigaan, Calauag, Quezon</small>
            </span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-menu" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <nav id="nav-menu" class="nav-links" aria-label="Page sections">
            <a href="#home">Home</a>
            <a href="#services">Public services</a>
            <a href="#about">About</a>
            <a class="nav-cta" href="<?= e(app_url('register.php')) ?>">Create account</a>
        </nav>
    </div>
</header>

<main>
    <!-- HOME -->
    <section id="home" class="page-section" data-section>
        <div class="container hero-grid">
            <div class="auth-hero">
                <img class="brand-logo" src="<?= e($logo) ?>" alt="Seal of <?= e(APP_BARANGAY) ?>">
                <p class="place-line">Barangay Bigaan &middot; Calauag, Quezon</p>
                <h1>Serbisyo ng Barangay, abot-kamay na.</h1>
                <p class="lead">Request barangay documents, follow your application, and see what is happening in the community, all from one secure portal.</p>
                <ul class="auth-points">
                    <?php foreach ($points as [$icon, $title, $text]): ?>
                        <li>
                            <span class="tile"><?= lg_icon($icon) ?></span>
                            <span><strong><?= e($title) ?></strong><span class="sub"><?= e($text) ?></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="auth-card">
                <h2>Welcome back</h2>
                <p class="card-sub">Sign in to request and track your documents.</p>

                <?php foreach ($alerts as $alert): ?>
                    <div class="alert alert-<?= e($alert['type']) ?>" role="alert"><?= e($alert['message']) ?></div>
                <?php endforeach; ?>

                <form method="post" class="login-form" autocomplete="on">
                    <fieldset class="portal-field">
                        <legend class="sr-only">Sign in to</legend>
                        <div class="portal-switch">
                            <label class="portal-option">
                                <input type="radio" name="portal" value="Resident" <?= checked(old('portal', 'Resident') !== 'Official') ?>>
                                <span>Resident</span>
                            </label>
                            <label class="portal-option">
                                <input type="radio" name="portal" value="Official" <?= checked(old('portal', 'Resident') === 'Official') ?>>
                                <span>Official / Admin</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="field">
                        <label for="identifier">Username</label>
                        <div class="input-wrap">
                            <span class="lead-icon"><?= lg_icon('user') ?></span>
                            <input class="form-control" id="identifier" type="text" name="identifier" placeholder="Enter your username" autocomplete="username" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <span class="lead-icon"><?= lg_icon('lock') ?></span>
                            <input class="form-control" id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                            <button class="toggle-pass" type="button" aria-label="Show password" aria-pressed="false">
                                <span class="icon-on"><?= lg_icon('eye') ?></span>
                                <span class="icon-off"><?= lg_icon('eye-off') ?></span>
                            </button>
                        </div>
                    </div>

                    <button class="btn" type="submit">Sign in</button>
                </form>

                <p class="card-foot">New to the portal? <a href="<?= e(app_url('register.php')) ?>">Register as a resident</a></p>

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
            </div>
        </div>
    </section>

    <!-- PUBLIC SERVICES -->
    <section id="services" class="page-section" data-section aria-labelledby="services-title">
        <div class="container">
            <div class="panel">
                <h2 id="services-title">Public services</h2>
                <p class="panel-lead">Services you can request online from Barangay Bigaan. Sign in to your resident account to start a request.</p>
                <div class="service-grid">
                    <?php foreach ($services as [$icon, $title, $text, $cta]): ?>
                        <a class="service-card" href="#home" data-focus="identifier">
                            <span class="tile"><?= lg_icon($icon) ?></span>
                            <h3><?= e($title) ?></h3>
                            <p><?= e($text) ?></p>
                            <span class="card-link"><?= e($cta) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT -->
    <section id="about" class="page-section page-section--end" data-section aria-labelledby="about-title">
        <div class="container">
            <div class="panel about-panel">
                <img class="about-seal-img" src="<?= e($logo) ?>" alt="Seal of <?= e(APP_BARANGAY) ?>">
                <div>
                    <h2 id="about-title">About Barangay Bigaan</h2>
                    <p class="panel-lead">Barangay Bigaan is in Calauag, Quezon. E-Barangay brings its everyday services online, so residents can request documents and follow their applications without waiting in line.</p>
                    <dl class="seal-facts">
                        <div><dt>Niyog</dt><dd>Coconut palms on the barangay seal.</dd></div>
                        <div><dt>Riles ng tren</dt><dd>The railway that runs through the seal.</dd></div>
                        <div><dt>Gabi at palay</dt><dd>Crops that grow in the community.</dd></div>
                    </dl>
                </div>
            </div>
            <footer class="site-footer">Barangay Bigaan, Calauag, Quezon &middot; E-Barangay System</footer>
        </div>
    </section>
</main>

<script>
(() => {
    'use strict';

    const nav = document.querySelector('.site-nav');
    const toggle = document.querySelector('.nav-toggle');
    const menu = document.getElementById('nav-menu');
    const navLinks = Array.from(document.querySelectorAll('.nav-links a[href^="#"]'));
    const sections = Array.from(document.querySelectorAll('[data-section]'));
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let lockUntil = 0; // pause scrollspy while a click-triggered scroll is running

    /* Mobile menu */
    const setMenu = (open) => {
        menu.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setMenu(!menu.classList.contains('is-open')));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });
    document.addEventListener('click', (e) => {
        if (menu.classList.contains('is-open') && !nav.contains(e.target)) setMenu(false);
    });

    /* Active nav link */
    const setActive = (id) => {
        navLinks.forEach((a) => {
            const on = a.getAttribute('href') === '#' + id;
            a.classList.toggle('is-active', on);
            if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
        });
    };

    /* Smooth scroll to a section */
    const scrollToId = (id, opts = {}) => {
        const target = document.getElementById(id);
        if (!target) return false;
        const push = opts.push !== false;
        const top = id === 'home' ? 0 : target.getBoundingClientRect().top + window.scrollY;

        lockUntil = performance.now() + (reduceMotion.matches ? 50 : 1000);
        window.scrollTo({ top, behavior: reduceMotion.matches ? 'auto' : 'smooth' });
        setActive(id);
        if (push && location.hash !== '#' + id) history.pushState(null, '', '#' + id);

        if (opts.focusId) {
            window.setTimeout(() => {
                const el = document.getElementById(opts.focusId);
                if (el) el.focus({ preventScroll: true });
            }, reduceMotion.matches ? 0 : 700);
        }
        return true;
    };

    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href^="#"]');
        if (!link) return;
        const hash = link.getAttribute('href');
        if (hash.length < 2) return;
        if (scrollToId(decodeURIComponent(hash.slice(1)), { focusId: link.dataset.focus || null })) {
            e.preventDefault();
            setMenu(false);
        }
    });

    window.addEventListener('popstate', () => {
        scrollToId(decodeURIComponent(location.hash.slice(1)) || 'home', { push: false });
    });

    /* Scrollspy */
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            if (performance.now() < lockUntil) return;
            entries.forEach((entry) => { if (entry.isIntersecting) setActive(entry.target.id); });
        }, { rootMargin: '-45% 0px -45% 0px' });
        sections.forEach((s) => observer.observe(s));
    }
    const initial = decodeURIComponent(location.hash.slice(1));
    setActive(document.getElementById(initial) ? initial : 'home');

    /* Password show / hide */
    const password = document.getElementById('password');
    const togglePass = document.querySelector('.toggle-pass');
    togglePass.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        togglePass.setAttribute('aria-pressed', String(show));
        togglePass.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    /* Demo credentials autofill */
    const identifier = document.getElementById('identifier');
    document.querySelectorAll('[data-demo-email]').forEach((btn) => {
        btn.addEventListener('click', () => {
            identifier.value = btn.dataset.demoEmail || '';
            password.value = btn.dataset.demoPassword || '';
            const radio = document.querySelector('input[name="portal"][value="' + (btn.dataset.demoPortal || 'Resident') + '"]');
            if (radio) radio.checked = true;
            identifier.focus();
        });
    });
})();
</script>
</body>
</html>