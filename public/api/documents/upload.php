<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_role(['super_admin','administrator','investigator','analyst']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.',405);

try {
    $caseId=(int)($_POST['case_id']??0);
    $name=trim($_POST['name']??'');
    if(!$caseId||$name==='') cg_json_error('Case and document name are required.',422);
    $user=cg_current_user();
    $pdo=Database::connect();
    $canUpload = cg_user_can_access_case($caseId, $user);
    if (!$canUpload) {
        $cStmt = $pdo->prepare("SELECT status FROM cases WHERE id = ?");
        $cStmt->execute([$caseId]);
        $cStatus = $cStmt->fetchColumn();
        if ($cStatus && $cStatus !== 'Archived' && in_array($user['role'], ['investigator','analyst'], true)) {
            $canUpload = true;
        }
    }
    if(!$canUpload) cg_json_error('You are not authorized to upload to this case.',403);
    if (empty($_FILES['file'])) {
        if (!empty($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > UPLOAD_MAX_SIZE) {
            cg_json_error('Uploaded file exceeds maximum upload size (50 MB).', 422);
        }
        cg_json_error('A valid file is required.', 422);
    }
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            cg_json_error('Uploaded file exceeds maximum upload size (50 MB).', 422);
        }
        if ($file['error'] === UPLOAD_ERR_PARTIAL) {
            cg_json_error('File upload was interrupted. Please try again.', 422);
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            cg_json_error('No file was selected.', 422);
        }
        cg_json_error('Upload failed with server error code ' . $file['error'], 422);
    }
    if ($file['size'] > UPLOAD_MAX_SIZE) cg_json_error('File exceeds the maximum upload size (50 MB).', 422);
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,ALLOWED_UPLOAD_EXTENSIONS,true)) cg_json_error('File type not permitted.',422);

    $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo,$file['tmp_name']); finfo_close($finfo);
    $allowedMimePrefixes=['text/','application/pdf','application/msword','application/vnd.','image/','video/','application/octet-stream'];
    $mimeOk=false; foreach($allowedMimePrefixes as $prefix){if(str_starts_with($mime,$prefix)){$mimeOk=true;break;}}
    if(!$mimeOk) cg_json_error('File content does not match an allowed type.',422);

    if(!is_dir(UPLOAD_DIR)&&!mkdir(UPLOAD_DIR,0755,true)&&!is_dir(UPLOAD_DIR)) cg_json_error('Unable to prepare document storage.',500);
    $storedName=bin2hex(random_bytes(16)).'.'.$ext; $destination=UPLOAD_DIR.'/'.$storedName;
    if(!move_uploaded_file($file['tmp_name'],$destination)) cg_json_error('Failed to store the uploaded file.',500);

    $pdo=Database::connect();
    try {
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('INSERT INTO documents (case_id,name,doc_type,description,source,confidentiality,stored_filename,original_filename,file_size,status,uploaded_by,uploaded_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$caseId,$name,strtoupper($ext),$_POST['description']??null,$_POST['source']??null,$_POST['confidentiality']??'Internal',$storedName,$file['name'],$file['size'],'Uploaded',$user['id']]);
        $documentId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO case_events (case_id,event_type,description,created_by,created_at) VALUES (?,?,?,?,NOW())')->execute([$caseId,'DOCUMENT_UPLOADED',"Document $name uploaded.",$user['id']]);
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack(); @unlink($destination); throw $e;
    }

    $s=$pdo->prepare('SELECT case_number FROM cases WHERE id=?');$s->execute([$caseId]);$caseNumber=(string)$s->fetchColumn();
    cg_log_audit($user['id'],'DOCUMENT_UPLOADED','documents',$caseNumber,'success',"Uploaded $name");
    cg_create_notification(null,'document','Document Uploaded',"$name uploaded to $caseNumber.","case-details.php?id=$caseId");
    cg_json_success('Document uploaded successfully.',['id'=>$documentId,'document_id'=>$documentId,'status'=>'Uploaded']);
} catch(Throwable $e) {
    error_log('[CYVANTA] document upload failed: '.$e->getMessage());
    cg_json_error('Unable to upload the document.',500);
}
