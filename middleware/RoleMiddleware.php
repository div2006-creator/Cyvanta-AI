<?php
require_once __DIR__ . '/../includes/auth.php';

class RoleMiddleware
{
    public static function handle(array $allowedRoles): void
    {
        cg_require_role($allowedRoles);
    }
}
