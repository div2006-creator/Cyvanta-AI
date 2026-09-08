<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$caseId = (int) ($_GET['case_id'] ?? 0);
$pdo = Database::connect();
$case = $pdo->prepare('SELECT case_number FROM cases WHERE id = ?');
$case->execute([$caseId]);
$caseNumber = $case->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT al.*, u.full_name AS user_name FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
     WHERE al.target = ? ORDER BY al.created_at DESC LIMIT 50"
);
$stmt->execute([$caseNumber]);
cg_json_success('', ['items' => $stmt->fetchAll()]);
