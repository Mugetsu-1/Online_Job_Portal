<?php
require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        throw new Exception("You must be logged in to change password");
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['current_password']) || !isset($data['new_password'])) {
        throw new Exception("Current password and new password are required");
    }

    if (strlen($data['new_password']) < 4) {
        throw new Exception("New password must be at least 4 characters");
    }

    if ($data['current_password'] === $data['new_password']) {
        throw new Exception("New password must be different from current password");
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found");
    }

    if (!password_verify($data['current_password'], $user['password_hash'])) {
        throw new Exception("Current password is incorrect");
    }

    $new_hash = password_hash($data['new_password'], PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$new_hash, $_SESSION['user_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);

} catch (Exception $e) {
    $code = http_response_code();
    if ($code === 200) {
        http_response_code(400);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
