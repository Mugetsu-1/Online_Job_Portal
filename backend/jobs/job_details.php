<?php
require_once __DIR__ . '/../config/db.php';

try {
    $job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$job_id) {
        throw new Exception("Job ID is required");
    }

    $db = getDB();
    
    // Check if job exists and is active, also check if positions are still available
    $stmt = $db->prepare("
        SELECT j.*, u.company_name, u.company_website, u.company_logo, u.company_description,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'accepted') as accepted_count
        FROM jobs j 
        JOIN users u ON j.employer_id = u.id 
        WHERE j.id = ? AND j.is_active = true
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    if (!$job) {
        http_response_code(404);
        throw new Exception("Job not found");
    }

    // Check if all positions are filled
    if ((int)$job['accepted_count'] >= (int)$job['positions_available']) {
        http_response_code(404);
        throw new Exception("This position has been filled");
    }

    $stmt = $db->prepare("UPDATE jobs SET views_count = views_count + 1 WHERE id = ?");
    $stmt->execute([$job_id]);

    // Remove internal count before returning
    unset($job['accepted_count']);
    
    $job['views_count'] = (int)$job['views_count'] + 1;
    $job['applications_count'] = (int)$job['applications_count'];
    $job['created_at'] = date('Y-m-d H:i:s', strtotime($job['created_at']));
    $job['updated_at'] = date('Y-m-d H:i:s', strtotime($job['updated_at']));
    if ($job['application_deadline']) {
        $job['application_deadline'] = date('Y-m-d', strtotime($job['application_deadline']));
    }
    if ($job['skills_required']) {
        $job['skills_required'] = array_map('trim', explode(',', $job['skills_required']));
    }
    if (!empty($job['company_logo'])) {
        $job['company_logo'] = publicUploadUrl($job['company_logo']);
    }

    echo json_encode([
        'success' => true,
        'data' => $job
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
