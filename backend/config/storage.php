<?php

function storageDriver() {
    return strtolower((string)envOrDefault('STORAGE_DRIVER', 'local'));
}

function supabaseStorageUrl() {
    return rtrim((string)envOrDefault('SUPABASE_URL', ''), '/');
}

function supabaseStorageBucket() {
    return trim((string)envOrDefault('SUPABASE_STORAGE_BUCKET', 'job-portal-assets'));
}

function supabaseStorageServiceKey() {
    return (string)envOrDefault('SUPABASE_SERVICE_ROLE_KEY', envOrDefault('SUPABASE_STORAGE_KEY', ''));
}

function isSupabaseStorageReference($storedPath) {
    return is_string($storedPath) && strpos($storedPath, 'supabase:') === 0;
}

function parseSupabaseStorageReference($storedPath) {
    if (!isSupabaseStorageReference($storedPath)) {
        return null;
    }
    $payload = substr($storedPath, strlen('supabase:'));
    $parts = explode('/', $payload, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return null;
    }
    return [
        'bucket' => $parts[0],
        'key' => $parts[1]
    ];
}

function makeSupabaseStorageReference($bucket, $key) {
    return 'supabase:' . trim($bucket, '/') . '/' . ltrim($key, '/');
}

function encodeStorageObjectPath($bucket, $key) {
    $bucketPart = rawurlencode(trim($bucket, '/'));
    $segments = array_values(array_filter(explode('/', trim($key, '/')), function ($segment) {
        return $segment !== '';
    }));
    $encodedKey = implode('/', array_map('rawurlencode', $segments));
    return $bucketPart . '/' . $encodedKey;
}

function normalizeStoredPath($storedPath) {
    return ltrim(str_replace('\\', '/', (string)$storedPath), '/');
}

function sanitizeStoredFilename($filename) {
    $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$filename);
    return $sanitized !== '' ? $sanitized : ('file_' . time());
}

function supabaseStorageRequest($method, $path, $body = '', $contentType = null, $extraHeaders = []) {
    $baseUrl = supabaseStorageUrl();
    $serviceKey = supabaseStorageServiceKey();
    if ($baseUrl === '' || $serviceKey === '') {
        throw new Exception('Supabase Storage is not fully configured');
    }

    $headers = [
        'Authorization: Bearer ' . $serviceKey,
        'apikey: ' . $serviceKey
    ];
    if ($contentType !== null) {
        $headers[] = 'Content-Type: ' . $contentType;
    }
    foreach ($extraHeaders as $header) {
        $headers[] = $header;
    }

    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 30
        ]
    ]);

    $responseBody = @file_get_contents($baseUrl . $path, false, $context);
    $responseHeaders = isset($http_response_header) ? $http_response_header : [];
    $status = 0;
    if (!empty($responseHeaders) && preg_match('#\s(\d{3})\s#', $responseHeaders[0], $matches)) {
        $status = (int)$matches[1];
    }

    if ($responseBody === false && $status === 0) {
        throw new Exception('Supabase Storage request failed');
    }

    $decoded = json_decode((string)$responseBody, true);
    return [
        'status' => $status,
        'body' => (string)$responseBody,
        'json' => is_array($decoded) ? $decoded : null
    ];
}

function storageUploadFile($tmpPath, $folder, $filename, $mimeType = null) {
    $safeFilename = sanitizeStoredFilename($filename);
    $normalizedFolder = trim(str_replace('\\', '/', (string)$folder), '/');

    if (storageDriver() === 'supabase') {
        $bucket = supabaseStorageBucket();
        $key = ($normalizedFolder !== '' ? $normalizedFolder . '/' : '') . $safeFilename;
        $content = @file_get_contents($tmpPath);
        if ($content === false) {
            throw new Exception('Unable to read uploaded file');
        }
        if ($mimeType === null || $mimeType === '') {
            $mimeType = function_exists('mime_content_type') ? (mime_content_type($tmpPath) ?: 'application/octet-stream') : 'application/octet-stream';
        }
        $response = supabaseStorageRequest(
            'POST',
            '/storage/v1/object/' . encodeStorageObjectPath($bucket, $key),
            $content,
            $mimeType,
            ['x-upsert: true']
        );
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new Exception('Supabase Storage upload failed');
        }
        return makeSupabaseStorageReference($bucket, $key);
    }

    $baseDir = rtrim(UPLOAD_DIR, '/\\');
    $targetDir = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedFolder);
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new Exception('Unable to create upload directory');
    }
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $safeFilename;
    $moved = is_uploaded_file($tmpPath) ? @move_uploaded_file($tmpPath, $targetPath) : @copy($tmpPath, $targetPath);
    if (!$moved) {
        throw new Exception('Unable to store uploaded file');
    }
    return 'uploads/' . ($normalizedFolder !== '' ? $normalizedFolder . '/' : '') . $safeFilename;
}

function storageDeleteFile($storedPath) {
    if (!$storedPath) {
        return false;
    }
    $parsed = parseSupabaseStorageReference($storedPath);
    if ($parsed) {
        $response = supabaseStorageRequest(
            'DELETE',
            '/storage/v1/object/' . encodeStorageObjectPath($parsed['bucket'], $parsed['key'])
        );
        return $response['status'] >= 200 && $response['status'] < 300;
    }

    $relativePath = normalizeStoredPath($storedPath);
    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolutePath)) {
        return @unlink($absolutePath);
    }
    return false;
}

function storageReferenceExists($storedPath) {
    if (!$storedPath) {
        return false;
    }
    if (parseSupabaseStorageReference($storedPath)) {
        return true;
    }
    $relativePath = normalizeStoredPath($storedPath);
    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return is_file($absolutePath);
}

function storagePublicUrl($storedPath) {
    if (!$storedPath) {
        return null;
    }
    if (preg_match('#^https?://#i', $storedPath)) {
        return $storedPath;
    }

    $parsed = parseSupabaseStorageReference($storedPath);
    if ($parsed) {
        $baseUrl = supabaseStorageUrl();
        if ($baseUrl === '') {
            return null;
        }
        return $baseUrl . '/storage/v1/object/public/' . encodeStorageObjectPath($parsed['bucket'], $parsed['key']);
    }

    $base = rtrim(getRequestBaseUrl(), '/');
    return $base . '/backend/' . normalizeStoredPath($storedPath);
}
?>
