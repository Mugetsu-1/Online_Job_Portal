<?php
require_once __DIR__ . '/config/db.php';

$db = getDB();
$hash = password_hash('1234', PASSWORD_BCRYPT);
$stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email IN ('jobseeker@example.com', 'employer@example.com', 'admin@example.com')");
$stmt->execute([$hash]);
$updated = $stmt->rowCount();
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => "Sample user passwords set to 1234. Updated $updated users."]);
?>
