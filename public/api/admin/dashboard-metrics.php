<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_admin();
try {
    $pdo=Database::connect();
    $totalUsers=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $activeUsers=(int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn();
    $disabledUsers=(int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active=0')->fetchColumn();
    $totalCases=(int)$pdo->query('SELECT COUNT(*) FROM cases')->fetchColumn();
    $activeCases=(int)$pdo->query("SELECT COUNT(*) FROM cases WHERE status NOT IN ('Resolved','Archived')")->fetchColumn();
    $documents=(int)$pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn();
    $docsProcessed=(int)$pdo->query("SELECT COUNT(*) FROM documents WHERE status='Processed'")->fetchColumn();
    $entities=(int)$pdo->query('SELECT COUNT(*) FROM entities')->fetchColumn();
    $relationships=(int)$pdo->query('SELECT COUNT(*) FROM relationships')->fetchColumn();
    $oneDayAgo = date('Y-m-d H:i:s', strtotime('-1 day'));
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM system_activity WHERE created_at >= ?');
    $stmt->execute([$oneDayAgo]);
    $systemActivity = (int)$stmt->fetchColumn();

    $growth=[];
    $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
    $stmt = $pdo->prepare("SELECT DATE(created_at) d,COUNT(*) c FROM users WHERE created_at>=? GROUP BY DATE(created_at)");
    $stmt->execute([$thirtyDaysAgo]);
    foreach($stmt as $r)$growth[$r['d']]=(int)$r['c'];
    $growthSeries=[];
    $running=$totalUsers-array_sum($growth);
    for($i=29;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i day"));$running+=$growth[$d]??0;$growthSeries[]=['date'=>date('d M',strtotime($d)),'count'=>$running];}

    $loginSeries=[];$successByDay=[];$failedByDay=[];
    $fourteenDaysAgo = date('Y-m-d H:i:s', strtotime('-14 days'));
    $stmt = $pdo->prepare("SELECT DATE(attempted_at) d, SUM(CASE WHEN success=1 THEN 1 ELSE 0 END) successful, SUM(CASE WHEN success=0 THEN 1 ELSE 0 END) failed FROM login_attempts WHERE attempted_at>=? GROUP BY DATE(attempted_at)");
    $stmt->execute([$fourteenDaysAgo]);
    foreach($stmt as $r){$successByDay[$r['d']]=(int)$r['successful'];$failedByDay[$r['d']]=(int)$r['failed'];}
    for($i=13;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i day"));$loginSeries[]=['date'=>date('d M',strtotime($d)),'successful'=>$successByDay[$d]??0,'failed'=>$failedByDay[$d]??0];}

    $s=$pdo->query("SELECT la.username,la.success,la.attempted_at,u.full_name
        FROM login_attempts la LEFT JOIN users u ON (u.username=la.username OR u.email=la.username)
        ORDER BY la.attempted_at DESC LIMIT 12");
    $loginRecent=$s->fetchAll();
    foreach($loginRecent as &$r){$r['status']=(int)$r['success']===1?'Successful login':'Failed login';$r['time_ago']=cg_time_ago($r['attempted_at']);}

    $systemRecent=$pdo->query("SELECT description,created_at FROM system_activity ORDER BY id DESC LIMIT 12")->fetchAll();
    foreach($systemRecent as &$r)$r['time_ago']=cg_time_ago($r['created_at']);

    cg_json_success('Admin dashboard metrics loaded.',[
        'cards'=>[
            ['label'=>'Total Users','value'=>$totalUsers,'icon'=>'fa-users'],
            ['label'=>'Active Users','value'=>$activeUsers,'icon'=>'fa-user-check'],
            ['label'=>'Disabled Users','value'=>$disabledUsers,'icon'=>'fa-user-slash'],
            ['label'=>'Total Cases','value'=>$totalCases,'icon'=>'fa-folder-open'],
            ['label'=>'Documents','value'=>$documents,'icon'=>'fa-file-lines'],
            ['label'=>'Documents Processed','value'=>$docsProcessed,'icon'=>'fa-file-circle-check'],
            ['label'=>'Entities','value'=>$entities,'icon'=>'fa-users-viewfinder'],
            ['label'=>'System Activity (24h)','value'=>$systemActivity,'icon'=>'fa-bolt'],
        ],
        'user_growth'=>$growthSeries,
        'login_activity'=>$loginSeries,
        'recent_logins'=>$loginRecent,
        'recent_system_activity'=>$systemRecent,
        'empty_states'=>[
            'growth'=>$totalUsers===0?'No users available.':'',
            'logins'=>empty($loginRecent)?'No login activity recorded.':'',
            'system'=>empty($systemRecent)?'No recent system activity yet.':'',
            'cases'=>$totalCases===0?'No cases available.':'',
        ],
    ]);
}catch(Throwable $e){
    error_log('[CYVANTA] admin dashboard metrics failed: '.$e->getMessage());
    cg_json_error('Unable to load admin dashboard data.',500);
}
