<?php
require_once __DIR__ . '/response.php';

function loadProjectEnv() {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $separatorPos = strpos($line, '=');
        if ($separatorPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separatorPos));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim(substr($line, $separatorPos + 1));
        $valueLength = strlen($value);
        if ($valueLength >= 2) {
            $first = $value[0];
            $last = $value[$valueLength - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

loadProjectEnv();

function envOrDefault($key, $default) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function currentRequestIsHttps() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    return false;
}

define('DB_HOST', envOrDefault('DB_HOST', 'localhost'));
define('DB_PORT', envOrDefault('DB_PORT', '5432'));
define('DB_NAME', envOrDefault('DB_NAME', 'job_portal'));
define('DB_USER', envOrDefault('DB_USER', 'postgres'));
define('DB_PASSWORD', envOrDefault('DB_PASSWORD', ''));
define('BASE_URL', envOrDefault('BASE_URL', 'http://localhost'));
define('UPLOAD_DIR', envOrDefault('UPLOAD_DIR', __DIR__ . '/../uploads/'));
define('APP_LOG_FILE', envOrDefault('APP_LOG_FILE', __DIR__ . '/../logs/app.log'));
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx']);
define('SESSION_LIFETIME', 3600 * 24);
define('SESSION_COOKIE_NAME', 'jp_session');
define('SESSION_COOKIE_SAMESITE', 'Lax');
define('SESSION_COOKIE_SECURE', currentRequestIsHttps());
define('CSRF_COOKIE_ENABLED', true);
error_reporting(E_ALL);
$debug = strtolower(envOrDefault('APP_DEBUG', ''));
ini_set('display_errors', ($debug === '1' || $debug === 'true') ? 1 : 0);

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $this->connection = new PDO($dsn, DB_USER, DB_PASSWORD);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed');
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

function baseCookieOptions($httpOnly = true) {
    return [
        'path' => '/',
        'secure' => SESSION_COOKIE_SECURE,
        'httponly' => $httpOnly,
        'samesite' => SESSION_COOKIE_SAMESITE
    ];
}

function setAppCookie($name, $value, $options = []) {
    $httpOnly = array_key_exists('httponly', $options) ? (bool)$options['httponly'] : true;
    $cookieOptions = array_merge(baseCookieOptions($httpOnly), $options);
    if (!array_key_exists('expires', $cookieOptions) || $cookieOptions['expires'] === null) {
        unset($cookieOptions['expires']);
    }
    return setcookie($name, $value, $cookieOptions);
}

function clearAppCookie($name, $options = []) {
    $httpOnly = array_key_exists('httponly', $options) ? (bool)$options['httponly'] : true;
    $cookieOptions = array_merge(baseCookieOptions($httpOnly), $options, [
        'expires' => time() - 3600
    ]);
    return setcookie($name, '', $cookieOptions);
}

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
session_name(SESSION_COOKIE_NAME);
session_set_cookie_params(baseCookieOptions(true) + ['lifetime' => SESSION_LIFETIME]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (CSRF_COOKIE_ENABLED) {
    setAppCookie('jp_csrf', $_SESSION['csrf_token'], [
        'expires' => time() + SESSION_LIFETIME,
        'httponly' => false
    ]);
}

header('Content-Type: application/json');

function getClientIp() {
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
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $backendPos = strpos($scriptName, '/backend/');
        if ($backendPos !== false) {
            return $scheme . '://' . $host . substr($scriptName, 0, $backendPos);
        }
        return $scheme . '://' . $host;
    }
    return rtrim(BASE_URL, '/');
}

function publicUploadUrl($relativePath) {
    return storagePublicUrl($relativePath);
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

require_once __DIR__ . '/storage.php';

logRequestStart();
requireCsrfForStateChange();
?>
