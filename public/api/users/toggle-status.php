<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$id = (int) ($input['id'] ?? 0);
$active = (int) ($input['is_active'] ?? 0);
if (!$id) cg_json_error('User id is required.', 422);

$currentUser = cg_current_user();
if ($id === $currentUser['id']) cg_json_error('You cannot disable your own account.', 422);

$pdo = Database::connect();
$pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$active, $id]);

cg_log_audit($currentUser['id'], $active ? 'USER_ENABLED' : 'USER_DISABLED', 'users', (string) $id, 'success', $active ? 'User enabled.' : 'User disabled.');
cg_json_success($active ? 'User enabled.' : 'User disabled.');
