<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$id = (int) ($_GET['id'] ?? 0);
$pdo = Database::connect();

$stmt = $pdo->prepare('SELECT e.*, et.name AS type_name FROM entities e JOIN entity_types et ON et.id = e.entity_type_id WHERE e.id = ?');
$stmt->execute([$id]);
$entity = $stmt->fetch();
if (!$entity) cg_json_error('Entity not found.', 404);

$rels = $pdo->prepare(
    "SELECT r.*, rt.name AS rel_type,
     CASE WHEN r.source_entity_id = ? THEN te.name ELSE se.name END AS other_name,
     CASE WHEN r.source_entity_id = ? THEN te.id ELSE se.id END AS other_id
     FROM relationships r
     JOIN relationship_types rt ON rt.id = r.relationship_type_id
     JOIN entities se ON se.id = r.source_entity_id
     JOIN entities te ON te.id = r.target_entity_id
     WHERE r.source_entity_id = ? OR r.target_entity_id = ?"
);
$rels->execute([$id, $id, $id, $id]);

cg_json_success('', ['entity' => $entity, 'relationships' => $rels->fetchAll()]);
