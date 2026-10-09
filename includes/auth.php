<?php

declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;

    if ($user !== null && (int) $user['id'] === (int) $_SESSION['user_id']) {
        return $user;
    }

    $user = fetch_one(
        'SELECT users.*, roles.slug AS role_slug, roles.name AS role_name
         FROM users
         JOIN roles ON roles.id = users.role_id
         WHERE users.id = ?',
        [(int) $_SESSION['user_id']]
    );

    return $user ?: null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
}

function role_slug(): string
{
    return current_user()['role_slug'] ?? 'guest';
}

function is_admin(): bool
{
    return role_slug() === 'admin';
}

function role_permissions(): array
{
    return [
        'resident' => ['documents', 'services', 'complaints', 'announcements', 'events', 'facilities', 'feed', 'search'],
        'secretary' => ['residents', 'households', 'puroks', 'documents', 'permits', 'services', 'complaints', 'katarungang', 'peace_order', 'protection', 'officials', 'budget', 'treasury', 'communication', 'announcements', 'feed', 'events', 'assembly_meetings', 'official_records', 'projects', 'facilities', 'assets', 'environment', 'assistance', 'reports', 'search', 'audit'],
        'treasurer' => ['treasury', 'budget', 'documents', 'permits', 'services', 'announcements', 'events', 'feed', 'reports', 'search', 'audit'],
        'punong_barangay' => ['residents', 'households', 'puroks', 'documents', 'permits', 'services', 'complaints', 'katarungang', 'peace_order', 'protection', 'officials', 'budget', 'treasury', 'communication', 'announcements', 'feed', 'events', 'assembly_meetings', 'official_records', 'projects', 'facilities', 'assets', 'environment', 'assistance', 'reports', 'search', 'audit'],
        'staff' => ['residents', 'households', 'documents', 'permits', 'services', 'complaints', 'treasury', 'announcements', 'feed', 'events', 'facilities', 'assets', 'reports', 'search'],
        'admin' => array_keys(app_modules()),
    ];
}

function can_access_module(string $slug): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $module = module_meta($slug);
    if (!$module) {
        return false;
    }

    if (!empty($module['sensitive']) && !in_array(role_slug(), ['secretary', 'punong_barangay', 'admin'], true)) {
        return false;
    }

    $permissions = role_permissions()[role_slug()] ?? [];
    return in_array($slug, $permissions, true);
}

function can_manage_module(string $slug): bool
{
    if (!can_access_module($slug)) {
        return false;
    }

    if (role_slug() === 'resident') {
        return in_array($slug, ['documents', 'services', 'complaints'], true); // facilities has its own checks
    }

    return in_array(role_slug(), ['secretary', 'treasurer', 'punong_barangay', 'staff', 'admin'], true);
}

function can_approve_module(string $slug): bool
{
    if (!can_access_module($slug)) {
        return false;
    }

    if ($slug === 'treasury') {
        return role_slug() !== 'resident';
    }

    if (in_array($slug, ['documents', 'permits', 'katarungang', 'official_records', 'budget'], true)) {
        return role_slug() !== 'resident';
    }

    return role_slug() !== 'resident';
}

function require_module_access(string $slug): void
{
    require_login();

    if (!can_access_module($slug)) {
        http_response_code(403);
        require_once __DIR__ . '/layout.php';
        render_header('Access restricted');
        echo '<section class="empty-state"><h1>Access restricted</h1><p>Your account role cannot open this module.</p><a class="btn" href="' . e(app_url('dashboard.php')) . '">Back to dashboard</a></section>';
        render_footer();
        exit;
    }
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
}
