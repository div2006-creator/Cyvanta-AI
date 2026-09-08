<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$user = cg_current_user();
$pdo = Database::connect();
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL")->execute([$user['id']]);
cg_json_success('Notifications marked as read.');
