<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// CSRF protection: every POST form gets a hidden token automatically, and every POST is verified.
if (PHP_SAPI !== 'cli') {
    ob_start('csrf_inject_forms');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(419);
        exit('Your session expired or the form was invalid. Go back, refresh the page and try again.');
    }
}
