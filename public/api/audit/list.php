<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_admin();
$pdo = Database::connect();

$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where = ['1=1']; $params = [];
if ($search !== '') { $where[] = '(al.action LIKE ? OR al.target LIKE ? OR al.description LIKE ?)'; $like = "%$search%"; array_push($params, $like, $like, $like); }
if ($status !== '') { $where[] = 'al.status = ?'; $params[] = $status; }
$whereSql = implode(' AND ', $where);

$count = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al WHERE $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();
$p = cg_paginate($total, $page, $perPage);

$stmt = $pdo->prepare(
    "SELECT al.*, u.full_name AS user_name FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
     WHERE $whereSql ORDER BY al.created_at DESC LIMIT {$p['perPage']} OFFSET {$p['offset']}"
);
$stmt->execute($params);

cg_json_success('', ['items' => $stmt->fetchAll(), 'pagination' => ['page' => $p['page'], 'total_pages' => $p['totalPages']]]);
