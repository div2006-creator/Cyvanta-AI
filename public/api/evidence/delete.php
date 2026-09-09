<?php
/**
 * CYVANTA - Delete Evidence API Endpoint
 * 
 * Access Control:
 * - Super Admin / Administrator: Can delete ANY evidence uploaded by any investigator across all cases.
 * - Investigator / Analyst: Can delete ONLY their OWN uploaded evidence.
 */

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

cg_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cg_json_error('Method not allowed.', 405);
}

$input = cg_input();
$evidenceId = (int) ($input['evidence_id'] ?? 0);

if (!$evidenceId) {
    cg_json_error('Evidence ID is required.', 422);
}

$pdo = Database::connect();
$user = cg_current_user();

// Fetch evidence record with case details
$stmt = $pdo->prepare('
    SELECT ev.*, c.case_number 
    FROM evidence ev 
    JOIN cases c ON c.id = ev.case_id 
    WHERE ev.id = ?
');
$stmt->execute([$evidenceId]);
$evidence = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evidence) {
    cg_json_error('Evidence item not found.', 404);
}

$caseId = (int) $evidence['case_id'];

if (!cg_user_can_access_case($caseId, $user)) {
    cg_json_error('You are not authorized to access this case.', 403);
}

// Permission Check: Super Admin / Administrator can delete ANY evidence; Investigators can delete ONLY their OWN uploaded evidence
$isSuperAdmin = in_array($user['role'], ['super_admin', 'administrator'], true);
$isOwner = ((int) ($evidence['uploaded_by'] ?? 0) === (int) $user['id']);

if (!$isSuperAdmin && !$isOwner) {
    cg_json_error('You are only authorized to delete your own uploaded evidence items. Super Admin rights are required to delete evidence collected by other investigators.', 403);
}

try {
    $pdo->beginTransaction();

    // Delete physical evidence file if stored
    if (!empty($evidence['stored_filename'])) {
        $filePath = UPLOAD_DIR . '/' . $evidence['stored_filename'];
        if (file_exists($filePath) && is_file($filePath)) {
            @unlink($filePath);
        }
        $evidenceDirFile = APP_ROOT . '/storage/evidence/' . $evidence['stored_filename'];
        if (file_exists($evidenceDirFile) && is_file($evidenceDirFile)) {
            @unlink($evidenceDirFile);
        }
    }

    // Delete database record
    $pdo->prepare('DELETE FROM evidence WHERE id = ?')->execute([$evidenceId]);

    // Record case event & audit log
    $pdo->prepare('INSERT INTO case_events (case_id, event_type, description, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')
        ->execute([$caseId, 'EVIDENCE_DELETED', "Evidence item '{$evidence['evidence_type']}' was deleted by {$user['name']}.", $user['id']]);

    $pdo->commit();

    cg_log_audit($user['id'], 'EVIDENCE_DELETED', 'evidence', $evidence['case_number'], 'success', "Deleted evidence item '{$evidence['evidence_type']}'");
    cg_json_success("Evidence item deleted successfully.");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[CYVANTA] Evidence deletion failed: ' . $e->getMessage());
    cg_json_error('Unable to delete evidence item.', 500);
}
