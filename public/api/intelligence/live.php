<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

$pdo = Database::connect();
$service = new IntelligenceService($pdo);

$events = $service->getEvents([], 1, 10);
cg_json_success('Latest live intelligence events retrieved.', [
    'events' => $events['events'],
    'status' => $service->getDashboardStatus()
]);
