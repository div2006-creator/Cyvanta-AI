<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$caseId = (int) ($_GET['case_id'] ?? 0);
$pdo = Database::connect();

$stmt = $pdo->prepare(
    "SELECT r.id, r.source_entity_id, r.target_entity_id, r.strength, rt.name AS rel_type,
            e1.name AS source_name, e2.name AS target_name
     FROM relationships r 
     JOIN relationship_types rt ON rt.id = r.relationship_type_id
     JOIN entities e1 ON e1.id = r.source_entity_id
     JOIN entities e2 ON e2.id = r.target_entity_id
     WHERE r.case_id = ?"
);
$stmt->execute([$caseId]);
cg_json_success('', ['items' => $stmt->fetchAll()]);
