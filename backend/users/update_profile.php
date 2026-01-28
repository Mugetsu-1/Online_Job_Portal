<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        throw new Exception("You must be logged in to update profile");
    }
    
    // Handle multipart form data (for file uploads) or JSON
    $is_multipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
    
    if ($is_multipart) {
        $data = $_POST;
    } else {
        $data = json_decode(file_get_contents('php://input'), true);
    }
    
    $db = getDB();
    
    // Build update query dynamically
    $update_fields = [];
    $params = [];
    
    // Common fields for all users
    $common_fields = ['full_name', 'phone'];
    
    foreach ($common_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    // Role-specific fields
    if ($_SESSION['role'] === 'job_seeker') {
        $job_seeker_fields = ['skills', 'experience_years', 'education', 'bio'];
        
        foreach ($job_seeker_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        // Handle resume upload
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['resume'];
            
            // Validate file size
            if ($file['size'] > MAX_FILE_SIZE) {
                throw new Exception("File size must be less than " . (MAX_FILE_SIZE / 1024 / 1024) . "MB");
            }
            
            // Validate file type
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, ALLOWED_FILE_TYPES)) {
                throw new Exception("Invalid file type. Allowed types: " . implode(', ', ALLOWED_FILE_TYPES));
            }
            
            // Create unique filename
            $filename = 'resume_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            $upload_path = UPLOAD_DIR . 'resumes/' . $filename;
            
            // Create directory if it doesn't exist
            if (!is_dir(dirname($upload_path))) {
                mkdir(dirname($upload_path), 0755, true);
            }
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $update_fields[] = "resume_path = ?";
                $params[] = 'uploads/resumes/' . $filename;
                
                // Delete old resume if exists
                $stmt = $db->prepare("SELECT resume_path FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $old_resume = $stmt->fetch()['resume_path'];
                if ($old_resume && file_exists(__DIR__ . '/../' . $old_resume)) {
                    unlink(__DIR__ . '/../' . $old_resume);
                }
            }
        }
        
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            
            // Validate image
            $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_image_types)) {
                throw new Exception("Invalid image type");
            }
            
            if ($file['size'] > 2 * 1024 * 1024) { // 2MB for images
                throw new Exception("Image size must be less than 2MB");
            }
            
            $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            $upload_path = UPLOAD_DIR . 'profiles/' . $filename;
            
            if (!is_dir(dirname($upload_path))) {
                mkdir(dirname($upload_path), 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $update_fields[] = "profile_picture = ?";
                $params[] = 'uploads/profiles/' . $filename;
            }
        }
        
    } else if ($_SESSION['role'] === 'employer') {
        $employer_fields = ['company_name', 'company_website', 'company_description'];
        
        foreach ($employer_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        // Handle company logo upload
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['company_logo'];
            
            $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_image_types)) {
                throw new Exception("Invalid image type");
            }
            
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception("Image size must be less than 2MB");
            }
            
            $filename = 'company_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            $upload_path = UPLOAD_DIR . 'logos/' . $filename;
            
            if (!is_dir(dirname($upload_path))) {
                mkdir(dirname($upload_path), 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $update_fields[] = "company_logo = ?";
                $params[] = 'uploads/logos/' . $filename;
            }
        }
    }
    
    if (empty($update_fields)) {
        throw new Exception("No fields to update");
    }
    
    // Add user ID to params
    $params[] = $_SESSION['user_id'];
    
    // Execute update
    $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ? RETURNING updated_at";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'data' => [
            'updated_at' => $result['updated_at']
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