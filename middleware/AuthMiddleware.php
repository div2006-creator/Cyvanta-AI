<?php
/**
 * Thin OOP wrapper around includes/auth.php's session/login helpers, kept
 * for teams that prefer to call middleware as classes from controllers.
 * Functionally identical to cg_require_login() / cg_require_role().
 */
require_once __DIR__ . '/../includes/auth.php';

class AuthMiddleware
{
    public static function handle(): void
    {
        cg_require_login();
    }
}
