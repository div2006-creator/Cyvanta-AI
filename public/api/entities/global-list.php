<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$pdo = Database::connect();
$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');

$where = ['1=1']; $params = [];
if ($search !== '') { $where[] = 'e.name LIKE ?'; $params[] = "%$search%"; }
if ($type !== '') { $where[] = 'et.name = ?'; $params[] = $type; }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT e.*, et.name AS type_name, c.case_number, c.title AS case_title,
     (SELECT COUNT(*) FROM relationships r WHERE r.source_entity_id = e.id OR r.target_entity_id = e.id) AS connections
     FROM entities e JOIN entity_types et ON et.id = e.entity_type_id JOIN cases c ON c.id = e.case_id
     WHERE $whereSql ORDER BY connections DESC LIMIT 200"
);
$stmt->execute($params);
$items = $stmt->fetchAll();

foreach ($items as &$e) {
    $rInfo = cg_calculate_risk_level($e['risk_score'], $e['type_name'], $e['name'], $e['description']);
    $e['is_risk_eligible'] = $rInfo['is_eligible'];
    $e['risk_score_display'] = $rInfo['score_display'];
    $e['risk_level_display'] = $rInfo['level_display'];
}
unset($e);

$types = $pdo->query('SELECT name FROM entity_types ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
cg_json_success('', ['items' => $items, 'types' => $types]);
