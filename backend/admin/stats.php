<?php
require_once __DIR__ . '/../config/db.php';

try {
    requireAuth(['admin']);

    $db = getDB();
    $stmt = $db->query("
        SELECT
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'job_seeker') AS total_job_seekers,
            (SELECT COUNT(*) FROM users WHERE role = 'employer') AS total_employers,
            (SELECT COUNT(*) FROM users WHERE is_active = true) AS active_users,
            (SELECT COUNT(*) FROM jobs) AS total_jobs,
            (SELECT COUNT(*) FROM jobs WHERE is_active = true) AS active_jobs,
            (SELECT COUNT(*) FROM applications) AS total_applications,
            (SELECT COUNT(*) FROM applications WHERE status = 'pending') AS pending_applications
    ");
    $stats = $stmt->fetch() ?: [];

    foreach ($stats as $key => $value) {
        $stats[$key] = (int)$value;
    }

    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
} catch (Exception $e) {
    $code = http_response_code();
    if ($code === 200) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
