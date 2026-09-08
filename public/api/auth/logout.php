<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_logout();
header('Location: ' . cg_base_url('/login.php'));
exit;
