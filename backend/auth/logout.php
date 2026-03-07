<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';

try {
    session_unset();
    session_destroy();
    setcookie('jp_last_login_email', '', time() - 3600, '/');
    setcookie('jp_last_login_at', '', time() - 3600, '/');
    api_success('Logged out successfully');
} catch (Throwable $e) {
    api_error('Unexpected server error', 500, 'SERVER_ERROR');
}
?>
