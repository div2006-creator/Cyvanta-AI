<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

$pdo = Database::connect();

$q = trim($_GET['q'] ?? '');
$role = trim($_GET['role'] ?? '');
$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(full_name LIKE ? OR username LIKE ? OR email LIKE ? OR department LIKE ?)';
    $searchTerm = "%$q%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($role !== '') {
    $where[] = 'role = ?';
    $params[] = $role;
}

if ($status !== null) {
    $where[] = 'is_active = ?';
    $params[] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT id, full_name, username, email, department, role, is_active, last_login, created_at FROM users $whereSql ORDER BY created_at DESC, full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

cg_json_success('', ['items' => $users]);
