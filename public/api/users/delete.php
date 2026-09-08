<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_role(['super_admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$id = (int) ($input['id'] ?? 0);
if (!$id) cg_json_error('User id is required.', 422);
if ($id === cg_current_user()['id']) cg_json_error('You cannot delete your own account.', 422);

$pdo = Database::connect();
// Prefer soft-delete/deactivation for important accounts to preserve audit history.
$pdo->prepare('UPDATE users SET is_active = 0, username = CONCAT(username, "_deleted_", UNIX_TIMESTAMP()) WHERE id = ?')->execute([$id]);

cg_log_audit(cg_current_user()['id'], 'USER_DELETED', 'users', (string) $id, 'success', 'User soft-deleted (deactivated).');
cg_json_success('User deleted (deactivated). Historical records are preserved for audit purposes.');
