<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/services/DocumentProcessingService.php';
cg_require_role(['super_admin','administrator','investigator','analyst']);
if($_SERVER['REQUEST_METHOD']!=='POST') cg_json_error('Method not allowed.',405);
$input=cg_input(); $documentId=(int)($input['document_id']??0);
if(!$documentId)cg_json_error('Document id is required.',422);
$pdo=Database::connect(); $user=cg_current_user();
$stmt=$pdo->prepare('SELECT d.*,c.case_number FROM documents d JOIN cases c ON c.id=d.case_id WHERE d.id=?');$stmt->execute([$documentId]);$document=$stmt->fetch();
if(!$document)cg_json_error('Document not found.',404);
$isUploader = ((int) ($document['uploaded_by'] ?? 0) === (int) $user['id']);
if(!$isUploader && !cg_user_can_access_case((int)$document['case_id'],$user))cg_json_error('You are not authorized to process this document.',403);
try{
 $service=new DocumentProcessingService($pdo); $result=$service->process($documentId);
 $pdo->prepare('INSERT INTO case_events (case_id,event_type,description,created_by,created_at) VALUES (?,?,?,?,NOW())')->execute([$document['case_id'],'DOCUMENT_PROCESSED',"Document {$document['name']} processed: {$result['entities_found']} entities and {$result['relationships_found']} relationships found.",$user['id']]);
 cg_log_audit($user['id'],'DOCUMENT_PROCESSED','documents',$document['case_number'],'success',"Processed {$document['name']}: {$result['entities_found']} entities, {$result['relationships_found']} relationships.");
 cg_create_notification(null,'analysis','Document Processing Completed',"Processing of {$document['name']} found {$result['entities_found']} entities and {$result['relationships_found']} relationships.","case-details.php?id={$document['case_id']}");
 cg_json_success('Document uploaded and processed successfully.',$result);
}catch(Throwable $e){
 try{$pdo->prepare("UPDATE documents SET status='Failed' WHERE id=?")->execute([$documentId]);}catch(Throwable $ignored){}
 error_log('[CYVANTA] document processing failed: '.$e->getMessage());
 try{cg_log_audit($user['id'],'DOCUMENT_PROCESSED','documents',$document['case_number'],'failure','Document processing failed.');}catch(Throwable $ignored){}
 cg_json_error('Unable to process document.',500);
}
