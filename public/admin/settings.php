<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
cg_require_role(['super_admin']);
$pageTitle = 'System Settings';
$activeNav = 'admin';
$pageScripts = ['admin/settings.js'];
require __DIR__ . '/../../includes/partials/header.php';
?>

<h4 class="fw-800 mb-3 d-none d-lg-block">System Settings</h4>

<form id="cgSettingsForm">
  <div class="cg-card mb-3">
    <h6 class="fw-700 mb-3">System Configuration</h6>
    <div class="row g-3">
      <div class="col-md-4"><label class="cg-label">Application Name</label><input name="app_name" class="cg-form-control"></div>
      <div class="col-md-4"><label class="cg-label">Session Timeout (minutes)</label><input type="number" name="session_timeout_minutes" class="cg-form-control"></div>
      <div class="col-md-4"><label class="cg-label">File Upload Limit (MB)</label><input type="number" name="file_upload_limit_mb" class="cg-form-control"></div>
      <div class="col-12"><label class="cg-label">Allowed File Extensions</label><input name="allowed_file_extensions" class="cg-form-control"></div>
    </div>
  </div>

  <div class="cg-card mb-3">
    <h6 class="fw-700 mb-3">Security Settings</h6>
    <div class="row g-3">
      <div class="col-md-6"><label class="cg-label">Maximum Login Attempts</label><input type="number" name="max_login_attempts" class="cg-form-control"></div>
      <div class="col-md-6"><label class="cg-label">Account Lock Duration (minutes)</label><input type="number" name="account_lock_minutes" class="cg-form-control"></div>
    </div>
  </div>

  <div class="cg-card mb-3">
    <h6 class="fw-700 mb-3">AI / NLP Settings</h6>
    <div class="row g-3">
      <div class="col-md-8"><label class="cg-label">AI Service URL</label><input name="ai_service_url" class="cg-form-control" placeholder="http://localhost:8000"></div>
      <div class="col-md-4 d-flex align-items-end gap-2">
        <label class="d-flex align-items-center gap-2"><input type="checkbox" name="ai_service_enabled" value="1"> Enable External Service</label>
      </div>
      <div class="col-md-4"><label class="d-flex align-items-center gap-2"><input type="checkbox" name="entity_extraction_enabled" value="1"> Entity Extraction Enabled</label></div>
      <div class="col-md-4"><label class="d-flex align-items-center gap-2"><input type="checkbox" name="relationship_extraction_enabled" value="1"> Relationship Extraction Enabled</label></div>
      <div class="col-md-4"><label class="cg-label">Analysis Confidence Threshold (%)</label><input type="number" name="analysis_confidence_threshold" class="cg-form-control"></div>
    </div>
    <div class="small text-muted mt-2">When disabled, CYVANTA uses its built-in deterministic extraction engine so the workflow always functions, even without a connected NLP service.</div>
  </div>

  <button type="submit" class="cg-btn cg-btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
</form>

<?php require __DIR__ . '/../../includes/partials/footer.php'; ?>
