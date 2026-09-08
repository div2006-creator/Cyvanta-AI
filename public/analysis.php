<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();
$pageTitle = 'AI Analysis';
$activeNav = 'analysis';
$pageScripts = ['analysis.js'];
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="cg-disclaimer mb-3">
  <i class="fa-solid fa-circle-info mt-1"></i>
  <div>CYVANTA is designed as an intelligence and investigation-support platform. Analytical results are based on available data and computational indicators and must not be treated as definitive evidence, proof of guilt, or an automated decision about any individual.</div>
</div>

<div class="row g-3 mb-3" id="cgAnalysisCards"></div>

<div class="cg-card">
  <h6 class="fw-700 mb-3">Recent Analytical Indicators</h6>
  <div id="cgRecentPatterns">Loading…</div>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
