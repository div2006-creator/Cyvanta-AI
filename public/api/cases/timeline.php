<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$caseId = (int) ($_GET['case_id'] ?? 0);
$pdo = Database::connect();
$stmt = $pdo->prepare(
    "SELECT ce.*, u.full_name AS created_by_name FROM case_events ce LEFT JOIN users u ON u.id = ce.created_by
     WHERE ce.case_id = ? ORDER BY ce.created_at ASC"
);
$stmt->execute([$caseId]);
cg_json_success('', ['items' => $stmt->fetchAll()]);
