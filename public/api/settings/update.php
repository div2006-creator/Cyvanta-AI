<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_role(['super_admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$pdo = Database::connect();
$stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
foreach ($input as $key => $value) {
    if ($key === 'csrf_token') continue;
    $stmt->execute([$key, is_array($value) ? json_encode($value) : (string) $value]);
}

cg_log_audit(cg_current_user()['id'], 'SETTINGS_CHANGED', 'settings', null, 'success', 'System settings updated.');
cg_json_success('Settings saved successfully.');
