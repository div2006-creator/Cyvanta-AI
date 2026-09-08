<?php
/**
 * CYVANTA - Page bootstrap
 * Include this at the top of every protected page.
 */

$__cg_is_api = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
if ($__cg_is_api) {
    ob_start();
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../services/AnalysisService.php';
require_once __DIR__ . '/../services/DocumentProcessingService.php';
require_once __DIR__ . '/../services/GovernmentApiService.php';

cg_session_start();
if ($__cg_is_api) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Centralized CSRF protection for state-changing API requests.
// The login endpoint is exempt since it is the entry point before a
// session-bound token exists; every other POST/PUT/DELETE under /api/
// must carry a valid X-CSRF-Token header (see assets/js/app.js's cgApi()).
$__uri = $_SERVER['REQUEST_URI'] ?? '';
$__method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (str_contains($__uri, '/api/') && !str_contains($__uri, '/api/auth/login.php') && in_array($__method, ['POST', 'PUT', 'DELETE'], true)) {
    $__token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? null;
    if (!cg_verify_csrf($__token)) {
        cg_json_error('Invalid or expired security token. Please refresh the page and try again.', 419);
    }
}
