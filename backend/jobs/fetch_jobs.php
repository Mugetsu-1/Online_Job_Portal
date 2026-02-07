<?php
require_once __DIR__ . '/../config/db.php';

try {
    $db = getDB();
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $job_type = isset($_GET['job_type']) ? trim($_GET['job_type']) : null;
    $location = isset($_GET['location']) ? trim($_GET['location']) : null;
    $min_salary = isset($_GET['min_salary']) ? (float)$_GET['min_salary'] : null;
    $max_salary = isset($_GET['max_salary']) ? (float)$_GET['max_salary'] : null;
    $experience = isset($_GET['experience']) ? (int)$_GET['experience'] : null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;

    $where = ["j.is_active = true"];
    $params = [];

    if ($search) {
        $where[] = "(to_tsvector('english', j.title || ' ' || j.description) @@ plainto_tsquery('english', ?))";
        $params[] = $search;
    }
    if ($job_type) {
        $where[] = "j.job_type = ?";
        $params[] = $job_type;
    }
    if ($location) {
        $where[] = "j.location ILIKE ?";
        $params[] = '%' . $location . '%';
    }
    if ($min_salary !== null && $min_salary > 0) {
        $where[] = "j.salary_max >= ?";
        $params[] = $min_salary;
    }
    if ($max_salary !== null && $max_salary > 0) {
        $where[] = "j.salary_min <= ?";
        $params[] = $max_salary;
    }
    if ($experience !== null && $experience >= 0) {
        $where[] = "(j.experience_required IS NULL OR j.experience_required <= ?)";
        $params[] = $experience;
    }

    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) as total FROM jobs j WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total = (int)$stmt->fetch()['total'];

    $params[] = $limit;
    $params[] = $offset;
    $sql = "SELECT j.id, j.title, j.description, j.job_type, j.location, j.salary_min, j.salary_max, j.salary_currency, j.experience_required, j.positions_available, j.application_deadline, j.views_count, j.applications_count, j.created_at, u.company_name, u.company_logo FROM jobs j JOIN users u ON j.employer_id = u.id WHERE $where_sql ORDER BY j.created_at DESC LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();

    foreach ($jobs as &$job) {
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
            'total_pages' => ceil($total / $limit)
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
