<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$pdo = Database::connect();

$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$category = trim($_GET['category'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where = ['1=1'];
$params = [];

$user = cg_current_user();
if (in_array($user['role'], ['investigator', 'analyst', 'viewer'], true)) {
    $where[] = '(c.lead_investigator_id = ? OR c.created_by = ? OR EXISTS (SELECT 1 FROM case_assignments ca WHERE ca.case_id = c.id AND ca.user_id = ?) OR EXISTS (SELECT 1 FROM documents d WHERE d.case_id = c.id AND d.uploaded_by = ?) OR EXISTS (SELECT 1 FROM evidence e WHERE e.case_id = c.id AND e.uploaded_by = ?))';
    array_push($params, $user['id'], $user['id'], $user['id'], $user['id'], $user['id']);
}
if ($search !== '') {
    $where[] = '(c.title LIKE ? OR c.case_number LIKE ? OR c.description LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if ($status !== '') { $where[] = 'c.status = ?'; $params[] = $status; }
if ($priority !== '') { $where[] = 'c.priority = ?'; $params[] = $priority; }
if ($category !== '') { $where[] = 'c.category = ?'; $params[] = $category; }

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cases c WHERE $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$p = cg_paginate($total, $page, $perPage);

$stmt = $pdo->prepare(
    "SELECT c.*, u.full_name AS investigator_name
     FROM cases c LEFT JOIN users u ON u.id = c.lead_investigator_id
     WHERE $whereSql ORDER BY c.updated_at DESC LIMIT {$p['perPage']} OFFSET {$p['offset']}"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$categories = $pdo->query('SELECT DISTINCT category FROM cases WHERE category IS NOT NULL ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

cg_json_success('', [
    'items' => $rows,
    'pagination' => ['page' => $p['page'], 'total_pages' => $p['totalPages'], 'total' => $total],
    'categories' => $categories,
]);
