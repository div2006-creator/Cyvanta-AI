(function () {
  function statusClass(s) { return 'status-' + s.toLowerCase().replace(/\s+/g, '-'); }
  function priorityClass(p) { return 'priority-' + p.toLowerCase(); }

  async function load() {
    const data = await cgApi('/api/cases/list.php?page=1');
    if (!data.success) return;
    document.getElementById('cgAdminCasesBody').innerHTML = data.data.items.map(c => `
      <tr>
        <td class="font-monospace text-muted">${c.case_number}</td>
        <td class="fw-600 text-white">${c.title}</td>
        <td>
          <select class="cg-form-select cg-btn-sm" style="width:auto" onchange="cgAdminUpdateCase(${c.id}, 'status', this.value)">
            ${['New','Under Investigation','Intelligence Review','Critical','Resolved','Archived'].map(s => `<option ${s === c.status ? 'selected' : ''}>${s}</option>`).join('')}
          </select>
        </td>
        <td>
          <select class="cg-form-select cg-btn-sm" style="width:auto" onchange="cgAdminUpdateCase(${c.id}, 'priority', this.value)">
            ${['Low','Medium','High','Critical'].map(p => `<option ${p === c.priority ? 'selected' : ''}>${p}</option>`).join('')}
          </select>
        </td>
        <td>${c.investigator_name || 'Unassigned'}</td>
        <td class="text-muted small">${new Date(c.created_at).toLocaleDateString()}</td>
        <td class="d-flex gap-1">
          <button class="cg-btn cg-btn-outline cg-btn-sm" onclick="cgOpenAssign(${c.id})" title="Assign"><i class="fa-solid fa-user-plus"></i></button>
          <a href="${CG.baseUrl}/case-details.php?id=${c.id}" class="cg-btn cg-btn-outline cg-btn-sm" title="View"><i class="fa-solid fa-eye"></i></a>
        </td>
      </tr>`).join('');
  }

  window.cgAdminUpdateCase = async (id, field, value) => {
    const res = await cgApi('/api/cases/update.php', { method: 'POST', body: JSON.stringify({ id, [field]: value }) });
    cgToast(res.message, res.success ? 'success' : 'error');
  };

  window.cgOpenAssign = (caseId) => {
    document.querySelector('#cgAssignForm [name=case_id]').value = caseId;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('cgAssignModal')).show();
  };

  document.getElementById('cgAssignForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(this).entries());
    const res = await cgApi('/api/cases/assign.php', { method: 'POST', body: JSON.stringify(payload) });
    cgToast(res.message, res.success ? 'success' : 'error');
    bootstrap.Modal.getInstance(document.getElementById('cgAssignModal')).hide();
    load();
  });

  load();
})();
