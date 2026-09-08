<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (cg_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
$expired = isset($_GET['expired']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Secure Login · CYVANTA</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="cg-app">
<div class="cg-auth-page">
  <canvas class="cg-auth-canvas" id="cgNetCanvas"></canvas>
  <div class="cg-auth-card">
    <div class="text-center mb-4">
      <span class="cg-brand-mark d-inline-flex" style="width:52px;height:52px;font-size:22px;"><i class="fa-solid fa-diagram-project"></i></span>
      <h4 class="mt-3 mb-0 fw-800">CYVANTA</h4>
      <div class="cg-text-dim text-muted small">Connecting Evidence. Revealing Networks. Supporting Investigations.</div>
    </div>

    <?php if ($expired): ?>
      <div class="alert alert-warning py-2 small">Your session expired. Please log in again.</div>
    <?php endif; ?>

    <div id="cgLoginAlert" class="alert alert-danger py-2 small" hidden></div>

    <form id="cgLoginForm">
      <label class="cg-label">Username or Email</label>
      <input type="text" name="username" class="cg-form-control mb-3" placeholder="e.g. adminsumitgu" required autofocus>

      <label class="cg-label">Password</label>
      <div class="input-group mb-2">
        <input type="password" name="password" id="cgPassword" class="cg-form-control" placeholder="Enter your password" required>
        <button class="cg-btn cg-btn-outline" type="button" id="cgTogglePw"><i class="fa-solid fa-eye"></i></button>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-3 small">
        <label class="d-flex align-items-center gap-2 text-muted"><input type="checkbox" name="remember"> Remember me</label>
        <a href="forgot-password.php" class="text-decoration-none" style="color:var(--cg-accent)">Forgot password?</a>
      </div>

      <button type="submit" class="cg-btn cg-btn-primary w-100 justify-content-center" id="cgLoginBtn">
        <i class="fa-solid fa-lock"></i> Secure Login
      </button>
    </form>

    <div class="text-center mt-4 small text-muted">
      <i class="fa-solid fa-shield-halved"></i> Authorized Personnel Only — all access is logged and audited.
    </div>
  </div>
</div>

<div class="cg-toast-stack" id="cgToastStack"></div>
<script>
document.getElementById('cgTogglePw').addEventListener('click', function () {
  const pw = document.getElementById('cgPassword');
  pw.type = pw.type === 'password' ? 'text' : 'password';
  this.querySelector('i').classList.toggle('fa-eye');
  this.querySelector('i').classList.toggle('fa-eye-slash');
});

document.getElementById('cgLoginForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const btn = document.getElementById('cgLoginBtn');
  const alertBox = document.getElementById('cgLoginAlert');
  alertBox.hidden = true;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Authenticating...';

  const formData = new FormData(this);
  const payload = Object.fromEntries(formData.entries());

  try {
    const res = await fetch('api/auth/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.success) {
      window.location.href = data.data.must_change_password ? 'change-password.php?first=1' : 'dashboard.php';
    } else {
      alertBox.textContent = data.message;
      alertBox.hidden = false;
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-lock"></i> Secure Login';
    }
  } catch (err) {
    alertBox.textContent = 'Unable to reach the server. Please try again.';
    alertBox.hidden = false;
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-lock"></i> Secure Login';
  }
});

// Subtle animated network background
(function () {
  const canvas = document.getElementById('cgNetCanvas');
  const ctx = canvas.getContext('2d');
  function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
  resize(); window.addEventListener('resize', resize);
  const nodes = Array.from({ length: 40 }, () => ({
    x: Math.random() * canvas.width, y: Math.random() * canvas.height,
    vx: (Math.random() - 0.5) * 0.3, vy: (Math.random() - 0.5) * 0.3,
  }));
  function tick() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    nodes.forEach(n => {
      n.x += n.vx; n.y += n.vy;
      if (n.x < 0 || n.x > canvas.width) n.vx *= -1;
      if (n.y < 0 || n.y > canvas.height) n.vy *= -1;
    });
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dx = nodes[i].x - nodes[j].x, dy = nodes[i].y - nodes[j].y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 150) {
          ctx.strokeStyle = `rgba(34,211,238,${0.12 * (1 - dist / 150)})`;
          ctx.beginPath(); ctx.moveTo(nodes[i].x, nodes[i].y); ctx.lineTo(nodes[j].x, nodes[j].y); ctx.stroke();
        }
      }
    }
    nodes.forEach(n => {
      ctx.fillStyle = 'rgba(124,92,255,.55)';
      ctx.beginPath(); ctx.arc(n.x, n.y, 2, 0, Math.PI * 2); ctx.fill();
    });
    requestAnimationFrame(tick);
  }
  tick();
})();
</script>
</body>
</html>
