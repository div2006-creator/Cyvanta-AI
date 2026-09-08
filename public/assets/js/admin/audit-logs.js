(function () {
  const search = document.getElementById('cgAuditSearch');
  const statusFilter = document.getElementById('cgAuditStatusFilter');

  async function load(page = 1) {
    const params = new URLSearchParams({ q: search.value.trim(), status: statusFilter.value, page });
    const data = await cgApi('/api/audit/list.php?' + params.toString());
    if (!data.success) return;
    document.getElementById('cgAuditTableBody').innerHTML = data.data.items.map(a => `
      <tr>
        <td>${a.user_name || 'System'}</td>
        <td class="fw-600 text-white">${a.action.replace(/_/g, ' ')}</td>
        <td class="text-muted">${a.module}</td>
        <td class="text-muted small">${a.target || '—'}</td>
        <td class="text-muted small font-monospace">${a.ip_address || '—'}</td>
        <td>${a.status === 'success' ? '<span class="cg-badge-status status-resolved">Success</span>' : '<span class="cg-badge-status status-critical">Failure</span>'}</td>
        <td class="text-muted small">${new Date(a.created_at).toLocaleString()}</td>
      </tr>`).join('');

    const pag = document.getElementById('cgAuditPagination');
    const p = data.data.pagination;
    let html = '';
    for (let i = 1; i <= p.total_pages; i++) html += `<button class="cg-btn cg-btn-sm ${i === p.page ? 'cg-btn-primary' : 'cg-btn-outline'}" data-page="${i}">${i}</button>`;
    pag.innerHTML = html;
    pag.querySelectorAll('button').forEach(b => b.addEventListener('click', () => load(+b.dataset.page)));
  }

  let t; search.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => load(1), 300); });
  statusFilter.addEventListener('change', () => load(1));
  load(1);
})();
