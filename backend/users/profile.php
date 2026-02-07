<?php
require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        throw new Exception("You must be logged in to view profile");
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, email, role, full_name, phone, profile_picture, resume_path, skills, experience_years, education, bio, company_name, company_website, company_logo, company_description, email_verified, created_at, updated_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        throw new Exception("User not found");
    }

    unset($user['is_active']);
    $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
    $user['updated_at'] = date('Y-m-d H:i:s', strtotime($user['updated_at']));

    echo json_encode([
        'success' => true,
        'data' => $user
    ]);

} catch (Exception $e) {
    $code = http_response_code();
    if ($code === 200) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
