<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);
$input = cg_input();
$id = (int) ($input['id'] ?? 0);
$pdo = Database::connect();
$user = cg_current_user();

$stmt = $pdo->prepare('SELECT * FROM investigation_notes WHERE id = ?');
$stmt->execute([$id]);
$note = $stmt->fetch();
if (!$note) cg_json_error('Note not found.', 404);
if ($note['author_id'] != $user['id'] && !in_array($user['role'], ['super_admin', 'administrator'], true)) {
    cg_json_error('You can only delete your own notes.', 403);
}
$pdo->prepare('DELETE FROM investigation_notes WHERE id = ?')->execute([$id]);
cg_log_audit($user['id'], 'NOTE_DELETED', 'notes', (string) $note['case_id'], 'success', 'Note deleted.');
cg_json_success('Note deleted.');
