<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

try {
    $caseId = (int)($_GET['case_id'] ?? 0);
    if (!$caseId) {
        cg_json_error('Case ID is required.', 422);
    }

    $user = cg_current_user();
    if (!cg_user_can_access_case($caseId, $user)) {
        cg_json_error('You are not authorized to view this case.', 403);
    }

    $pdo = Database::connect();
    require_once APP_ROOT . '/services/AnalysisService.php';
    $service = new AnalysisService($pdo);

    $matches = $service->findCrossCaseMatches($caseId);

    cg_json_success('Cross-case matches retrieved successfully.', [
        'case_id' => $caseId,
        'matches_count' => count($matches),
        'items' => $matches,
    ]);
} catch (Throwable $e) {
    error_log('[CYVANTA] Cross-case match error: ' . $e->getMessage());
    cg_json_error('Unable to compute cross-case entity matches.', 500);
}
