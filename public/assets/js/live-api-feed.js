(function () {
  let activeFilters = {};
  let isSimActive = false;

  async function loadDashboardStatus() {
    try {
      const res = await cgApi('/api/intelligence/status.php');
      if (res.success && res.data) {
        const st = res.data.status || {};
        document.getElementById('cgMetricLiveEvents').textContent = st.events_received || 0;
        document.getElementById('cgMetricActiveNetworks').textContent = st.active_networks || 0;
        document.getElementById('cgMetricEntitiesIdentified').textContent = st.entities_identified || 0;
        document.getElementById('cgMetricRelsDiscovered').textContent = st.relationships_discovered || 0;
        document.getElementById('cgMetricHighRisk').textContent = st.high_risk_alerts || 0;

        isSimActive = !!st.simulation_active;
        updateSimBtnUI();

        if (st.last_event_timestamp) {
          document.getElementById('cgLastUpdateText').textContent = 'Last update: ' + new Date(st.last_event_timestamp).toLocaleTimeString();
        }
      }
    } catch (e) {
      console.error('Error loading intelligence status:', e);
    }
  }

  function updateSimBtnUI() {
    const btn = document.getElementById('cgToggleSimBtn');
    if (!btn) return;
    if (isSimActive) {
      btn.className = 'cg-btn cg-btn-outline border-danger text-danger';
      btn.innerHTML = '<i class="fa-solid fa-pause me-1"></i> Stop Simulation';
    } else {
      btn.className = 'cg-btn cg-btn-outline';
      btn.innerHTML = '<i class="fa-solid fa-play me-1 text-warning"></i> Start Simulation';
    }
  }

  function getSourceBadge(sourceType, sourceName) {
    const type = (sourceType || 'SIMULATION').toUpperCase();
    if (type === 'PUBLIC_RECORD') {
      return `<span class="badge bg-info bg-opacity-10 text-info border border-info px-2 py-1"><i class="fa-solid fa-globe me-1"></i> REAL DATA (${sourceName || 'PUBLIC RECORD'})</span>`;
    }
    if (type === 'AUTHORIZED_API') {
      return `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-2 py-1"><i class="fa-solid fa-shield-halved me-1"></i> AUTHORIZED SOURCE</span>`;
    }
    return `<span class="badge bg-warning bg-opacity-10 text-dark border border-warning px-2 py-1"><i class="fa-solid fa-flask me-1"></i> SIMULATED DATA</span>`;
  }

  function getSeverityClass(sev) {
    const s = (sev || 'Medium').toLowerCase();
    return 'priority-' + s;
  }

  async function loadFeed() {
    const queryParams = new URLSearchParams(activeFilters).toString();
    const url = `/api/intelligence/events.php?${queryParams}`;
    
    const tbody = document.getElementById('cgIntelFeedTableBody');
    try {
      const res = await cgApi(url);
      if (!res.success) {
        cgToast('Failed to load live intelligence feed.', 'error');
        return;
      }

      const events = res.data.events || [];
      if (!events.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">
          <i class="fa-solid fa-satellite-dish fs-3 d-block mb-2 text-secondary"></i>
          No intelligence events found matching your criteria.<br>
          Click <strong>Start Simulation</strong> or <strong>Poll Now</strong> to ingest live intelligence.
        </td></tr>`;
        return;
      }

      tbody.innerHTML = events.map(e => `
        <tr onclick="window.cgShowEventDetail(${e.id})" style="cursor:pointer" id="cgIntelRow-${e.id}">
          <td class="small text-muted font-monospace">${new Date(e.event_timestamp).toLocaleString()}</td>
          <td>${getSourceBadge(e.source_type, e.source_name)}</td>
          <td>
            <div class="fw-700 text-dark">${e.title}</div>
            <div class="small text-muted text-truncate" style="max-width:340px">${e.description || ''}</div>
          </td>
          <td class="small text-muted"><i class="fa-solid fa-location-dot me-1 text-danger"></i>${e.location || 'Unspecified'}</td>
          <td><span class="cg-priority ${getSeverityClass(e.severity)}">${e.severity}</span></td>
          <td class="fw-700 font-monospace text-primary">${e.confidence}%</td>
          <td onclick="event.stopPropagation()">
            <button onclick="window.cgShowEventDetail(${e.id})" class="cg-btn cg-btn-outline cg-btn-sm"><i class="fa-solid fa-eye me-1"></i> Details</button>
          </td>
        </tr>
      `).join('');

      await loadDashboardStatus();
    } catch (e) {
      console.error('Error loading feed:', e);
    }
  }

  // Global window handler for Event Details Modal
  window.cgShowEventDetail = async function (id) {
    try {
      const res = await cgApi(`/api/intelligence/events.php?id=${id}`);
      if (!res.success || !res.data) {
        cgToast('Could not fetch event details.', 'error');
        return;
      }

      const e = res.data;
      const modalBody = document.getElementById('cgModalEventBody');
      document.getElementById('cgModalEventTitle').textContent = e.title;

      let sourceBanner = '';
      const st = (e.source_type || 'SIMULATION').toUpperCase();
      if (st === 'SIMULATION') {
        sourceBanner = `<div class="alert alert-warning border-warning d-flex align-items-center gap-2 py-2 mb-3 fw-700 small">
          <i class="fa-solid fa-triangle-exclamation fs-5"></i> SIMULATED DATA — FOR DEMONSTRATION ONLY
        </div>`;
      } else if (st === 'PUBLIC_RECORD') {
        sourceBanner = `<div class="alert alert-info border-info d-flex align-items-center gap-2 py-2 mb-3 fw-700 small">
          <i class="fa-solid fa-globe fs-5"></i> PUBLICLY AVAILABLE SOURCE DATA
        </div>`;
      } else {
        sourceBanner = `<div class="alert alert-primary border-primary d-flex align-items-center gap-2 py-2 mb-3 fw-700 small">
          <i class="fa-solid fa-shield-halved fs-5"></i> AUTHORIZED GOVERNMENT SOURCE DATA
        </div>`;
      }

      const entities = e.entities || [];
      const rels = e.relationships || [];

      modalBody.innerHTML = `
        ${sourceBanner}
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <div class="small text-muted text-uppercase fw-700">Network Type</div>
            <div class="fw-700 text-primary">${e.event_type}</div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted text-uppercase fw-700">Severity & Confidence</div>
            <div><span class="cg-priority ${getSeverityClass(e.severity)}">${e.severity}</span> <span class="fw-700 font-monospace text-primary ms-1">${e.confidence}%</span></div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted text-uppercase fw-700">Location</div>
            <div class="fw-600 text-dark"><i class="fa-solid fa-location-dot me-1 text-danger"></i>${e.location || 'Unspecified'}</div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted text-uppercase fw-700">Source Name</div>
            <div class="fw-600 text-dark">${e.source_name}</div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted text-uppercase fw-700">Source URL</div>
            <div class="text-truncate">${e.source_url ? `<a href="${e.source_url}" target="_blank" class="text-primary">${e.source_url}</a>` : 'N/A'}</div>
          </div>
        </div>

        <div class="mb-3">
          <div class="small text-muted text-uppercase fw-700 mb-1">Event Narrative</div>
          <div class="p-3 bg-light rounded border text-dark">${e.description}</div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <h6 class="fw-700 text-dark"><i class="fa-solid fa-users me-1 text-primary"></i>Extracted Entities (${entities.length})</h6>
            ${!entities.length ? '<div class="small text-muted">No entities extracted</div>' : 
              '<ul class="list-group list-group-flush border rounded small">' +
              entities.map(ent => `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div><strong>${ent.name}</strong> <span class="badge bg-secondary ms-1">${ent.type || 'Entity'}</span></div>
                  <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Risk ${ent.risk || 75}</span>
                </li>
              `).join('') + '</ul>'
            }
          </div>
          <div class="col-md-6">
            <h6 class="fw-700 text-dark"><i class="fa-solid fa-diagram-project me-1 text-primary"></i>Discovered Relationships (${rels.length})</h6>
            ${!rels.length ? '<div class="small text-muted">No relationships extracted</div>' : 
              '<ul class="list-group list-group-flush border rounded small">' +
              rels.map(r => `
                <li class="list-group-item">
                  <strong>${r.source}</strong> <i class="fa-solid fa-arrow-right mx-1 text-primary"></i> <strong>${r.target}</strong>
                  <span class="badge bg-light text-dark border ms-1">${r.type || 'CONNECTED'}</span>
                </li>
              `).join('') + '</ul>'
            }
          </div>
        </div>
      `;

      const graphLink = document.getElementById('cgModalInspectGraphLink');
      if (graphLink) {
        graphLink.href = `${CG.baseUrl}/case-details.php?id=${e.master_case_id || 1}`;
      }

      const modalEl = document.getElementById('cgEventDetailModal');
      if (modalEl && window.bootstrap) {
        new bootstrap.Modal(modalEl).show();
      }
    } catch (e) {
      console.error('Error showing event details:', e);
    }
  };

  // Setup Event Listeners
  document.addEventListener('DOMContentLoaded', function () {
    // Filter Form
    const filterForm = document.getElementById('cgIntelFilterForm');
    if (filterForm) {
      filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const data = new FormData(filterForm);
        activeFilters = Object.fromEntries(data.entries());
        loadFeed();
      });
    }

    const resetBtn = document.getElementById('cgResetFiltersBtn');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        if (filterForm) filterForm.reset();
        activeFilters = {};
        loadFeed();
      });
    }

    // Toggle Simulation Button
    const simBtn = document.getElementById('cgToggleSimBtn');
    if (simBtn) {
      simBtn.addEventListener('click', async function () {
        this.disabled = true;
        try {
          const endpoint = isSimActive ? '/api/intelligence/simulation/stop.php' : '/api/intelligence/simulation/start.php';
          const res = await cgApi(endpoint, { method: 'POST' });
          if (res.success) {
            cgToast(res.message, 'success');
            await loadFeed();
          } else {
            cgToast(res.message || 'Simulation toggle failed.', 'error');
          }
        } catch (e) {
          cgToast('Error toggling simulation.', 'error');
        } finally {
          this.disabled = false;
        }
      });
    }

    // Manual Poll Button
    const pollBtn = document.getElementById('cgManualPollBtn');
    if (pollBtn) {
      pollBtn.addEventListener('click', async function () {
        this.disabled = true;
        try {
          const res = await cgApi('/api/government/sync.php', { method: 'POST' });
          if (res.success) {
            cgToast(res.message, 'success');
            await loadFeed();
          }
        } catch (e) {
          cgToast('Error polling intelligence.', 'error');
        } finally {
          this.disabled = false;
        }
      });
    }

    // Manual Ingest Form
    const ingestForm = document.getElementById('cgManualIngestForm');
    if (ingestForm) {
      ingestForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const payload = Object.fromEntries(new FormData(ingestForm).entries());

        const res = await cgApi('/api/intelligence/ingest.php', {
          method: 'POST',
          body: JSON.stringify(payload)
        });

        if (res.success) {
          cgToast('Intelligence event ingested successfully!', 'success');
          const modalEl = document.getElementById('cgIngestEventModal');
          if (modalEl && window.bootstrap) bootstrap.Modal.getInstance(modalEl)?.hide();
          ingestForm.reset();
          loadFeed();
        } else {
          cgToast(res.message || 'Ingestion failed.', 'error');
        }
      });
    }

    // Listen for Real-Time WebSocket Events
    if (window.CG && window.CG.ws) {
      window.CG.ws.on('INTELLIGENCE_EVENT_CREATED', function (data) {
        cgToast(`🚨 New Intelligence Event: ${data.title}`, 'info');
        loadFeed();
      });
    }

    loadFeed();
  });
})();
