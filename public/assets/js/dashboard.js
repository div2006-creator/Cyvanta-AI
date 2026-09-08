(async function () {
  const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  const empty=(el,msg)=>{if(el)el.innerHTML=`<div class="text-muted py-2">${esc(msg)}</div>`;};
  try {
    const data=await cgApi('/api/analysis/dashboard-metrics.php');
    if(!data.success){cgToast(data.message||'Unable to load dashboard data.','error');return;}
    const d=data.data||{};
    const cards=document.getElementById('cgMetricCards');
    cards.innerHTML=(d.cards||[]).map(c=>`<div class="col-6 col-lg-3"><div class="cg-card cg-metric-card"><div class="cg-metric-icon"><i class="fa-solid ${esc(c.icon)}"></i></div><div class="cg-metric-value">${Number(c.value||0).toLocaleString()}</div><div class="cg-metric-label">${esc(c.label)}</div></div></div>`).join('');

    const stats=document.getElementById('cgCaseStats');
    const cs=d.case_status||{};
    const total=Object.values(cs).reduce((a,b)=>a+Number(b),0);
    stats.innerHTML=total?Object.entries(cs).map(([k,v])=>`<div class="d-flex justify-content-between py-1"><span>${esc(k)}</span><strong>${Number(v).toLocaleString()}</strong></div>`).join(''):'<div class="text-muted">Not enough case data available.</div>';

    const activity=d.investigation_activity||[];
    const ia=document.getElementById('cgInvestigationActivity');
    ia.innerHTML=activity.length?activity.map(a=>`<div class="py-2 border-bottom" style="border-color:var(--cg-border-soft)!important"><div>${esc(a.description)}</div><div class="text-muted">${esc(a.case_number||'')} · ${esc(a.time_ago||'')}</div></div>`).join(''):'<div class="text-muted">No investigation activity yet.</div>';

    const net=d.network||{};
    const ne=document.getElementById('cgNetworkIntel');
    ne.innerHTML=(net.available)?`<div class="d-flex gap-4 mb-3"><div><div class="fw-800 fs-4">${Number(net.total_entities||0).toLocaleString()}</div><div>Total Entities</div></div><div><div class="fw-800 fs-4">${Number(net.total_relationships||0).toLocaleString()}</div><div>Total Relationships</div></div></div><div class="fw-700 mb-2">Most Connected Entities</div>${(net.top_entities||[]).map((e,i)=>`<div class="d-flex justify-content-between py-1 border-bottom" style="border-color:var(--cg-border-soft)!important"><span>${i+1}. ${esc(e.name)}</span><span>${Number(e.connections||0)} connections</span></div>`).join('')}`:'<div class="py-2">No network intelligence available yet.</div>';

    const recent=d.recent_activity||[];
    document.getElementById('cgRecentActivity').innerHTML=recent.length?recent.map(a=>`<div class="py-2 border-bottom" style="border-color:var(--cg-border-soft)!important"><div>${esc(a.description)}</div><div class="text-muted">${esc(a.case_number||'')} · ${esc(a.time_ago||'')}</div></div>`).join(''):'<div class="py-2">No recent activity yet.</div>';

    try {
      await loadScript('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js');
      new Chart(document.getElementById('cgActivityChart'),{type:'line',data:{labels:(d.activity_series||[]).map(x=>x.date),datasets:[{label:'Activity',data:(d.activity_series||[]).map(x=>x.count),borderColor:'#22d3ee',backgroundColor:'rgba(34,211,238,.12)',tension:.35,fill:true,pointRadius:0}]},options:{plugins:{legend:{display:false}}}});
      const labels=Object.keys(cs);
      if(labels.length)new Chart(document.getElementById('cgCaseStatusChart'),{type:'doughnut',data:{labels,datasets:[{data:labels.map(k=>cs[k]),backgroundColor:['#60a5fa','#fbbf24','#a78bfa','#f87171','#34d399','#94a3b8'],borderWidth:0}]},options:{plugins:{legend:{position:'bottom'}}}});
    } catch(e) {
      const c=document.getElementById('cgActivityChart'); if(c)c.replaceWith(Object.assign(document.createElement('div'),{className:'text-muted py-3',textContent:'Activity chart unavailable; investigation activity data is shown below.'}));
      const c2=document.getElementById('cgCaseStatusChart'); if(c2)c2.replaceWith(Object.assign(document.createElement('div'),{className:'text-muted py-3',textContent:total?'Case statistics are shown below.':'Not enough case data available.'}));
    }
  } catch(e){console.error(e);cgToast('Unable to load dashboard data.','error');}
  function loadScript(src){return new Promise((resolve,reject)=>{if(document.querySelector(`script[src="${src}"]`))return resolve();const s=document.createElement('script');s.src=src;s.onload=resolve;s.onerror=reject;document.head.appendChild(s);});}
})();