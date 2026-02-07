<?php
require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'job_seeker') {
        http_response_code(403);
        throw new Exception("Only job seekers can withdraw applications");
    }

    $application_id = $_GET['id'] ?? null;
    if (!$application_id) {
        throw new Exception("Application ID is required");
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, status FROM applications WHERE id = ? AND applicant_id = ?");
    $stmt->execute([$application_id, $_SESSION['user_id']]);
    $application = $stmt->fetch();

    if (!$application) {
        http_response_code(404);
        throw new Exception("Application not found or you don't have permission to withdraw it");
    }

    if (in_array($application['status'], ['accepted', 'rejected'])) {
        throw new Exception("Cannot withdraw application with status: " . $application['status']);
    }

    $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
    $stmt->execute([$application_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Application withdrawn successfully'
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
