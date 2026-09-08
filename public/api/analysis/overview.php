<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$pdo = Database::connect();

$totalAnalyses = (int) $pdo->query('SELECT COUNT(*) FROM ai_analyses')->fetchColumn();
$entitiesFound = (int) $pdo->query('SELECT COUNT(*) FROM entities')->fetchColumn();
$relationshipsFound = (int) $pdo->query('SELECT COUNT(*) FROM relationships')->fetchColumn();
$clusters = (int) $pdo->query(
    "SELECT COUNT(DISTINCT case_id) FROM entities e WHERE (SELECT COUNT(*) FROM relationships r WHERE r.source_entity_id = e.id OR r.target_entity_id = e.id) >= 3"
)->fetchColumn();

$recentPatterns = $pdo->query(
    "SELECT ar.*, c.case_number, c.title FROM analysis_results ar
     JOIN ai_analyses a ON a.id = ar.analysis_id JOIN cases c ON c.id = a.case_id
     ORDER BY ar.id DESC LIMIT 10"
)->fetchAll();

cg_json_success('', [
    'ai_analyses' => $totalAnalyses,
    'entities_found' => $entitiesFound,
    'relationships_found' => $relationshipsFound,
    'network_clusters' => $clusters,
    'recent_patterns' => $recentPatterns,
]);
