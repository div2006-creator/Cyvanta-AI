<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

try {
    $documentId = (int)($_GET['id'] ?? 0);
    if (!$documentId) {
        http_response_code(400);
        die('Document ID required.');
    }

    $user = cg_current_user();
    $pdo = Database::connect();
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->execute([$documentId]);
    $doc = $stmt->fetch();
    if (!$doc) {
        http_response_code(404);
        die('Document not found.');
    }

    if (!cg_user_can_access_case((int)$doc['case_id'], $user)) {
        http_response_code(403);
        die('Unauthorized.');
    }

    $filePath = UPLOAD_DIR . '/' . $doc['stored_filename'];
    if (!file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        die('Stored file missing or unreadable.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $filePath);
    finfo_close($finfo);

    $ext = strtolower(pathinfo($doc['original_filename'], PATHINFO_EXTENSION));
    $mimeMap = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'gif' => 'image/gif', 'bmp' => 'image/bmp', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo', 'mkv' => 'video/x-matroska',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv'
    ];
    if (isset($mimeMap[$ext])) {
        $mime = $mimeMap[$ext];
    }

    $size = filesize($filePath);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('Accept-Ranges: bytes');
    header('Content-Disposition: inline; filename="' . basename($doc['original_filename']) . '"');
    
    readfile($filePath);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    die('Unable to stream file.');
}
