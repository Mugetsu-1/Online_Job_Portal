<?php
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

$seedToken = (string)envOrDefault('SEED_ENDPOINT_TOKEN', '');
$providedToken = (string)($_GET['token'] ?? ($_SERVER['HTTP_X_SEED_TOKEN'] ?? ''));
$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    if ($seedToken === '') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Seed endpoint is disabled']);
        exit();
    }
    if ($providedToken === '' || !hash_equals($seedToken, $providedToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid seed token']);
        exit();
    }
}

$db = getDB();
$hash = password_hash('Pass@1234', PASSWORD_BCRYPT);
$stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email IN ('jobseeker@example.com', 'employer@example.com', 'admin@example.com')");
$stmt->execute([$hash]);
$updated = $stmt->rowCount();
echo json_encode(['success' => true, 'message' => "Sample user passwords set to Pass@1234. Updated $updated users."]);
?>
