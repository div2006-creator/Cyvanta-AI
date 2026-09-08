<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cg_json_error('Method not allowed.', 405);
}

$user = cg_current_user();
$input = cg_input();
$pdo = Database::connect();
$service = new IntelligenceService($pdo);

try {
    $res = $service->ingestEvent($input, $user['id'] ?? null);
    if ($res['success']) {
        cg_json_success('Intelligence event ingested successfully.', $res);
    } else {
        cg_json_error($res['message'] ?? 'Ingestion failed.', 400);
    }
} catch (InvalidArgumentException $e) {
    cg_json_error($e->getMessage(), 422);
} catch (Throwable $e) {
    cg_json_error('Server error during intelligence ingestion: ' . $e->getMessage(), 500);
}
