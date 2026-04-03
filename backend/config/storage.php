<?php

function normalizeStoredPath($storedPath) {
    return ltrim(str_replace('\\', '/', (string)$storedPath), '/');
}

function sanitizeStoredFilename($filename) {
    $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$filename);
    return $sanitized !== '' ? $sanitized : ('file_' . time());
}

function storageUploadFile($tmpPath, $folder, $filename, $mimeType = null) {
    $safeFilename = sanitizeStoredFilename($filename);
    $normalizedFolder = trim(str_replace('\\', '/', (string)$folder), '/');

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
    $base = rtrim(getRequestBaseUrl(), '/');
    return $base . '/backend/' . normalizeStoredPath($storedPath);
}
?>
