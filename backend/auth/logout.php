<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';

try {
    session_unset();
    session_destroy();
    clearAppCookie(SESSION_COOKIE_NAME);
    clearAppCookie('jp_csrf', ['httponly' => false]);
    clearAppCookie('jp_last_login_email', ['httponly' => false]);
    clearAppCookie('jp_last_login_at', ['httponly' => false]);
    api_success('Logged out successfully');
} catch (Throwable $e) {
    api_error('Unexpected server error', 500, 'SERVER_ERROR');
}
?>
