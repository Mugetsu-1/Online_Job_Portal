<?php
require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
        http_response_code(403);
        throw new Exception("Only employers can update application status");
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['application_id'])) {
        throw new Exception("Application ID is required");
    }

    $valid_statuses = ['pending', 'reviewed', 'shortlisted', 'rejected', 'accepted'];
    $status = isset($data['status']) ? $data['status'] : null;
    if (!$status || !in_array($status, $valid_statuses)) {
        throw new Exception("Valid status is required: " . implode(', ', $valid_statuses));
    }

    $notes = isset($data['notes']) ? trim($data['notes']) : null;
    $application_id = (int)$data['application_id'];

    $db = getDB();
    $stmt = $db->prepare("SELECT a.id FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.id = ? AND j.employer_id = ?");
    $stmt->execute([$application_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        throw new Exception("Application not found or you don't have permission");
    }

    $stmt = $db->prepare("UPDATE applications SET status = ?::application_status, notes = COALESCE(?, notes), reviewed_at = CASE WHEN ?::application_status IN ('shortlisted', 'rejected', 'accepted') THEN CURRENT_TIMESTAMP ELSE reviewed_at END, updated_at = CURRENT_TIMESTAMP WHERE id = ? RETURNING updated_at, reviewed_at");
    $stmt->execute([$status, $notes, $status, $application_id]);
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Application status updated',
        'data' => [
            'updated_at' => $result['updated_at'],
            'reviewed_at' => $result['reviewed_at']
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
