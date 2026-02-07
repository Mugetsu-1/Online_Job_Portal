<?php
require_once __DIR__ . '/../config/db.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'job_seeker') {
        http_response_code(403);
        throw new Exception("Only job seekers can apply to jobs");
    }

    $input_json = null;
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : (isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0);
    if (!$job_id) {
        $input_json = json_decode(file_get_contents('php://input'), true);
        if ($input_json && isset($input_json['job_id'])) {
            $job_id = (int)$input_json['job_id'];
        }
    }
    if (!$job_id) {
        throw new Exception("Job ID is required");
    }

    $cover_letter = isset($_POST['cover_letter']) ? trim($_POST['cover_letter']) : null;
    if (!$cover_letter && $input_json && isset($input_json['cover_letter'])) {
        $cover_letter = trim($input_json['cover_letter']);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, positions_available, application_deadline FROM jobs WHERE id = ? AND is_active = true");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();
    if (!$job) {
        http_response_code(404);
        throw new Exception("Job not found or no longer active");
    }
    if ($job['application_deadline'] && strtotime($job['application_deadline']) < time()) {
        throw new Exception("Application deadline has passed");
    }

    $stmt = $db->prepare("SELECT id FROM applications WHERE job_id = ? AND applicant_id = ?");
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        throw new Exception("You have already applied to this job");
    }

    $resume_path = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['resume'];
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("Resume size must be less than 5MB");
        }
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, ALLOWED_FILE_TYPES)) {
            throw new Exception("Invalid file type. Allowed: PDF, DOC, DOCX");
        }
        $filename = 'app_' . $_SESSION['user_id'] . '_' . $job_id . '_' . time() . '.' . $file_ext;
        $upload_path = UPLOAD_DIR . 'resumes/' . $filename;
        if (!is_dir(dirname($upload_path))) {
            mkdir(dirname($upload_path), 0755, true);
        }
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $resume_path = 'uploads/resumes/' . $filename;
        }
    } else {
        $stmt = $db->prepare("SELECT resume_path FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch();
        if ($u && $u['resume_path'] && file_exists(__DIR__ . '/../' . $u['resume_path'])) {
            $resume_path = $u['resume_path'];
        }
    }

    $stmt = $db->prepare("INSERT INTO applications (job_id, applicant_id, cover_letter, resume_path) VALUES (?, ?, ?, ?) RETURNING id, applied_at");
    $stmt->execute([$job_id, $_SESSION['user_id'], $cover_letter, $resume_path]);
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully',
        'data' => [
            'id' => $result['id'],
            'applied_at' => $result['applied_at']
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
