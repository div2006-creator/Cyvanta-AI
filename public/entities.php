<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();
$pageTitle = 'Entity Database';
$activeNav = 'entities';
$pageScripts = ['entities.js'];
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="cg-card mb-3">
  <div class="row g-2">
    <div class="col-md-4"><input type="text" id="cgEntitySearch" class="cg-form-control" placeholder="Search entities..."></div>
    <div class="col-md-3">
      <select id="cgEntityTypeFilter" class="cg-form-select"><option value="">All Types</option></select>
    </div>
  </div>
</div>

<div class="cg-card p-0 overflow-hidden">
  <table class="cg-table mb-0">
    <thead><tr><th>Name</th><th>Type</th><th>Case</th><th>Risk</th><th>Connections</th></tr></thead>
    <tbody id="cgGlobalEntitiesBody"></tbody>
  </table>
  <div id="cgGlobalEntitiesEmpty" class="cg-empty-state" hidden><i class="fa-solid fa-users-viewfinder"></i>No entities found.</div>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
