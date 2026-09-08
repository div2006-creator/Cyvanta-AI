<?php
require_once __DIR__ . '/../includes/auth.php';

class CsrfMiddleware
{
    /** Validates the X-CSRF-Token header against the session token. */
    public static function handle(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!cg_verify_csrf($token)) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page.']);
            exit;
        }
    }
}
