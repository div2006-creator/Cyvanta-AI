<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
cg_require_admin();
$pageTitle = 'Audit Logs';
$activeNav = 'admin';
$pageScripts = ['admin/audit-logs.js'];
require __DIR__ . '/../../includes/partials/header.php';
?>

<h4 class="fw-800 mb-3 d-none d-lg-block">Audit Logs</h4>

<div class="cg-card mb-3">
  <div class="row g-2">
    <div class="col-md-4"><input type="text" id="cgAuditSearch" class="cg-form-control" placeholder="Search by action, target, description..."></div>
    <div class="col-md-3">
      <select id="cgAuditStatusFilter" class="cg-form-select"><option value="">All Results</option><option value="success">Success</option><option value="failure">Failure</option></select>
    </div>
  </div>
</div>

<div class="cg-card p-0 overflow-hidden">
  <table class="cg-table mb-0">
    <thead><tr><th>User</th><th>Action</th><th>Module</th><th>Target</th><th>IP</th><th>Result</th><th>Time</th></tr></thead>
    <tbody id="cgAuditTableBody"></tbody>
  </table>
</div>
<div id="cgAuditPagination" class="d-flex justify-content-center gap-2 mt-3"></div>

<?php require __DIR__ . '/../../includes/partials/footer.php'; ?>
