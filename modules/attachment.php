<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
$record = $id ? fetch_one('SELECT id, module_slug, resident_id, attachment_path, attachment_name FROM service_records WHERE id = ?', [$id]) : null;

if (!$record || empty($record['attachment_path']) || !can_access_module((string) $record['module_slug'])) {
    http_response_code(404);
    exit('File not found.');
}

// Residents can only open attachments on their own records.
if (role_slug() === 'resident' && (int) $record['resident_id'] !== (int) current_resident_id()) {
    http_response_code(403);
    exit('You cannot open this file.');
}

$path = storage_dir() . '/' . basename((string) $record['attachment_path']);
if (!is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) $record['attachment_name']) . '"');
readfile($path);
exit;
