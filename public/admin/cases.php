<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
cg_require_admin();
$pageTitle = 'Case Management';
$activeNav = 'admin';
$pageScripts = ['admin/cases.js'];
require __DIR__ . '/../../includes/partials/header.php';
$pdo = Database::connect();
$investigators = $pdo->query("SELECT id, full_name FROM users WHERE role = 'investigator' AND is_active = 1 ORDER BY full_name")->fetchAll();
?>

<h4 class="fw-800 mb-3 d-none d-lg-block">All Cases</h4>

<div class="cg-card p-0 overflow-hidden">
  <table class="cg-table mb-0">
    <thead><tr><th>Case ID</th><th>Title</th><th>Status</th><th>Priority</th><th>Investigator</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody id="cgAdminCasesBody"></tbody>
  </table>
</div>

<div class="modal fade" id="cgAssignModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0"><h5 class="modal-title fw-700">Assign Case</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <form id="cgAssignForm">
        <input type="hidden" name="case_id">
        <div class="modal-body">
          <label class="cg-label">Investigator</label>
          <select name="user_id" class="cg-form-select" required>
            <?php foreach ($investigators as $i): ?><option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['full_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="cg-btn cg-btn-primary">Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/partials/footer.php'; ?>
