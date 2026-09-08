<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();
$first = isset($_GET['first']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password · CYVANTA</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="cg-app">
<div class="cg-auth-page">
  <div class="cg-auth-card">
    <h5 class="fw-800 mb-1"><i class="fa-solid fa-key" style="color:var(--cg-accent)"></i> Change Password</h5>
    <p class="text-muted small mb-4"><?= $first ? 'For security, you must set a new password before continuing.' : 'Update your account password.' ?></p>
    <div id="cgAlert" class="alert alert-danger py-2 small" hidden></div>
    <form id="cgForm">
      <label class="cg-label">Current Password</label>
      <input required type="password" name="current_password" class="cg-form-control mb-3">
      <label class="cg-label">New Password</label>
      <input required type="password" name="new_password" minlength="8" class="cg-form-control mb-3">
      <label class="cg-label">Confirm New Password</label>
      <input required type="password" name="confirm_password" minlength="8" class="cg-form-control mb-4">
      <button class="cg-btn cg-btn-primary w-100 justify-content-center">Update Password</button>
    </form>
  </div>
</div>
<div class="cg-toast-stack" id="cgToastStack"></div>
<script>window.CG = { baseUrl: '', csrfToken: '<?= cg_csrf_token() ?>' };</script>
<script src="assets/js/app.js"></script>
<script>
document.getElementById('cgForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(this).entries());
  const alertBox = document.getElementById('cgAlert');
  if (payload.new_password !== payload.confirm_password) { alertBox.textContent = 'New passwords do not match.'; alertBox.hidden = false; return; }
  const res = await cgApi('/api/auth/change-password.php', { method: 'POST', body: JSON.stringify(payload) });
  if (res.success) { window.location.href = 'dashboard.php'; } else { alertBox.textContent = res.message; alertBox.hidden = false; }
});
</script>
</body>
</html>
