<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$id = (int) ($input['id'] ?? 0);
if (!$id) cg_json_error('User id is required.', 422);

$tempPassword = bin2hex(random_bytes(6));
$pdo = Database::connect();
$pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?')
    ->execute([password_hash($tempPassword, PASSWORD_BCRYPT), $id]);

cg_log_audit(cg_current_user()['id'], 'PASSWORD_RESET_BY_ADMIN', 'users', (string) $id, 'success', 'Password reset by administrator.');
cg_json_success('Password reset. Temporary password generated.', ['temp_password' => $tempPassword]);
