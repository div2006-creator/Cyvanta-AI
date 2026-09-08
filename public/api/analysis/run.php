<?php
require_once dirname(__DIR__,3).'/includes/bootstrap.php';
require_once dirname(__DIR__,3).'/services/AnalysisService.php';
cg_require_role(['super_admin','administrator','investigator','analyst']);
if($_SERVER['REQUEST_METHOD']!=='POST')cg_json_error('Method not allowed.',405);
$input=cg_input();$caseId=(int)($input['case_id']??0);if(!$caseId)cg_json_error('Case id is required.',422);
$pdo=Database::connect();$user=cg_current_user();
if(!cg_user_can_access_case($caseId,$user))cg_json_error('You are not authorized to analyze this case.',403);
try{
 $result=(new AnalysisService($pdo))->runForCase($caseId,$user['id']);
 $s=$pdo->prepare('SELECT case_number FROM cases WHERE id=?');$s->execute([$caseId]);$caseNumber=(string)$s->fetchColumn();
 $pdo->prepare('INSERT INTO case_events (case_id,event_type,description,created_by,created_at) VALUES (?,?,?,?,NOW())')->execute([$caseId,'PATTERN_ANALYSIS_COMPLETED','Pattern analysis completed successfully.', $user['id']]);
 cg_log_audit($user['id'],'AI_ANALYSIS_PERFORMED','analysis',$caseNumber,'success',count($result['patterns']).' analytical indicators generated.');
 cg_create_notification(null,'analysis','Pattern Analysis Completed',count($result['patterns'])." analytical indicators found for $caseNumber.","case-details.php?id=$caseId");
 cg_json_success('Pattern analysis completed successfully.',$result);
}catch(Throwable $e){
 error_log('[CYVANTA] pattern analysis failed: '.$e->getMessage());
 cg_json_error('Unable to complete pattern analysis.',500);
}
