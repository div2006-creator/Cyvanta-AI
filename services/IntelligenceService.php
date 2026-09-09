<?php
/**
 * CNI - AI-Powered Criminal Network Analysis System
 * Live Intelligence Ingestion Service & Source Adapter Architecture
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AnalysisService.php';
require_once __DIR__ . '/DocumentProcessingService.php';

abstract class IngestionSource
{
    abstract public function getSourceType(): string; // PUBLIC_RECORD | AUTHORIZED_API | SIMULATION
    abstract public function getSourceName(): string;
    abstract public function isAvailable(): bool;
    abstract public function fetchEvents(): array;
}

/**
 * Source Adapter: Public Record Source
 * Real public records are ONLY ingested when backend successfully verifies a legitimate HTTP 200 payload.
 */
class PublicRecordSource extends IngestionSource
{
    public function getSourceType(): string
    {
        return 'PUBLIC_RECORD';
    }

    public function getSourceName(): string
    {
        return 'Public Record Register';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function fetchEvents(): array
    {
        // PublicRecordSource produces events ONLY when a real URL is provided or retrieved.
        // Unverified demonstration events are strictly delegated to SimulationSource.
        return [];
    }
}

/**
 * Source Adapter: Simulation Source (Demo Mode)
 * Synthetic intelligence event generator using non-personal synthetic identifiers.
 */
class SimulationSource extends IngestionSource
{
    public function getSourceType(): string
    {
        return 'SIMULATION';
    }

    public function getSourceName(): string
    {
        return 'CNI Demonstration Generator';
    }

    public function isAvailable(): bool
    {
        return SIMULATION_ENABLED;
    }

    public function fetchEvents(): array
    {
        $fetchedAt = date('Y-m-d H:i:s');
        $uniq = substr(md5(uniqid()), 0, 6);

        $syntheticPool = [
            [
                'event_type' => 'FINANCIAL_NETWORK',
                'title' => 'Simulation: Financial Network Alert',
                'description' => 'Synthetic financial intelligence alert tracking rapid layering transactions between shell entity Company Alpha and Account 001.',
                'source_type' => 'SIMULATION',
                'source_name' => 'CNI Demonstration Generator',
                'source_url' => null,
                'source_id' => 'SIM-FN-' . $uniq,
                'source_fetched_at' => $fetchedAt,
                'verification_method' => 'SIMULATION',
                'fetched_http_status' => null,
                'is_verified' => false,
                'severity' => 'High',
                'confidence' => 91,
                'location' => 'Mumbai, Maharashtra',
                'entities' => [
                    ['name' => 'Person A', 'type' => 'Person', 'risk' => 88],
                    ['name' => 'Company Alpha', 'type' => 'Organization', 'risk' => 82],
                    ['name' => 'Account 001', 'type' => 'Financial Account', 'risk' => 79],
                    ['name' => 'Person B', 'type' => 'Person', 'risk' => 70]
                ],
                'relationships' => [
                    ['source' => 'Person A', 'target' => 'Company Alpha', 'type' => 'CONTROLS'],
                    ['source' => 'Company Alpha', 'target' => 'Account 001', 'type' => 'WIRED_FUNDS'],
                    ['source' => 'Person B', 'target' => 'Person A', 'type' => 'ASSOCIATE_OF']
                ],
                'raw_data' => ['demo_session' => rand(1000, 9999), 'synthetic_id' => 'SIM-FN-' . time()]
            ],
            [
                'event_type' => 'ARMS_NETWORK',
                'title' => 'Simulation: Interstate Arms Network',
                'description' => 'Synthetic logistics telemetry tracking unverified armaments movement across inter-state border checkposts.',
                'source_type' => 'SIMULATION',
                'source_name' => 'CNI Demonstration Generator',
                'source_url' => null,
                'source_id' => 'SIM-ARMS-' . $uniq,
                'source_fetched_at' => $fetchedAt,
                'verification_method' => 'SIMULATION',
                'fetched_http_status' => null,
                'is_verified' => false,
                'severity' => 'Critical',
                'confidence' => 94,
                'location' => 'Uttar Pradesh Checkpoint',
                'entities' => [
                    ['name' => 'Person C', 'type' => 'Person', 'risk' => 95],
                    ['name' => 'Company Beta', 'type' => 'Organization', 'risk' => 86],
                    ['name' => 'Checkpoint 001', 'type' => 'Location', 'risk' => 60]
                ],
                'relationships' => [
                    ['source' => 'Person C', 'target' => 'Company Beta', 'type' => 'LOGISTICS_LEAD'],
                    ['source' => 'Company Beta', 'target' => 'Checkpoint 001', 'type' => 'ROUTED_THROUGH']
                ],
                'raw_data' => ['demo_session' => rand(1000, 9999), 'synthetic_id' => 'SIM-ARMS-' . time()]
            ],
            [
                'event_type' => 'CYBER_NETWORK',
                'title' => 'Simulation: Cyber Extortion Cluster',
                'description' => 'Synthetic cyber threat intelligence monitoring anomalous command-and-control communication from host node Alpha-1.',
                'source_type' => 'SIMULATION',
                'source_name' => 'CNI Demonstration Generator',
                'source_url' => null,
                'source_id' => 'SIM-CYBER-' . $uniq,
                'source_fetched_at' => $fetchedAt,
                'verification_method' => 'SIMULATION',
                'fetched_http_status' => null,
                'is_verified' => false,
                'severity' => 'High',
                'confidence' => 89,
                'location' => 'NCR Cyber Sector',
                'entities' => [
                    ['name' => 'Host Node Alpha-1', 'type' => 'Digital Asset', 'risk' => 90],
                    ['name' => 'Person D', 'type' => 'Person', 'risk' => 84],
                    ['name' => 'Account 002', 'type' => 'Financial Account', 'risk' => 77]
                ],
                'relationships' => [
                    ['source' => 'Person D', 'target' => 'Host Node Alpha-1', 'type' => 'OPERATES'],
                    ['source' => 'Host Node Alpha-1', 'target' => 'Account 002', 'type' => 'RECEIVES_RANSOM']
                ],
                'raw_data' => ['demo_session' => rand(1000, 9999), 'synthetic_id' => 'SIM-CYBER-' . time()]
            ]
        ];

        return [$syntheticPool[array_rand($syntheticPool)]];
    }
}

/**
 * Source Adapter: Authorized Government Source (Production Adapter)
 * Default: Disabled. Active ONLY when AUTHORIZED_SOURCE_ENABLED=true and a valid URL/KEY is provided.
 */
class AuthorizedGovernmentSource extends IngestionSource
{
    public function getSourceType(): string
    {
        return 'AUTHORIZED_API';
    }

    public function getSourceName(): string
    {
        return 'Authorized Government API Gateway';
    }

    public function isAvailable(): bool
    {
        return AUTHORIZED_SOURCE_ENABLED && !empty(AUTHORIZED_SOURCE_API_URL);
    }

    public function fetchEvents(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $apiUrl = AUTHORIZED_SOURCE_API_URL;
        $apiKey = AUTHORIZED_SOURCE_API_KEY;
        $fetchedAt = date('Y-m-d H:i:s');

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpStatus !== 200 || !$response) {
            return [];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['events'])) {
            return [];
        }

        $events = [];
        foreach ($decoded['events'] as $evt) {
            $events[] = [
                'event_type' => $evt['event_type'] ?? 'ORGANIZED_CRIME',
                'title' => 'LIVE INTELLIGENCE: ' . ($evt['title'] ?? 'Authorized Department Alert'),
                'description' => $evt['description'] ?? 'Authorized dispatch received.',
                'source_type' => 'AUTHORIZED_API',
                'source_name' => 'Authorized Government Gateway',
                'source_url' => $apiUrl,
                'source_id' => $evt['source_id'] ?? ('AUTH-' . uniqid()),
                'source_fetched_at' => $fetchedAt,
                'verification_method' => 'AUTHORIZED_API_RESPONSE',
                'fetched_http_status' => 200,
                'is_verified' => true,
                'severity' => $evt['severity'] ?? 'High',
                'confidence' => (int) ($evt['confidence'] ?? 95),
                'location' => $evt['location'] ?? 'Department Zone',
                'entities' => $evt['entities'] ?? [],
                'relationships' => $evt['relationships'] ?? [],
                'raw_data' => $evt
            ];
        }

        return $events;
    }
}

/**
 * Central Intelligence Ingestion Service
 */
class IntelligenceService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get active source adapters
     */
    public function getActiveSources(): array
    {
        return [
            new PublicRecordSource(),
            new SimulationSource(),
            new AuthorizedGovernmentSource()
        ];
    }

    /**
     * Ingest a single validated intelligence event with strict backend verification
     */
    public function ingestEvent(array $data, ?int $userId = null): array
    {
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $eventType = strtoupper(trim($data['event_type'] ?? 'ORGANIZED_CRIME'));
        $sourceType = strtoupper(trim($data['source_type'] ?? 'SIMULATION'));
        $sourceName = trim($data['source_name'] ?? 'CNI Intelligence System');
        $sourceUrl = !empty($data['source_url']) ? trim($data['source_url']) : null;
        $sourceId = !empty($data['source_id']) ? trim($data['source_id']) : ('SRC-' . strtoupper(substr(md5($title), 0, 8)));
        $sourceFetchedAt = !empty($data['source_fetched_at']) ? trim($data['source_fetched_at']) : date('Y-m-d H:i:s');
        $severity = ucfirst(strtolower(trim($data['severity'] ?? 'Medium')));
        $confidence = max(1, min(100, (int) ($data['confidence'] ?? 80)));
        $location = trim($data['location'] ?? 'Unspecified Location');

        if ($title === '' || $description === '') {
            throw new InvalidArgumentException('Event title and description are required.');
        }

        $verificationMethod = 'SIMULATION';
        $fetchedHttpStatus = null;
        $isVerified = 0;

        // STRICT SOURCE PROVENANCE VERIFICATION AUDIT RULES
        if ($sourceType === 'PUBLIC_RECORD') {
            if (empty($sourceUrl) || filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
                // No valid URL provided -> Force SIMULATION
                $sourceType = 'SIMULATION';
                $sourceName = 'CNI Demonstration Generator';
                $sourceUrl = null;
                $isVerified = 0;
                $verificationMethod = 'SIMULATION';
                $fetchedHttpStatus = null;
            } else {
                // Real HTTP Verification Check
                $ch = curl_init($sourceUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 4,
                    CURLOPT_NOBODY => false,
                    CURLOPT_USERAGENT => 'CNI-Source-Verification/1.0'
                ]);
                $resp = curl_exec($ch);
                $fetchedHttpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($fetchedHttpStatus === 200 && !empty($resp)) {
                    $isVerified = 1;
                    $verificationMethod = 'SOURCE_FETCH';
                } else {
                    // Fetch failed or non-200 -> Force SIMULATION
                    $sourceType = 'SIMULATION';
                    $sourceName = 'CNI Demonstration Generator';
                    $sourceUrl = null;
                    $isVerified = 0;
                    $verificationMethod = 'SIMULATION';
                }
            }
        } elseif ($sourceType === 'AUTHORIZED_API') {
            $isAuthorizedAvailable = AUTHORIZED_SOURCE_ENABLED && !empty(AUTHORIZED_SOURCE_API_URL);
            if (!$isAuthorizedAvailable) {
                $sourceType = 'SIMULATION';
                $sourceName = 'CNI Demonstration Generator';
                $sourceUrl = null;
                $isVerified = 0;
                $verificationMethod = 'SIMULATION';
                $fetchedHttpStatus = null;
            } else {
                $isVerified = 1;
                $verificationMethod = 'AUTHORIZED_API_RESPONSE';
                $fetchedHttpStatus = $data['fetched_http_status'] ?? 200;
            }
        } else {
            // SIMULATION
            $sourceType = 'SIMULATION';
            $sourceName = 'CNI Demonstration Generator';
            $sourceUrl = null;
            $isVerified = 0;
            $verificationMethod = 'SIMULATION';
            $fetchedHttpStatus = null;
        }

        // Duplicate Check (same title within last 1 hour)
        $chk = $this->pdo->prepare("
            SELECT id FROM intelligence_events 
            WHERE title = ? AND event_timestamp > datetime('now', '-1 hour')
        ");
        $chk->execute([$title]);
        if ($chk->fetch()) {
            return [
                'success' => false,
                'duplicate' => true,
                'message' => 'Duplicate event detected within recent window. Ignored.'
            ];
        }

        $entities = is_array($data['entities'] ?? null) ? $data['entities'] : [];
        $relationships = is_array($data['relationships'] ?? null) ? $data['relationships'] : [];
        $rawData = is_array($data['raw_data'] ?? null) ? $data['raw_data'] : ['ingested_at' => date('Y-m-d H:i:s')];

        // Insert into intelligence_events
        $stmt = $this->pdo->prepare("
            INSERT INTO intelligence_events (
                event_type, title, description, source_type, source_name, source_url, source_id, source_fetched_at,
                verification_method, fetched_http_status, is_verified,
                severity, confidence, location, entities, relationships, raw_data,
                processing_status, event_timestamp, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'processed', NOW(), NOW(), NOW())
        ");
        $stmt->execute([
            $eventType,
            $title,
            $description,
            $sourceType,
            $sourceName,
            $sourceUrl,
            $sourceId,
            $sourceFetchedAt,
            $verificationMethod,
            $fetchedHttpStatus,
            $isVerified,
            $severity,
            $confidence,
            $location,
            json_encode($entities),
            json_encode($relationships),
            json_encode($rawData)
        ]);

        $eventId = (int) $this->pdo->lastInsertId();

        // Integrate with Criminal Network Graph (Persist Entities & Relationships)
        $this->integrateWithNetworkGraph($eventId, $title, $description, $entities, $relationships, $userId);

        // Record System Activity for Real-Time WebSocket Push
        $this->pdo->prepare("
            INSERT INTO system_activity (description, created_at)
            VALUES (?, NOW())
        ")->execute([
            "LIVE INTELLIGENCE: {$title} [Source: {$sourceType}]"
        ]);

        // Create System Notification
        cg_create_notification(
            null,
            'intelligence',
            "🚨 Live Intelligence Alert ({$severity})",
            "New event: {$title} ({$sourceType})",
            "live-api-feed.php?event_id={$eventId}"
        );

        // Audit Log
        cg_log_audit(
            $userId ?: 1,
            'INTEL_EVENT_INGESTED',
            'intelligence_events',
            (string) $eventId,
            'success',
            "Ingested {$sourceType} intelligence event: {$title}"
        );

        return [
            'success' => true,
            'event_id' => $eventId,
            'title' => $title,
            'source_type' => $sourceType,
            'source_name' => $sourceName,
            'source_url' => $sourceUrl,
            'source_id' => $sourceId,
            'source_fetched_at' => $sourceFetchedAt,
            'verification_method' => $verificationMethod,
            'fetched_http_status' => $fetchedHttpStatus,
            'is_verified' => (bool) $isVerified,
            'severity' => $severity,
            'entities_count' => count($entities),
            'relationships_count' => count($relationships)
        ];
    }

    /**
     * Map extracted entities and relationships into persistent entities/relationships graph tables
     */
    private function integrateWithNetworkGraph(
        int $eventId,
        string $title,
        string $description,
        array $entities,
        array $relationships,
        ?int $userId
    ): void {
        // Find or create dedicated Live Intelligence Case
        $caseStmt = $this->pdo->prepare("SELECT id FROM cases WHERE case_number = 'CASE-INTEL-LIVE'");
        $caseStmt->execute();
        $caseId = $caseStmt->fetchColumn();

        if (!$caseId) {
            $adminStmt = $this->pdo->query("SELECT id FROM users WHERE role IN ('super_admin', 'administrator') ORDER BY id ASC LIMIT 1");
            $adminId = $adminStmt->fetchColumn() ?: 1;

            $insCase = $this->pdo->prepare("
                INSERT INTO cases (case_number, title, description, category, location, priority, status, tags, created_by, lead_investigator_id, created_at, updated_at)
                VALUES ('CASE-INTEL-LIVE', 'Live Intelligence Master Network', 'Central dynamic intelligence container for real-time network graph updates.', 'Intelligence', 'National', 'Critical', 'Under Investigation', 'live-intel,master-graph', ?, ?, NOW(), NOW())
            ");
            $insCase->execute([$adminId, $adminId]);
            $caseId = (int) $this->pdo->lastInsertId();
        }

        // Get default entity types mapping
        $typeStmt = $this->pdo->query("SELECT id, name FROM entity_types");
        $typeMap = [];
        while ($row = $typeStmt->fetch(PDO::FETCH_ASSOC)) {
            $typeMap[strtolower($row['name'])] = (int) $row['id'];
        }
        $defaultTypeId = current($typeMap) ?: 1;

        $entityIdMap = [];

        foreach ($entities as $ent) {
            $eName = trim($ent['name'] ?? '');
            if ($eName === '') continue;

            $eType = strtolower(trim($ent['type'] ?? 'person'));
            $typeId = $typeMap[$eType] ?? $defaultTypeId;
            $isEligible = cg_is_entity_risk_eligible($ent['type'] ?? 'Person', $eName, '');
            $riskToSave = $isEligible ? max(10, min(99, (int) ($ent['risk'] ?? 75))) : -1;

            // Find existing entity or insert
            $chkE = $this->pdo->prepare("SELECT id FROM entities WHERE case_id = ? AND name = ?");
            $chkE->execute([$caseId, $eName]);
            $existingId = $chkE->fetchColumn();

            if ($existingId) {
                $entityIdMap[$eName] = (int) $existingId;
                if ($isEligible) {
                    $this->pdo->prepare("UPDATE entities SET risk_score = MAX(COALESCE(risk_score, 0), ?), updated_at = NOW() WHERE id = ?")->execute([$riskToSave, $existingId]);
                } else {
                    $this->pdo->prepare("UPDATE entities SET risk_score = -1, updated_at = NOW() WHERE id = ?")->execute([$existingId]);
                }
            } else {
                $insE = $this->pdo->prepare("
                    INSERT INTO entities (case_id, entity_type_id, name, description, risk_score, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insE->execute([$caseId, $typeId, $eName, "Ingested via Intelligence Event #{$eventId}", $riskToSave]);
                $entityIdMap[$eName] = (int) $this->pdo->lastInsertId();
            }
        }

        // Map Relationship Types
        $relTypeStmt = $this->pdo->query("SELECT id, name FROM relationship_types");
        $relTypeMap = [];
        while ($row = $relTypeStmt->fetch(PDO::FETCH_ASSOC)) {
            $relTypeMap[strtolower($row['name'])] = (int) $row['id'];
        }
        $defaultRelTypeId = current($relTypeMap) ?: 1;

        foreach ($relationships as $rel) {
            $srcName = trim($rel['source'] ?? '');
            $tgtName = trim($rel['target'] ?? '');
            if (!$srcName || !$tgtName || !isset($entityIdMap[$srcName], $entityIdMap[$tgtName])) continue;

            $srcId = $entityIdMap[$srcName];
            $tgtId = $entityIdMap[$tgtName];
            if ($srcId === $tgtId) continue;

            $relTypeName = strtolower(trim($rel['type'] ?? 'associated_with'));
            $relTypeId = $relTypeMap[$relTypeName] ?? $defaultRelTypeId;

            $chkR = $this->pdo->prepare("
                SELECT id FROM relationships 
                WHERE case_id = ? AND source_entity_id = ? AND target_entity_id = ? AND relationship_type_id = ?
            ");
            $chkR->execute([$caseId, $srcId, $tgtId, $relTypeId]);
            if (!$chkR->fetch()) {
                $insR = $this->pdo->prepare("
                    INSERT INTO relationships (case_id, source_entity_id, target_entity_id, relationship_type_id, strength, created_at)
                    VALUES (?, ?, ?, ?, 3, NOW())
                ");
                $insR->execute([$caseId, $srcId, $tgtId, $relTypeId]);
            }
        }

        // Re-run Graph Analytics to update hub & bridge indicators
        try {
            $analyzer = new AnalysisService($this->pdo);
            $analyzer->runForCase((int) $caseId, $userId);
        } catch (Throwable $e) {
            error_log('[CNI] Analysis recalculation notice: ' . $e->getMessage());
        }
    }

    /**
     * Poll all available sources and ingest new events
     */
    public function pollSources(?int $userId = null): array
    {
        $ingested = [];
        foreach ($this->getActiveSources() as $source) {
            if (!$source->isAvailable()) continue;
            try {
                $events = $source->fetchEvents();
                foreach ($events as $evt) {
                    $res = $this->ingestEvent($evt, $userId);
                    if ($res['success']) {
                        $ingested[] = $res;
                    }
                }
            } catch (Throwable $e) {
                error_log("[CNI] Ingestion error on source {$source->getSourceName()}: " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'ingested_count' => count($ingested),
            'events' => $ingested
        ];
    }

    /**
     * Get paginated and filtered intelligence events
     */
    public function getEvents(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['severity'])) {
            $where[] = "severity = ?";
            $params[] = ucfirst(strtolower($filters['severity']));
        }
        if (!empty($filters['event_type'])) {
            $where[] = "event_type = ?";
            $params[] = strtoupper($filters['event_type']);
        }
        if (!empty($filters['source_type'])) {
            $where[] = "source_type = ?";
            $params[] = strtoupper($filters['source_type']);
        }
        if (!empty($filters['location'])) {
            $where[] = "location LIKE ?";
            $params[] = '%' . $filters['location'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(event_timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(event_timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['verified_only'])) {
            $where[] = "is_verified = 1";
        }

        $whereClause = implode(' AND ', $where);

        // Count total matching
        $cStmt = $this->pdo->prepare("SELECT COUNT(*) FROM intelligence_events WHERE {$whereClause}");
        $cStmt->execute($params);
        $total = (int) $cStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $limit);

        $stmt = $this->pdo->prepare("
            SELECT * FROM intelligence_events 
            WHERE {$whereClause} 
            ORDER BY event_timestamp DESC, id DESC 
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['entities'] = json_decode($r['entities'] ?? '[]', true);
            $r['relationships'] = json_decode($r['relationships'] ?? '[]', true);
            $r['raw_data'] = json_decode($r['raw_data'] ?? '{}', true);
            $r['is_verified'] = (bool) ($r['is_verified'] ?? 0);
            $r['verification_method'] = $r['verification_method'] ?? 'SIMULATION';
            $r['fetched_http_status'] = $r['fetched_http_status'] !== null ? (int) $r['fetched_http_status'] : null;
        }

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / max(1, $limit)),
            'events' => $rows
        ];
    }

    /**
     * Get single event by ID
     */
    public function getEventById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intelligence_events WHERE id = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;

        $r['entities'] = json_decode($r['entities'] ?? '[]', true);
        $r['relationships'] = json_decode($r['relationships'] ?? '[]', true);
        $r['raw_data'] = json_decode($r['raw_data'] ?? '{}', true);
        $r['is_verified'] = (bool) ($r['is_verified'] ?? 0);
        $r['verification_method'] = $r['verification_method'] ?? 'SIMULATION';
        $r['fetched_http_status'] = $r['fetched_http_status'] !== null ? (int) $r['fetched_http_status'] : null;

        // Fetch master case graph node if exists
        $caseStmt = $this->pdo->prepare("SELECT id FROM cases WHERE case_number = 'CASE-INTEL-LIVE'");
        $caseStmt->execute();
        $r['master_case_id'] = $caseStmt->fetchColumn() ?: null;

        return $r;
    }

    /**
     * Get system-wide dashboard metrics
     */
    public function getDashboardStatus(): array
    {
        $totalEvents = (int) $this->pdo->query("SELECT COUNT(*) FROM intelligence_events")->fetchColumn();
        $processedEvents = (int) $this->pdo->query("SELECT COUNT(*) FROM intelligence_events WHERE processing_status = 'processed'")->fetchColumn();
        $lastTimestamp = $this->pdo->query("SELECT MAX(event_timestamp) FROM intelligence_events")->fetchColumn();

        $activeNetworks = (int) $this->pdo->query("SELECT COUNT(DISTINCT event_type) FROM intelligence_events")->fetchColumn();
        $totalEntities = (int) $this->pdo->query("SELECT COUNT(*) FROM entities")->fetchColumn();
        $totalRels = (int) $this->pdo->query("SELECT COUNT(*) FROM relationships")->fetchColumn();
        $highRiskAlerts = (int) $this->pdo->query("SELECT COUNT(*) FROM intelligence_events WHERE severity IN ('High', 'Critical')")->fetchColumn();

        // Sources status breakdown
        $lastPrFetch = $this->pdo->query("SELECT MAX(source_fetched_at) FROM intelligence_events WHERE source_type = 'PUBLIC_RECORD' AND is_verified = 1")->fetchColumn();
        $lastAuthFetch = $this->pdo->query("SELECT MAX(source_fetched_at) FROM intelligence_events WHERE source_type = 'AUTHORIZED_API' AND is_verified = 1")->fetchColumn();
        $lastSimFetch = $this->pdo->query("SELECT MAX(source_fetched_at) FROM intelligence_events WHERE source_type = 'SIMULATION'")->fetchColumn();

        $sourcesStatus = [
            [
                'type' => 'PUBLIC_RECORD',
                'name' => 'Public Record Register',
                'available' => true,
                'last_fetch' => $lastPrFetch ?: 'Never',
                'status' => 'Active — Real HTTP 200 Fetch & Valid URL Required'
            ],
            [
                'type' => 'AUTHORIZED_API',
                'name' => 'Authorized Government Gateway',
                'available' => AUTHORIZED_SOURCE_ENABLED && !empty(AUTHORIZED_SOURCE_API_URL),
                'endpoint' => !empty(AUTHORIZED_SOURCE_API_URL) ? preg_replace('/(?<=:\/\/)[^@]+@/', '***@', AUTHORIZED_SOURCE_API_URL) : 'Not Configured',
                'last_fetch' => $lastAuthFetch ?: 'Never',
                'status' => AUTHORIZED_SOURCE_ENABLED ? 'Connected & Active' : 'Not Configured (Requires Authorized Gateway)'
            ],
            [
                'type' => 'SIMULATION',
                'name' => 'CNI Demonstration Generator',
                'available' => SIMULATION_ENABLED,
                'last_fetch' => $lastSimFetch ?: 'Never',
                'status' => SIMULATION_ENABLED ? 'Enabled — Synthetic Identifiers Only' : 'Disabled'
            ]
        ];

        $simState = $this->isSimulationActive();

        return [
            'ingestion_status' => 'ONLINE',
            'events_received' => $totalEvents,
            'events_processed' => $processedEvents,
            'last_event_timestamp' => $lastTimestamp ?: 'No events yet',
            'active_networks' => $activeNetworks,
            'entities_identified' => $totalEntities,
            'relationships_discovered' => $totalRels,
            'high_risk_alerts' => $highRiskAlerts,
            'simulation_active' => $simState,
            'sources' => $sourcesStatus
        ];
    }

    /**
     * Simulation State Controllers
     */
    public function isSimulationActive(): bool
    {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'cni_sim_active'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val === false ? true : filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public function setSimulationState(bool $active): void
    {
        $val = $active ? 'true' : 'false';
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $this->pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at)
                VALUES ('cni_sim_active', ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at)
                VALUES ('cni_sim_active', ?, NOW())
                ON CONFLICT(setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()
            ");
        }
        $stmt->execute([$val]);
    }
}
