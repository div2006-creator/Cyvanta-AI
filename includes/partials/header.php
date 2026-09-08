<?php
/**
 * Expects $pageTitle and $activeNav to be set by the including page.
 */
$user = cg_current_user();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$base = cg_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> · CYVANTA</title>
<link rel="icon" href="<?= $base ?>/assets/images/favicon.svg">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link href="<?= $base ?>/assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body class="cg-app">
<div class="cg-shell">
  <aside class="cg-sidebar d-none d-lg-flex flex-column">
    <div class="cg-brand">
      <span class="cg-brand-mark"><i class="fa-solid fa-diagram-project"></i></span>
      <div>
        <div class="cg-brand-name">CYVANTA</div>
        <div class="cg-brand-tag">Cyber Investigation &amp; Network Intelligence Platform</div>
      </div>
    </div>
    <nav class="cg-nav">
      <a href="<?= $base ?>/dashboard.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
      <a href="<?= $base ?>/cases.php" class="<?= $activeNav === 'cases' ? 'active' : '' ?>"><i class="fa-solid fa-folder-open"></i> Cases</a>
      <a href="<?= $base ?>/network.php" class="<?= $activeNav === 'network' ? 'active' : '' ?>"><i class="fa-solid fa-circle-nodes"></i> Network</a>
      <a href="<?= $base ?>/entities.php" class="<?= $activeNav === 'entities' ? 'active' : '' ?>"><i class="fa-solid fa-users-viewfinder"></i> Entities</a>
      <a href="<?= $base ?>/documents.php" class="<?= $activeNav === 'documents' ? 'active' : '' ?>"><i class="fa-solid fa-file-shield"></i> Documents</a>
      <a href="<?= $base ?>/analysis.php" class="<?= $activeNav === 'analysis' ? 'active' : '' ?>"><i class="fa-solid fa-brain"></i> AI Analysis</a>
      <a href="<?= $base ?>/alerts.php" class="<?= $activeNav === 'alerts' ? 'active' : '' ?>"><i class="fa-solid fa-bell"></i> Alerts</a>
      <a href="<?= $base ?>/live-api-feed.php" class="<?= $activeNav === 'live-api' ? 'active' : '' ?>"><i class="fa-solid fa-cloud-arrow-down"></i> Live Gov Feed</a>
      <?php if (in_array($user['role'], ['super_admin', 'administrator'], true)): ?>
      <div class="cg-nav-divider">Administration</div>
      <a href="<?= $base ?>/admin/index.php" class="<?= $activeNav === 'admin' ? 'active' : '' ?>"><i class="fa-solid fa-shield-halved"></i> Admin Panel</a>
      <?php endif; ?>
    </nav>
    <div class="cg-sidebar-footer">
      <a href="<?= $base ?>/profile.php" class="cg-user-chip">
        <span class="cg-avatar"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?></span>
        <span>
          <div class="cg-user-name"><?= htmlspecialchars($user['name'] ?? '') ?></div>
          <div class="cg-user-role"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? ''))) ?></div>
        </span>
      </a>
    </div>
  </aside>

  <div class="cg-main">
    <header class="cg-topbar">
      <button class="cg-mobile-toggle d-lg-none" id="cgMobileMenuBtn"><i class="fa-solid fa-bars"></i></button>
      <div class="d-lg-none cg-brand-mini"><i class="fa-solid fa-diagram-project"></i> CYVANTA</div>
      <h1 class="cg-page-title d-none d-lg-block"><?= htmlspecialchars($pageTitle) ?></h1>
      <div class="cg-topbar-actions">
        <div class="cg-search d-none d-md-flex">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="cgGlobalSearch" placeholder="Search cases, people, entities...">
          <div class="cg-search-results" id="cgSearchResults"></div>
        </div>
        <div class="cg-notif-wrap">
          <button class="cg-icon-btn" id="cgNotifBtn"><i class="fa-solid fa-bell"></i><span class="cg-badge" id="cgNotifBadge" hidden>0</span></button>
          <div class="cg-notif-dropdown" id="cgNotifDropdown"></div>
        </div>
        <span class="cg-ws-status" id="cgWsStatus" title="Real-time connection status"><i class="fa-solid fa-circle"></i></span>
      </div>
    </header>
    <main class="cg-content">
