<?php
/**
 * CYVANTA - Analysis Service
 * Computes explainable, application-side graph analytics (degree centrality,
 * hubs, bridge candidates, repeated relationships). These are analytical
 * indicators, not accusations — see the disclaimers shown throughout the UI.
 */

require_once __DIR__ . '/../config/database.php';

class AnalysisService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function runForCase(int $caseId, ?int $userId = null): array
    {
        $entities = $this->pdo->prepare('SELECT id, name FROM entities WHERE case_id = ?');
        $entities->execute([$caseId]);
        $entities = $entities->fetchAll();

        $rels = $this->pdo->prepare('SELECT source_entity_id, target_entity_id FROM relationships WHERE case_id = ?');
        $rels->execute([$caseId]);
        $rels = $rels->fetchAll();

        $degree = [];
        foreach ($entities as $e) $degree[$e['id']] = 0;
        $adjacency = [];
        foreach ($rels as $r) {
            $degree[$r['source_entity_id']] = ($degree[$r['source_entity_id']] ?? 0) + 1;
            $degree[$r['target_entity_id']] = ($degree[$r['target_entity_id']] ?? 0) + 1;
            $adjacency[$r['source_entity_id']][] = $r['target_entity_id'];
            $adjacency[$r['target_entity_id']][] = $r['source_entity_id'];
        }

        $nameById = array_column($entities, 'name', 'id');
        $totalEntities = count($entities);
        $totalRels = count($rels);
        $avgDegree = $totalEntities ? round((2 * $totalRels) / $totalEntities, 2) : 0;

        $results = [];

        // Network Hub: entity connected to unusually many others
        arsort($degree);
        $hubThreshold = max(3, (int) ceil($avgDegree * 2));
        foreach ($degree as $entityId => $conn) {
            if ($conn >= $hubThreshold) {
                $results[] = [
                    'pattern_type' => 'Network Hub',
                    'entity_id' => $entityId,
                    'entity_name' => $nameById[$entityId] ?? "Entity #$entityId",
                    'confidence' => min(95, 50 + $conn * 3),
                    'reason' => "Entity is connected to $conn other entities, well above the network average of $avgDegree.",
                ];
            }
        }

        // Bridge Entity candidate: connects to entities that are not otherwise connected to each other
        foreach ($adjacency as $entityId => $neighbors) {
            $neighbors = array_values(array_unique($neighbors));
            if (count($neighbors) < 2) continue;
            $interconnected = 0;
            $pairs = 0;
            for ($i = 0; $i < count($neighbors); $i++) {
                for ($j = $i + 1; $j < count($neighbors); $j++) {
                    $pairs++;
                    $a = $neighbors[$i]; $b = $neighbors[$j];
                    if (in_array($b, $adjacency[$a] ?? [], true)) $interconnected++;
                }
            }
            if ($pairs > 0 && ($interconnected / $pairs) < 0.3 && count($neighbors) >= 3) {
                $results[] = [
                    'pattern_type' => 'Bridge Entity',
                    'entity_id' => $entityId,
                    'entity_name' => $nameById[$entityId] ?? "Entity #$entityId",
                    'confidence' => 70,
                    'reason' => 'Entity connects ' . count($neighbors) . ' otherwise loosely-connected entities, suggesting a structural bridge role.',
                ];
            }
        }

        // Repeated relationship: same pair connected via multiple relationship records
        $pairCounts = [];
        foreach ($rels as $r) {
            $key = min($r['source_entity_id'], $r['target_entity_id']) . '-' . max($r['source_entity_id'], $r['target_entity_id']);
            $pairCounts[$key] = ($pairCounts[$key] ?? 0) + 1;
        }
        foreach ($pairCounts as $key => $count) {
            if ($count >= 3) {
                [$a, $b] = explode('-', $key);
                $results[] = [
                    'pattern_type' => 'Repeated Relationship',
                    'entity_id' => $a,
                    'entity_name' => ($nameById[$a] ?? '?') . ' ↔ ' . ($nameById[$b] ?? '?'),
                    'confidence' => min(90, 40 + $count * 10),
                    'reason' => "$count separate relationship records connect these two entities, suggesting repeated contact.",
                ];
            }
        }

        // Persist as an analysis record
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_analyses (case_id, analysis_type, status, entities_found, relationships_found, started_at, completed_at, created_by)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)'
        );
        $stmt->execute([$caseId, 'pattern_detection', 'completed', $totalEntities, $totalRels, $userId]);
        $analysisId = (int) $this->pdo->lastInsertId();

        $insert = $this->pdo->prepare('INSERT INTO analysis_results (analysis_id, pattern_type, entity_id, confidence, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        foreach ($results as $r) {
            $insert->execute([$analysisId, $r['pattern_type'], is_numeric($r['entity_id']) ? $r['entity_id'] : null, $r['confidence'], $r['reason']]);
        }

        // Recalculate Risk Scores strictly for risk-eligible entities (Person Accused/Suspect/Involved only)
        // All non-eligible entities (Courts, Police Agencies, Locations, Vehicles, Weapons, etc.) get risk_score = NULL.
        $fullEntitiesStmt = $this->pdo->prepare('
            SELECT e.id, e.name, e.description, et.name AS type_name
            FROM entities e
            JOIN entity_types et ON et.id = e.entity_type_id
            WHERE e.case_id = ?
        ');
        $fullEntitiesStmt->execute([$caseId]);
        $fullEntities = $fullEntitiesStmt->fetchAll();

        $relsFullStmt = $this->pdo->prepare('
            SELECT r.source_entity_id, r.target_entity_id, rt.name AS rel_type
            FROM relationships r
            JOIN relationship_types rt ON rt.id = r.relationship_type_id
            WHERE r.case_id = ?
        ');
        $relsFullStmt->execute([$caseId]);
        $relsFull = $relsFullStmt->fetchAll();

        $relWeights = [
            'ACCUSED_IN' => 10, 'PRIME_SUSPECT_IN' => 10, 'CHARGESHEETED_IN' => 10,
            'SUSPECTED_IN' => 9, 'SUSPECT_OF' => 9,
            'INVOLVED_IN' => 8, 'MASTERMIND_OF' => 8, 'CO_CONSPIRATOR' => 8,
            'OPERATES' => 7, 'CONTROLS' => 7, 'WIRED_FUNDS' => 7, 'LOGISTICS_LEAD' => 7,
            'LINKED_TO_CASE' => 6, 'ASSOCIATED_WITH' => 6, 'ASSOCIATE_OF' => 6,
            'CONNECTED_TO' => 4, 'CALLS' => 4, 'TRANSFERRED_TO' => 4,
            'WITNESS_IN' => 2, 'PRESENT_AT' => 2,
            'HEARD_BY' => 0, 'APPEALED_TO' => 0, 'INVESTIGATED_BY' => 0,
            'LOCATED_IN' => 0, 'JURISDICTION_OF' => 0, 'PART_OF' => 0,
            'MENTIONED_IN' => 0, 'ROUTED_THROUGH' => 0
        ];

        // Load Entity Type IDs Map
        $allTypesStmt = $this->pdo->query('SELECT id, name FROM entity_types');
        $typeIdByName = [];
        while ($row = $allTypesStmt->fetch(PDO::FETCH_ASSOC)) {
            $typeIdByName[strtolower($row['name'])] = (int) $row['id'];
        }

        $updateTypeStmt = $this->pdo->prepare('UPDATE entities SET entity_type_id = ? WHERE id = ?');
        $updateRiskStmt = $this->pdo->prepare('UPDATE entities SET risk_score = ? WHERE id = ?');

        foreach ($fullEntities as $ent) {
            $eId = (int) $ent['id'];
            $typeName = $ent['type_name'];
            $name = $ent['name'];
            $desc = $ent['description'] ?? '';

            // 1. Auto-Reclassify entity if improperly categorized in database
            $correctType = cg_determine_entity_type($name, $desc, $typeName);
            if (strtolower($correctType) !== strtolower($typeName)) {
                $targetTypeId = $typeIdByName[strtolower($correctType)] ?? null;
                if ($targetTypeId) {
                    $updateTypeStmt->execute([$targetTypeId, $eId]);
                    $typeName = $correctType;
                }
            }

            // 2. Risk Eligibility Enforcement
            if (!cg_is_entity_risk_eligible($typeName, $name, $desc)) {
                $updateRiskStmt->execute([-1, $eId]);
                continue;
            }

            $rawScore = 0;
            $textLower = strtolower($name . ' ' . $desc);

            if (preg_match('/\b(accused|prime suspect|mastermind|co-conspirator)\b/i', $textLower)) {
                $rawScore += 35;
            } elseif (preg_match('/\b(suspect)\b/i', $textLower)) {
                $rawScore += 25;
            } elseif (preg_match('/\b(involved)\b/i', $textLower)) {
                $rawScore += 15;
            }

            foreach ($relsFull as $r) {
                if ($r['source_entity_id'] == $eId || $r['target_entity_id'] == $eId) {
                    $relType = strtoupper(trim($r['rel_type'] ?? ''));
                    $w = $relWeights[$relType] ?? 3;
                    $rawScore += $w;
                }
            }

            $finalRisk = min(98, max(15, (int) round($rawScore * 1.5)));
            $updateRiskStmt->execute([$finalRisk, $eId]);
        }

        return [
            'analysis_id' => $analysisId,
            'patterns' => $results,
            'network_density' => $totalEntities > 1 ? round((2 * $totalRels) / ($totalEntities * ($totalEntities - 1)), 3) : 0,
            'avg_degree' => $avgDegree,
        ];
    }
}
