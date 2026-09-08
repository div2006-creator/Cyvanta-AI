<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cg_json_error('Method not allowed.', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $caseId = (int)($input['case_id'] ?? 0);
    $source = trim($input['source_name'] ?? 'Open-Source Intelligence');
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $url = trim($input['url'] ?? '');

    if (!$caseId || $title === '' || $content === '') {
        cg_json_error('Case ID, title, and intelligence content are required.', 422);
    }

    $user = cg_current_user();
    if (!cg_user_can_access_case($caseId, $user)) {
        cg_json_error('You are not authorized to access this case.', 403);
    }

    $pdo = Database::connect();
    require_once APP_ROOT . '/services/OsintIngestionService.php';
    $osintService = new OsintIngestionService($pdo);

    $res = $osintService->ingestIntelligence($caseId, $source, $title, $content, $url);

    cg_log_audit($user['id'], 'OSINT_FEED_INGESTED', 'cases', (string)$caseId, 'success', "Ingested OSINT feed: $title");
    cg_create_notification(null, 'analysis', 'Live OSINT Feed Ingested', "New intelligence ingested for case: $title", "case-details.php?id=$caseId");

    cg_json_success('Live OSINT feed ingested and processed successfully.', $res);
} catch (Throwable $e) {
    error_log('[CYVANTA] OSINT Ingestion Error: ' . $e->getMessage());
    cg_json_error('Unable to process OSINT intelligence feed.', 500);
}
