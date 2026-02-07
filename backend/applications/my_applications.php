<?php
require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        throw new Exception("You must be logged in to view applications");
    }

    $db = getDB();
    $status = $_GET['status'] ?? null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;

    if ($_SESSION['role'] === 'job_seeker') {
        $where_clauses = ["a.applicant_id = ?"];
        $params = [$_SESSION['user_id']];
        if ($status) {
            $where_clauses[] = "a.status = ?";
            $params[] = $status;
        }
        $where_sql = implode(' AND ', $where_clauses);
        $count_sql = "SELECT COUNT(*) as total FROM applications a WHERE $where_sql";
        $stmt = $db->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        $sql = "SELECT a.id, a.job_id, a.status, a.cover_letter, a.resume_path, a.applied_at, a.reviewed_at, a.updated_at, j.title as job_title, j.location, j.job_type, j.salary_min, j.salary_max, j.salary_currency, u.company_name, u.company_logo FROM applications a JOIN jobs j ON a.job_id = j.id JOIN users u ON j.employer_id = u.id WHERE $where_sql ORDER BY a.applied_at DESC LIMIT ? OFFSET ?";
    } else if ($_SESSION['role'] === 'employer') {
        $where_clauses = ["j.employer_id = ?"];
        $params = [$_SESSION['user_id']];
        if ($status) {
            $where_clauses[] = "a.status = ?";
            $params[] = $status;
        }
        $where_sql = implode(' AND ', $where_clauses);
        $count_sql = "SELECT COUNT(*) as total FROM applications a JOIN jobs j ON a.job_id = j.id WHERE $where_sql";
        $stmt = $db->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        $sql = "SELECT a.id, a.job_id, a.applicant_id, a.status, a.cover_letter, a.resume_path, a.applied_at, a.reviewed_at, a.updated_at, a.notes, j.title as job_title, u.full_name as applicant_name, u.email as applicant_email, u.phone as applicant_phone, u.skills as applicant_skills, u.experience_years, u.education, u.profile_picture FROM applications a JOIN jobs j ON a.job_id = j.id JOIN users u ON a.applicant_id = u.id WHERE $where_sql ORDER BY a.applied_at DESC LIMIT ? OFFSET ?";
    } else {
        throw new Exception("Invalid user role");
    }

    $params[] = $limit;
    $params[] = $offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll();

    foreach ($applications as &$app) {
        $app['applied_at'] = date('Y-m-d H:i:s', strtotime($app['applied_at']));
        if (!empty($app['reviewed_at'])) {
            $app['reviewed_at'] = date('Y-m-d H:i:s', strtotime($app['reviewed_at']));
        }
        if (!empty($app['updated_at'])) {
            $app['updated_at'] = date('Y-m-d H:i:s', strtotime($app['updated_at']));
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $applications,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => (int)$total,
            'total_pages' => ceil($total / $limit)
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
