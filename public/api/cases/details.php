<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$id = (int) ($_GET['id'] ?? 0);
$pdo = Database::connect();

$stmt = $pdo->prepare('SELECT c.*, u.full_name AS investigator_name, u.email AS investigator_email FROM cases c LEFT JOIN users u ON u.id = c.lead_investigator_id WHERE c.id = ?');
$stmt->execute([$id]);
$case = $stmt->fetch();
if (!$case) cg_json_error('Case not found.', 404);

$entityCount = $pdo->prepare('SELECT COUNT(*) FROM entities WHERE case_id = ?'); $entityCount->execute([$id]);
$relCount = $pdo->prepare('SELECT COUNT(*) FROM relationships WHERE case_id = ?'); $relCount->execute([$id]);
$docCount = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE case_id = ?'); $docCount->execute([$id]);
$noteCount = $pdo->prepare('SELECT COUNT(*) FROM investigation_notes WHERE case_id = ?'); $noteCount->execute([$id]);
$analysisCount = $pdo->prepare('SELECT COUNT(*) FROM ai_analyses WHERE case_id = ?'); $analysisCount->execute([$id]);

$memStmt = $pdo->prepare(
    'SELECT u.id, u.full_name, u.email, u.role, u.department, ca.assigned_at
     FROM case_assignments ca
     JOIN users u ON u.id = ca.user_id
     WHERE ca.case_id = ?
     ORDER BY ca.assigned_at DESC'
);
$memStmt->execute([$id]);
$assignedMembers = $memStmt->fetchAll();

cg_json_success('', [
    'case' => $case,
    'assigned_members' => $assignedMembers,
    'snapshot' => [
        'entities' => (int) $entityCount->fetchColumn(),
        'relationships' => (int) $relCount->fetchColumn(),
        'documents' => (int) $docCount->fetchColumn(),
        'notes' => (int) $noteCount->fetchColumn(),
        'analyses' => (int) $analysisCount->fetchColumn(),
    ],
]);
