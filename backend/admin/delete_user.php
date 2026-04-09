<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';

try {
    requireAuth(['admin']);

    $user_id = $_GET['id'] ?? null;
    if (!$user_id) {
        api_error('User ID is required', 400, 'VALIDATION_ERROR');
    }

    $userId = safeInt($user_id, null, 1);
    
    if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
        api_error('You cannot delete your own admin account', 400, 'VALIDATION_ERROR');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, role, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        api_error('User not found', 404, 'NOT_FOUND');
    }
    if (($target['role'] ?? '') === 'admin') {
        api_error('Admin accounts cannot be deleted', 403, 'FORBIDDEN');
    }

    // Delete user (cascades to their jobs and applications via foreign keys)
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    auditLog('admin.user_deleted', 'user', $userId, ['email' => $target['email']]);

    api_success('User deleted successfully', [
        'user_id' => $userId
    ]);
} catch (Throwable $e) {
    logServerEvent('error', 'delete_user_exception', ['error' => $e->getMessage()]);
    $message = APP_DEBUG ? $e->getMessage() : 'Unexpected server error';
    api_error($message, 500, 'SERVER_ERROR');
}
?>
