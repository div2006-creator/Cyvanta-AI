<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
cg_require_admin();
$pageTitle = 'System Health';
$activeNav = 'admin';

$phpOk = true;
try {
    $pdo = Database::connect();
    $dbOk = true;
} catch (Throwable $e) {
    $dbOk = false;
}
$uploadWritable = is_writable(UPLOAD_DIR);
$aiConfigured = AI_SERVICE_ENABLED && AI_SERVICE_URL;

require __DIR__ . '/../../includes/partials/header.php';

function statusPill($ok, $onlineLabel = 'ONLINE', $offlineLabel = 'OFFLINE') {
    return $ok
        ? "<span class=\"cg-badge-status status-resolved\">$onlineLabel</span>"
        : "<span class=\"cg-badge-status status-critical\">$offlineLabel</span>";
}
?>

<h4 class="fw-800 mb-3 d-none d-lg-block">System Health</h4>

<div class="row g-3">
  <div class="col-md-6 col-lg-3">
    <div class="cg-card text-center">
      <i class="fa-brands fa-php fs-1 mb-2" style="color:var(--cg-accent)"></i>
      <div class="fw-700 mb-2">PHP <?= PHP_VERSION ?></div>
      <?= statusPill($phpOk) ?>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="cg-card text-center">
      <i class="fa-solid fa-database fs-1 mb-2" style="color:var(--cg-accent)"></i>
      <div class="fw-700 mb-2">MySQL</div>
      <?= statusPill($dbOk) ?>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="cg-card text-center">
      <i class="fa-solid fa-tower-broadcast fs-1 mb-2" style="color:var(--cg-accent)"></i>
      <div class="fw-700 mb-2">WebSocket</div>
      <span class="cg-badge-status status-under-investigation" id="cgWsHealthPill">CHECKING…</span>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="cg-card text-center">
      <i class="fa-solid fa-brain fs-1 mb-2" style="color:var(--cg-accent)"></i>
      <div class="fw-700 mb-2">AI / NLP Service</div>
      <?= statusPill($aiConfigured, 'CONNECTED', 'DEMO MODE') ?>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="cg-card text-center">
      <i class="fa-solid fa-hard-drive fs-1 mb-2" style="color:var(--cg-accent)"></i>
      <div class="fw-700 mb-2">Storage</div>
      <?= statusPill($uploadWritable, 'WRITABLE', 'READ-ONLY') ?>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="cg-card text-center">
      <i class="fa-solid fa-server fs-1 mb-2" style="color:var(--cg-accent)"></i>
      <div class="fw-700 mb-2">Application</div>
      <?= statusPill(true) ?>
    </div>
  </div>
</div>

<div class="cg-disclaimer mt-3">
  <i class="fa-solid fa-circle-info mt-1"></i>
  <div>The AI/NLP service shows "Demo Mode" until an external service URL is configured and enabled in System Settings. In demo mode, CYVANTA's built-in deterministic extraction engine performs the full processing pipeline.</div>
</div>

<?php require __DIR__ . '/../../includes/partials/footer.php'; ?>
<script>
(function () {
  const pill = document.getElementById('cgWsHealthPill');
  try {
    const ws = new WebSocket(CG.wsUrl);
    const timeout = setTimeout(() => { pill.textContent = 'OFFLINE'; pill.className = 'cg-badge-status status-critical'; ws.close(); }, 2000);
    ws.onopen = () => { clearTimeout(timeout); pill.textContent = 'ONLINE'; pill.className = 'cg-badge-status status-resolved'; ws.close(); };
    ws.onerror = () => { clearTimeout(timeout); pill.textContent = 'OFFLINE'; pill.className = 'cg-badge-status status-critical'; };
  } catch (e) {
    pill.textContent = 'OFFLINE'; pill.className = 'cg-badge-status status-critical';
  }
})();
</script>
