<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (cg_is_logged_in()) { header('Location: dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password · CYVANTA</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="cg-app">
<div class="cg-auth-page">
  <div class="cg-auth-card">
    <h5 class="fw-800 mb-1"><i class="fa-solid fa-unlock-keyhole" style="color:var(--cg-accent)"></i> Forgot Password</h5>
    <p class="text-muted small mb-4">Enter your registered email. If an account exists, a reset link will be generated.</p>
    <div id="cgAlert" class="alert alert-success py-2 small" hidden></div>
    <form id="cgForm">
      <label class="cg-label">Email</label>
      <input required type="email" name="email" class="cg-form-control mb-4">
      <button class="cg-btn cg-btn-primary w-100 justify-content-center">Send Reset Link</button>
    </form>
    <div class="text-center mt-3"><a href="login.php" style="color:var(--cg-accent)" class="small">Back to login</a></div>
  </div>
</div>
<div class="cg-toast-stack" id="cgToastStack"></div>
<script>window.CG = { baseUrl: '', csrfToken: '<?= cg_csrf_token() ?>' };</script>
<script src="assets/js/app.js"></script>
<script>
document.getElementById('cgForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(this).entries());
  const res = await cgApi('/api/auth/forgot-password.php', { method: 'POST', body: JSON.stringify(payload) });
  const alertBox = document.getElementById('cgAlert');
  alertBox.textContent = res.message; alertBox.hidden = false;
});
</script>
</body>
</html>
