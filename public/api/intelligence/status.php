<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

$pdo = Database::connect();
$service = new IntelligenceService($pdo);

$status = $service->getDashboardStatus();
$recent = $service->getEvents([], 1, 15);

cg_json_success('Intelligence status retrieved.', [
    'status' => $status,
    'recent_events' => $recent['events']
]);
