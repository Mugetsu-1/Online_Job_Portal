<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';

try {
    requireAuth(['admin']);

    $data = read_json_body();
    require_fields($data, ['job_id', 'is_active']);

    $jobId = safeInt($data['job_id'], null, 1);
    $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($isActive === null) {
        api_error('is_active must be true or false', 400, 'VALIDATION_ERROR');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    if (!$stmt->fetch()) {
        api_error('Job not found', 404, 'NOT_FOUND');
    }

    $stmt = $db->prepare("UPDATE jobs SET is_active = ? WHERE id = ? RETURNING updated_at");
    $stmt->execute([$isActive, $jobId]);
    $result = $stmt->fetch();

    auditLog('admin.job_status_updated', 'job', $jobId, ['is_active' => $isActive]);

    api_success('Job status updated', [
        'job_id' => $jobId,
        'is_active' => $isActive,
        'updated_at' => $result['updated_at'] ?? null
    ]);
} catch (Throwable $e) {
    api_error('Unexpected server error', 500, 'SERVER_ERROR');
}
?>
