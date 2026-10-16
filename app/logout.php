<?php
/**
 * Sign Out / Logout Handler
 */
require_once dirname(__DIR__) . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session data
$_SESSION = [];
session_unset();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"] ?: '/', $params["domain"] ?: '', $params["secure"] ?? false, $params["httponly"] ?? false);
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy session and force write to disk
session_destroy();
session_write_close();

// Redirect directly to /login
header('Location: /login');
exit;
