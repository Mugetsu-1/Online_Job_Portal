<?php
require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
        http_response_code(403);
        throw new Exception("Only employers can create jobs");
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['title']) || !isset($data['description'])) {
        throw new Exception("Title and description are required");
    }

    $title = trim($data['title']);
    $description = trim($data['description']);
    $requirements = isset($data['requirements']) ? trim($data['requirements']) : null;
    $responsibilities = isset($data['responsibilities']) ? trim($data['responsibilities']) : null;
    $job_type = isset($data['job_type']) ? $data['job_type'] : 'full_time';
    $location = isset($data['location']) ? trim($data['location']) : null;
    $salary_min = isset($data['salary_min']) ? (float)$data['salary_min'] : null;
    $salary_max = isset($data['salary_max']) ? (float)$data['salary_max'] : null;
    $salary_currency = isset($data['salary_currency']) ? trim($data['salary_currency']) : 'USD';
    $experience_required = isset($data['experience_required']) ? (int)$data['experience_required'] : null;
    $education_required = isset($data['education_required']) ? trim($data['education_required']) : null;
    $skills_required = isset($data['skills_required']) ? (is_array($data['skills_required']) ? implode(', ', $data['skills_required']) : trim($data['skills_required'])) : null;
    $application_deadline = isset($data['application_deadline']) ? $data['application_deadline'] : null;
    $positions_available = isset($data['positions_available']) ? max(1, (int)$data['positions_available']) : 1;

    $valid_types = ['full_time', 'part_time', 'contract', 'internship', 'remote'];
    if (!in_array($job_type, $valid_types)) {
        $job_type = 'full_time';
    }

    if ($salary_min !== null && $salary_max !== null && $salary_max < $salary_min) {
        throw new Exception("Maximum salary must be greater than or equal to minimum salary");
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO jobs (employer_id, title, description, requirements, responsibilities, job_type, location, salary_min, salary_max, salary_currency, experience_required, education_required, skills_required, application_deadline, positions_available) VALUES (?, ?, ?, ?, ?, ?::job_type, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id, created_at");
    $stmt->execute([$_SESSION['user_id'], $title, $description, $requirements, $responsibilities, $job_type, $location, $salary_min, $salary_max, $salary_currency, $experience_required, $education_required, $skills_required, $application_deadline ?: null, $positions_available]);
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Job created successfully',
        'data' => [
            'id' => $result['id'],
            'created_at' => $result['created_at']
        ]
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
