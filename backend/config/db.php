<?php
require_once __DIR__ . '/response.php';

function envOrDefault($key, $default) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

define('DB_HOST', envOrDefault('DB_HOST', 'localhost'));
define('DB_PORT', envOrDefault('DB_PORT', '5432'));
define('DB_NAME', envOrDefault('DB_NAME', 'job_portal'));
define('DB_USER', envOrDefault('DB_USER', 'postgres'));
define('DB_PASSWORD', envOrDefault('DB_PASSWORD', ''));
define('DB_SSLMODE', envOrDefault('DB_SSLMODE', 'prefer'));
define('BASE_URL', envOrDefault('BASE_URL', 'http://localhost:8080'));
define('UPLOAD_DIR', envOrDefault('UPLOAD_DIR', __DIR__ . '/../uploads/'));
define('APP_LOG_FILE', envOrDefault('APP_LOG_FILE', __DIR__ . '/../logs/app.log'));
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx']);
define('SESSION_LIFETIME', 3600 * 24);
error_reporting(E_ALL);
$debug = strtolower(envOrDefault('APP_DEBUG', ''));
ini_set('display_errors', ($debug === '1' || $debug === 'true') ? 1 : 0);

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=" . DB_SSLMODE;
            $this->connection = new PDO($dsn, DB_USER, DB_PASSWORD);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}

function getDB() {
    return Database::getInstance()->getConnection();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
setcookie('jp_csrf', $_SESSION['csrf_token'], [
    'expires' => time() + SESSION_LIFETIME,
    'path' => '/',
    'httponly' => false,
    'samesite' => 'Lax'
]);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', envOrDefault('ALLOWED_ORIGINS', ''))));
if (empty($allowedOrigins)) {
    $allowedOrigins = ['http://localhost:8000', 'http://localhost:8080'];
}
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function logServerEvent($level, $message, $context = []) {
    $dir = dirname(APP_LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $record = [
        'ts' => date('c'),
        'level' => $level,
        'message' => $message,
        'context' => $context
    ];
    @file_put_contents(APP_LOG_FILE, json_encode($record) . PHP_EOL, FILE_APPEND);
}

function logRequestStart() {
    logServerEvent('info', 'request.start', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'path' => $_SERVER['REQUEST_URI'] ?? '',
        'ip' => getClientIp(),
        'user_id' => $_SESSION['user_id'] ?? null
    ]);
}

function safeString($value, $maxLen = 255, $allowEmpty = true) {
    $value = trim((string)$value);
    if ($value === '' && !$allowEmpty) {
        throw new Exception('Required field is missing');
    }
    if (strlen($value) > $maxLen) {
        $value = substr($value, 0, $maxLen);
    }
    return $value;
}

function safeInt($value, $default = null, $min = null, $max = null) {
    if ($value === null || $value === '') {
        return $default;
    }
    if (!is_numeric($value)) {
        throw new Exception('Invalid numeric value');
    }
    $int = (int)$value;
    if ($min !== null && $int < $min) {
        throw new Exception('Numeric value below allowed range');
    }
    if ($max !== null && $int > $max) {
        throw new Exception('Numeric value above allowed range');
    }
    return $int;
}

function safeEnum($value, $allowed, $default = null) {
    if ($value === null || $value === '') {
        return $default;
    }
    if (!in_array($value, $allowed, true)) {
        throw new Exception('Invalid option value');
    }
    return $value;
}

function isValidStrongPassword($password) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,32}$/', (string)$password) === 1;
}

function isValidPhoneNumber($phone) {
    if ($phone === null || $phone === '') return true;
    return preg_match('/^(?:9841|9746)\d{6}$/', (string)$phone) === 1;
}

function requireAuth($roles = null) {
    if (!isset($_SESSION['user_id'])) {
        api_error('Unauthorized', 403, 'UNAUTHORIZED');
    }
    if (is_array($roles) && !in_array($_SESSION['role'] ?? '', $roles, true)) {
        api_error('Forbidden', 403, 'FORBIDDEN');
    }
}

function requireCsrfForStateChange() {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        return;
    }
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($provided === '' || $expected === '' || !hash_equals($expected, $provided)) {
        api_error('Invalid CSRF token', 419, 'CSRF_INVALID');
    }
}

function throttleGuard($key, $limit, $windowSeconds) {
    $now = time();
    if (!isset($_SESSION['throttle'])) {
        $_SESSION['throttle'] = [];
    }
    if (!isset($_SESSION['throttle'][$key])) {
        $_SESSION['throttle'][$key] = [];
    }
    $_SESSION['throttle'][$key] = array_values(array_filter($_SESSION['throttle'][$key], function ($ts) use ($now, $windowSeconds) {
        return ($now - (int)$ts) < $windowSeconds;
    }));
    if (count($_SESSION['throttle'][$key]) >= $limit) {
        api_error('Too many attempts. Please try again later.', 429, 'RATE_LIMITED');
    }
    $_SESSION['throttle'][$key][] = $now;
}

function validateUploadedMime($tmpPath, $allowedMimes) {
    if (!function_exists('mime_content_type')) {
        return true;
    }
    $mime = mime_content_type($tmpPath);
    if ($mime === false) {
        return false;
    }
    return in_array($mime, $allowedMimes, true);
}

function getRequestBaseUrl() {
    $scheme = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    }
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
    if ($host) {
        return $scheme . '://' . $host;
    }
    return rtrim(BASE_URL, '/');
}

function publicUploadUrl($relativePath) {
    if (!$relativePath) return null;
    if (preg_match('#^https?://#i', $relativePath)) return $relativePath;
    $base = rtrim(getRequestBaseUrl(), '/');
    return $base . '/backend/' . ltrim($relativePath, '/');
}

function auditLog($action, $targetType = null, $targetId = null, $meta = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, target_type, target_id, meta) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $targetType,
            $targetId,
            $meta ? json_encode($meta) : null
        ]);
    } catch (Throwable $e) {
        logServerEvent('warn', 'audit_log_failed', ['error' => $e->getMessage()]);
    }
}

logRequestStart();
requireCsrfForStateChange();
?>
