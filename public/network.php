<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();
$pageTitle = 'Network Intelligence';
$activeNav = 'network';
$pdo = Database::connect();
$cases = $pdo->query("SELECT id, case_number, title FROM cases WHERE status != 'Archived' ORDER BY updated_at DESC")->fetchAll();
require __DIR__ . '/../includes/partials/header.php';
?>

<div class="cg-card mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="cg-label">Select a case to explore its network graph</label>
      <select id="cgCaseSelector" class="cg-form-select">
        <option value="">Choose a case…</option>
        <?php foreach ($cases as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['case_number'] . ' — ' . $c['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <a id="cgOpenCaseBtn" href="#" class="cg-btn cg-btn-primary w-100 justify-content-center"><i class="fa-solid fa-circle-nodes"></i> Open Network</a>
    </div>
  </div>
</div>

<div class="cg-empty-state">
  <i class="fa-solid fa-diagram-project"></i>
  Select a case above to open its interactive network graph, or open any case and go to its <strong>Network</strong> tab directly.
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
<script>
document.getElementById('cgCaseSelector').addEventListener('change', function () {
  document.getElementById('cgOpenCaseBtn').href = this.value ? CG.baseUrl + '/case-details.php?id=' + this.value + '#network' : '#';
});
</script>
