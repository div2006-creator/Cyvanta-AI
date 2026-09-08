(function () {
  let currentPage = 1;
  const search = document.getElementById('cgCaseSearch');
  const fStatus = document.getElementById('cgFilterStatus');
  const fPriority = document.getElementById('cgFilterPriority');
  const fCategory = document.getElementById('cgFilterCategory');

  function statusClass(s) { return 'status-' + (s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
  function priorityClass(p) { return 'priority-' + p.toLowerCase(); }

  async function load(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({
      q: search.value.trim(), status: fStatus.value, priority: fPriority.value,
      category: fCategory.value, page,
    });
    const data = await cgApi('/api/cases/list.php?' + params.toString());
    if (!data.success) { cgToast('Failed to load cases.', 'error'); return; }
    renderCategories(data.data.categories);
    renderTable(data.data.items);
    renderPagination(data.data.pagination);
  }

  function renderCategories(cats) {
    if (fCategory.dataset.loaded) return;
    fCategory.innerHTML = '<option value="">All Categories</option>' + cats.map(c => `<option>${c}</option>`).join('');
    fCategory.dataset.loaded = '1';
  }

  function renderTable(items) {
    const tbody = document.getElementById('cgCaseTableBody');
    const cards = document.getElementById('cgCaseCards');
    const empty = document.getElementById('cgCaseEmpty');
    empty.hidden = items.length > 0;

    tbody.innerHTML = items.map(c => `
      <tr onclick="window.location='${CG.baseUrl}/case-details.php?id=${c.id}'" style="cursor:pointer">
        <td class="font-monospace text-muted">${c.case_number}</td>
        <td class="fw-600 text-white">${c.title}</td>
        <td>${c.category || '—'}</td>
        <td><span class="cg-priority ${priorityClass(c.priority)}">${c.priority}</span></td>
        <td><span class="cg-badge-status ${statusClass(c.status)}">${c.status}</span></td>
        <td>${c.investigator_name || 'Unassigned'}</td>
        <td class="text-muted">${new Date(c.created_at).toLocaleDateString()}</td>
        <td class="text-muted">${new Date(c.updated_at).toLocaleDateString()}</td>
        <td onclick="event.stopPropagation()">
          <a href="${CG.baseUrl}/case-details.php?id=${c.id}" class="cg-btn cg-btn-outline cg-btn-sm"><i class="fa-solid fa-eye"></i></a>
        </td>
      </tr>`).join('');

    cards.innerHTML = items.map(c => `
      <a href="${CG.baseUrl}/case-details.php?id=${c.id}" class="cg-card d-block mb-2 text-reset text-decoration-none">
        <div class="d-flex justify-content-between">
          <span class="font-monospace text-muted small">${c.case_number}</span>
          <span class="cg-priority ${priorityClass(c.priority)}">${c.priority}</span>
        </div>
        <div class="fw-700 text-white my-1">${c.title}</div>
        <div class="d-flex justify-content-between align-items-center">
          <span class="cg-badge-status ${statusClass(c.status)}">${c.status}</span>
          <span class="small text-muted">${c.investigator_name || 'Unassigned'}</span>
        </div>
      </a>`).join('');
  }

  function renderPagination(p) {
    const el = document.getElementById('cgCasePagination');
    if (p.total_pages <= 1) { el.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= p.total_pages; i++) {
      html += `<button class="cg-btn cg-btn-sm ${i === p.page ? 'cg-btn-primary' : 'cg-btn-outline'}" data-page="${i}">${i}</button>`;
    }
    el.innerHTML = html;
    el.querySelectorAll('button').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
  }

  let searchTimer;
  search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => load(1), 350); });
  [fStatus, fPriority, fCategory].forEach(el => el.addEventListener('change', () => load(1)));
  document.getElementById('cgResetFilters').addEventListener('click', () => {
    search.value = ''; fStatus.value = ''; fPriority.value = ''; fCategory.value = ''; load(1);
  });

  const caseForm = document.getElementById('cgCaseForm');
  if (caseForm) {
    caseForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(caseForm).entries());
      const res = await cgApi('/api/cases/create.php', { method: 'POST', body: JSON.stringify(payload) });
      if (res.success) {
        cgToast(res.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('cgCaseModal')).hide();
        caseForm.reset();
        load(1);
      } else {
        cgToast(res.message, 'error');
      }
    });
  }

  async function loadUsers() {
    const sel = document.getElementById('cgLeadInvestigatorSelect');
    if (!sel) return;
    const res = await cgApi('/api/users/list.php');
    if (res.success && res.data.items) {
      sel.innerHTML = '<option value="">Select Lead Investigator...</option>' + 
        res.data.items.map(u => `<option value="${u.id}">${u.full_name} (${u.role.replace(/_/g,' ')}) — ${u.department || 'General'}</option>`).join('');
    }
  }

  loadUsers();
  load(1);
})();
