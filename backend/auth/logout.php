<?php
require_once __DIR__ . '/../config/db.php';

try {
    session_unset();
    session_destroy();
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
