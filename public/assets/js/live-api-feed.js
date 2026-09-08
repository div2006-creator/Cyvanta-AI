(function () {
  async function loadFeed() {
    const res = await cgApi('/api/government/status.php');
    if (!res.success) {
      cgToast('Failed to load Government API status.', 'error');
      return;
    }

    const cfg = res.data.config || {};
    const cases = res.data.recent_cases || [];

    // Render connection metrics
    document.getElementById('cgGovApiEndpoint').textContent = cfg.endpoint || 'Not configured';
    document.getElementById('cgGovApiLastSync').textContent = cfg.last_sync ? new Date(cfg.last_sync).toLocaleString() : 'Never';
    document.getElementById('cgGovApiTotalIngested').textContent = cfg.total_ingested || cases.length;
    document.getElementById('cgLiveCasesCount').textContent = `${cases.length} Ingested Dispatches`;

    // Render settings input if available
    const elEp = document.getElementById('inputGovEndpoint');
    if (elEp) elEp.value = cfg.endpoint || '';
    const elDept = document.getElementById('inputGovDept');
    if (elDept) elDept.value = cfg.department || '';
    const elKey = document.getElementById('inputGovApiKey');
    if (elKey) elKey.value = cfg.api_key || '';
    const elInt = document.getElementById('inputGovInterval');
    if (elInt) elInt.value = cfg.sync_interval_mins || 15;
    const elEn = document.getElementById('inputGovEnabled');
    if (elEn) elEn.checked = !!cfg.enabled;

    // Render Cases Table
    const tbody = document.getElementById('cgGovCasesTableBody');
    if (!cases.length) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">
        <i class="fa-solid fa-cloud-arrow-down fs-3 d-block mb-2"></i>
        No live government cases ingested yet. Click <strong>Sync Live Feed Now</strong> to trigger automatic API polling.
      </td></tr>`;
      return;
    }

    function statusClass(s) { return 'status-' + (s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
    function priorityClass(p) { return 'priority-' + (p || '').toLowerCase(); }

    tbody.innerHTML = cases.map(c => `
      <tr onclick="window.location='${CG.baseUrl}/case-details.php?id=${c.id}'" style="cursor:pointer">
        <td class="font-monospace text-primary fw-700">${c.case_number}</td>
        <td>
          <div class="fw-700 text-dark">${c.title}</div>
          <div class="small text-muted text-truncate" style="max-width:320px">${c.description || ''}</div>
        </td>
        <td><span class="badge bg-secondary">${c.category || 'Organized Crime'}</span></td>
        <td class="small text-muted">${c.location || '—'}</td>
        <td><span class="cg-priority ${priorityClass(c.priority)}">${c.priority}</span></td>
        <td><span class="cg-badge-status ${statusClass(c.status)}">${c.status}</span></td>
        <td class="small text-muted">${new Date(c.created_at).toLocaleString()}</td>
        <td onclick="event.stopPropagation()">
          <a href="${CG.baseUrl}/case-details.php?id=${c.id}" class="cg-btn cg-btn-outline cg-btn-sm"><i class="fa-solid fa-folder-open me-1"></i> Inspect</a>
        </td>
      </tr>
    `).join('');
  }

  // Trigger manual sync button
  const syncBtn = document.getElementById('cgTriggerGovSyncBtn');
  if (syncBtn) {
    syncBtn.addEventListener('click', async function () {
      this.disabled = true;
      const originalHtml = this.innerHTML;
      this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Contacting Gov API…';

      try {
        const res = await cgApi('/api/government/sync.php', { method: 'POST' });
        if (res.success) {
          cgToast(res.message, 'success');
          await loadFeed();
        } else {
          cgToast(res.message || 'API Sync failed.', 'error');
        }
      } catch (e) {
        cgToast('Unable to reach Government API server.', 'error');
      } finally {
        this.disabled = false;
        this.innerHTML = originalHtml;
      }
    });
  }

  // Save config form
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

  loadFeed();
})();
