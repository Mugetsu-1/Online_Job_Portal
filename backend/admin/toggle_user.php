<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';

try {
    requireAuth(['admin']);

    $data = read_json_body();
    require_fields($data, ['user_id', 'is_active']);

    $userId = safeInt($data['user_id'], null, 1);
    $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($isActive === null) {
        api_error('is_active must be true or false', 400, 'VALIDATION_ERROR');
    }
    if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
        api_error('You cannot change your own admin account status', 400, 'VALIDATION_ERROR');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        api_error('User not found', 404, 'NOT_FOUND');
    }
    if (($target['role'] ?? '') === 'admin') {
        api_error('Admin accounts cannot be changed here', 403, 'FORBIDDEN');
    }

    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ? RETURNING updated_at");
    $stmt->execute([$isActive, $userId]);
    $result = $stmt->fetch();

    auditLog('admin.user_status_updated', 'user', $userId, ['is_active' => $isActive]);

    api_success('User status updated', [
        'user_id' => $userId,
        'is_active' => $isActive,
        'updated_at' => $result['updated_at'] ?? null
    ]);
} catch (Throwable $e) {
    api_error('Unexpected server error', 500, 'SERVER_ERROR');
}
?>
