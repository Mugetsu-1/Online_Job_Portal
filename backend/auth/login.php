<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';

try {
    $data = read_json_body();
    require_fields($data, ['email', 'password']);
    throttleGuard('auth_login_' . getClientIp(), 10, 300);
    $email = strtolower(trim((string)$data['email']));
    $password = (string)$data['password'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        api_error('Valid email is required', 400, 'VALIDATION_ERROR');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, email, password_hash, role, full_name, is_active, company_name, profile_picture, company_logo FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        api_error('Invalid email or password', 401, 'AUTH_FAILED');
    }

    if (!password_verify($password, $user['password_hash'])) {
        api_error('Invalid email or password', 401, 'AUTH_FAILED');
    }

    if (!$user['is_active']) {
        api_error('Account is deactivated. Please contact support.', 403, 'ACCOUNT_INACTIVE');
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $cookie_expiry = time() + (30 * 24 * 60 * 60);
    setcookie('jp_last_login_email', $user['email'], $cookie_expiry, '/');
    setcookie('jp_last_login_at', date('c'), $cookie_expiry, '/');

    $response = [
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
            'profile_picture' => $user['profile_picture']
        ]
    ];
    $response['csrf_token'] = $_SESSION['csrf_token'];

    if ($user['role'] === 'employer') {
        $response['user']['company_name'] = $user['company_name'];
        $response['user']['company_logo'] = $user['company_logo'];
    }

    api_success('Login successful', $response);

} catch (Throwable $e) {
    api_error('Unexpected server error', 500, 'SERVER_ERROR');
}
?>
