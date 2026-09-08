<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

$pdo = Database::connect();
$service = new GovernmentApiService($pdo);
$config = $service->getConfig();

// Fetch recently ingested government cases
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name AS investigator_name 
    FROM cases c 
    LEFT JOIN users u ON u.id = c.lead_investigator_id 
    WHERE c.tags LIKE '%cctns%' OR c.tags LIKE '%live-api%' OR c.case_number LIKE 'GOV-%'
    ORDER BY c.created_at DESC 
    LIMIT 20
");
$stmt->execute();
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

cg_json_success('', [
    'config' => $config,
    'recent_cases' => $cases
]);
