<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (cg_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CYVANTA — Reveal Hidden Criminal Networks</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="cg-landing">

<nav class="cg-landing-nav">
  <div class="d-flex align-items-center gap-2 fw-800"><i class="fa-solid fa-diagram-project" style="color:var(--cg-accent)"></i> CYVANTA</div>
  <div class="d-none d-md-flex gap-4 small text-muted">
    <a href="#how-it-works" class="text-reset">How it works</a>
    <a href="#capabilities" class="text-reset">Capabilities</a>
    <a href="#security" class="text-reset">Security</a>
  </div>
  <a href="login.php" class="cg-btn cg-btn-primary cg-btn-sm">Secure Login</a>
</nav>

<section class="cg-hero">
  <h1>Reveal Hidden Criminal Networks With Intelligent Graph Analysis</h1>
  <p>CYVANTA connects fragmented investigative information — people, phones, vehicles, locations, and transactions — into one interactive intelligence network.</p>
  <div class="d-flex justify-content-center gap-3">
    <a href="login.php" class="cg-btn cg-btn-primary">Access Investigation Platform</a>
    <a href="#how-it-works" class="cg-btn cg-btn-outline">Learn More</a>
  </div>
</section>

<section class="cg-section" id="how-it-works">
  <div class="cg-section-title">How It Works</div>
  <div class="cg-section-sub">Collect → Process → Extract → Connect → Analyze → Investigate</div>
  <div class="row g-3">
    <?php
    $steps = [
      ['fa-file-import', 'Collect', 'Upload case documents, reports and evidence from the field.'],
      ['fa-gears', 'Process', 'Documents move through a transparent, auditable processing pipeline.'],
      ['fa-user-magnifying-glass', 'Extract', 'People, organizations, phones, vehicles and locations are identified.'],
      ['fa-circle-nodes', 'Connect', 'Relationships between entities are mapped automatically.'],
      ['fa-chart-network', 'Analyze', 'Analytical indicators highlight hubs, bridges and patterns.'],
      ['fa-magnifying-glass-location', 'Investigate', 'Investigators review, annotate and act on the intelligence.'],
    ];
    foreach ($steps as $s): ?>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="cg-feature-card text-center">
        <div class="cg-feature-icon mx-auto"><i class="fa-solid <?= $s[0] ?>"></i></div>
        <div class="fw-700 mb-1"><?= $s[1] ?></div>
        <div class="text-muted small"><?= $s[2] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="cg-section" id="capabilities" style="background:var(--cg-bg-alt)">
  <div class="cg-section-title">Key Capabilities</div>
  <div class="cg-section-sub">Everything an investigation team needs in one platform</div>
  <div class="row g-3">
    <?php
    $caps = [
      ['fa-folder-open', 'Case Management', 'Create, assign, prioritize and track investigations end-to-end.'],
      ['fa-users-viewfinest'=>'fa-users-viewfinder', 'Entity Extraction', 'Identify people, organizations, vehicles and locations from documents.'],
      ['fa-diagram-project', 'Graph Intelligence', 'Interactive, explorable network visualization for every case.'],
      ['fa-brain', 'AI/NLP-Ready Analysis', 'Modular analysis layer ready for real NLP models.'],
      ['fa-bell', 'Real-Time Intelligence', 'Live notifications for processing, assignments and alerts.'],
      ['fa-shield-halved', 'Auditable & Secure', 'Role-based access with a complete, immutable audit trail.'],
    ];
    foreach ($caps as $c): $icon = is_array($c) ? $c[0] : $c; ?>
    <div class="col-md-6 col-lg-4">
      <div class="cg-feature-card">
        <div class="cg-feature-icon"><i class="fa-solid <?= $c[0] ?>"></i></div>
        <div class="fw-700 mb-1"><?= $c[1] ?></div>
        <div class="text-muted small"><?= $c[2] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="cg-section" id="security">
  <div class="cg-section-title">Built For Controlled, Auditable Investigation Workflows</div>
  <div class="cg-section-sub">Turn unstructured intelligence into structured investigative insight — responsibly</div>
  <div class="cg-disclaimer mx-auto" style="max-width:760px">
    <i class="fa-solid fa-circle-info mt-1"></i>
    <div>CYVANTA is an intelligence and investigation-support platform. Analytical results are based on available data and computational indicators and must not be treated as definitive evidence, proof of guilt, or an automated decision about any individual. Human investigators remain responsible for interpretation and decisions.</div>
  </div>
  <div class="text-center mt-4">
    <a href="login.php" class="cg-btn cg-btn-primary">Start Investigation</a>
  </div>
</section>

<footer class="cg-footer">
  <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
    <div>
      <div class="fw-800 mb-1">CYVANTA</div>
      <div>Connecting Evidence. Revealing Networks. Supporting Investigations.</div>
    </div>
    <div>© <?= date('Y') ?> CYVANTA. All rights reserved.</div>
  </div>
</footer>

</body>
</html>
