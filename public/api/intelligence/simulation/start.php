<?php
require_once dirname(__DIR__, 4) . '/includes/bootstrap.php';
cg_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cg_json_error('Method not allowed.', 405);
}

$user = cg_current_user();
$pdo = Database::connect();
$service = new IntelligenceService($pdo);

$service->setSimulationState(true);

// Trigger immediate synthetic event ingestion for demonstration
$res = $service->pollSources($user['id'] ?? null);

cg_json_success('Simulation generator started successfully.', [
    'simulation_active' => true,
    'ingested' => $res
]);
