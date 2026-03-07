<?php
function api_success($message = 'OK', $data = null, $status = 200) {
    http_response_code($status);
    $payload = [
        'success' => true,
        'message' => $message
    ];
    if ($data !== null) {
        $payload['data'] = $data;
    }
    echo json_encode($payload);
    exit();
}

function api_error($message, $status = 400, $code = 'BAD_REQUEST', $errors = null) {
    if (function_exists('logServerEvent')) {
        logServerEvent('error', 'api_error', [
            'status' => $status,
            'code' => $code,
            'message' => $message,
            'path' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ]);
    }
    http_response_code($status);
    $payload = [
        'success' => false,
        'code' => $code,
        'message' => $message
    ];
    if ($errors !== null) {
        $payload['errors'] = $errors;
    }
    echo json_encode($payload);
    exit();
}

function read_json_body() {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        api_error('Invalid JSON body', 400, 'INVALID_JSON');
    }
    return $data;
}

function require_fields($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        api_error('Missing required fields', 400, 'VALIDATION_ERROR', ['missing' => $missing]);
    }
}
?>
