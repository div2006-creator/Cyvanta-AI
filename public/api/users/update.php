<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$id = (int) ($input['id'] ?? 0);
if (!$id) cg_json_error('User id is required.', 422);

$pdo = Database::connect();
$currentUser = cg_current_user();

$fields = ['full_name', 'email', 'phone', 'department', 'role'];
$updates = []; $params = [];
foreach ($fields as $f) {
    if (array_key_exists($f, $input)) {
        if ($f === 'role' && $input[$f] === 'super_admin' && $currentUser['role'] !== 'super_admin') {
            cg_json_error('Only a Super Admin can assign the Super Admin role.', 403);
        }
        $updates[] = "$f = ?"; $params[] = $input[$f];
    }
}
if (!$updates) cg_json_error('No fields to update.', 422);
$params[] = $id;
$pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?')->execute($params);

cg_log_audit($currentUser['id'], 'USER_UPDATED', 'users', (string) $id, 'success', 'User fields updated.');
cg_json_success('User updated successfully.');
