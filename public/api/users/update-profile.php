<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$user = cg_current_user();
$pdo = Database::connect();

$stmt = $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, department = ?, email = ? WHERE id = ?');
$stmt->execute([
    trim($input['full_name'] ?? $user['name']), $input['phone'] ?? null,
    $input['department'] ?? null, trim($input['email'] ?? $user['email']), $user['id'],
]);

$_SESSION['user']['name'] = trim($input['full_name'] ?? $user['name']);
$_SESSION['user']['email'] = trim($input['email'] ?? $user['email']);
$_SESSION['user']['department'] = $input['department'] ?? null;

cg_log_audit($user['id'], 'PROFILE_UPDATED', 'users', (string) $user['id'], 'success', 'Profile updated.');
cg_json_success('Profile updated successfully.');
