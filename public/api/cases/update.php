<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_role(['super_admin', 'administrator', 'investigator', 'analyst']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$id = (int) ($input['id'] ?? 0);
if (!$id) cg_json_error('Case id is required.', 422);

$pdo = Database::connect();
$stmt = $pdo->prepare('SELECT * FROM cases WHERE id = ?');
$stmt->execute([$id]);
$case = $stmt->fetch();
if (!$case) cg_json_error('Case not found.', 404);

$fields = ['title', 'description', 'category', 'location', 'incident_date', 'priority', 'status', 'tags', 'lead_investigator_id'];
$updates = [];
$params = [];
foreach ($fields as $f) {
    if (array_key_exists($f, $input)) {
        $updates[] = "$f = ?";
        $params[] = $input[$f] === '' ? null : $input[$f];
    }
}
if (!$updates) cg_json_error('No fields to update.', 422);
$params[] = $id;

$pdo->prepare('UPDATE cases SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?')->execute($params);

$user = cg_current_user();
$pdo->prepare('INSERT INTO case_events (case_id, event_type, description, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')->execute([$id, 'CASE_UPDATED', 'Case information updated.', $user['id']]);
if (isset($input['status']) && $input['status'] !== $case['status']) {
    $pdo->prepare('INSERT INTO case_events (case_id, event_type, description, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')
        ->execute([$id, 'STATUS_CHANGED', "Status changed from {$case['status']} to {$input['status']}.", $user['id']]);
    cg_create_notification(null, 'case', 'Case Status Updated', "{$case['case_number']} is now {$input['status']}.", "case-details.php?id=$id");
}

cg_log_audit($user['id'], 'CASE_UPDATED', 'cases', $case['case_number'], 'success', 'Case fields updated.');
cg_json_success('Case updated successfully.');
