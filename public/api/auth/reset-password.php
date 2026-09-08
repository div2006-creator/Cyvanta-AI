<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);
$input = cg_input();
$token = $input['token'] ?? '';
$password = (string) ($input['password'] ?? '');
if (strlen($password) < 8) cg_json_error('Password must be at least 8 characters.', 422);

$pdo = Database::connect();
$stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()');
$stmt->execute([$token]);
$reset = $stmt->fetch();
if (!$reset) cg_json_error('This reset link is invalid or has expired.', 422);

$pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
    ->execute([password_hash($password, PASSWORD_BCRYPT), $reset['user_id']]);
$pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$reset['id']]);

cg_log_audit((int) $reset['user_id'], 'PASSWORD_RESET_COMPLETED', 'auth', (string) $reset['user_id'], 'success', 'Password reset via token.');
cg_json_success('Password reset successfully. You can now log in.');
