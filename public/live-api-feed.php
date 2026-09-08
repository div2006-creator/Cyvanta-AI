<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();

$pageTitle = 'Live Government API Ingestion Feed';
$activeNav = 'live-api';
$pageScripts = ['live-api-feed.js'];
require __DIR__ . '/../includes/partials/header.php';
$user = cg_current_user();
$isAdmin = in_array($user['role'], ['super_admin', 'administrator'], true);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="fw-800 m-0"><i class="fa-solid fa-cloud-arrow-down text-primary me-2"></i>Live Government API Ingestion Feed</h4>
    <div class="small text-muted">Real-time crime intelligence ingestion from CCTNS, ICJS, NCRB, and Interpol dispatches</div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($isAdmin): ?>
    <button class="cg-btn cg-btn-outline" data-bs-toggle="modal" data-bs-target="#cgGovConfigModal"><i class="fa-solid fa-sliders"></i> API Settings</button>
    <?php endif; ?>
    <button class="cg-btn cg-btn-primary" id="cgTriggerGovSyncBtn"><i class="fa-solid fa-arrows-rotate"></i> Sync Live Feed Now</button>
  </div>
</div>

<!-- Connection Status Card -->
<div class="cg-card mb-4 bg-white">
  <div class="row g-3 align-items-center">
    <div class="col-md-3">
      <div class="d-flex align-items-center gap-3">
        <div class="cg-metric-icon bg-success bg-opacity-10 text-success fs-4 border-success">
          <i class="fa-solid fa-server"></i>
        </div>
        <div>
          <div class="small text-muted text-uppercase fw-700">API Status</div>
          <div class="fw-800 text-success d-flex align-items-center gap-2" id="cgGovApiStatusBadge">
            <span class="spinner-grow spinner-grow-sm text-success" role="status"></span> Connected & Active
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="small text-muted text-uppercase fw-700">Configured API Endpoint</div>
      <div class="fw-600 text-dark font-monospace small text-truncate" id="cgGovApiEndpoint">https://cctns.ncrb.gov.in/api/v2/live-firs</div>
    </div>
    <div class="col-md-3">
      <div class="small text-muted text-uppercase fw-700">Last Sync Timestamp</div>
      <div class="fw-600 text-dark" id="cgGovApiLastSync">—</div>
    </div>
    <div class="col-md-3">
      <div class="small text-muted text-uppercase fw-700">Total Cases Ingested</div>
      <div class="fw-800 text-primary fs-5 font-monospace" id="cgGovApiTotalIngested">0</div>
    </div>
  </div>
</div>

<!-- Ingested Live Cases Table -->
<div class="cg-card p-0 overflow-hidden">
  <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
    <h6 class="fw-700 m-0 text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i>Live Ingested Crime Dispatches</h6>
    <span class="badge bg-primary text-white" id="cgLiveCasesCount">0 Live Cases</span>
  </div>
  <div class="table-responsive">
    <table class="cg-table mb-0">
      <thead>
        <tr>
          <th>Case Ref</th>
          <th>Title & Details</th>
          <th>Category</th>
          <th>Location</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Ingested Time</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="cgGovCasesTableBody">
        <tr><td colspan="8" class="text-center py-4 text-muted">Loading live government feed…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- API Config Modal -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="cgGovConfigModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-700"><i class="fa-solid fa-sliders me-2 text-primary"></i>Government API Connection Settings</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="cgGovConfigForm">
        <div class="modal-body row g-3">
          <div class="col-md-8">
            <label class="cg-label">API Endpoint URL</label>
            <input required name="endpoint" id="inputGovEndpoint" class="cg-form-control font-monospace" value="https://cctns.ncrb.gov.in/api/v2/live-firs">
          </div>
          <div class="col-md-4">
            <label class="cg-label">Department Code</label>
            <input required name="department" id="inputGovDept" class="cg-form-control" value="Crime Investigation Department (CID)">
          </div>
          <div class="col-md-8">
            <label class="cg-label">Government Authentication Key / OAuth Bearer Token</label>
            <input required name="api_key" id="inputGovApiKey" class="cg-form-control font-monospace" value="CCTNS-LIVE-KEY-8849-2026">
          </div>
          <div class="col-md-4">
            <label class="cg-label">Auto-Sync Interval (Minutes)</label>
            <input required type="number" min="1" max="1440" name="sync_interval_mins" id="inputGovInterval" class="cg-form-control" value="15">
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="enabled" id="inputGovEnabled" checked>
              <label class="form-check-label fw-600 text-dark" for="inputGovEnabled">Enable Live Automatic API Polling</label>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="cg-btn cg-btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Configuration</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
