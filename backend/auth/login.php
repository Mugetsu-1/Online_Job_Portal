<?php
require_once __DIR__ . '/../config/db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['email']) || !isset($data['password'])) {
        throw new Exception("Email and password are required");
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, email, password_hash, role, full_name, is_active, company_name, profile_picture, company_logo FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("Invalid email or password");
    }

    if (!password_verify($data['password'], $user['password_hash'])) {
        throw new Exception("Invalid email or password");
    }

    if (!$user['is_active']) {
        throw new Exception("Account is deactivated. Please contact support.");
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];

    $response = [
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
            'profile_picture' => $user['profile_picture']
        ]
    ];

    if ($user['role'] === 'employer') {
        $response['user']['company_name'] = $user['company_name'];
        $response['user']['company_logo'] = $user['company_logo'];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
