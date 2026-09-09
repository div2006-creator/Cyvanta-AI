<?php
/**
 * CYVANTA - Delete Document API Endpoint
 * 
 * Access Control:
 * - Super Admin / Administrator: Can delete ANY document uploaded by any user across all cases.
 * - Investigator / Analyst: Can delete ONLY their OWN uploaded documents.
 */

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/services/AnalysisService.php';

cg_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cg_json_error('Method not allowed.', 405);
}

$input = cg_input();
$documentId = (int) ($input['document_id'] ?? 0);

if (!$documentId) {
    cg_json_error('Document ID is required.', 422);
}

$pdo = Database::connect();
$user = cg_current_user();

// Fetch document with case details
$stmt = $pdo->prepare('
    SELECT d.*, c.case_number 
    FROM documents d 
    JOIN cases c ON c.id = d.case_id 
    WHERE d.id = ?
');
$stmt->execute([$documentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    cg_json_error('Document not found.', 404);
}

$caseId = (int) $document['case_id'];

if (!cg_user_can_access_case($caseId, $user)) {
    cg_json_error('You are not authorized to access this case.', 403);
}

// Permission Check: Super Admin / Administrator can delete ANY document; Investigators/Analyst can delete ONLY their OWN uploaded document
$isSuperAdmin = in_array($user['role'], ['super_admin', 'administrator'], true);
$isOwner = ((int) ($document['uploaded_by'] ?? 0) === (int) $user['id']);

if (!$isSuperAdmin && !$isOwner) {
    cg_json_error('You are only authorized to delete your own uploaded documents. Super Admin rights are required to delete documents uploaded by other investigators.', 403);
}

try {
    $pdo->beginTransaction();

    // 1. Delete physical file from storage if present
    $filePath = UPLOAD_DIR . '/' . $document['stored_filename'];
    if (file_exists($filePath) && is_file($filePath)) {
        @unlink($filePath);
    }
    $altPath = APP_ROOT . '/storage/uploads/' . $document['stored_filename'];
    if (file_exists($altPath) && is_file($altPath)) {
        @unlink($altPath);
    }

    // 2. Cascade cleanup database records tied to this document
    $pdo->prepare('DELETE FROM relationships WHERE source_document_id = ?')->execute([$documentId]);
    
    // Delete entities originating from this document that NO LONGER have any relationships in this case
    $pdo->prepare('
        DELETE FROM entities 
        WHERE case_id = ? 
          AND (source_document_id = ? OR source_document_id IS NULL)
          AND id NOT IN (
              SELECT source_entity_id FROM relationships WHERE case_id = ?
              UNION
              SELECT target_entity_id FROM relationships WHERE case_id = ?
          )
    ')->execute([$caseId, $documentId, $caseId, $caseId]);

    $pdo->prepare('DELETE FROM rejected_entities WHERE document_id = ?')->execute([$documentId]);
    $pdo->prepare('DELETE FROM document_processing WHERE document_id = ?')->execute([$documentId]);
    $pdo->prepare('DELETE FROM ai_analyses WHERE document_id = ?')->execute([$documentId]);
    $pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$documentId]);

    // 3. Record case timeline event & audit log
    $pdo->prepare('INSERT INTO case_events (case_id, event_type, description, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')
        ->execute([$caseId, 'DOCUMENT_DELETED', "Document '{$document['name']}' was deleted by {$user['name']}.", $user['id']]);

    $pdo->commit();

    // 4. Trigger Graph Recalculation so case risk scores and degree centralities update dynamically
    try {
        $analyzer = new AnalysisService($pdo);
        $analyzer->runForCase($caseId, $user['id']);
    } catch (Throwable $e) {
        error_log('[CYVANTA] Post-deletion graph update notice: ' . $e->getMessage());
    }

    cg_log_audit($user['id'], 'DOCUMENT_DELETED', 'documents', $document['case_number'], 'success', "Deleted document '{$document['name']}'");
    cg_json_success("Document '{$document['name']}' deleted successfully.");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[CYVANTA] Document deletion failed: ' . $e->getMessage());
    cg_json_error('Unable to delete document.', 500);
}
