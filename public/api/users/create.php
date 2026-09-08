<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$fullName = trim($input['full_name'] ?? '');
$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$password = (string) ($input['password'] ?? '');
$role = $input['role'] ?? 'viewer';

if ($fullName === '' || $username === '' || $email === '' || strlen($password) < 8) {
    cg_json_error('Full name, username, email and an 8+ character password are required.', 422);
}
$currentUser = cg_current_user();
if ($role === 'super_admin' && $currentUser['role'] !== 'super_admin') {
    cg_json_error('Only a Super Admin can create another Super Admin.', 403);
}

$pdo = Database::connect();
$dupe = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
$dupe->execute([$username, $email]);
if ($dupe->fetch()) cg_json_error('A user with that username or email already exists.', 422);

$stmt = $pdo->prepare(
    'INSERT INTO users (full_name, username, email, phone, department, password_hash, role, is_active, must_change_password, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())'
);
$stmt->execute([$fullName, $username, $email, $input['phone'] ?? null, $input['department'] ?? null, password_hash($password, PASSWORD_BCRYPT), $role]);
$newId = (int) $pdo->lastInsertId();

cg_log_audit($currentUser['id'], 'USER_CREATED', 'users', $username, 'success', "Created user $fullName ($role).");
cg_json_success('User created successfully.', ['id' => $newId]);
