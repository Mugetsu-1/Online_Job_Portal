<?php
require_once __DIR__ . '/../config/db.php';

try {
    requireAuth(['admin']);

    $db = getDB();
    $search = isset($_GET['search']) ? safeString($_GET['search'], 120, true) : null;
    $role = isset($_GET['role']) ? safeEnum(trim((string)$_GET['role']), ['job_seeker', 'employer', 'admin'], null) : null;
    $page = safeInt($_GET['page'] ?? 1, 1, 1, 100000);
    $limit = safeInt($_GET['limit'] ?? 25, 25, 1, 100);
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];
    $params = [];

    if ($search) {
        $where[] = "(u.full_name ILIKE ? OR u.email ILIKE ? OR COALESCE(u.company_name, '') ILIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($role) {
        $where[] = "u.role = ?::user_role";
        $params[] = $role;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM users u WHERE $whereSql");
    $countStmt->execute($params);
    $total = (int)($countStmt->fetch()['total'] ?? 0);

    $sql = "
        SELECT
            u.id,
            u.email,
            u.role,
            u.full_name,
            u.phone,
            u.company_name,
            u.is_active,
            u.created_at,
            (SELECT COUNT(*) FROM jobs j WHERE j.employer_id = u.id) AS jobs_posted,
            (SELECT COUNT(*) FROM applications a WHERE a.applicant_id = u.id) AS applications_submitted
        FROM users u
        WHERE $whereSql
        ORDER BY u.created_at DESC, u.id DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $users = $stmt->fetchAll();

    foreach ($users as &$user) {
        $user['is_active'] = (bool)$user['is_active'];
        $user['jobs_posted'] = (int)$user['jobs_posted'];
        $user['applications_submitted'] = (int)$user['applications_submitted'];
        $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
    }

    echo json_encode([
        'success' => true,
        'data' => $users,
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
