<?php

declare(strict_types=1);

function icon(string $name): string
{
    static $paths = [
        'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M14 14h7v7h-7z M3 14h7v7H3z',
        'users' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
        'home' => 'M3 10.5 12 3l9 7.5V21H3z M9 21v-6h6v6',
        'pin' => 'M12 22s8-6.5 8-12a8 8 0 1 0-16 0c0 5.5 8 12 8 12z M12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
        'file' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M8 13h8 M8 17h8',
        'briefcase' => 'M3 7h18v13H3z M8 7V4h8v3 M3 13h18',
        'heart' => 'M12 21s-8-5.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.5-8 11-8 11z',
        'alert' => 'M12 3 2 20h20z M12 10v5 M12 18v.01',
        'scale' => 'M12 3v18 M5 21h14 M5 7h14 M5 7l-3 7a3 3 0 0 0 6 0z M19 7l-3 7a3 3 0 0 0 6 0z',
        'shield' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
        'lock' => 'M5 11h14v10H5z M8 11V7a4 4 0 0 1 8 0v4',
        'badge' => 'M12 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12z M8.5 14 7 22l5-3 5 3-1.5-8',
        'pie' => 'M21 12A9 9 0 1 1 12 3v9z M12 3a9 9 0 0 1 9 9h-9',
        'receipt' => 'M5 2h14v20l-3-2-2 2-2-2-2 2-2-2-3 2z M9 8h6 M9 12h6',
        'message' => 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
        'megaphone' => 'M3 11v3l14 5V6z M17 8a4 4 0 0 1 0 8 M6 14v5h3v-3',
        'calendar' => 'M3 5h18v16H3z M3 10h18 M8 2v5 M16 2v5',
        'landmark' => 'M3 21h18 M5 21V10 M19 21V10 M9 21V10 M15 21V10 M2 10l10-7 10 7z',
        'trending' => 'M3 17l6-6 4 4 8-8 M15 7h6v6',
        'building' => 'M4 21V3h10v18 M14 9h6v12 M8 7h2 M8 11h2 M8 15h2',
        'box' => 'M21 8l-9-5-9 5v8l9 5 9-5z M3 8l9 5 9-5 M12 13v8',
        'leaf' => 'M5 21c0-10 6-16 16-16 0 10-6 16-16 16z M5 21 14 12',
        'gift' => 'M3 8h18v4H3z M5 12v9h14v-9 M12 8v13 M12 8C9 8 8 4.5 10 4s2 4 2 4 0-4.5 2-4 1 4-2 4z',
        'chart' => 'M4 20V10 M10 20V4 M16 20v-8 M22 20H2',
        'search' => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z M21 21l-4.3-4.3',
        'clipboard' => 'M9 3h6v4H9z M7 5H5a1 1 0 0 0-1 1v15h16V6a1 1 0 0 0-1-1h-2 M8 12h8 M8 16h8',
        'sliders' => 'M4 6h16 M4 12h16 M4 18h16 M9 4v4 M15 10v4 M8 16v4',
        'bell' => 'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9z M13.7 21a2 2 0 0 1-3.4 0',
        'menu' => 'M3 6h18 M3 12h18 M3 18h18',
        'logout' => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4 M16 17l5-5-5-5 M21 12H9',
        'plus' => 'M12 5v14 M5 12h14',
        'qr' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h3v3h-3z M20 14v7 M14 20h3',
        'check' => 'M20 6 9 17l-5-5',
    ];
    $d = $paths[$name] ?? $paths['file'];
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>';
}

function module_icon(string $slug): string
{
    static $map = [
        'residents' => 'users', 'households' => 'home', 'puroks' => 'pin', 'documents' => 'file', 'permits' => 'briefcase',
        'services' => 'heart', 'complaints' => 'alert', 'katarungang' => 'scale', 'peace_order' => 'shield', 'protection' => 'lock',
        'officials' => 'badge', 'budget' => 'pie', 'treasury' => 'receipt', 'communication' => 'message', 'announcements' => 'megaphone',
        'events' => 'calendar', 'assembly_meetings' => 'users', 'official_records' => 'landmark', 'projects' => 'trending',
        'facilities' => 'building', 'assets' => 'box', 'environment' => 'leaf', 'assistance' => 'gift', 'reports' => 'chart',
        'search' => 'search', 'audit' => 'clipboard', 'settings' => 'sliders', 'feed' => 'megaphone',
    ];
    return icon($map[$slug] ?? 'file');
}

function nav_active(string $slug): bool
{
    $module = module_meta($slug);
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (($module['type'] ?? '') === 'records') {
        return $script === 'records.php' && ($_GET['module'] ?? '') === $slug;
    }
    return basename($module['url'] ?? '') === $script;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? substr(end($parts), 0, 1) : ''));
}

function brand_block(string $class = 'brand', string $href = 'dashboard.php'): string
{
    return '<a class="' . $class . '" href="' . e(app_url($href)) . '">'
        . '<img class="brand-logo" src="' . e(app_url('assets/img/logo.svg')) . '" alt="Seal of ' . e(APP_BARANGAY) . '">'
        . '<span><strong>' . e(APP_BARANGAY) . '</strong><small>E-Barangay · ' . e(APP_MUNICIPALITY) . ', ' . e(APP_PROVINCE) . '</small></span></a>';
}

function render_header(string $title, array $options = []): void
{
    global $layoutUser;

    $bodyClass = $options['body_class'] ?? '';
    $user = !empty($options['public']) ? null : current_user();
    $layoutUser = $user;
    $unread = $user ? (int) fetch_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [(int) $user['id']]) : 0;
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c2733">
    <title><?= e($title) ?> - <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(app_url('assets/img/logo.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/styles.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<?php if ($user): ?>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <?= brand_block() ?>
            <nav class="nav" aria-label="Main navigation">
                <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'dashboard.php' ? 'active' : '' ?>" href="<?= e(app_url('dashboard.php')) ?>"><?= icon('dashboard') ?>Dashboard</a>
                <?php foreach (group_modules_for_sidebar() as $group => $items): ?>
                    <div class="nav-group"><?= e($group) ?></div>
                    <?php foreach ($items as [$slug, $module]): ?>
                        <a class="nav-link <?= nav_active($slug) ? 'active' : '' ?>" href="<?= e(app_url(module_url($slug))) ?>"><?= module_icon($slug) ?><?= e($module['label']) ?></a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <div class="nav-group">Future modules</div>
                <?php foreach (future_modules() as $label): ?>
                    <span class="nav-link locked" title="Future module - not part of the active scope"><?= icon('lock') ?><?= e($label) ?><small>Locked</small></span>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="main">
            <header class="topbar">
                <button class="icon-button" type="button" data-toggle-sidebar aria-label="Toggle menu"><?= icon('menu') ?></button>
                <form class="top-search" action="<?= e(app_url('modules/search.php')) ?>" method="get" role="search">
                    <?= icon('search') ?>
                    <input type="search" name="q" placeholder="Search residents, records, payments" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Global search">
                </form>
                <span class="portal-label">Barangay Officials &amp; Admin Portal</span>
                <span class="top-date"><?= e(date('l, F j, Y')) ?></span>
                <a class="notification-pill" href="<?= e(app_url('dashboard.php#notifications')) ?>" aria-label="Notifications">
                    <?= icon('bell') ?><?php if ($unread): ?><span><?= $unread ?></span><?php endif; ?>
                </a>
                <a class="profile-chip" href="<?= e(app_url('modules/profile.php')) ?>" title="My profile">
                    <span class="avatar"><?= e(initials($user['full_name'])) ?></span>
                    <div><strong><?= e($user['full_name']) ?></strong><small><?= e($user['role_name']) ?></small></div>
                </a>
                <a class="notification-pill" href="<?= e(app_url('logout.php')) ?>" aria-label="Log out" title="Log out"><?= icon('logout') ?></a>
            </header>
            <div class="content">
                <?php render_flash(); ?>
<?php else: ?>
        <main class="auth-main">
            <?php render_flash(); ?>
<?php endif;
}

function render_footer(): void
{
    global $layoutUser;

    $user = $layoutUser ?? current_user();
    ?>
<?php if ($user): ?>
            </div>
            <footer class="system-footer"><strong>Mapagdalita, Mark Luigi B. | Mendez, Justin Lloyd B. | Prada, Angel Ivy D.</strong><span>BSIT-LQ-2-1 | PROGRAMMING 3</span></footer>
        </main>
    </div>
<?php else: ?>
        <footer class="system-footer"><strong>Mapagdalita, Mark Luigi B. | Mendez, Justin Lloyd B. | Prada, Angel Ivy D.</strong><span>BSIT-LQ-2-1 | PROGRAMMING 3</span></footer>
        </main>
<?php endif; ?>
    <script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php
}

function render_flash(): void
{
    foreach (flash_messages() as $message) {
        echo '<div class="alert alert-' . e($message['type']) . '" role="status">' . e($message['message']) . '</div>';
    }
}

/** PDF / Excel / Word / CSV links for a dataset handled by modules/export.php. */
function export_buttons(string $dataset, array $params = []): string
{
    $html = '<span class="export-group" style="display:inline-flex;gap:6px;flex-wrap:wrap;align-items:center"><small class="muted">Export:</small>';
    foreach (['pdf' => 'PDF', 'xls' => 'Excel', 'doc' => 'Word', 'csv' => 'CSV'] as $format => $label) {
        $url = app_url('modules/export.php?' . http_build_query(array_merge(['dataset' => $dataset, 'format' => $format], $params)));
        $buttonClass = $format === 'xls' ? 'btn export-excel' : 'btn btn-ghost';
        $buttonLabel = $format === 'xls' ? 'Export to Excel' : $label;
        $html .= '<a class="' . $buttonClass . '" href="' . e($url) . '"' . ($format === 'pdf' ? ' target="_blank" rel="noopener"' : '') . '>' . $buttonLabel . '</a>';
    }
    return $html . '</span>';
}

function page_header(string $title, string $description = '', string $actionHtml = ''): void
{
    echo '<section class="page-header">';
    echo '<div><span class="eyebrow">' . e(APP_BARANGAY) . '</span><h1>' . e($title) . '</h1>';
    if ($description !== '') {
        echo '<p>' . e($description) . '</p>';
    }
    echo '</div>';
    if ($actionHtml !== '') {
        echo '<div class="page-actions">' . $actionHtml . '</div>';
    }
    echo '</section>';
}
