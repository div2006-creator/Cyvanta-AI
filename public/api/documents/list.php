<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$caseId = (int) ($_GET['case_id'] ?? 0);
$pdo = Database::connect();

$stmt = $pdo->prepare(
    "SELECT d.*, u.full_name AS uploaded_by_name FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by
     WHERE d.case_id = ? ORDER BY d.uploaded_at DESC"
);
$stmt->execute([$caseId]);
$docs = $stmt->fetchAll();

foreach ($docs as &$doc) {
    $stages = $pdo->prepare('SELECT stage, status, details FROM document_processing WHERE document_id = ? ORDER BY id');
    $stages->execute([$doc['id']]);
    $doc['stages'] = $stages->fetchAll();
}

cg_json_success('', ['items' => $docs]);
