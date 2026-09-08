<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
cg_require_admin();
$pageTitle = 'Activity Monitoring';
$activeNav = 'admin';
$pageScripts = ['admin/activity.js'];
require __DIR__ . '/../../includes/partials/header.php';
?>

<h4 class="fw-800 mb-3 d-none d-lg-block">Live Activity Feed</h4>
<div class="cg-card">
  <div id="cgActivityFeed">Loading…</div>
</div>

<?php require __DIR__ . '/../../includes/partials/footer.php'; ?>
