(function () {
  let activeFilters = {};
  let isSimActive = false;
  let isWsConnected = false;

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
          document.getElementById('cgLastSyncTimeLabel').textContent = 'Last sync: ' + new Date(st.last_event_timestamp).toLocaleTimeString();
        }

        // Update settings modal info if available
        const sources = st.sources || [];
        const pr = sources.find(s => s.type === 'PUBLIC_RECORD');
        if (pr && document.getElementById('cgLastPublicFetch')) {
          document.getElementById('cgLastPublicFetch').textContent = pr.last_fetch;
        }

        const auth = sources.find(s => s.type === 'AUTHORIZED_API');
        const elEn = document.getElementById('inputGovEnabled');
        if (elEn && auth) {
          elEn.checked = !!auth.available;
        }
        const elEp = document.getElementById('inputGovEndpoint');
        if (elEp && auth && auth.endpoint !== 'Not Configured') {
          elEp.value = auth.endpoint || '';
        }
      }
    } catch (e) {
      console.error('Error loading intelligence status:', e);
    }
  }

  function updateSimBtnUI() {
    const btn = document.getElementById('cgToggleSimBtn');
    const simBadge = document.getElementById('cgSimStatusBadge');

    if (btn) {
      if (isSimActive) {
        btn.className = 'cg-btn cg-btn-outline border-danger text-danger';
        btn.innerHTML = '<i class="fa-solid fa-pause me-1"></i> Stop Simulation';
      } else {
        btn.className = 'cg-btn cg-btn-outline';
        btn.innerHTML = '<i class="fa-solid fa-play me-1 text-warning"></i> Start Simulation';
      }
    }

    if (simBadge) {
      simBadge.style.display = isSimActive ? 'inline-flex' : 'none';
    }
  }

  function updateTransportUI(connected) {
    isWsConnected = connected;
    const wsBadge = document.getElementById('cgWsConnectionBadge');
    const wsStateLabel = document.getElementById('cgWsStateLabel');
    const pollStateLabel = document.getElementById('cgPollStateLabel');

    if (connected) {
      if (wsBadge) {
        wsBadge.className = 'badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 fw-700 d-flex align-items-center gap-2';
        wsBadge.innerHTML = '<span class="spinner-grow spinner-grow-sm text-success" role="status"></span> SYSTEM ONLINE — WEBSOCKET CONNECTED';
      }
      if (wsStateLabel) { wsStateLabel.className = 'fw-700 text-success'; wsStateLabel.textContent = 'Connected'; }
      if (pollStateLabel) { pollStateLabel.className = 'fw-700 text-muted'; pollStateLabel.textContent = 'Disabled'; }
    } else {
      if (wsBadge) {
        wsBadge.className = 'badge bg-danger bg-opacity-10 text-danger border border-danger px-3 py-2 fw-700 d-flex align-items-center gap-2';
        wsBadge.innerHTML = '<i class="fa-solid fa-plug-circle-xmark me-1"></i> OFFLINE — RECONNECTING';
      }
      if (wsStateLabel) { wsStateLabel.className = 'fw-700 text-danger'; wsStateLabel.textContent = 'Disconnected'; }
      if (pollStateLabel) { pollStateLabel.className = 'fw-700 text-warning'; pollStateLabel.textContent = 'Active (Fallback)'; }
    }
  }

  function renderProvenanceCell(e) {
    const st = (e.source_type || 'SIMULATION').toUpperCase();
    const isVerified = !!e.is_verified;

    if (st === 'PUBLIC_RECORD' && isVerified && e.source_url) {
      return `
        <div>
          <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1"><i class="fa-solid fa-check-circle me-1"></i> REAL DATA</span>
          <div class="small fw-700 text-dark mt-1">Public Record</div>
          <div class="small text-muted">Source: ${e.source_name || 'Public Register'}</div>
          <div class="small text-success fw-600"><i class="fa-solid fa-circle-check me-1"></i> Verified: &#10003; (HTTP ${e.fetched_http_status || 200})</div>
          <a href="${e.source_url}" target="_blank" class="small text-primary fw-600 d-inline-block mt-1" onclick="event.stopPropagation()">View Source &rarr;</a>
        </div>
      `;
    }

    if (st === 'AUTHORIZED_API') {
      if (isVerified) {
        return `
          <div>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-2 py-1"><i class="fa-solid fa-shield-halved me-1"></i> AUTHORIZED SOURCE</span>
            <div class="small fw-700 text-dark mt-1">${e.source_name || 'Department Gateway'}</div>
            <div class="small text-primary fw-600"><i class="fa-solid fa-circle-check me-1"></i> Verified: &#10003;</div>
            ${e.source_url ? `<a href="${e.source_url}" target="_blank" class="small text-primary fw-600 d-inline-block mt-1" onclick="event.stopPropagation()">View Source &rarr;</a>` : ''}
          </div>
        `;
      } else {
        return `
          <div>
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-2 py-1"><i class="fa-solid fa-lock me-1"></i> AUTHORIZED SOURCE — Not Configured</span>
            <div class="small text-muted mt-1">Unconfigured Department Gateway</div>
          </div>
        `;
      }
    }

    // Default to SIMULATION / Unverified
    return `
      <div>
        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning px-2 py-1"><i class="fa-solid fa-flask me-1"></i> SIMULATED DATA</span>
        <div class="small fw-700 text-dark mt-1">CNI Demonstration Generator</div>
        <div class="small text-muted">Synthetic Event</div>
      </div>
    `;
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
          Click <strong>Start Simulation</strong> or <strong>SYNC NOW</strong> to ingest live intelligence.
        </td></tr>`;
        return;
      }

      tbody.innerHTML = events.map(e => `
        <tr onclick="window.cgShowEventDetail(${e.id})" style="cursor:pointer" id="cgIntelRow-${e.id}">
          <td class="small text-muted font-monospace">${new Date(e.event_timestamp).toLocaleString()}</td>
          <td>${renderProvenanceCell(e)}</td>
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
      const isVerified = !!e.is_verified;

      if (st === 'PUBLIC_RECORD' && isVerified && e.source_url) {
        sourceBanner = `<div class="alert alert-success border-success d-flex align-items-center justify-content-between py-2 mb-3 fw-700 small">
          <div><i class="fa-solid fa-circle-check fs-5 me-2"></i> REAL DATA — LEGITIMATE PUBLIC SOURCE VERIFIED (HTTP ${e.fetched_http_status || 200})</div>
          <a href="${e.source_url}" target="_blank" class="btn btn-sm btn-success text-white font-monospace">View Source &rarr;</a>
        </div>`;
      } else if (st === 'AUTHORIZED_API' && isVerified) {
        sourceBanner = `<div class="alert alert-primary border-primary d-flex align-items-center justify-content-between py-2 mb-3 fw-700 small">
          <div><i class="fa-solid fa-shield-halved fs-5 me-2"></i> AUTHORIZED GOVERNMENT SOURCE DISPATCH</div>
          ${e.source_url ? `<a href="${e.source_url}" target="_blank" class="btn btn-sm btn-primary text-white font-monospace">View Gateway &rarr;</a>` : ''}
        </div>`;
      } else if (st === 'AUTHORIZED_API' && !isVerified) {
        sourceBanner = `<div class="alert alert-secondary border-secondary d-flex align-items-center py-2 mb-3 fw-700 small">
          <i class="fa-solid fa-lock fs-5 me-2"></i> AUTHORIZED SOURCE — NOT CONFIGURED
        </div>`;
      } else {
        sourceBanner = `<div class="alert alert-warning border-warning d-flex align-items-center gap-2 py-2 mb-3 fw-700 small">
          <i class="fa-solid fa-flask fs-5"></i> SIMULATED DATA — FOR DEMONSTRATION ONLY
        </div>`;
      }

      const entities = e.entities || [];
      const rels = e.relationships || [];

      modalBody.innerHTML = `
        ${sourceBanner}
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <div class="small text-muted text-uppercase fw-700">Network Type</div>
            <div class="fw-700 text-primary">${e.event_type}</div>
          </div>
          <div class="col-md-3">
            <div class="small text-muted text-uppercase fw-700">Source Type</div>
            <div class="fw-700 text-dark">${e.source_type}</div>
          </div>
          <div class="col-md-3">
            <div class="small text-muted text-uppercase fw-700">Verification Method</div>
            <div class="fw-600 font-monospace text-dark">${e.verification_method || 'SIMULATION'}</div>
          </div>
          <div class="col-md-3">
            <div class="small text-muted text-uppercase fw-700">HTTP Fetch Status</div>
            <div class="fw-600 font-monospace ${e.fetched_http_status === 200 ? 'text-success' : 'text-muted'}">${e.fetched_http_status ? e.fetched_http_status + ' OK' : 'N/A'}</div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted text-uppercase fw-700">Source Name &amp; ID</div>
            <div class="fw-600 text-dark">${e.source_name} <span class="font-monospace text-muted small">(${e.source_id || 'SIM-GEN'})</span></div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted text-uppercase fw-700">Source URL</div>
            <div class="text-truncate">${e.source_url ? `<a href="${e.source_url}" target="_blank" class="text-primary fw-600">${e.source_url}</a>` : '<span class="text-muted font-monospace">N/A (Synthetic / None)</span>'}</div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted text-uppercase fw-700">Fetched Timestamp</div>
            <div class="small text-dark font-monospace">${e.source_fetched_at ? new Date(e.source_fetched_at).toLocaleString() : 'N/A'}</div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted text-uppercase fw-700">Event Timestamp</div>
            <div class="small text-dark font-monospace">${new Date(e.event_timestamp).toLocaleString()}</div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted text-uppercase fw-700">Severity &amp; Confidence</div>
            <div><span class="cg-priority ${getSeverityClass(e.severity)}">${e.severity}</span> <span class="fw-700 font-monospace text-primary ms-1">${e.confidence}%</span></div>
          </div>
        </div>

        <div class="mb-3">
          <div class="small text-muted text-uppercase fw-700 mb-1">Event Telemetry Narrative</div>
          <div class="p-3 bg-light rounded border text-dark font-monospace small">${e.description}</div>
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
        activeFilters.verified_only = filterForm.querySelector('[name=verified_only]').checked ? 1 : '';
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

    // SYNC NOW Button
    const syncBtn = document.getElementById('cgManualSyncBtn');
    if (syncBtn) {
      syncBtn.addEventListener('click', async function () {
        this.disabled = true;
        const originalHtml = this.innerHTML;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Syncing…';
        try {
          const res = await cgApi('/api/government/sync.php', { method: 'POST' });
          if (res.success) {
            cgToast(res.message, 'success');
            await loadFeed();
          }
        } catch (e) {
          cgToast('Error syncing intelligence.', 'error');
        } finally {
          this.disabled = false;
          this.innerHTML = originalHtml;
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

    // Save Source Settings Form
    const cfgForm = document.getElementById('cgGovConfigForm');
    if (cfgForm) {
      cfgForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const payload = Object.fromEntries(new FormData(cfgForm).entries());
        payload.action = 'update_config';
        payload.enabled = cfgForm.querySelector('[name=enabled]').checked;

        const res = await cgApi('/api/government/sync.php', { method: 'POST', body: JSON.stringify(payload) });
        if (res.success) {
          cgToast(res.message, 'success');
          const modalEl = document.getElementById('cgGovConfigModal');
          if (modalEl && window.bootstrap) bootstrap.Modal.getInstance(modalEl)?.hide();
          loadFeed();
        } else {
          cgToast(res.message || 'Failed to update settings.', 'error');
        }
      });
    }

    // Initialize WebSocket Transport Indicators
    updateTransportUI(true);

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
