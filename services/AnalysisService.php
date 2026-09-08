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
            $neighbors = array_unique($neighbors);
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

        // Also update risk_score on top hub entities as an explainable indicator
        foreach (array_slice($degree, 0, 5, true) as $entityId => $conn) {
            $riskScore = min(98, 20 + $conn * 8);
            $this->pdo->prepare('UPDATE entities SET risk_score = ? WHERE id = ?')->execute([$riskScore, $entityId]);
        }

        return [
            'analysis_id' => $analysisId,
            'patterns' => $results,
            'network_density' => $totalEntities > 1 ? round((2 * $totalRels) / ($totalEntities * ($totalEntities - 1)), 3) : 0,
            'avg_degree' => $avgDegree,
        ];
    }
}
