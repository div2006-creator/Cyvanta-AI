<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_role(['super_admin', 'administrator', 'investigator']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$id = (int) ($input['id'] ?? 0);
if (!$id) cg_json_error('Case id is required.', 422);

$pdo = Database::connect();
$pdo->prepare("UPDATE cases SET status = 'Archived', updated_at = NOW() WHERE id = ?")->execute([$id]);

$case = $pdo->prepare('SELECT case_number FROM cases WHERE id = ?');
$case->execute([$id]);
$caseNumber = $case->fetchColumn();

cg_log_audit(cg_current_user()['id'], 'CASE_ARCHIVED', 'cases', (string) $caseNumber, 'success', 'Case archived.');
cg_json_success('Case archived.');
