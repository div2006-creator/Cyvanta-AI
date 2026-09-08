<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$caseId = (int) ($_GET['case_id'] ?? 0);
$pdo = Database::connect();

$stmt = $pdo->prepare(
    "SELECT e.*, et.name AS type_name, et.icon, et.color,
     (SELECT COUNT(*) FROM relationships r WHERE r.source_entity_id = e.id OR r.target_entity_id = e.id) AS connections
     FROM entities e JOIN entity_types et ON et.id = e.entity_type_id
     WHERE e.case_id = ? ORDER BY connections DESC"
);
$stmt->execute([$caseId]);
cg_json_success('', ['items' => $stmt->fetchAll()]);
