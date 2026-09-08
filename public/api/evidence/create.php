<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_role(['super_admin', 'administrator', 'investigator', 'analyst']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$caseId = (int) ($input['case_id'] ?? 0);
$type = trim($input['evidence_type'] ?? '');
if (!$caseId || $type === '') cg_json_error('Case and evidence type are required.', 422);

$pdo = Database::connect();
$user = cg_current_user();
$stmt = $pdo->prepare(
    'INSERT INTO evidence (case_id, evidence_type, description, source, collected_date, uploaded_by, status, confidentiality, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$stmt->execute([
    $caseId, $type, $input['description'] ?? null, $input['source'] ?? null,
    !empty($input['collected_date']) ? $input['collected_date'] : null,
    $user['id'], 'Collected', $input['confidentiality'] ?? 'Internal',
]);

$caseNumber = $pdo->prepare('SELECT case_number FROM cases WHERE id = ?');
$caseNumber->execute([$caseId]);
$pdo->prepare('INSERT INTO case_events (case_id, event_type, description, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$caseId, 'EVIDENCE_ADDED', 'Evidence added.', $user['id']]);

cg_log_audit($user['id'], 'EVIDENCE_UPLOADED', 'evidence', (string) $caseNumber->fetchColumn(), 'success', "Added evidence: $type");

cg_json_success('Evidence recorded successfully.');
