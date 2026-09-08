<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$user = cg_current_user();
$limit = min(30, (int) ($_GET['limit'] ?? 10));
$pdo = Database::connect();

$stmt = $pdo->prepare(
    "SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT $limit"
);
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();
foreach ($items as &$n) $n['time_ago'] = cg_time_ago($n['created_at']);

$unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
$unread->execute([$user['id']]);

cg_json_success('', ['items' => $items, 'unread_count' => (int) $unread->fetchColumn()]);
