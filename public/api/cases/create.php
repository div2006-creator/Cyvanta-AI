<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_role(['super_admin', 'administrator', 'investigator']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);

$input = cg_input();
$title = trim($input['title'] ?? '');
if ($title === '') cg_json_error('Case title is required.', 422);

$pdo = Database::connect();
$user = cg_current_user();
$caseNumber = cg_generate_case_id();
$leadId = !empty($input['lead_investigator_id']) ? (int)$input['lead_investigator_id'] : $user['id'];

$stmt = $pdo->prepare(
    'INSERT INTO cases (case_number, title, description, category, location, incident_date, priority, status, tags, created_by, lead_investigator_id, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$stmt->execute([
    $caseNumber,
    $title,
    $input['description'] ?? null,
    $input['category'] ?? null,
    $input['location'] ?? null,
    !empty($input['incident_date']) ? $input['incident_date'] : null,
    $input['priority'] ?? 'Medium',
    $input['status'] ?? 'New',
    $input['tags'] ?? null,
    $user['id'],
    $leadId,
]);
$caseId = (int) $pdo->lastInsertId();

// Create assignment entry
$pdo->prepare('INSERT INTO case_assignments (case_id, user_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())')
    ->execute([$caseId, $leadId, $user['id']]);

$pdo->prepare('INSERT INTO case_events (case_id, event_type, description, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')
    ->execute([$caseId, 'CASE_CREATED', "Case $caseNumber created.", $user['id']]);

cg_log_audit($user['id'], 'CASE_CREATED', 'cases', $caseNumber, 'success', "Created case: $title");
cg_create_notification(null, 'case', 'New Case Created', "$caseNumber — $title was created.", "case-details.php?id=$caseId");

cg_json_success('Case created successfully.', ['id' => $caseId, 'case_number' => $caseNumber]);
