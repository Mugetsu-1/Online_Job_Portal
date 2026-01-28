<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in and is an employer
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
        http_response_code(403);
        throw new Exception("Only employers can update jobs");
    }
    
    // Get PUT data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate job ID
    if (!isset($data['id'])) {
        throw new Exception("Job ID is required");
    }
    
    $db = getDB();
    
    // Check if job exists and belongs to the employer
    $stmt = $db->prepare("SELECT id FROM jobs WHERE id = ? AND employer_id = ?");
    $stmt->execute([$data['id'], $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        throw new Exception("Job not found or you don't have permission to update it");
    }
    
    // Update query
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
    
    // Handle skills_required (convert array to string)
    if (isset($data['skills_required'])) {
        $update_fields[] = "skills_required = ?";
        if (is_array($data['skills_required'])) {
            $params[] = implode(', ', $data['skills_required']);
        } else {
            $params[] = $data['skills_required'];
        }
    }
    
    if (empty($update_fields)) {
        throw new Exception("No fields to update");
    }
    
    // Validate salary range if both are being updated
    if (isset($data['salary_min']) && isset($data['salary_max'])) {
        if ($data['salary_max'] < $data['salary_min']) {
            throw new Exception("Maximum salary must be greater than or equal to minimum salary");
        }
    }
    
    // Add job ID to params
    $params[] = $data['id'];
    
   
    $sql = "UPDATE jobs SET " . implode(', ', $update_fields) . " WHERE id = ? RETURNING updated_at";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Job updated successfully',
        'data' => [
            'updated_at' => $result['updated_at']
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