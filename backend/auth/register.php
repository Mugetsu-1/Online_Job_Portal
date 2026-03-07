<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';

try {
    $data = read_json_body();
    require_fields($data, ['email', 'password', 'full_name', 'role']);
    throttleGuard('auth_register_' . getClientIp(), 6, 300);

    $email = strtolower(trim($data['email']));
    $password = (string)$data['password'];
    $full_name = trim((string)$data['full_name']);

    $allowed_roles = ['job_seeker', 'employer'];
    if (!in_array($data['role'], $allowed_roles)) {
        api_error('Role must be job_seeker or employer', 400, 'VALIDATION_ERROR');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        api_error('Valid email is required', 400, 'VALIDATION_ERROR');
    }

    if ($full_name === '') {
        api_error('Full name is required', 400, 'VALIDATION_ERROR');
    }

    if (!isValidStrongPassword($password)) {
        api_error('Password must be 8-32 chars with uppercase, lowercase, number, and special symbol', 400, 'VALIDATION_ERROR');
    }
    $phone = isset($data['phone']) ? trim((string)$data['phone']) : null;
    if (!isValidPhoneNumber($phone)) {
        api_error('Phone must be 10 digits and start with 9841 or 9746', 400, 'VALIDATION_ERROR');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        api_error('Email already registered', 409, 'DUPLICATE_EMAIL');
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $role = $data['role'];

    if ($role === 'job_seeker') {
        $skills = isset($data['skills']) ? trim($data['skills']) : null;
        $experience_years = isset($data['experience_years']) ? (int)$data['experience_years'] : null;
        $education = isset($data['education']) ? trim($data['education']) : null;
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, full_name, phone, skills, experience_years, education) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$email, $password_hash, $role, $full_name, $phone, $skills, $experience_years, $education]);
    } else {
        $company_name = isset($data['company_name']) ? trim($data['company_name']) : null;
        $company_website = isset($data['company_website']) ? trim($data['company_website']) : null;
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, full_name, phone, company_name, company_website) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$email, $password_hash, $role, $full_name, $phone, $company_name, $company_website]);
    }

    $user_id = $db->lastInsertId('users_id_seq');
    $stmt = $db->prepare("SELECT id, email, role, full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    api_success('Registration successful', $user, 201);

} catch (Throwable $e) {
    api_error('Unexpected server error', 500, 'SERVER_ERROR');
}
?>
