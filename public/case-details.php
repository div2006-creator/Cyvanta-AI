<?php
require_once __DIR__ . '/../includes/bootstrap.php';
cg_require_login();

$caseId = (int) ($_GET['id'] ?? 0);
$pdo = Database::connect();
$stmt = $pdo->prepare('SELECT c.*, u.full_name AS investigator_name FROM cases c LEFT JOIN users u ON u.id = c.lead_investigator_id WHERE c.id = ?');
$stmt->execute([$caseId]);
$case = $stmt->fetch();
if (!$case) {
    header('Location: cases.php');
    exit;
}

$pageTitle = $case['case_number'];
$activeNav = 'cases';
$pageScripts = ['case-details.js'];
require __DIR__ . '/../includes/partials/header.php';

function statusClass($s) { return 'status-' . strtolower(str_replace(' ', '-', $s)); }
function priorityClass($p) { return 'priority-' . strtolower($p); }
?>

<div class="cg-card mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="font-monospace text-muted"><?= htmlspecialchars($case['case_number']) ?></span>
        <span class="cg-badge-status <?= statusClass($case['status']) ?>"><?= htmlspecialchars($case['status']) ?></span>
        <span class="cg-priority <?= priorityClass($case['priority']) ?>"><?= htmlspecialchars($case['priority']) ?></span>
      </div>
      <h4 class="fw-800 mt-2 mb-1"><?= htmlspecialchars($case['title']) ?></h4>
      <div class="small text-muted d-flex align-items-center gap-2 flex-wrap mt-1">
        <span><i class="fa-solid fa-user-shield me-1 text-primary"></i>Lead Investigator: <strong id="cgLeadInvestigatorName"><?= htmlspecialchars($case['investigator_name'] ?? 'Unassigned') ?></strong></span>
        <button class="btn btn-sm btn-link p-0 text-decoration-none text-primary fw-600" data-bs-toggle="modal" data-bs-target="#cgAssignModal"><i class="fa-solid fa-user-pen"></i> Change / Assign</button>
        <span>&nbsp;·&nbsp; <i class="fa-solid fa-calendar"></i> Created <?= date('d M Y', strtotime($case['created_at'])) ?></span>
      </div>
    </div>
    <select id="cgCaseStatusSelect" class="cg-form-select" style="width:auto">
      <?php foreach (['New','Under Investigation','Intelligence Review','Critical','Resolved','Archived'] as $s): ?>
        <option <?= $s === $case['status'] ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="cg-tabs" id="cgTabs">
  <div class="cg-tab active" data-tab="overview">Overview</div>
  <div class="cg-tab" data-tab="network">Network</div>
  <div class="cg-tab" data-tab="entities">Entities</div>
  <div class="cg-tab" data-tab="documents">Documents</div>
  <div class="cg-tab" data-tab="osint"><i class="fa-solid fa-globe me-1 text-info"></i> Live OSINT</div>
  <div class="cg-tab" data-tab="evidence">Evidence</div>
  <div class="cg-tab" data-tab="timeline">Timeline</div>
  <div class="cg-tab" data-tab="analysis">Analysis</div>
  <div class="cg-tab" data-tab="notes">Notes</div>
  <div class="cg-tab" data-tab="activity">Activity</div>
  <div class="cg-tab" data-tab="reports">Reports</div>
</div>

<div id="tab-overview" class="cg-tab-panel">
  <div id="cgCrossCaseAlerts" class="mb-3"></div>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="cg-card">
        <h6 class="fw-700 mb-2">Case Description & Agency Reference</h6>
        <p class="text-muted mb-3"><?= nl2br(htmlspecialchars($case['description'] ?: 'No description provided.')) ?></p>
        <div class="row g-3 small">
          <div class="col-6"><div class="text-muted">Category</div><div class="text-white fw-600"><?= htmlspecialchars($case['category'] ?: '—') ?></div></div>
          <div class="col-6"><div class="text-muted">Location</div><div class="text-white fw-600"><?= htmlspecialchars($case['location'] ?: '—') ?></div></div>
          <div class="col-6"><div class="text-muted">Incident Date</div><div class="text-white fw-600"><?= $case['incident_date'] ? date('d M Y', strtotime($case['incident_date'])) : '—' ?></div></div>
          <div class="col-6"><div class="text-muted">Tags</div><div class="text-white fw-600"><?= htmlspecialchars($case['tags'] ?: '—') ?></div></div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="cg-card">
        <h6 class="fw-700 mb-3">Case Snapshot</h6>
        <div id="cgCaseSnapshot" class="small text-muted">Loading…</div>
      </div>
    </div>
  </div>
</div>

<div id="tab-network" class="cg-tab-panel" hidden>
  <div class="cg-graph-toolbar">
    <input type="text" id="cgGraphSearch" class="cg-form-control cg-btn-sm" style="width:180px" placeholder="Search node...">
    <select id="cgGraphTypeFilter" class="cg-form-select cg-btn-sm" style="width:160px"><option value="">All Types</option></select>
    <button class="cg-btn cg-btn-outline cg-btn-sm" id="cgGraphReset"><i class="fa-solid fa-arrows-rotate"></i></button>
    <button class="cg-btn cg-btn-outline cg-btn-sm" id="cgGraphFullscreen"><i class="fa-solid fa-expand"></i></button>
  </div>
  <div class="cg-graph-wrap" style="height:560px" id="cgGraphWrap">
    <div id="cgNetworkGraph" style="height:100%"></div>
    <div class="cg-graph-panel" id="cgGraphSidePanel"></div>
  </div>
</div>

<div id="tab-entities" class="cg-tab-panel" hidden>
  <div class="cg-card p-0 overflow-hidden">
    <table class="cg-table mb-0">
      <thead><tr><th>Name</th><th>Type</th><th>Risk</th><th>Connections</th><th>Description</th></tr></thead>
      <tbody id="cgEntitiesTableBody"></tbody>
    </table>
    <div id="cgEntitiesEmpty" class="cg-empty-state" hidden><i class="fa-solid fa-users-viewfinder"></i>No entities extracted yet. Upload and process a document to begin.</div>
  </div>
</div>

<div id="tab-documents" class="cg-tab-panel" hidden>
  <div class="d-flex justify-content-end mb-3">
    <button class="cg-btn cg-btn-primary" data-bs-toggle="modal" data-bs-target="#cgDocModal"><i class="fa-solid fa-upload"></i> Upload Document</button>
  </div>
  <div id="cgDocumentsList"></div>
  <div id="cgDocumentsEmpty" class="cg-empty-state" hidden><i class="fa-solid fa-file-shield"></i>No documents uploaded yet.</div>
</div>

<div id="tab-osint" class="cg-tab-panel" hidden>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="small text-muted"><i class="fa-solid fa-satellite-dish text-info me-1"></i> Live Open-Source Intelligence & Public Alert Feed</div>
    <button class="cg-btn cg-btn-primary" data-bs-toggle="modal" data-bs-target="#cgOsintModal"><i class="fa-solid fa-plus me-1"></i> Ingest OSINT Update</button>
  </div>
  <div id="cgOsintFeedList"></div>
  <div id="cgOsintEmpty" class="cg-empty-state" hidden><i class="fa-solid fa-globe"></i>No open-source intelligence feeds ingested yet. Ingest web alerts to monitor active unsolved cases in real time.</div>
</div>
  <div class="d-flex justify-content-end mb-3">
    <button class="cg-btn cg-btn-primary" data-bs-toggle="modal" data-bs-target="#cgEvidenceModal"><i class="fa-solid fa-plus"></i> Add Evidence</button>
  </div>
  <div id="cgEvidenceList"></div>
  <div id="cgEvidenceEmpty" class="cg-empty-state" hidden><i class="fa-solid fa-box-archive"></i>No evidence recorded yet.</div>
</div>

<div id="tab-timeline" class="cg-tab-panel" hidden>
  <div class="cg-card"><div id="cgTimeline">Loading timeline…</div></div>
</div>

<div id="tab-analysis" class="cg-tab-panel" hidden>
  <div class="cg-disclaimer mb-3">
    <i class="fa-solid fa-circle-info mt-1"></i>
    <div>Analytical indicators are intelligence-support signals and should not be interpreted as proof of criminal activity.</div>
  </div>
  <div class="d-flex justify-content-end mb-3">
    <button class="cg-btn cg-btn-primary" id="cgRunAnalysisBtn"><i class="fa-solid fa-brain"></i> Run Pattern Analysis</button>
  </div>
  <div id="cgAnalysisResults"></div>
</div>

<div id="tab-notes" class="cg-tab-panel" hidden>
  <form id="cgNoteForm" class="cg-card mb-3">
    <div class="row g-2">
      <div class="col-md-4"><input required name="title" class="cg-form-control" placeholder="Note title"></div>
      <div class="col-md-6"><input required name="note" class="cg-form-control" placeholder="Write a note..."></div>
      <div class="col-md-2"><button class="cg-btn cg-btn-primary w-100">Add Note</button></div>
    </div>
  </form>
  <div id="cgNotesList"></div>
</div>

<div id="tab-activity" class="cg-tab-panel" hidden>
  <div class="cg-card"><div id="cgCaseActivity">Loading activity…</div></div>
</div>

<div id="tab-reports" class="cg-tab-panel" hidden>
  <div id="cgReportContainer"></div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="cgDocModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0"><h5 class="modal-title fw-700">Upload Document</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <form id="cgDocForm" enctype="multipart/form-data">
        <div class="modal-body row g-3">
          <div class="col-12"><label class="cg-label">Document Name</label><input required name="name" class="cg-form-control"></div>
          <div class="col-md-6"><label class="cg-label">Source</label><input name="source" class="cg-form-control" placeholder="e.g. Field report"></div>
          <div class="col-md-6">
            <label class="cg-label">Confidentiality</label>
            <select name="confidentiality" class="cg-form-select"><option>Public</option><option selected>Internal</option><option>Restricted</option><option>Classified</option></select>
          </div>
          <div class="col-12"><label class="cg-label">Description</label><textarea name="description" rows="2" class="cg-form-control"></textarea></div>
          <div class="col-12"><label class="cg-label">File (.txt, .csv, .pdf, .doc, .docx, .jpg, .png, .mp4, .mov)</label><input required type="file" name="file" class="cg-form-control" accept=".txt,.csv,.pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.tiff,.bmp,.mp4,.avi,.mov,.mkv,.webm"></div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="cg-btn cg-btn-primary">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Evidence Modal -->
<div class="modal fade" id="cgEvidenceModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0"><h5 class="modal-title fw-700">Add Evidence</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <form id="cgEvidenceForm">
        <div class="modal-body row g-3">
          <div class="col-md-6"><label class="cg-label">Evidence Type</label><input required name="evidence_type" class="cg-form-control" placeholder="e.g. Physical, Digital"></div>
          <div class="col-md-6"><label class="cg-label">Date Collected</label><input type="date" name="collected_date" class="cg-form-control"></div>
          <div class="col-12"><label class="cg-label">Description</label><textarea name="description" rows="2" class="cg-form-control"></textarea></div>
          <div class="col-md-6"><label class="cg-label">Source</label><input name="source" class="cg-form-control"></div>
          <div class="col-md-6">
            <label class="cg-label">Confidentiality</label>
            <select name="confidentiality" class="cg-form-select"><option>Public</option><option selected>Internal</option><option>Restricted</option><option>Classified</option></select>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="cg-btn cg-btn-primary">Save Evidence</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Document Processing Progress Modal -->
<div class="modal fade" id="cgProcessModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-body p-4">
        <h6 class="fw-700 mb-3"><i class="fa-solid fa-gears me-1" style="color:var(--cg-accent)"></i> Processing Document…</h6>
        <div class="progress mb-3" style="height:8px;background:var(--cg-bg-alt)">
          <div class="progress-bar" id="cgProcessBar" style="width:0%;background:var(--cg-accent)"></div>
        </div>
        <div id="cgProcessSteps" class="small"></div>
      </div>
    </div>
  </div>
</div>

<!-- Assign Case Modal -->
<div class="modal fade" id="cgAssignModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0"><h5 class="modal-title fw-700"><i class="fa-solid fa-user-gear me-2 text-primary"></i>Assign Case Investigator</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="cgAssignForm">
        <div class="modal-body row g-3">
          <div class="col-12">
            <label class="cg-label">Select Lead Investigator</label>
            <select name="user_id" id="cgAssignUserSelect" class="cg-form-select" required>
              <option value="">Loading active personnel...</option>
            </select>
          </div>
          <div class="col-12">
            <div class="small text-muted"><i class="fa-solid fa-circle-info me-1"></i>Assigning a lead investigator updates case ownership, sends a notification, and records an official audit event.</div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="cg-btn cg-btn-primary"><i class="fa-solid fa-check me-1"></i> Save Assignment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Ingest OSINT Modal -->
<div class="modal fade" id="cgOsintModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--cg-panel);border:1px solid var(--cg-border-soft);color:var(--cg-text)">
      <div class="modal-header border-0"><h5 class="modal-title fw-700"><i class="fa-solid fa-globe me-2 text-info"></i>Ingest Live OSINT Web Intelligence Feed</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <form id="cgOsintForm">
        <div class="modal-body row g-3">
          <div class="col-md-6"><label class="cg-label">Intelligence Source / News Agency</label><input required name="source_name" class="cg-form-control" placeholder="e.g. National Crime Intelligence Bureau"></div>
          <div class="col-md-6"><label class="cg-label">Article / Report URL</label><input name="url" class="cg-form-control" placeholder="https://..."></div>
          <div class="col-12"><label class="cg-label">Intelligence Headline / Title</label><input required name="title" class="cg-form-control" placeholder="e.g. Smuggling Logistics Operations Flagged in Andheri"></div>
          <div class="col-12"><label class="cg-label">Full Article / Intelligence Text</label><textarea required name="content" rows="4" class="cg-form-control" placeholder="Paste news report, alert text, or intelligence update..."></textarea></div>
          <div class="col-12"><div class="small text-muted"><i class="fa-solid fa-circle-info me-1"></i>Ingesting an OSINT feed automatically extracts entities and triggers cross-case suspect linking.</div></div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="cg-btn cg-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="cg-btn cg-btn-primary"><i class="fa-solid fa-brain me-1"></i> Ingest & Run AI Extraction</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>window.CG_CASE_ID = <?= (int) $caseId ?>;</script>
<?php require __DIR__ . '/../includes/partials/footer.php'; ?>
