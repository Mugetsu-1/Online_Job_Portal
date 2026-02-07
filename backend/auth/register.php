<?php
require_once __DIR__ . '/../config/db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['email']) || !isset($data['password']) || !isset($data['full_name']) || !isset($data['role'])) {
        throw new Exception("Email, password, full_name and role are required");
    }

    $allowed_roles = ['job_seeker', 'employer'];
    if (!in_array($data['role'], $allowed_roles)) {
        throw new Exception("Role must be job_seeker or employer");
    }

    if (strlen($data['password']) < 4) {
        throw new Exception("Password must be at least 4 characters");
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([trim($data['email'])]);
    if ($stmt->fetch()) {
        throw new Exception("Email already registered");
    }

    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
    $full_name = trim($data['full_name']);
    $phone = isset($data['phone']) ? trim($data['phone']) : null;
    $role = $data['role'];

    if ($role === 'job_seeker') {
        $skills = isset($data['skills']) ? trim($data['skills']) : null;
        $experience_years = isset($data['experience_years']) ? (int)$data['experience_years'] : null;
        $education = isset($data['education']) ? trim($data['education']) : null;
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, full_name, phone, skills, experience_years, education) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([trim($data['email']), $password_hash, $role, $full_name, $phone, $skills, $experience_years, $education]);
    } else {
        $company_name = isset($data['company_name']) ? trim($data['company_name']) : null;
        $company_website = isset($data['company_website']) ? trim($data['company_website']) : null;
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, full_name, phone, company_name, company_website) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([trim($data['email']), $password_hash, $role, $full_name, $phone, $company_name, $company_website]);
    }

    $user_id = $db->lastInsertId('users_id_seq');
    $stmt = $db->prepare("SELECT id, email, role, full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'data' => $user
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
