<?php
require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['employer', 'admin'])) {
        http_response_code(403);
        throw new Exception("Unauthorized");
    }

    $job_id = $_GET['id'] ?? null;
    if (!$job_id) {
        throw new Exception("Job ID is required");
    }

    $db = getDB();
    if ($_SESSION['role'] === 'employer') {
        $stmt = $db->prepare("SELECT id FROM jobs WHERE id = ? AND employer_id = ?");
        $stmt->execute([$job_id, $_SESSION['user_id']]);
    } else {
        $stmt = $db->prepare("SELECT id FROM jobs WHERE id = ?");
        $stmt->execute([$job_id]);
    }

    if (!$stmt->fetch()) {
        http_response_code(404);
        throw new Exception("Job not found or you don't have permission to delete it");
    }

    $stmt = $db->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Job deleted successfully'
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
