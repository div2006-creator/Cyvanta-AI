<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$caseId = (int) ($_GET['case_id'] ?? 0);
$documentId = (int) ($_GET['document_id'] ?? 0);
$pdo = Database::connect();

if ($caseId > 0 && $documentId === 0) {
    try {
        $analyzer = new AnalysisService($pdo);
        $analyzer->runForCase($caseId);
    } catch (Throwable $e) {}
}

if ($documentId > 0) {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT e.*, et.name AS type_name, et.icon, et.color,
         (SELECT COUNT(*) FROM relationships r WHERE (r.source_entity_id = e.id OR r.target_entity_id = e.id) AND r.source_document_id = ?) AS connections
         FROM entities e 
         JOIN entity_types et ON et.id = e.entity_type_id
         WHERE e.case_id = ? 
           AND (
             e.source_document_id = ? 
             OR e.id IN (SELECT source_entity_id FROM relationships WHERE source_document_id = ?)
             OR e.id IN (SELECT target_entity_id FROM relationships WHERE source_document_id = ?)
           )
         ORDER BY connections DESC"
    );
    $stmt->execute([$documentId, $caseId, $documentId, $documentId, $documentId]);
} else {
    $stmt = $pdo->prepare(
        "SELECT e.*, et.name AS type_name, et.icon, et.color,
         (SELECT COUNT(*) FROM relationships r WHERE r.source_entity_id = e.id OR r.target_entity_id = e.id) AS connections
         FROM entities e JOIN entity_types et ON et.id = e.entity_type_id
         WHERE e.case_id = ? ORDER BY connections DESC"
    );
    $stmt->execute([$caseId]);
}
$items = $stmt->fetchAll();

foreach ($items as &$e) {
    $rInfo = cg_calculate_risk_level($e['risk_score'], $e['type_name'], $e['name'], $e['description']);
    $e['is_risk_eligible'] = $rInfo['is_eligible'];
    $e['risk_score_display'] = $rInfo['score_display'];
    $e['risk_level_display'] = $rInfo['level_display'];
}
unset($e);

cg_json_success('', ['items' => $items]);
