<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_role(['super_admin', 'administrator', 'investigator', 'analyst']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$caseId = (int) ($input['case_id'] ?? 0);
$title = trim($input['title'] ?? '');
$note = trim($input['note'] ?? '');
if (!$caseId || $title === '' || $note === '') cg_json_error('Case, title and note text are required.', 422);

$pdo = Database::connect();
$user = cg_current_user();
$stmt = $pdo->prepare('INSERT INTO investigation_notes (case_id, title, note, author_id, created_at) VALUES (?, ?, ?, ?, NOW())');
$stmt->execute([$caseId, $title, $note, $user['id']]);

$caseNumber = $pdo->prepare('SELECT case_number FROM cases WHERE id = ?');
$caseNumber->execute([$caseId]);
$pdo->prepare('INSERT INTO case_events (case_id, event_type, description, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$caseId, 'NOTE_ADDED', 'Investigation note added.', $user['id']]);

cg_log_audit($user['id'], 'NOTE_ADDED', 'notes', (string) $caseNumber->fetchColumn(), 'success', "Added note: $title");

cg_json_success('Note added.');
