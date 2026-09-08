    </main>
  </div>
</div>

<nav class="cg-bottom-nav d-lg-none">
  <a href="<?= $base ?>/dashboard.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
  <a href="<?= $base ?>/cases.php" class="<?= $activeNav === 'cases' ? 'active' : '' ?>"><i class="fa-solid fa-folder-open"></i><span>Cases</span></a>
  <a href="<?= $base ?>/network.php" class="<?= $activeNav === 'network' ? 'active' : '' ?>"><i class="fa-solid fa-circle-nodes"></i><span>Network</span></a>
  <a href="<?= $base ?>/alerts.php" class="<?= $activeNav === 'alerts' ? 'active' : '' ?>"><i class="fa-solid fa-bell"></i><span>Alerts</span></a>
  <a href="<?= $base ?>/profile.php" class="<?= $activeNav === 'profile' ? 'active' : '' ?>"><i class="fa-solid fa-user"></i><span>Profile</span></a>
</nav>

<div class="cg-toast-stack" id="cgToastStack"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
  window.CG = {
    baseUrl: "<?= $base ?>",
    wsUrl: "<?= WEBSOCKET_URL ?>",
    csrfToken: "<?= cg_csrf_token() ?>",
    user: <?= json_encode($user) ?>
  };
</script>
<script src="<?= $base ?>/assets/js/app.js"></script>
<?php if (!empty($pageScripts)) foreach ($pageScripts as $src): ?>
<script src="<?= $base ?>/assets/js/<?= $src ?>"></script>
<?php endforeach; ?>
</body>
</html>
