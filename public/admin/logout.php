<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
cg_logout();
header('Location: ' . cg_base_url('/login.php'));
exit;
