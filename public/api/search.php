<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
cg_require_login();
$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) cg_json_success('', ['items' => []]);
$like = "%$q%";
$pdo = Database::connect();
$items = [];

$stmt = $pdo->prepare("SELECT id, case_number, title FROM cases WHERE title LIKE ? OR case_number LIKE ? LIMIT 5");
$stmt->execute([$like, $like]);
foreach ($stmt->fetchAll() as $c) {
    $items[] = ['type' => 'Case', 'label' => "{$c['case_number']} — {$c['title']}", 'link' => "case-details.php?id={$c['id']}"];
}

$stmt = $pdo->prepare(
    "SELECT e.id, e.name, e.case_id, et.name AS type_name FROM entities e JOIN entity_types et ON et.id = e.entity_type_id
     WHERE e.name LIKE ? LIMIT 8"
);
$stmt->execute([$like]);
foreach ($stmt->fetchAll() as $e) {
    $items[] = ['type' => $e['type_name'], 'label' => $e['name'], 'meta' => 'View in case network', 'link' => "case-details.php?id={$e['case_id']}#network"];
}

$stmt = $pdo->prepare("SELECT id, name, case_id FROM documents WHERE name LIKE ? LIMIT 5");
$stmt->execute([$like]);
foreach ($stmt->fetchAll() as $d) {
    $items[] = ['type' => 'Document', 'label' => $d['name'], 'link' => "case-details.php?id={$d['case_id']}#documents"];
}

cg_json_success('', ['items' => $items]);
