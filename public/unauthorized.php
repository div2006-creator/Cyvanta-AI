<?php require_once __DIR__ . '/../includes/bootstrap.php'; http_response_code(403); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unauthorized · CYVANTA</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet"></head>
<body class="cg-app"><div class="cg-auth-page"><div class="cg-auth-card text-center">
<i class="fa-solid fa-ban fs-1 mb-3" style="color:var(--cg-critical)"></i>
<h5 class="fw-800">403 — Access Denied</h5>
<p class="text-muted small">You do not have permission to view this page. This attempt has been logged.</p>
<a href="<?= cg_is_logged_in() ? 'dashboard.php' : 'login.php' ?>" class="cg-btn cg-btn-primary w-100 justify-content-center mt-2">Go Back</a>
</div></div></body></html>
