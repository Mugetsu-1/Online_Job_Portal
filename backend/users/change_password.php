<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';

try {
    requireAuth(['job_seeker', 'employer', 'admin']);

    $data = read_json_body();
    require_fields($data, ['current_password', 'new_password']);

    if (!isValidStrongPassword((string)$data['new_password'])) {
        api_error('New password must be 8-32 chars with uppercase, lowercase, number, and special symbol', 400, 'VALIDATION_ERROR');
    }

    if ($data['current_password'] === $data['new_password']) {
        api_error('New password must be different from current password', 400, 'VALIDATION_ERROR');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        api_error('User not found', 404, 'NOT_FOUND');
    }

    if (!password_verify($data['current_password'], $user['password_hash'])) {
        api_error('Current password is incorrect', 401, 'AUTH_FAILED');
    }

    $new_hash = password_hash($data['new_password'], PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$new_hash, $_SESSION['user_id']]);
    auditLog('password.changed', 'user', $_SESSION['user_id']);

    api_success('Password changed successfully');

} catch (Throwable $e) {
    api_error('Unexpected server error', 500, 'SERVER_ERROR');
}
?>
