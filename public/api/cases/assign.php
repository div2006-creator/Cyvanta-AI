<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_role(['super_admin', 'administrator', 'investigator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$caseId = (int) ($input['case_id'] ?? 0);
$userId = (int) ($input['user_id'] ?? $input['lead_investigator_id'] ?? 0);

if (!$caseId || !$userId) cg_json_error('Case ID and user selection are required.', 422);

$pdo = Database::connect();

// Fetch target user and case info
$uStmt = $pdo->prepare('SELECT full_name FROM users WHERE id = ? AND is_active = 1');
$uStmt->execute([$userId]);
$targetUser = $uStmt->fetch();
if (!$targetUser) cg_json_error('Selected user does not exist or is inactive.', 404);

$cStmt = $pdo->prepare('SELECT case_number, title FROM cases WHERE id = ?');
$cStmt->execute([$caseId]);
$case = $cStmt->fetch();
if (!$case) cg_json_error('Case not found.', 404);

// Update lead investigator
$pdo->prepare('UPDATE cases SET lead_investigator_id = ?, updated_at = NOW() WHERE id = ?')
    ->execute([$userId, $caseId]);

// Record assignment record (using SQLite compatible query)
$check = $pdo->prepare('SELECT id FROM case_assignments WHERE case_id = ? AND user_id = ?');
$check->execute([$caseId, $userId]);
if (!$check->fetchColumn()) {
    $pdo->prepare('INSERT INTO case_assignments (case_id, user_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())')
        ->execute([$caseId, $userId, cg_current_user()['id']]);
}

// Log event & notification
$desc = "Case assigned to lead investigator: {$targetUser['full_name']}.";
$pdo->prepare('INSERT INTO case_events (case_id, event_type, description, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')
    ->execute([$caseId, 'CASE_ASSIGNED', $desc, cg_current_user()['id']]);

cg_log_audit(cg_current_user()['id'], 'CASE_ASSIGNED', 'cases', $case['case_number'], 'success', $desc);
cg_create_notification($userId, 'case', 'Case Assignment Updated', "You were assigned as lead investigator to {$case['case_number']} ({$case['title']}).", "case-details.php?id=$caseId");

cg_json_success("Case assigned to {$targetUser['full_name']}.", [
    'lead_investigator_id' => $userId,
    'investigator_name' => $targetUser['full_name'],
]);
