<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Session Expired · CYVANTA</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet"></head>
<body class="cg-app"><div class="cg-auth-page"><div class="cg-auth-card text-center">
<i class="fa-solid fa-hourglass-end fs-1 mb-3" style="color:var(--cg-warning)"></i>
<h5 class="fw-800">Session Expired</h5>
<p class="text-muted small">Your session has timed out for security reasons. Please log in again.</p>
<a href="login.php?expired=1" class="cg-btn cg-btn-primary w-100 justify-content-center mt-2">Log In Again</a>
</div></div></body></html>
