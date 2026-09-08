<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_admin();
$pdo = Database::connect();
$rows = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
$settings = [];
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
cg_json_success('', ['settings' => $settings]);
