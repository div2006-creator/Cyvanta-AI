<?php
require_once dirname(__DIR__, 4) . '/includes/bootstrap.php';
cg_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cg_json_error('Method not allowed.', 405);
}

$pdo = Database::connect();
$service = new IntelligenceService($pdo);

$service->setSimulationState(false);

cg_json_success('Simulation generator stopped.', [
    'simulation_active' => false
]);
