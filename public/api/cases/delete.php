<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_role(['super_admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$id = (int) ($input['id'] ?? 0);
if (!$id) cg_json_error('Case id is required.', 422);

$pdo = Database::connect();
$stmt = $pdo->prepare('SELECT case_number FROM cases WHERE id = ?');
$stmt->execute([$id]);
$caseNumber = $stmt->fetchColumn();
if (!$caseNumber) cg_json_error('Case not found.', 404);

$pdo->prepare('DELETE FROM cases WHERE id = ?')->execute([$id]);

cg_log_audit(cg_current_user()['id'], 'CASE_DELETED', 'cases', (string) $caseNumber, 'success', 'Case permanently deleted.');
cg_json_success('Case deleted.');
