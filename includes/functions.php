<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(): string
{
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = preg_replace('#/(modules|public)$#', '', $script);
    return rtrim($base === '/' ? '' : $base, '/');
}

function app_url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function selected(mixed $actual, mixed $expected): string
{
    return (string) $actual === (string) $expected ? 'selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_value(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function run_query(string $sql, array $params = []): bool
{
    $stmt = db()->prepare($sql);
    return $stmt->execute($params);
}

function generate_reference(string $prefix): string
{
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function module_meta(string $slug): ?array
{
    $modules = app_modules();
    return $modules[$slug] ?? null;
}

function module_url(string $slug): string
{
    $module = module_meta($slug);
    if (!$module) {
        return 'dashboard.php';
    }

    if (($module['type'] ?? '') === 'records') {
        return 'modules/records.php?module=' . urlencode($slug);
    }

    return $module['url'] ?? 'dashboard.php';
}

function status_class(string $status): string
{
    $normalized = strtolower(str_replace([' ', '/'], '-', $status));
    return 'status status-' . preg_replace('/[^a-z0-9-]/', '', $normalized);
}

function peso(mixed $amount): string
{
    return 'PHP ' . number_format((float) $amount, 2);
}

function payment_method_label(string $method): string
{
    return match (strtolower(trim($method))) {
        'gcash', 'maya', 'maribank', 'e-wallet', 'ewallet' => 'E-Wallet',
        'online transfer', 'bank transfer' => 'Bank Transfer',
        default => $method,
    };
}

function payment_status_label(string $status): string
{
    return match (strtolower(trim($status))) {
        'paid' => 'Approved',
        'unpaid', 'partial' => 'Pending',
        'refunded', 'cancelled' => 'Rejected',
        default => $status,
    };
}

function current_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'local';
}

function audit_log(string $action, string $entity, ?int $entityId = null, string $details = ''): void
{
    $current = current_user();
    $userId = $current['id'] ?? null;
    run_query(
        'INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$userId, $action, $entity, $entityId, $details, current_ip()]
    );
}

function record_history(int $recordId, string $status, string $notes = ''): void
{
    $current = current_user();
    $userId = $current['id'] ?? null;
    run_query(
        'INSERT INTO record_history (record_id, status, notes, changed_by, created_at)
         VALUES (?, ?, ?, ?, NOW())',
        [$recordId, $status, $notes, $userId]
    );
}

function notify_user(?int $userId, string $title, string $message, string $channel = 'In-App'): void
{
    if (!$userId) {
        return;
    }

    run_query(
        'INSERT INTO notifications (user_id, title, message, channel, is_read, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())',
        [$userId, $title, $message, $channel]
    );
}

function group_modules_for_sidebar(): array
{
    $groups = [];

    foreach (app_modules() as $slug => $module) {
        if (!can_access_module($slug)) {
            continue;
        }

        $groups[$module['group']][] = [$slug, $module];
    }

    return $groups;
}

function resident_full_name(array $resident): string
{
    return trim(($resident['first_name'] ?? '') . ' ' . ($resident['middle_name'] ?? '') . ' ' . ($resident['last_name'] ?? '') . ' ' . ($resident['suffix'] ?? ''));
}

function current_resident_id(): ?int
{
    $user = current_user();
    return isset($user['resident_id']) ? (int) $user['resident_id'] : null;
}


function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_verify(): bool
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    return $sent !== '' && hash_equals(csrf_token(), $sent);
}

function csrf_inject_forms(string $html): string
{
    $field = '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
    return preg_replace('/(<form\b[^>]*\bmethod=["\']post["\'][^>]*>)/i', '$1' . $field, $html) ?? $html;
}

function can_transition(string $from, string $to, string $slug): bool
{
    $allowed = workflow_transitions()[$from] ?? [];
    if (!in_array($to, $allowed, true)) {
        return false;
    }
    // Approval / signature belongs to the Punong Barangay (or the administrator).
    if ($from === 'For Approval' && $to === 'Processing' && in_array($slug, ['documents', 'permits', 'official_records', 'budget'], true)) {
        return in_array(role_slug(), ['punong_barangay', 'admin'], true);
    }
    return true;
}

/**
 * Validate and store an uploaded file.
 * Returns [stored_filename, original_name] or null when no file was chosen.
 * Throws RuntimeException with a user-friendly message on any problem.
 */
function save_upload(string $field, string $dir, bool $imagesOnly = false, int $maxBytes = 5242880): ?array
{
    if (empty($_FILES[$field]) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The file could not be uploaded (error code ' . (int) $file['error'] . '). Try a smaller file.');
    }

    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException('The file is too large. Maximum size is ' . (int) ($maxBytes / 1048576) . ' MB.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$imagesOnly) {
        $allowed['application/pdf'] = 'pdf';
    }

    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException($imagesOnly ? 'Only JPG, PNG or WEBP images are allowed.' : 'Only JPG, PNG, WEBP or PDF files are allowed.');
    }

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('The upload folder is not writable.');
    }

    $stored = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $stored)) {
        throw new RuntimeException('The file could not be saved on the server.');
    }

    $original = preg_replace('/[^\w.\- ]+/u', '_', basename((string) $file['name'])) ?: 'attachment';
    return [$stored, mb_substr($original, 0, 200)];
}

function storage_dir(): string
{
    return dirname(__DIR__) . '/storage/uploads';
}

function posts_dir(): string
{
    return dirname(__DIR__) . '/uploads/posts';
}

/** Barangay place line used on certificates, e.g. "Barangay Bigaan, Calauag, Quezon". */
function barangay_place(): string
{
    return APP_BARANGAY . ', ' . APP_MUNICIPALITY . ', ' . APP_PROVINCE;
}
