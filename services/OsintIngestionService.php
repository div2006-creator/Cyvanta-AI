<?php
/**
 * CYVANTA - OSINT Ingestion Service
 *
 * Ingests live open-source intelligence (news, RSS, web alerts, agency updates)
 * for active unsolved investigation cases and feeds them into the AI extraction pipeline.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/DocumentProcessingService.php';

class OsintIngestionService
{
    private PDO $pdo;
    private DocumentProcessingService $docService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->docService = new DocumentProcessingService($pdo);
    }

    /** Ingest raw OSINT news/intelligence update text for a specific case */
    public function ingestIntelligence(int $caseId, string $sourceName, string $title, string $content, ?string $url = null): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cases WHERE id = ?');
        $stmt->execute([$caseId]);
        $case = $stmt->fetch();
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        // 1. Save OSINT feed item
        $stmt = $this->pdo->prepare(
            'INSERT INTO osint_feeds (case_id, source_name, title, content, url, published_at, created_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$caseId, $sourceName, $title, $content, $url]);
        $feedId = (int)$this->pdo->lastInsertId();

        // 2. Create document record for OSINT intelligence update
        $storedName = 'osint_' . bin2hex(random_bytes(8)) . '.txt';
        $fullPath = UPLOAD_DIR . '/' . $storedName;
        $docText = "LIVE OSINT INTELLIGENCE FEED UPDATE\nSource: $sourceName\nTitle: $title\nURL: " . ($url ?? 'N/A') . "\n\n$content";
        file_put_contents($fullPath, $docText);

        $stmt = $this->pdo->prepare(
            'INSERT INTO documents (case_id, name, doc_type, description, source, confidentiality, stored_filename, original_filename, file_size, status, uploaded_by, uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())'
        );
        $stmt->execute([
            $caseId,
            '[OSINT Live] ' . $title,
            'TXT',
            'Live Open-Source Intelligence feed update from ' . $sourceName,
            $sourceName,
            'Public',
            $storedName,
            'osint_feed_' . $feedId . '.txt',
            strlen($docText),
            'Uploaded'
        ]);
        $docId = (int)$this->pdo->lastInsertId();

        // 3. Process OSINT document through extraction pipeline
        $processResult = $this->docService->process($docId);

        // 4. Record Case Event
        $stmt = $this->pdo->prepare('INSERT INTO case_events (case_id, event_type, description, created_by, created_at) VALUES (?, ?, ?, NULL, NOW())');
        $stmt->execute([$caseId, 'OSINT_FEED_INGESTED', "Live OSINT update ingested from $sourceName: $title"]);

        return [
            'feed_id' => $feedId,
            'document_id' => $docId,
            'entities_found' => $processResult['entities_found'],
            'relationships_found' => $processResult['relationships_found'],
        ];
    }

    /** Pre-loaded public RSS feed simulation for active unsolved cases */
    public function fetchLivePublicAlerts(string $keywords): array
    {
        // Generates structured OSINT alerts for given keywords (e.g. crime, smuggling, cyber attack, shell company)
        $keywordsClean = strtolower(trim($keywords));
        $alerts = [
            [
                'source' => 'National Crime Intelligence Bureau',
                'title' => 'Cross-Border Smuggling Network Operations Flagged in Andheri',
                'content' => 'Law enforcement agencies have flagged suspected smuggling logistics activities linked to Nexus Freight Pvt Ltd operating near Andheri Warehouse District. Suspect Aarav Mehta and associate Rohan Verma spotted in connection with vehicle MH04AB1234 and phone +919876543210.',
                'url' => 'https://ncib.gov.in/alerts/nexus-logistics-2026'
            ],
            [
                'source' => 'Cyber Crime & Financial Intelligence Unit',
                'title' => 'Suspicious Shell Company Transactions Linked to Shadowline Holdings',
                'content' => 'Financial intelligence report highlights repeated high-value wire transfers to Bank Account ACC-7742 registered under Shadowline Holdings. Signatory Devika Rao identified during audit.',
                'url' => 'https://fiu.gov.in/reports/shadowline-fraud-2026'
            ]
        ];

        return array_filter($alerts, function($a) use ($keywordsClean) {
            if (!$keywordsClean) return true;
            return str_contains(strtolower($a['title'] . ' ' . $a['content']), $keywordsClean);
        });
    }
}
