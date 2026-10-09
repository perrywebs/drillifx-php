<?php
declare(strict_types=1);
// Secure session bootstrap — include FIRST on every page (before any output).
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
// Quiet PHP notices in AJAX JSON responses
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/ajax/') === 0) {
    ini_set('display_errors', '0');
}
