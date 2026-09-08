<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$token = $_GET['token'] ?? '';
$pdo = Database::connect();
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
$stmt->execute([$token]);
$reset = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password · CYVANTA</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="cg-app">
<div class="cg-auth-page">
  <div class="cg-auth-card">
    <h5 class="fw-800 mb-3"><i class="fa-solid fa-key" style="color:var(--cg-accent)"></i> Reset Password</h5>
    <?php if (!$reset): ?>
      <div class="alert alert-danger py-2 small">This reset link is invalid or has expired. Please request a new one.</div>
      <a href="forgot-password.php" class="cg-btn cg-btn-outline w-100 justify-content-center">Request New Link</a>
    <?php else: ?>
      <div id="cgAlert" class="alert alert-danger py-2 small" hidden></div>
      <form id="cgForm">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <label class="cg-label">New Password</label>
        <input required type="password" name="password" minlength="8" class="cg-form-control mb-3">
        <label class="cg-label">Confirm Password</label>
        <input required type="password" name="confirm_password" minlength="8" class="cg-form-control mb-4">
        <button class="cg-btn cg-btn-primary w-100 justify-content-center">Reset Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<div class="cg-toast-stack" id="cgToastStack"></div>
<script>window.CG = { baseUrl: '', csrfToken: '<?= cg_csrf_token() ?>' };</script>
<script src="assets/js/app.js"></script>
<script>
document.getElementById('cgForm')?.addEventListener('submit', async function (e) {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(this).entries());
  if (payload.password !== payload.confirm_password) { cgToast('Passwords do not match.', 'error'); return; }
  const res = await cgApi('/api/auth/reset-password.php', { method: 'POST', body: JSON.stringify(payload) });
  if (res.success) { window.location.href = 'login.php'; } else {
    const alertBox = document.getElementById('cgAlert'); alertBox.textContent = res.message; alertBox.hidden = false;
  }
});
</script>
</body>
</html>
