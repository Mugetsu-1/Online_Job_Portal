<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        throw new Exception("You must be logged in to change password");
    }
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['current_password']) || !isset($data['new_password'])) {
        throw new Exception("Current password and new password are required");
    }
    
    // Validate new password strength
    if (strlen($data['new_password']) < 8) {
        throw new Exception("New password must be at least 8 characters long");
    }
    
    // Check if new password is different from current
    if ($data['current_password'] === $data['new_password']) {
        throw new Exception("New password must be different from current password");
    }
    
    $db = getDB();
    
    // Get current password hash
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Verify current password
    if (!password_verify($data['current_password'], $user['password_hash'])) {
        throw new Exception("Current password is incorrect");
    }
    
    // Hash new password
    $new_password_hash = password_hash($data['new_password'], PASSWORD_BCRYPT);
    
    // Update password
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$new_password_hash, $_SESSION['user_id']]);
    
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