<?php
require_once __DIR__ . '/../config/db.php';

try {
    requireAuth(['employer']);

    $db = getDB();
    $page = safeInt($_GET['page'] ?? 1, 1, 1, 100000);
    $limit = safeInt($_GET['limit'] ?? 50, 50, 1, 100);
    $offset = ($page - 1) * $limit;

    // Get count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM jobs WHERE employer_id = ?");
    $countStmt->execute([$_SESSION['user_id']]);
    $total = (int)($countStmt->fetch()['total'] ?? 0);

    // Get employer's jobs (including filled ones)
    $sql = "
        SELECT 
            j.id, j.employer_id, j.title, j.description, j.job_type, j.location, 
            j.salary_min, j.salary_max, j.salary_currency, j.experience_required, 
            j.positions_available, j.application_deadline, j.views_count, 
            j.applications_count, j.is_active, j.created_at,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'accepted') as accepted_count
        FROM jobs j 
        WHERE j.employer_id = ?
        ORDER BY j.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$_SESSION['user_id'], $limit, $offset]);
    $jobs = $stmt->fetchAll();

    foreach ($jobs as &$job) {
        $job['is_active'] = (bool)$job['is_active'];
        $job['positions_available'] = (int)$job['positions_available'];
        $job['accepted_count'] = (int)$job['accepted_count'];
        $job['is_filled'] = $job['accepted_count'] >= $job['positions_available'];
        $job['created_at'] = date('Y-m-d H:i:s', strtotime($job['created_at']));
        if ($job['application_deadline']) {
            $job['application_deadline'] = date('Y-m-d', strtotime($job['application_deadline']));
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $jobs,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => (int)ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
