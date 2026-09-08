<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

$pdo = Database::connect();
$user = cg_current_user();
$isAdmin = in_array($user['role'], ['super_admin', 'administrator'], true);

/*
 * Investigator dashboard scope: administrators can see the whole system;
 * other roles see cases they created, lead, or are assigned to.
 */
$scopeSql = '';
$scopeParams = [];
if (!$isAdmin) {
    $scopeSql = ' WHERE (c.created_by = ? OR c.lead_investigator_id = ? OR EXISTS (
        SELECT 1 FROM case_assignments ca WHERE ca.case_id = c.id AND ca.user_id = ?
    ))';
    $scopeParams = [$user['id'], $user['id'], $user['id']];
}
try {
    $caseCountStmt = $pdo->prepare("SELECT COUNT(*) FROM cases c" . $scopeSql);
    $caseCountStmt->execute($scopeParams);
    $totalCases = (int)$caseCountStmt->fetchColumn();

    $activeStmt = $pdo->prepare("SELECT COUNT(*) FROM cases c" . ($scopeSql ? $scopeSql . " AND " : " WHERE ") . "c.status NOT IN ('Resolved','Archived')");
    $activeStmt->execute($scopeParams);
    $activeCases = (int)$activeStmt->fetchColumn();

    $highStmt = $pdo->prepare("SELECT COUNT(*) FROM cases c" . ($scopeSql ? $scopeSql . " AND " : " WHERE ") . "c.priority IN ('High','Critical')");
    $highStmt->execute($scopeParams);
    $highRisk = (int)$highStmt->fetchColumn();

    $caseIdsStmt = $pdo->prepare("SELECT c.id FROM cases c" . $scopeSql);
    $caseIdsStmt->execute($scopeParams);
    $caseIds = array_map('intval', $caseIdsStmt->fetchAll(PDO::FETCH_COLUMN));

    $entities = $relationships = $documents = $evidence = $analyses = $pendingTasks = 0;
    $caseStatus = [];
    $statusSql = "SELECT c.status, COUNT(*) c FROM cases c" . $scopeSql . " GROUP BY c.status";
    $statusStmt = $pdo->prepare($statusSql);
    $statusStmt->execute($scopeParams);
    foreach ($statusStmt as $row) {
        $caseStatus[$row['status']] = (int)$row['c'];
    }

    if ($caseIds) {
        $in = implode(',', array_fill(0, count($caseIds), '?'));
        $params = $caseIds;
        foreach ([
            'entities' => "SELECT COUNT(*) FROM entities WHERE case_id IN ($in)",
            'relationships' => "SELECT COUNT(*) FROM relationships WHERE case_id IN ($in)",
            'documents' => "SELECT COUNT(*) FROM documents WHERE case_id IN ($in)",
            'evidence' => "SELECT COUNT(*) FROM evidence WHERE case_id IN ($in)",
            'analyses' => "SELECT COUNT(*) FROM ai_analyses WHERE case_id IN ($in)",
            'pending' => "SELECT COUNT(*) FROM documents WHERE case_id IN ($in) AND status IN ('Uploaded','Queued')",
        ] as $key => $sql) {
            $s = $pdo->prepare($sql); $s->execute($params);
            ${$key === 'pending' ? 'pendingTasks' : $key} = (int)$s->fetchColumn();
        }
    }

    $activity = [];
    $fourteenDaysAgo = date('Y-m-d H:i:s', strtotime('-14 days'));
    $s = $pdo->prepare("SELECT DATE(al.created_at) d, COUNT(*) c
        FROM audit_logs al
        LEFT JOIN cases c ON c.case_number = al.target
        WHERE al.created_at >= ?
        AND (al.module IN ('cases','documents','evidence','notes','analysis') OR al.action IN ('CASE_CREATED','CASE_UPDATED','DOCUMENT_UPLOADED','DOCUMENT_PROCESSED','EVIDENCE_UPLOADED','NOTE_ADDED','AI_ANALYSIS_PERFORMED'))
        " . (!$isAdmin ? " AND (c.created_by = ? OR c.lead_investigator_id = ? OR EXISTS (SELECT 1 FROM case_assignments ca WHERE ca.case_id=c.id AND ca.user_id=?))" : "") . "
        GROUP BY DATE(al.created_at) ORDER BY d");
    $execParams = array_merge([$fourteenDaysAgo], $isAdmin ? [] : [$user['id'],$user['id'],$user['id']]);
    $s->execute($execParams);
    foreach ($s as $row) $activity[$row['d']] = (int)$row['c'];
    $activitySeries = [];
    for ($i=13; $i>=0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $activitySeries[] = ['date'=>date('d M',strtotime($d)), 'count'=>$activity[$d] ?? 0];
    }

    $topEntities = [];
    if ($caseIds) {
        $in = implode(',', array_fill(0,count($caseIds),'?'));
        $s=$pdo->prepare("SELECT e.id,e.name,e.risk_score,COUNT(r.id) connections
            FROM entities e
            LEFT JOIN relationships r ON r.source_entity_id=e.id OR r.target_entity_id=e.id
            WHERE e.case_id IN ($in)
            GROUP BY e.id ORDER BY connections DESC,e.name ASC LIMIT 5");
        $s->execute($caseIds); $topEntities=$s->fetchAll();
    }

    $recent = [];
    if ($caseIds) {
        $in = implode(',', array_fill(0,count($caseIds),'?'));
        $s=$pdo->prepare("SELECT ce.description,ce.event_type,ce.created_at,c.case_number,c.title
            FROM case_events ce JOIN cases c ON c.id=ce.case_id
            WHERE ce.case_id IN ($in) ORDER BY ce.created_at DESC LIMIT 12");
        $s->execute($caseIds);
        $recent=$s->fetchAll();
    }
    foreach ($recent as &$r) $r['time_ago']=cg_time_ago($r['created_at']);

    $networkTotal = $entities + 0;
    cg_json_success('Dashboard metrics loaded.', [
        'cards'=>[
            ['label'=>'Total Cases','value'=>$totalCases,'icon'=>'fa-folder-open'],
            ['label'=>'Active Cases','value'=>$activeCases,'icon'=>'fa-folder'],
            ['label'=>'High/Critical Cases','value'=>$highRisk,'icon'=>'fa-triangle-exclamation'],
            ['label'=>'Documents','value'=>$documents,'icon'=>'fa-file-lines'],
            ['label'=>'Entities','value'=>$entities,'icon'=>'fa-users-viewfinder'],
            ['label'=>'Relationships','value'=>$relationships,'icon'=>'fa-circle-nodes'],
            ['label'=>'Evidence','value'=>$evidence,'icon'=>'fa-fingerprint'],
            ['label'=>'Analyses','value'=>$analyses,'icon'=>'fa-brain'],
        ],
        'case_status'=>$caseStatus,
        'activity_series'=>$activitySeries,
        'network'=>[
            'total_entities'=>$entities,
            'total_relationships'=>$relationships,
            'top_entities'=>$topEntities,
            'available'=>(bool)($entities || $relationships),
        ],
        'investigation_activity'=>$recent,
        'recent_activity'=>$recent,
        'empty_states'=>[
            'investigation_activity'=>empty($recent)?'No investigation activity yet.':'',
            'case_statistics'=>$totalCases===0?'Not enough case data available.':'',
            'network'=>($entities===0 && $relationships===0)?'No network intelligence available yet.':'',
            'recent_activity'=>empty($recent)?'No recent activity yet.':'',
        ],
    ]);
} catch (Throwable $e) {
    error_log('[CYVANTA] dashboard metrics failed: '.$e->getMessage());
    cg_json_error('Unable to load dashboard data.',500);
}
