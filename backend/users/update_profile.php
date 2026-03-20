<?php
require_once __DIR__ . '/../config/db.php';

try {
    requireAuth(['job_seeker', 'employer', 'admin']);
    $uploadedFilesToCleanup = [];
    $oldFilesToDelete = [];

    $is_multipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
    $data = $is_multipart ? $_POST : (array)json_decode(file_get_contents('php://input'), true);

    $db = getDB();
    $db->beginTransaction();
    $currentUploads = null;
    $update_fields = [];
    $params = [];
    $common_fields = ['full_name', 'phone'];
    foreach ($common_fields as $field) {
        if (isset($data[$field])) {
            if ($field === 'phone' && !isValidPhoneNumber(trim((string)$data[$field]))) {
                throw new Exception("Phone must be 10 digits and start with 9841 or 9746");
            }
            $update_fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if ($_SESSION['role'] === 'job_seeker') {
        foreach (['skills', 'experience_years', 'education', 'bio'] as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['resume'];
            if ($file['size'] > MAX_FILE_SIZE) {
                throw new Exception("File size must be less than 5MB");
            }
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, ALLOWED_FILE_TYPES)) {
                throw new Exception("Invalid file type. Allowed: PDF, DOC, DOCX");
            }
            if (!validateUploadedMime($file['tmp_name'], ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
                throw new Exception("Invalid resume MIME type");
            }
            $filename = 'resume_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            if ($currentUploads === null) {
                $stmt = $db->prepare("SELECT resume_path, profile_picture, company_logo FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $currentUploads = $stmt->fetch() ?: [];
            }
            $storedPath = storageUploadFile($file['tmp_name'], 'resumes', $filename);
            $uploadedFilesToCleanup[] = $storedPath;
            $update_fields[] = "resume_path = ?";
            $params[] = $storedPath;
            if (!empty($currentUploads['resume_path'])) {
                $oldFilesToDelete[] = $currentUploads['resume_path'];
                $currentUploads['resume_path'] = null;
            }
        }
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowed_img = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_img)) {
                throw new Exception("Invalid image type");
            }
            if (!validateUploadedMime($file['tmp_name'], ['image/jpeg', 'image/png', 'image/gif'])) {
                throw new Exception("Invalid profile image MIME type");
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception("Image size must be less than 2MB");
            }
            $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            if ($currentUploads === null) {
                $stmt = $db->prepare("SELECT resume_path, profile_picture, company_logo FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $currentUploads = $stmt->fetch() ?: [];
            }
            $storedPath = storageUploadFile($file['tmp_name'], 'profiles', $filename);
            $uploadedFilesToCleanup[] = $storedPath;
            $update_fields[] = "profile_picture = ?";
            $params[] = $storedPath;
            if (!empty($currentUploads['profile_picture'])) {
                $oldFilesToDelete[] = $currentUploads['profile_picture'];
                $currentUploads['profile_picture'] = null;
            }
        }
    } else if ($_SESSION['role'] === 'employer') {
        foreach (['company_name', 'company_website', 'company_description'] as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['company_logo'];
            $allowed_img = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_img)) {
                throw new Exception("Invalid image type");
            }
            if (!validateUploadedMime($file['tmp_name'], ['image/jpeg', 'image/png', 'image/gif'])) {
                throw new Exception("Invalid company logo MIME type");
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception("Image size must be less than 2MB");
            }
            $filename = 'company_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            if ($currentUploads === null) {
                $stmt = $db->prepare("SELECT resume_path, profile_picture, company_logo FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $currentUploads = $stmt->fetch() ?: [];
            }
            $storedPath = storageUploadFile($file['tmp_name'], 'logos', $filename);
            $uploadedFilesToCleanup[] = $storedPath;
            $update_fields[] = "company_logo = ?";
            $params[] = $storedPath;
            if (!empty($currentUploads['company_logo'])) {
                $oldFilesToDelete[] = $currentUploads['company_logo'];
                $currentUploads['company_logo'] = null;
            }
        }
    }

    if (empty($update_fields)) {
        throw new Exception("No fields to update");
    }

    $params[] = $_SESSION['user_id'];
    $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ? RETURNING updated_at";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    auditLog('profile.updated', 'user', $_SESSION['user_id'], ['fields' => $update_fields]);
    $db->commit();
    foreach ($oldFilesToDelete as $oldFile) {
        storageDeleteFile($oldFile);
    }
    $uploadedFilesToCleanup = [];

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'data' => ['updated_at' => $result['updated_at']]
    ]);

} catch (Exception $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    foreach ($uploadedFilesToCleanup as $uploadedFile) {
        storageDeleteFile($uploadedFile);
    }
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
