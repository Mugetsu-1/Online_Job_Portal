<?php
require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
        http_response_code(403);
        throw new Exception("Only employers can update jobs");
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        throw new Exception("Job ID is required");
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM jobs WHERE id = ? AND employer_id = ?");
    $stmt->execute([$data['id'], $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        throw new Exception("Job not found or you don't have permission to update it");
    }

    $update_fields = [];
    $params = [];
    $allowed_fields = [
        'title', 'description', 'requirements', 'responsibilities',
        'job_type', 'location', 'salary_min', 'salary_max', 'salary_currency',
        'experience_required', 'education_required', 'application_deadline',
        'positions_available', 'is_active'
    ];

    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (isset($data['skills_required'])) {
        $update_fields[] = "skills_required = ?";
        $params[] = is_array($data['skills_required']) ? implode(', ', $data['skills_required']) : $data['skills_required'];
    }

    if (empty($update_fields)) {
        throw new Exception("No fields to update");
    }

    if (isset($data['salary_min']) && isset($data['salary_max']) && $data['salary_max'] < $data['salary_min']) {
        throw new Exception("Maximum salary must be greater than or equal to minimum salary");
    }

    if (in_array('job_type', array_keys($data))) {
        $idx = array_search('job_type', $allowed_fields);
        $valid_types = ['full_time', 'part_time', 'contract', 'internship', 'remote'];
        if (!in_array($data['job_type'], $valid_types)) {
            throw new Exception("Invalid job_type");
        }
    }

    $params[] = $data['id'];
    $sql = "UPDATE jobs SET " . implode(', ', $update_fields) . " WHERE id = ? RETURNING updated_at";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Job updated successfully',
        'data' => ['updated_at' => $result['updated_at']]
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
