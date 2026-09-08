<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_admin();
$id = (int) ($_GET['id'] ?? 0);
$pdo = Database::connect();
$stmt = $pdo->prepare('SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([$id]);
cg_json_success('', ['items' => $stmt->fetchAll()]);
