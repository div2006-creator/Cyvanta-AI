<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();
$pageTitle = 'Case Management';
$activeNav = 'cases';
$pageScripts = ['cases.js'];
require __DIR__ . '/../includes/partials/header.php';
$user = cg_current_user();
$canCreate = in_array($user['role'], ['super_admin', 'administrator', 'investigator'], true);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h4 class="fw-800 m-0 d-none d-lg-block">Case Management</h4>
  <?php if ($canCreate): ?>
  <button class="cg-btn cg-btn-primary" data-bs-toggle="modal" data-bs-target="#cgCaseModal"><i class="fa-solid fa-plus"></i> New Case</button>
  <?php endif; ?>
</div>

<div class="cg-card mb-3">
  <div class="row g-2">
    <div class="col-md-4"><input type="text" id="cgCaseSearch" class="cg-form-control" placeholder="Search cases..."></div>
    <div class="col-6 col-md-2">
      <select id="cgFilterStatus" class="cg-form-select">
        <option value="">All Statuses</option>
        <option>New</option><option>Under Investigation</option><option>Intelligence Review</option>
        <option>Critical</option><option>Resolved</option><option>Archived</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select id="cgFilterPriority" class="cg-form-select">
        <option value="">All Priorities</option>
        <option>Low</option><option>Medium</option><option>High</option><option>Critical</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select id="cgFilterCategory" class="cg-form-select"><option value="">All Categories</option></select>
    </div>
    <div class="col-6 col-md-2"><button class="cg-btn cg-btn-outline w-100" id="cgResetFilters">Reset</button></div>
  </div>
</div>

<div class="cg-card p-0 overflow-hidden">
  <div class="table-responsive d-none d-md-block">
    <table class="cg-table mb-0">
      <thead><tr>
        <th>Case ID</th><th>Case Name</th><th>Category</th><th>Priority</th><th>Status</th>
        <th>Investigator</th><th>Created</th><th>Updated</th><th>Actions</th>
      </tr></thead>
      <tbody id="cgCaseTableBody"></tbody>
    </table>
  </div>
  <div class="d-md-none p-2" id="cgCaseCards"></div>
  <div id="cgCaseEmpty" class="cg-empty-state" hidden><i class="fa-solid fa-folder-open"></i>No cases match your filters.</div>
</div>
<div id="cgCasePagination" class="d-flex justify-content-center gap-2 mt-3"></div>

<!-- Create Case Modal -->
<div class="modal fade" id="cgCaseModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0"><h5 class="modal-title fw-700">Create New Case</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <form id="cgCaseForm">
        <div class="modal-body row g-3">
          <div class="col-md-6"><label class="cg-label">Case Title</label><input required name="title" class="cg-form-control"></div>
          <div class="col-md-6"><label class="cg-label">Crime Category</label><input required name="category" class="cg-form-control" placeholder="e.g. Organized Crime"></div>
          <div class="col-12"><label class="cg-label">Description</label><textarea name="description" rows="3" class="cg-form-control"></textarea></div>
          <div class="col-md-6"><label class="cg-label">Location</label><input name="location" class="cg-form-control"></div>
          <div class="col-md-6"><label class="cg-label">Incident Date</label><input type="date" name="incident_date" class="cg-form-control"></div>
          <div class="col-md-6">
            <label class="cg-label">Lead Investigator</label>
            <select name="lead_investigator_id" id="cgLeadInvestigatorSelect" class="cg-form-select">
              <option value="">Assign Lead Investigator...</option>
            </select>
          </div>
          <div class="col-md-6"><label class="cg-label">Tags</label><input name="tags" class="cg-form-control" placeholder="comma,separated"></div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="cg-btn cg-btn-primary">Create Case</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
