<?php
require_once __DIR__ . '/../config/db.php';

try {
    requireAuth(['admin']);

    $db = getDB();
    $search = isset($_GET['search']) ? safeString($_GET['search'], 120, true) : null;
    $status = isset($_GET['status']) ? safeEnum(trim((string)$_GET['status']), ['active', 'inactive', 'all'], 'all') : 'all';
    $page = safeInt($_GET['page'] ?? 1, 1, 1, 100000);
    $limit = safeInt($_GET['limit'] ?? 25, 25, 1, 100);
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];
    $params = [];

    if ($search) {
        $where[] = "(j.title ILIKE ? OR j.description ILIKE ? OR COALESCE(u.company_name, '') ILIKE ? OR u.full_name ILIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($status === 'active') {
        $where[] = "j.is_active = true";
    } elseif ($status === 'inactive') {
        $where[] = "j.is_active = false";
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM jobs j JOIN users u ON j.employer_id = u.id WHERE $whereSql");
    $countStmt->execute($params);
    $total = (int)($countStmt->fetch()['total'] ?? 0);

    $sql = "
        SELECT
            j.id,
            j.employer_id,
            j.title,
            j.location,
            j.job_type,
            j.is_active,
            j.applications_count,
            j.created_at,
            j.application_deadline,
            u.full_name AS employer_name,
            u.company_name
        FROM jobs j
        JOIN users u ON j.employer_id = u.id
        WHERE $whereSql
        ORDER BY j.created_at DESC, j.id DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $jobs = $stmt->fetchAll();

    foreach ($jobs as &$job) {
        $job['is_active'] = (bool)$job['is_active'];
        $job['applications_count'] = (int)$job['applications_count'];
        $job['created_at'] = date('Y-m-d H:i:s', strtotime($job['created_at']));
        if (!empty($job['application_deadline'])) {
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
