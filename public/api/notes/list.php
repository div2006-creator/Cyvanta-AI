<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$caseId = (int) ($_GET['case_id'] ?? 0);
$pdo = Database::connect();
$stmt = $pdo->prepare(
    "SELECT n.*, u.full_name AS author_name FROM investigation_notes n LEFT JOIN users u ON u.id = n.author_id
     WHERE n.case_id = ? ORDER BY n.created_at DESC"
);
$stmt->execute([$caseId]);
cg_json_success('', ['items' => $stmt->fetchAll()]);
