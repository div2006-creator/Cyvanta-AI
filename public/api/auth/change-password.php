<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$user = cg_current_user();
$pdo = Database::connect();

$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$hash = $stmt->fetchColumn();

if (!password_verify((string) ($input['current_password'] ?? ''), $hash)) {
    cg_json_error('Current password is incorrect.', 422);
}
$newPassword = (string) ($input['new_password'] ?? '');
if (strlen($newPassword) < 8) {
    cg_json_error('New password must be at least 8 characters.', 422);
}

$pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
    ->execute([password_hash($newPassword, PASSWORD_BCRYPT), $user['id']]);

cg_log_audit($user['id'], 'PASSWORD_CHANGED', 'auth', (string) $user['id'], 'success', 'Password changed.');
cg_json_success('Password updated successfully.');
