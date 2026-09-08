<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

$user = cg_current_user();
$pdo = Database::connect();
$service = new GovernmentApiService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = cg_input();

    // If config update
    if (isset($input['action']) && $input['action'] === 'update_config') {
        cg_require_admin();
        $service->updateConfig($input);
        cg_json_success('Government API configuration updated successfully.');
    }

    // Trigger sync
    $intelService = new IntelligenceService($pdo);
    $res = $intelService->pollSources($user['id']);
    cg_json_success(
        $res['ingested_count'] > 0 
            ? "Successfully ingested {$res['ingested_count']} new intelligence alert(s)." 
            : "Intelligence sync completed. All feeds are up to date.",
        $res
    );
}

$config = $service->getConfig();
cg_json_success('Government API status retrieved.', $config);
