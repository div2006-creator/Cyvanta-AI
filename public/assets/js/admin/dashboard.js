(async function(){
 const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
 try{
  const data=await cgApi('/api/admin/dashboard-metrics.php'); if(!data.success){cgToast(data.message||'Failed to load admin dashboard data.','error');return;}
  const d=data.data||{};
  document.getElementById('cgAdminCards').innerHTML=(d.cards||[]).map(c=>`<div class="col-6 col-lg-3"><div class="cg-card cg-metric-card"><div class="cg-metric-icon"><i class="fa-solid ${esc(c.icon)}"></i></div><div class="cg-metric-value">${Number(c.value||0).toLocaleString()}</div><div class="cg-metric-label">${esc(c.label)}</div></div></div>`).join('');
  const logins=d.recent_logins||[], systems=d.recent_system_activity||[];
  document.getElementById('cgRecentLogins').innerHTML=logins.length?logins.map(x=>`<div class="py-2 border-bottom"><strong>${esc(x.full_name||x.username)}</strong> <span class="text-muted">(${esc(x.username)})</span><div>${esc(x.status)} · ${new Date(x.attempted_at).toLocaleString()}</div></div>`).join(''):'<div class="text-muted">No login activity recorded.</div>';
  document.getElementById('cgRecentSystemActivity').innerHTML=systems.length?systems.map(x=>`<div class="py-2 border-bottom"><div>${esc(x.description)}</div><div class="text-muted">${new Date(x.created_at).toLocaleString()}</div></div>`).join(''):'<div class="text-muted">No recent system activity yet.</div>';
  const gs=d.user_growth||[], ls=d.login_activity||[];
  document.getElementById('cgUserGrowthData').innerHTML=gs.length?`Latest recorded total: <strong>${Number(gs[gs.length-1].count).toLocaleString()}</strong> users.`:'No users available.';
  const loginTotal=ls.reduce((a,x)=>a+Number(x.successful||0)+Number(x.failed||0),0);
  document.getElementById('cgLoginActivityData').textContent=loginTotal?'Successful and failed login attempts are shown below.':'No login activity recorded.';
  try{
   await loadScript('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js');
   new Chart(document.getElementById('cgUserGrowthChart'),{type:'line',data:{labels:gs.map(x=>x.date),datasets:[{label:'Registered users',data:gs.map(x=>x.count),borderColor:'#7c5cff',backgroundColor:'rgba(124,92,255,.12)',tension:.3,fill:true,pointRadius:0}]},options:{plugins:{legend:{display:false}}}});
   new Chart(document.getElementById('cgLoginActivityChart'),{type:'bar',data:{labels:ls.map(x=>x.date),datasets:[{label:'Successful',data:ls.map(x=>x.successful),backgroundColor:'#34d399'},{label:'Failed',data:ls.map(x=>x.failed),backgroundColor:'#f87171'}]},options:{scales:{y:{beginAtZero:true}}}});
  }catch(e){document.getElementById('cgUserGrowthChart').replaceWith(Object.assign(document.createElement('div'),{className:'text-muted py-3',textContent:'User growth data is available below.'}));document.getElementById('cgLoginActivityChart').replaceWith(Object.assign(document.createElement('div'),{className:'text-muted py-3',textContent:loginTotal?'Login activity data is available below.':'No login activity recorded.'}));}
 }catch(e){console.error(e);cgToast('Unable to load admin dashboard data.','error');}
 function loadScript(src){return new Promise((resolve,reject)=>{if(document.querySelector(`script[src="${src}"]`))return resolve();const s=document.createElement('script');s.src=src;s.onload=resolve;s.onerror=reject;document.head.appendChild(s);});}
})();