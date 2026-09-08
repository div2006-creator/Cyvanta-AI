(async function () {
  const data = await cgApi('/api/analysis/overview.php');
  if (!data.success) return;
  const d = data.data;

  document.getElementById('cgAnalysisCards').innerHTML = [
    ['AI Analyses', d.ai_analyses, 'fa-brain'],
    ['Entities Found', d.entities_found, 'fa-users-viewfinder'],
    ['Relationships', d.relationships_found, 'fa-circle-nodes'],
    ['Network Clusters', d.network_clusters, 'fa-chart-network'],
  ].map(([label, val, icon]) => `
    <div class="col-6 col-lg-3">
      <div class="cg-card cg-metric-card">
        <div class="cg-metric-icon"><i class="fa-solid ${icon}"></i></div>
        <div class="cg-metric-value">${val.toLocaleString()}</div>
        <div class="cg-metric-label">${label}</div>
      </div>
    </div>`).join('');

  const el = document.getElementById('cgRecentPatterns');
  el.innerHTML = d.recent_patterns.length
    ? d.recent_patterns.map(p => `
      <div class="cg-card mb-2">
        <div class="d-flex justify-content-between flex-wrap gap-2">
          <div>
            <div class="fw-700 text-white">${p.pattern_type}</div>
            <div class="small text-muted">${p.case_number} — ${p.title}</div>
          </div>
          <span class="cg-priority ${p.confidence >= 80 ? 'priority-critical' : p.confidence >= 60 ? 'priority-high' : 'priority-medium'}">Confidence ${p.confidence}%</span>
        </div>
        <div class="small text-muted mt-1">${p.reason}</div>
      </div>`).join('')
    : '<div class="cg-empty-state"><i class="fa-solid fa-brain"></i>No analytical indicators yet. Open a case and run pattern analysis.</div>';
})();
