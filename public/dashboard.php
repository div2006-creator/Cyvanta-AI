<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$pageScripts = ['dashboard.js'];
require __DIR__ . '/../includes/partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 d-lg-none"><h4 class="fw-800 m-0">CYVANTA</h4></div>
<div class="mb-3">
  <div class="small text-muted">CYVANTA – Cyber Investigation &amp; Network Intelligence Platform</div>
</div>
<div class="row g-3 mb-3" id="cgMetricCards"></div>
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="cg-card h-100">
      <div class="d-flex justify-content-between align-items-center mb-3"><h6 class="fw-700 m-0">Investigation Activity</h6><span class="small text-muted">Last 14 days</span></div>
      <canvas id="cgActivityChart" height="110"></canvas>
      <div id="cgInvestigationActivity" class="small mt-3"></div>
    </div>
  </div>
  <div class="col-lg-4"><div class="cg-card h-100"><h6 class="fw-700 mb-3">Case Statistics</h6><canvas id="cgCaseStatusChart" height="200"></canvas><div id="cgCaseStats" class="small mt-3"></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-6"><div class="cg-card h-100"><h6 class="fw-700 mb-3"><i class="fa-solid fa-circle-nodes me-1" style="color:var(--cg-accent)"></i> Network Intelligence</h6><div id="cgNetworkIntel" class="small text-muted">Loading network intelligence…</div></div></div>
  <div class="col-lg-6"><div class="cg-card h-100"><h6 class="fw-700 mb-3"><i class="fa-solid fa-clock-rotate-left me-1" style="color:var(--cg-accent)"></i> Recent Activity</h6><div id="cgRecentActivity" class="small text-muted">Loading recent activity…</div></div></div>
</div>
<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
