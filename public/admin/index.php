<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
cg_require_admin();
$pageTitle='Admin Dashboard'; $activeNav='admin'; $pageScripts=['admin/dashboard.js'];
require __DIR__ . '/../../includes/partials/header.php';
?>
<div class="mb-3"><div class="small text-muted">CYVANTA – Cyber Investigation &amp; Network Intelligence Platform</div></div>
<div class="row g-3 mb-3" id="cgAdminCards"></div>
<div class="row g-3">
 <div class="col-lg-6"><div class="cg-card h-100"><h6 class="fw-700 mb-3">User Growth</h6><canvas id="cgUserGrowthChart" height="200"></canvas><div id="cgUserGrowthData" class="small mt-2"></div></div></div>
 <div class="col-lg-6"><div class="cg-card h-100"><h6 class="fw-700 mb-3">Login Activity</h6><canvas id="cgLoginActivityChart" height="200"></canvas><div id="cgLoginActivityData" class="small mt-2"></div></div></div>
</div>
<div class="row g-3 mt-1">
 <div class="col-lg-6"><div class="cg-card h-100"><h6 class="fw-700 mb-3">Recent Login Activity</h6><div id="cgRecentLogins"></div></div></div>
 <div class="col-lg-6"><div class="cg-card h-100"><h6 class="fw-700 mb-3">Recent System Activity</h6><div id="cgRecentSystemActivity"></div></div></div>
</div>
<div class="cg-card mt-3"><h6 class="fw-700 mb-3">Quick Links</h6><div class="d-flex flex-wrap gap-2">
<a href="users.php" class="cg-btn cg-btn-outline"><i class="fa-solid fa-users"></i> Manage Users</a><a href="cases.php" class="cg-btn cg-btn-outline"><i class="fa-solid fa-folder-open"></i> Manage Cases</a><a href="audit-logs.php" class="cg-btn cg-btn-outline"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a><a href="activity.php" class="cg-btn cg-btn-outline"><i class="fa-solid fa-chart-line"></i> Activity Monitor</a><a href="settings.php" class="cg-btn cg-btn-outline"><i class="fa-solid fa-sliders"></i> System Settings</a><a href="system-health.php" class="cg-btn cg-btn-outline"><i class="fa-solid fa-heart-pulse"></i> System Health</a>
</div></div>
<?php require __DIR__ . '/../../includes/partials/footer.php'; ?>
