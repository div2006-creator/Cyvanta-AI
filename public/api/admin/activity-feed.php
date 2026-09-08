<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_admin();
$pdo = Database::connect();
$since = (int) ($_GET['since_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM system_activity WHERE id > ? ORDER BY id DESC LIMIT 50');
$stmt->execute([$since]);
$items = $stmt->fetchAll();
foreach ($items as &$i) $i['time_ago'] = cg_time_ago($i['created_at']);

cg_json_success('', ['items' => $items, 'latest_id' => $items[0]['id'] ?? $since]);
