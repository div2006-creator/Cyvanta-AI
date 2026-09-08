<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();
$pageTitle = 'Profile';
$activeNav = 'profile';
$user = cg_current_user();
$pdo = Database::connect();
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="cg-card text-center">
      <div class="cg-avatar mx-auto mb-3" style="width:72px;height:72px;font-size:26px;"><?= strtoupper(substr($profile['full_name'], 0, 1)) ?></div>
      <h5 class="fw-800 mb-0"><?= htmlspecialchars($profile['full_name']) ?></h5>
      <div class="text-muted small mb-2">@<?= htmlspecialchars($profile['username']) ?></div>
      <span class="cg-badge-status status-resolved"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $profile['role']))) ?></span>
      <hr style="border-color:var(--cg-border-soft)">
      <div class="text-start small">
        <div class="d-flex justify-content-between py-1"><span class="text-muted">Email</span><span><?= htmlspecialchars($profile['email']) ?></span></div>
        <div class="d-flex justify-content-between py-1"><span class="text-muted">Department</span><span><?= htmlspecialchars($profile['department'] ?: '—') ?></span></div>
        <div class="d-flex justify-content-between py-1"><span class="text-muted">Last Login</span><span><?= $profile['last_login'] ? date('d M Y, H:i', strtotime($profile['last_login'])) : '—' ?></span></div>
        <div class="d-flex justify-content-between py-1"><span class="text-muted">Account Status</span><span class="text-success"><?= $profile['is_active'] ? 'Active' : 'Disabled' ?></span></div>
      </div>
      <a href="api/auth/logout.php" class="cg-btn cg-btn-outline w-100 justify-content-center mt-3"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="cg-card mb-3">
      <h6 class="fw-700 mb-3">Update Profile</h6>
      <form id="cgProfileForm" class="row g-3">
        <div class="col-md-6"><label class="cg-label">Full Name</label><input name="full_name" class="cg-form-control" value="<?= htmlspecialchars($profile['full_name']) ?>"></div>
        <div class="col-md-6"><label class="cg-label">Phone</label><input name="phone" class="cg-form-control" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="cg-label">Department</label><input name="department" class="cg-form-control" value="<?= htmlspecialchars($profile['department'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="cg-label">Email</label><input name="email" class="cg-form-control" value="<?= htmlspecialchars($profile['email']) ?>"></div>
        <div class="col-12"><button class="cg-btn cg-btn-primary">Save Changes</button></div>
      </form>
    </div>
    <div class="cg-card">
      <h6 class="fw-700 mb-3">Change Password</h6>
      <form id="cgChangePwForm" class="row g-3">
        <div class="col-md-4"><label class="cg-label">Current Password</label><input required type="password" name="current_password" class="cg-form-control"></div>
        <div class="col-md-4"><label class="cg-label">New Password</label><input required type="password" name="new_password" class="cg-form-control" minlength="8"></div>
        <div class="col-md-4"><label class="cg-label">Confirm New Password</label><input required type="password" name="confirm_password" class="cg-form-control" minlength="8"></div>
        <div class="col-12"><button class="cg-btn cg-btn-primary">Update Password</button></div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
<script>
document.getElementById('cgProfileForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(this).entries());
  const res = await cgApi('/api/users/update-profile.php', { method: 'POST', body: JSON.stringify(payload) });
  cgToast(res.message, res.success ? 'success' : 'error');
});
document.getElementById('cgChangePwForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(this).entries());
  if (payload.new_password !== payload.confirm_password) { cgToast('New passwords do not match.', 'error'); return; }
  const res = await cgApi('/api/auth/change-password.php', { method: 'POST', body: JSON.stringify(payload) });
  cgToast(res.message, res.success ? 'success' : 'error');
  if (res.success) this.reset();
});
</script>
