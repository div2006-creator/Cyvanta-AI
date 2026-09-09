<?php
/**
 * CYVANTA - Full Case Evidence Reprocessing & Migration Script
 * Reprocesses all documents across all existing cases to ensure the Evidence section,
 * original timeline dates, intelligence findings, and network graphs are 100% complete.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../services/DocumentProcessingService.php';
require_once __DIR__ . '/../services/AnalysisService.php';

$pdo = Database::connect();
$procService = new DocumentProcessingService($pdo);
$analysisService = new AnalysisService($pdo);

echo "=====================================================================\n";
echo "  CYVANTA - FULL REPROCESSING OF ALL CASES & EVIDENCES\n";
echo "=====================================================================\n\n";

$cases = $pdo->query('SELECT id, case_number, title FROM cases ORDER BY id ASC')->fetchAll();
echo "Found " . count($cases) . " total cases in database.\n\n";

$totalEvSaved = 0;

foreach ($cases as $c) {
    $caseId = (int) $c['id'];
    echo "Processing Case #{$caseId} [{$c['case_number']}] — '{$c['title']}'...\n";

    $stmt = $pdo->prepare('SELECT * FROM documents WHERE case_id = ? ORDER BY id ASC');
    $stmt->execute([$caseId]);
    $documents = $stmt->fetchAll();

    echo "  Found " . count($documents) . " document(s) uploaded to this case.\n";

    foreach ($documents as $doc) {
        $docId = (int) $doc['id'];
        echo "   -> Processing Document #{$docId}: '{$doc['name']}' ({$doc['doc_type']})...\n";

        try {
            $res = $procService->process($docId);
            echo "      Extracted " . ($res['entities_found'] ?? 0) . " entities, " . ($res['relationships_found'] ?? 0) . " relationships.\n";
        } catch (Throwable $e) {
            echo "      Processing error: " . $e->getMessage() . "\n";
        }
    }

    // Run case-wide pattern analysis & risk scoring
    try {
        $analysisService->runForCase($caseId);
    } catch (Throwable $e) {
        echo "      Analysis warning: " . $e->getMessage() . "\n";
    }

    // Count total evidence items now present for this case
    $totalInCaseStmt = $pdo->prepare('SELECT COUNT(*) FROM evidence WHERE case_id = ?');
    $totalInCaseStmt->execute([$caseId]);
    $totalCaseEv = $totalInCaseStmt->fetchColumn();

    echo "  [DONE] Case #{$caseId} now has {$totalCaseEv} total items in Evidence section.\n\n";
}

echo "=====================================================================\n";
echo "  REPROCESSING COMPLETE ALL EXISTING CASES AND DOCUMENTS PROCESSED.\n";
echo "=====================================================================\n";
