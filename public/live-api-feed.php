<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();

$pageTitle = 'Live Intelligence Ingestion System';
$activeNav = 'live-api';
$pageScripts = ['live-api-feed.js'];
require __DIR__ . '/../includes/partials/header.php';
$user = cg_current_user();
$isAdmin = in_array($user['role'], ['super_admin', 'administrator'], true);
?>

<!-- Header & Top Actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="fw-800 m-0"><i class="fa-solid fa-satellite-dish text-primary me-2"></i>Live Intelligence Ingestion System</h4>
    <div class="small text-muted">Real-time multi-source intelligence ingestion, entity extraction, and criminal network synthesis</div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 fw-700 d-flex align-items-center gap-2" id="cgWsConnectionBadge">
      <span class="spinner-grow spinner-grow-sm text-success" role="status"></span> LIVE — WEBSOCKET CONNECTED
    </span>
    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning px-3 py-2 fw-700" id="cgSimStatusBadge" style="display:none">
      <i class="fa-solid fa-play me-1 text-warning"></i> SIMULATION RUNNING
    </span>
    <button class="cg-btn cg-btn-outline" id="cgToggleSimBtn"><i class="fa-solid fa-play me-1 text-warning"></i> Start Simulation</button>
    <button class="cg-btn cg-btn-outline" data-bs-toggle="modal" data-bs-target="#cgIngestEventModal"><i class="fa-solid fa-plus me-1"></i> Ingest Event</button>
    <?php if ($isAdmin): ?>
    <button class="cg-btn cg-btn-outline" data-bs-toggle="modal" data-bs-target="#cgGovConfigModal"><i class="fa-solid fa-sliders me-1"></i> Source Settings</button>
    <?php endif; ?>
  </div>
</div>

<!-- Transport Architecture Indicator -->
<div class="d-flex justify-content-between align-items-center bg-light border rounded px-3 py-2 mb-3">
  <div class="small font-monospace text-dark" id="cgTransportStatusText">
    <i class="fa-solid fa-network-wired text-primary me-1"></i> WebSocket: <span class="fw-700 text-success" id="cgWsStateLabel">Connected</span> | Fallback Polling: <span class="fw-700 text-muted" id="cgPollStateLabel">Disabled</span>
  </div>
  <div class="small text-muted" id="cgLastSyncTimeLabel">Last sync: Just now</div>
</div>

<!-- Dashboard Metrics Row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-2">
    <div class="cg-card p-3 text-center bg-white">
      <div class="small text-muted text-uppercase fw-700">Live Events</div>
      <div class="fs-4 fw-800 text-primary font-monospace" id="cgMetricLiveEvents">0</div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="cg-card p-3 text-center bg-white">
      <div class="small text-muted text-uppercase fw-700">Active Networks</div>
      <div class="fs-4 fw-800 text-info font-monospace" id="cgMetricActiveNetworks">0</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="cg-card p-3 text-center bg-white">
      <div class="small text-muted text-uppercase fw-700">Entities Identified</div>
      <div class="fs-4 fw-800 text-dark font-monospace" id="cgMetricEntitiesIdentified">0</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="cg-card p-3 text-center bg-white">
      <div class="small text-muted text-uppercase fw-700">Relationships Discovered</div>
      <div class="fs-4 fw-800 text-secondary font-monospace" id="cgMetricRelsDiscovered">0</div>
    </div>
  </div>
  <div class="col-12 col-md-2">
    <div class="cg-card p-3 text-center bg-white">
      <div class="small text-muted text-uppercase fw-700">High-Risk Alerts</div>
      <div class="fs-4 fw-800 text-danger font-monospace" id="cgMetricHighRisk">0</div>
    </div>
  </div>
</div>

<!-- Filter Controls Bar -->
<div class="cg-card mb-4 bg-white p-3">
  <form id="cgIntelFilterForm" class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="cg-label small mb-1">Source Type</label>
      <select name="source_type" class="cg-form-control form-select-sm">
        <option value="">All Sources</option>
        <option value="PUBLIC_RECORD">Public Record</option>
        <option value="AUTHORIZED_API">Authorized API</option>
        <option value="SIMULATION">Simulation</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="cg-label small mb-1">Severity</label>
      <select name="severity" class="cg-form-control form-select-sm">
        <option value="">All Severities</option>
        <option value="Critical">Critical</option>
        <option value="High">High</option>
        <option value="Medium">Medium</option>
        <option value="Low">Low</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="cg-label small mb-1">Event Type</label>
      <select name="event_type" class="cg-form-control form-select-sm">
        <option value="">All Networks</option>
        <option value="FINANCIAL_NETWORK">Financial Network</option>
        <option value="ARMS_NETWORK">Arms Network</option>
        <option value="DRUG_NETWORK">Narcotics Network</option>
        <option value="CYBER_NETWORK">Cyber Network</option>
        <option value="ORGANIZED_CRIME">Organized Crime</option>
        <option value="HUMAN_TRAFFICKING">Human Trafficking</option>
        <option value="COMMUNICATION_NETWORK">Communication</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="cg-label small mb-1">Date From</label>
      <input type="date" name="date_from" class="cg-form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="cg-label small mb-1">Date To</label>
      <input type="date" name="date_to" class="cg-form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="cg-label small mb-1">Location Search</label>
      <input type="text" name="location" class="cg-form-control form-control-sm" placeholder="e.g. Delhi, Mumbai">
    </div>
    <div class="col-12 d-flex justify-content-between align-items-center pt-2">
      <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" name="verified_only" id="cgVerifiedOnlyCheck">
        <label class="form-check-label small fw-600 text-dark" for="cgVerifiedOnlyCheck"><i class="fa-solid fa-circle-check text-success me-1"></i> Verified Sources Only</label>
      </div>
      <div class="d-flex gap-2">
        <button type="button" id="cgResetFiltersBtn" class="cg-btn cg-btn-outline cg-btn-sm"><i class="fa-solid fa-undo me-1"></i> Reset</button>
        <button type="submit" class="cg-btn cg-btn-primary cg-btn-sm"><i class="fa-solid fa-filter me-1"></i> Apply Filters</button>
      </div>
    </div>
  </form>
</div>

<!-- Live Intelligence Feed Table -->
<div class="cg-card p-0 overflow-hidden">
  <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
    <h6 class="fw-700 m-0 text-dark"><i class="fa-solid fa-stream me-2 text-primary"></i>Live Intelligence Feed</h6>
    <div class="d-flex align-items-center gap-2">
      <button id="cgManualSyncBtn" class="cg-btn cg-btn-primary cg-btn-sm"><i class="fa-solid fa-arrows-rotate me-1"></i> SYNC NOW</button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="cg-table mb-0">
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>Source &amp; Provenance</th>
          <th>Event Title &amp; Network Narrative</th>
          <th>Location</th>
          <th>Severity</th>
          <th>Confidence</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="cgIntelFeedTableBody">
        <tr><td colspan="7" class="text-center py-4 text-muted">Loading live intelligence feed…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Event Detail Modal -->
<div class="modal fade" id="cgEventDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-700" id="cgModalEventTitle">Intelligence Event Details</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="cgModalEventBody">
        <!-- Injected by JS -->
      </div>
      <div class="modal-footer border-0">
        <a id="cgModalInspectGraphLink" href="#" class="cg-btn cg-btn-primary"><i class="fa-solid fa-diagram-project me-1"></i> Open in Network Graph</a>
        <button class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Manual Event Ingest Modal -->
<div class="modal fade" id="cgIngestEventModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-700"><i class="fa-solid fa-plus me-2 text-primary"></i>Ingest New Intelligence Event</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="cgManualIngestForm">
        <div class="modal-body row g-3">
          <div class="col-md-8">
            <label class="cg-label">Event Title</label>
            <input required name="title" class="cg-form-control" placeholder="e.g. LIVE INTELLIGENCE: Financial Network Alert">
          </div>
          <div class="col-md-4">
            <label class="cg-label">Network Type</label>
            <select name="event_type" class="cg-form-control">
              <option value="FINANCIAL_NETWORK">Financial Network</option>
              <option value="ARMS_NETWORK">Arms Network</option>
              <option value="DRUG_NETWORK">Narcotics Network</option>
              <option value="CYBER_NETWORK">Cyber Network</option>
              <option value="ORGANIZED_CRIME">Organized Crime</option>
              <option value="HUMAN_TRAFFICKING">Human Trafficking</option>
              <option value="COMMUNICATION_NETWORK">Communication Cluster</option>
            </select>
          </div>
          <div class="col-12">
            <label class="cg-label">Description &amp; Telemetry Narrative</label>
            <textarea required name="description" class="cg-form-control" rows="3" placeholder="Provide event summary narrative..."></textarea>
          </div>
          <div class="col-md-4">
            <label class="cg-label">Source Type</label>
            <select name="source_type" class="cg-form-control">
              <option value="PUBLIC_RECORD">PUBLIC_RECORD</option>
              <option value="AUTHORIZED_API">AUTHORIZED_API</option>
              <option value="SIMULATION">SIMULATION</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="cg-label">Source Name</label>
            <input required name="source_name" class="cg-form-control" value="Public Record Register">
          </div>
          <div class="col-md-4">
            <label class="cg-label">Source URL (Required for REAL DATA)</label>
            <input name="source_url" class="cg-form-control" placeholder="https://public-records.intelligence.org/alerts/fn-9902">
          </div>
          <div class="col-md-4">
            <label class="cg-label">Severity</label>
            <select name="severity" class="cg-form-control">
              <option value="High">High</option>
              <option value="Critical">Critical</option>
              <option value="Medium">Medium</option>
              <option value="Low">Low</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="cg-label">Confidence Score (1-100)</label>
            <input type="number" min="1" max="100" name="confidence" class="cg-form-control" value="87">
          </div>
          <div class="col-md-4">
            <label class="cg-label">Location</label>
            <input name="location" class="cg-form-control" value="New Delhi">
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="cg-btn cg-btn-primary"><i class="fa-solid fa-paper-plane me-1"></i> Submit Ingest</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Source Settings Modal -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="cgGovConfigModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-700"><i class="fa-solid fa-sliders me-2 text-primary"></i>Source Settings &amp; Provenance Configuration</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs mb-3" role="tablist">
          <li class="nav-item">
            <button class="nav-link active fw-600" data-bs-toggle="tab" data-bs-target="#tabPublicSources"><i class="fa-solid fa-globe me-1"></i> Public Sources</button>
          </li>
          <li class="nav-item">
            <button class="nav-link fw-600" data-bs-toggle="tab" data-bs-target="#tabAuthSources"><i class="fa-solid fa-shield-halved me-1"></i> Authorized Sources</button>
          </li>
          <li class="nav-item">
            <button class="nav-link fw-600" data-bs-toggle="tab" data-bs-target="#tabSimSources"><i class="fa-solid fa-flask me-1"></i> Simulation Engine</button>
          </li>
        </ul>

        <div class="tab-content">
          <!-- Public Sources Tab -->
          <div class="tab-pane fade show active" id="tabPublicSources">
            <div class="alert alert-info py-2 small border-0 mb-3">
              <i class="fa-solid fa-circle-info me-1"></i> Public records must have valid, reachable URLs. Unverified records automatically fall back to <strong>SIMULATED DATA</strong>.
            </div>
            <div class="cg-card bg-light p-3 border mb-3">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="fw-700 m-0 text-dark">Public Record Register</h6>
                  <div class="small text-muted">Open public bulletin &amp; gazette register ingestion</div>
                </div>
                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Active</span>
              </div>
              <hr class="my-2">
              <div class="row g-2 small">
                <div class="col-md-6 text-muted">Last Fetch: <span class="fw-600 text-dark" id="cgLastPublicFetch">Just now</span></div>
                <div class="col-md-6 text-muted">Status: <span class="fw-600 text-success">Verified &amp; Active</span></div>
              </div>
            </div>
          </div>

          <!-- Authorized Sources Tab -->
          <div class="tab-pane fade" id="tabAuthSources">
            <div class="alert alert-primary py-2 small border-0 mb-3">
              <i class="fa-solid fa-shield-halved me-1"></i> Production gateway integration for authorized department feeds (CCTNS/ICJS). Requires department authentication credentials.
            </div>
            <form id="cgGovConfigForm">
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="cg-label">Authorized Gateway Endpoint URL</label>
                  <input name="endpoint" id="inputGovEndpoint" class="cg-form-control font-monospace" placeholder="https://api.icjs.gov.in/v1/dispatches">
                </div>
                <div class="col-md-4">
                  <label class="cg-label">Department Code</label>
                  <input name="department" id="inputGovDept" class="cg-form-control" placeholder="CID-INTEL-DIV">
                </div>
                <div class="col-md-8">
                  <label class="cg-label">OAuth Bearer Token / API Key</label>
                  <input type="password" name="api_key" id="inputGovApiKey" class="cg-form-control font-monospace" placeholder="••••••••••••••••">
                </div>
                <div class="col-md-4">
                  <label class="cg-label">Poll Interval (Mins)</label>
                  <input type="number" min="1" max="1440" name="sync_interval_mins" id="inputGovInterval" class="cg-form-control" value="15">
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="enabled" id="inputGovEnabled">
                    <label class="form-check-label fw-600 text-dark" for="inputGovEnabled">Enable Authorized Integration</label>
                  </div>
                </div>
                <div class="col-12 text-end">
                  <button type="submit" class="cg-btn cg-btn-primary cg-btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i> Save Gateway Settings</button>
                </div>
              </div>
            </form>
          </div>

          <!-- Simulation Tab -->
          <div class="tab-pane fade" id="tabSimSources">
            <div class="alert alert-warning py-2 small border-0 mb-3">
              <i class="fa-solid fa-flask me-1"></i> Simulation Engine generates synthetic demonstration events using generic non-personal identifiers (<code>Person A</code>, <code>Company Alpha</code>, <code>Account 001</code>).
            </div>
            <div class="cg-card bg-light p-3 border">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="fw-700 m-0 text-dark">CNI Demonstration Generator</h6>
                  <div class="small text-muted">Synthetic event synthesis with automatic entity extraction</div>
                </div>
                <span class="badge bg-warning text-dark"><i class="fa-solid fa-circle-play me-1"></i> Simulation Engine Ready</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
