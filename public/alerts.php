<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();
$pageTitle = 'Alerts';
$activeNav = 'alerts';
require __DIR__ . '/../includes/partials/header.php';
$user = cg_current_user();
$pdo = Database::connect();
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL')->execute([$user['id']]);

$typeIcon = ['case' => 'fa-folder-open', 'document' => 'fa-file-shield', 'analysis' => 'fa-brain', 'system' => 'fa-gear', 'security' => 'fa-shield-halved'];
?>

<div class="cg-card p-0 overflow-hidden">
  <?php if (!$notifications): ?>
    <div class="cg-empty-state"><i class="fa-solid fa-bell-slash"></i>No alerts yet.</div>
  <?php endif; ?>
  <?php foreach ($notifications as $n): ?>
    <a href="<?= htmlspecialchars($n['link'] ?? '#') ?>" class="d-flex gap-3 p-3 border-bottom text-reset text-decoration-none" style="border-color:var(--cg-border-soft)!important">
      <div class="cg-metric-icon flex-shrink-0"><i class="fa-solid <?= $typeIcon[$n['type']] ?? 'fa-bell' ?>"></i></div>
      <div>
        <div class="fw-700 text-white"><?= htmlspecialchars($n['title']) ?></div>
        <div class="small text-muted"><?= htmlspecialchars($n['message']) ?></div>
        <div class="small text-muted mt-1"><?= cg_time_ago($n['created_at']) ?></div>
      </div>
    </a>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
