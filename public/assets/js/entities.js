(function () {
  const search = document.getElementById('cgEntitySearch');
  const typeFilter = document.getElementById('cgEntityTypeFilter');

  async function load() {
    const params = new URLSearchParams({ q: search.value.trim(), type: typeFilter.value });
    const data = await cgApi('/api/entities/global-list.php?' + params.toString());
    if (!data.success) return;
    if (!typeFilter.dataset.loaded) {
      typeFilter.innerHTML = '<option value="">All Types</option>' + data.data.types.map(t => `<option>${t}</option>`).join('');
      typeFilter.dataset.loaded = '1';
    }
    const tbody = document.getElementById('cgGlobalEntitiesBody');
    const empty = document.getElementById('cgGlobalEntitiesEmpty');
    empty.hidden = data.data.items.length > 0;
    tbody.innerHTML = data.data.items.map(e => {
      const rInfo = (window.cgFormatRisk ? window.cgFormatRisk(e.risk_score, e.type_name, e.name, e.description) : { isEligible: false, scoreText: 'N/A', badgeClass: 'priority-low bg-secondary text-white' });
      return `
      <tr onclick="window.location='${CG.baseUrl}/case-details.php?id=${e.case_id}#network'" style="cursor:pointer">
        <td class="fw-600 text-white">${e.name}</td>
        <td>${e.type_name}</td>
        <td class="text-muted small">${e.case_number} — ${e.case_title}</td>
        <td><span class="cg-priority ${rInfo.badgeClass}">${rInfo.scoreText}</span></td>
        <td>${e.connections}</td>
      </tr>`;
    }).join('');
  }
  let t; search.addEventListener('input', () => { clearTimeout(t); t = setTimeout(load, 300); });
  typeFilter.addEventListener('change', load);
  load();
})();
