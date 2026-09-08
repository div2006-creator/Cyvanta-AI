<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$caseId = (int) ($_GET['case_id'] ?? 0);
$pdo = Database::connect();
$stmt = $pdo->prepare(
    "SELECT ev.*, u.full_name AS uploaded_by_name FROM evidence ev LEFT JOIN users u ON u.id = ev.uploaded_by
     WHERE ev.case_id = ? ORDER BY ev.created_at DESC"
);
$stmt->execute([$caseId]);
cg_json_success('', ['items' => $stmt->fetchAll()]);
