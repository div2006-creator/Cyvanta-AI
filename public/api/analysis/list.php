<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();
$caseId = (int) ($_GET['case_id'] ?? 0);
$pdo = Database::connect();

$analyses = $pdo->prepare('SELECT * FROM ai_analyses WHERE case_id = ? ORDER BY id DESC');
$analyses->execute([$caseId]);
$analyses = $analyses->fetchAll();

$latestPatterns = [];
if ($analyses) {
    $patternAnalysis = null;
    foreach ($analyses as $a) {
        if ($a['analysis_type'] === 'pattern_detection') { $patternAnalysis = $a; break; }
    }
    if ($patternAnalysis) {
        $stmt = $pdo->prepare('SELECT * FROM analysis_results WHERE analysis_id = ? ORDER BY confidence DESC');
        $stmt->execute([$patternAnalysis['id']]);
        $latestPatterns = $stmt->fetchAll();
    }
}

cg_json_success('', ['analyses' => $analyses, 'patterns' => $latestPatterns]);
