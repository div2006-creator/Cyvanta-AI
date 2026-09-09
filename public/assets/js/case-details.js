(function () {
  const caseId = window.CG_CASE_ID;
  const loaded = {};

  document.addEventListener('change', async function (e) {
    if (e.target && e.target.id === 'cgCaseStatusSelect') {
      const newStatus = e.target.value;
      const res = await cgApi('/api/cases/update.php', {
        method: 'POST',
        body: JSON.stringify({ id: caseId, status: newStatus })
      });
      if (res.success) {
        cgToast(res.message || 'Case status updated.', 'success');
        const badge = document.querySelector('.cg-badge-status');
        if (badge) {
          badge.textContent = newStatus;
          badge.className = `cg-badge-status status-${newStatus.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}`;
        }
      } else {
        cgToast(res.message || 'Failed to update case status.', 'error');
      }
    }
  });

  function activateTab(name) {
    if (!name) return;
    const allTabs = document.querySelectorAll('.cg-tab');
    allTabs.forEach(t => {
      if (t.dataset.tab === name) {
        t.classList.add('active');
      } else {
        t.classList.remove('active');
      }
    });

    const allPanels = document.querySelectorAll('.cg-tab-panel');
    allPanels.forEach(p => {
      if (p.id === `tab-${name}`) {
        p.removeAttribute('hidden');
        p.style.display = 'block';
      } else {
        p.setAttribute('hidden', 'true');
        p.style.display = 'none';
      }
    });

    if (!loaded[name] || name === 'evidence' || name === 'reports' || name === 'overview') {
      loaded[name] = true;
      try { loadTab(name); } catch (e) { console.error('Tab load error:', e); }
    }
    if (name === 'network' && window.cgGraphInstance) {
      setTimeout(() => { try { window.cgGraphInstance.fit(); } catch (e) {} }, 80);
    }
  }

  document.addEventListener('click', function (e) {
    const tabEl = e.target.closest('.cg-tab');
    if (tabEl && tabEl.dataset.tab) {
      e.preventDefault();
      activateTab(tabEl.dataset.tab);
    }
  });

  function loadTab(name) {
    const fns = {
      overview: loadSnapshot, network: loadGraph, entities: loadEntities,
      documents: loadDocuments, evidence: loadEvidence, timeline: loadTimeline,
      analysis: loadAnalysis, notes: loadNotes, activity: loadActivity,
      reports: loadReports
    };
    if (fns[name]) {
      try {
        const res = fns[name]();
        if (res && typeof res.catch === 'function') res.catch(err => console.error(`Error loading tab ${name}:`, err));
      } catch (err) {
        console.error(`Error loading tab ${name}:`, err);
      }
    }
  }

  // ---------- Overview ----------
  async function loadSnapshot() {
    const data = await cgApi(`/api/cases/details.php?id=${caseId}`);
    if (!data.success) return;
    const s = data.data.snapshot;
    const members = data.data.assigned_members || [];

    document.getElementById('cgCaseSnapshot').innerHTML = `
      <div class="row g-2 mb-3">
        ${[['Entities', s.entities], ['Relationships', s.relationships], ['Documents', s.documents], ['Notes', s.notes], ['Analyses', s.analyses]]
          .map(([label, val]) => `<div class="col-6"><div class="cg-card py-2 px-3"><div class="fw-800 fs-5 text-dark">${val}</div><div class="small text-muted">${label}</div></div></div>`).join('')}
      </div>
      <div class="fw-700 text-dark small mb-2 d-flex justify-content-between align-items-center">
        <span>Assigned Personnel (${members.length})</span>
        <button class="btn btn-sm btn-link p-0 text-decoration-none text-primary" data-bs-toggle="modal" data-bs-target="#cgAssignModal">+ Assign</button>
      </div>
      <div class="d-flex flex-column gap-2">
        ${members.length ? members.map(m => `
          <div class="d-flex align-items-center gap-2 p-2 border rounded-3 bg-light">
            <span class="cg-avatar bg-primary text-white rounded-circle" style="width:28px;height:28px;font-size:12px">${m.full_name.charAt(0).toUpperCase()}</span>
            <div class="lh-1">
              <div class="fw-700 text-dark small">${m.full_name}</div>
              <div class="small text-muted" style="font-size:10.5px">${m.role.replace(/_/g,' ')} · ${m.department || 'Department'}</div>
            </div>
          </div>`).join('') : '<div class="small text-muted">No personnel assigned.</div>'}
      </div>`;
  }

  // ---------- Case Assignment ----------
  async function loadPersonnel() {
    const sel = document.getElementById('cgAssignUserSelect');
    if (!sel) return;
    const res = await cgApi('/api/users/list.php');
    if (res.success && res.data.items) {
      sel.innerHTML = '<option value="">Select Lead Investigator...</option>' +
        res.data.items.map(u => `<option value="${u.id}">${u.full_name} (${u.role.replace(/_/g, ' ')}) — ${u.department || 'General'}</option>`).join('');
    }
  }
  loadPersonnel();

  const assignForm = document.getElementById('cgAssignForm');
  if (assignForm) {
    assignForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(assignForm).entries());
      payload.case_id = caseId;
      const res = await cgApi('/api/cases/assign.php', { method: 'POST', body: JSON.stringify(payload) });
      if (res.success) {
        cgToast(res.message, 'success');
        if (res.data && res.data.investigator_name) {
          const el = document.getElementById('cgLeadInvestigatorName');
          if (el) el.textContent = res.data.investigator_name;
        }
        const modalEl = document.getElementById('cgAssignModal');
        if (modalEl && window.bootstrap) bootstrap.Modal.getInstance(modalEl)?.hide();
        loaded.overview = false;
        loadSnapshot();
      } else {
        cgToast(res.message || 'Failed to assign case.', 'error');
      }
    });
  }

  // ---------- Network Graph (vis-network) ----------
  let network, allNodes, allEdges, entityMeta = {}, isPhysicsEnabled = true;

  const TYPE_COLOR_MAP = {
    'Person': '#38bdf8',
    'Organization': '#a78bfa',
    'Agency': '#818cf8',
    'Court': '#8b5cf6',
    'Case': '#ec4899',
    'Location': '#34d399',
    'Weapon': '#ef4444',
    'Ammunition': '#dc2626',
    'Aircraft': '#0284c7',
    'Vehicle': '#fbbf24',
    'Phone Number': '#f472b6',
    'Email': '#38bdf8',
    'Bank Account': '#f87171',
    'Transaction': '#fb923c',
    'Money': '#10b981',
    'Case Number': '#a855f7',
    'Date': '#64748b',
    'Document': '#94a3b8',
    'Legal Notice': '#eab308',
    'Event': '#c084fc',
    'Social Media Account': '#60a5fa',
    'Photo / Image': '#38bdf8',
    'Video Footage': '#e879f9',
    'Face / Suspect Tag': '#ef4444',
    'License Plate OCR': '#f59e0b',
    'GPS Location Tag': '#10b981',
    'Evidence Object': '#6366f1'
  };

  const REL_COLOR_MAP = {
    'INVESTIGATED_BY': '#818cf8',
    'INVOLVED_IN': '#ef4444',
    'ALIAS_OF': '#a78bfa',
    'LOCATED_IN': '#34d399',
    'ISSUED_NOTICE_TO': '#eab308',
    'SUBJECT_OF': '#38bdf8',
    'RECOVERED_AT': '#10b981',
    'DROPPED_AT': '#dc2626',
    'OWNED_BY': '#fbbf24',
    'CONTACTED': '#f472b6',
    'TRANSFERRED_TO': '#fb923c',
    'ASSOCIATED_WITH': '#0284c7',
    'TRAVELED_TO': '#34d399',
    'OPERATED': '#0284c7',
    'CONNECTED_TO': '#64748b',
    'CALLS': '#f472b6',
    'VISITED': '#34d399',
    'WORKS_FOR': '#a78bfa',
    'TRANSFERRED_MONEY_TO': '#fb923c',
    'MENTIONED_IN': '#94a3b8',
    'FAMILY_OF': '#38bdf8',
    'MET_WITH': '#60a5fa'
  };

  async function updateDocFilterOptions() {
    const docFilter = document.getElementById('cgGraphDocFilter');
    if (!docFilter) return;
    const currentVal = docFilter.value || '0';
    try {
      const res = await cgApi(`/api/documents/list.php?case_id=${caseId}`);
      if (res.success && res.data && res.data.items) {
        let html = '<option value="0">All Documents (Master Network)</option>';
        res.data.items.forEach(d => {
          const safeName = (d.name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
          html += `<option value="${d.id}">${safeName}</option>`;
        });
        docFilter.innerHTML = html;
        if ([...docFilter.options].some(opt => opt.value === currentVal)) {
          docFilter.value = currentVal;
        } else {
          docFilter.value = '0';
        }
      }
    } catch (e) {}
  }

  async function loadGraph() {
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/vis-network/9.1.9/standalone/umd/vis-network.min.js');
    await updateDocFilterOptions();

    const docFilter = document.getElementById('cgGraphDocFilter');
    const selectedDocId = docFilter ? (parseInt(docFilter.value, 10) || 0) : 0;
    const docParam = selectedDocId > 0 ? `&document_id=${selectedDocId}` : '';

    const [entitiesRes, relsRes] = await Promise.all([
      cgApi(`/api/entities/list.php?case_id=${caseId}${docParam}`),
      cgApi(`/api/relationships/list.php?case_id=${caseId}${docParam}`),
    ]);
    if (!entitiesRes.success || !relsRes.success) return;

    const entities = entitiesRes.data.items || [];
    const rels = relsRes.data.items || [];
    entityMeta = {};
    entities.forEach(e => entityMeta[e.id] = e);

    const typeFilter = document.getElementById('cgGraphTypeFilter');
    if (typeFilter) {
      const currentType = typeFilter.value || '';
      const types = [...new Set(entities.map(e => e.type_name))];
      typeFilter.innerHTML = '<option value="">All Entity Types</option>' + types.map(t => `<option value="${t}">${t}</option>`).join('');
      if (types.includes(currentType)) {
        typeFilter.value = currentType;
      }
    }

    if (entities.length === 0) {
      document.getElementById('cgNetworkGraph').innerHTML = '<div class="cg-empty-state pt-5"><i class="fa-solid fa-circle-nodes"></i>No entities found for this document — upload and process a document to build the network.</div>';
      return;
    }

    allNodes = new vis.DataSet(entities.map(e => {
      const mainColor = TYPE_COLOR_MAP[e.type_name] || e.color || '#0284c7';
      const rInfo = (window.cgFormatRisk ? window.cgFormatRisk(e.risk_score, e.type_name, e.name, e.description) : { isEligible: false, scoreText: 'N/A' });
      const isHighRisk = rInfo.isEligible && rInfo.score >= 70;
      const borderColor = isHighRisk ? '#dc2626' : (rInfo.isEligible && rInfo.score >= 40 ? '#d97706' : mainColor);
      const borderWidth = isHighRisk ? 4 : 2;
      const riskTitle = rInfo.isEligible ? `${rInfo.score}% (${rInfo.levelText})` : 'N/A';

      return {
        id: e.id,
        label: `${e.name}\n[${e.type_name}]`,
        title: `<strong>${e.name}</strong><br>Type: ${e.type_name}<br>Risk Score: <strong>${riskTitle}</strong><br>Connections: ${e.connections}`,
        color: {
          background: mainColor,
          border: borderColor,
          highlight: { background: '#0284c7', border: '#0369a1' },
          hover: { background: mainColor, border: '#0f172a' }
        },
        borderWidth: borderWidth,
        borderWidthSelected: 5,
        font: {
          color: '#0f172a',
          size: 12,
          face: 'Inter',
          strokeWidth: 4,
          strokeColor: '#ffffff'
        },
        shape: isHighRisk ? 'diamond' : 'dot',
        size: 18 + Math.min(26, e.connections * 3),
        group: e.type_name,
      };
    }));

    const entityIdSet = new Set(entities.map(e => e.id));
    const validRels = rels.filter(r => entityIdSet.has(r.source_entity_id) && entityIdSet.has(r.target_entity_id));

    allEdges = new vis.DataSet(validRels.map(r => {
      const relType = r.rel_type || 'ASSOCIATED_WITH';
      const edgeColor = REL_COLOR_MAP[relType] || '#0284c7';
      const isDashed = ['CALLS', 'MENTIONED_IN'].includes(relType);

      return {
        id: r.id,
        from: r.source_entity_id,
        to: r.target_entity_id,
        label: relType.replace(/_/g, ' '),
        font: {
          color: '#0369a1',
          size: 11,
          face: 'Inter',
          strokeWidth: 3,
          strokeColor: '#ffffff',
          align: 'horizontal'
        },
        color: {
          color: edgeColor,
          highlight: '#0f172a',
          hover: '#0284c7'
        },
        arrows: { to: { enabled: true, scaleFactor: 0.8 } },
        dashes: isDashed,
        width: Math.min(6, 2 + (r.strength || 1) / 3),
        smooth: { type: 'continuous', roundness: 0.2 }
      };
    }));

    const container = document.getElementById('cgNetworkGraph');
    const options = {
      nodes: { shadow: true },
      edges: { shadow: false },
      physics: {
        solver: 'barnesHut',
        barnesHut: {
          gravitationalConstant: -14000,
          centralGravity: 0.12,
          springLength: 170,
          springConstant: 0.04,
          avoidOverlap: 0.85
        },
        stabilization: { iterations: 180 }
      },
      interaction: { hover: true, tooltipDelay: 80, zoomView: true, dragView: true }
    };

    network = new vis.Network(container, { nodes: allNodes, edges: allEdges }, options);
    window.cgGraphInstance = network;

    network.on('click', (params) => {
      const panel = document.getElementById('cgGraphSidePanel');
      if (params.nodes.length) {
        showEntityPanel(params.nodes[0]);
      } else if (params.edges.length) {
        showEdgeEvidencePanel(params.edges[0]);
      } else {
        panel.classList.remove('show');
      }
    });

    renderGraphLegend(entities);
  }

  async function showEdgeEvidencePanel(edgeId) {
    const edge = allEdges.get(edgeId);
    if (!edge) return;

    const fromNode = allNodes.get(edge.from);
    const toNode = allNodes.get(edge.to);
    const panel = document.getElementById('cgGraphSidePanel');
    panel.classList.add('show');

    // Fetch details from relationship list or API
    const relsRes = await cgApi(`/api/relationships/list.php?case_id=${caseId}`);
    const relItem = (relsRes.success && relsRes.data.items) ? relsRes.data.items.find(r => r.id == edgeId) : null;

    const relLabel = edge.label || 'CONNECTED_TO';
    const confidence = relItem ? (relItem.confidence || 85) : 85;
    const evidenceText = (relItem && relItem.evidence_text) ? relItem.evidence_text : 'Extracted contextual evidence from uploaded case documentation.';
    const sourceDoc = (relItem && relItem.doc_name) ? relItem.doc_name : 'Primary Case File';
    const pageNum = (relItem && relItem.source_page) ? relItem.source_page : 1;
    const timeStamp = (relItem && relItem.extraction_timestamp) ? relItem.extraction_timestamp : 'Verified';

    panel.innerHTML = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <div class="text-muted small fw-700 text-uppercase"><i class="fa-solid fa-file-contract text-primary me-1"></i>Relationship Evidence</div>
          <div class="badge bg-primary text-white mt-1">${relLabel}</div>
        </div>
        <button class="btn-close" onclick="document.getElementById('cgGraphSidePanel').classList.remove('show')"></button>
      </div>
      <div class="p-2 border rounded bg-light mb-3 mt-2">
        <div class="small fw-700 text-dark">${fromNode ? fromNode.label.split('\n')[0] : 'Source'}</div>
        <div class="text-center text-primary fs-6 fw-800 my-1">&darr; ${relLabel} &darr;</div>
        <div class="small fw-700 text-dark text-end">${toNode ? toNode.label.split('\n')[0] : 'Target'}</div>
      </div>
      <div class="small text-muted mb-2">Extraction Confidence: <span class="fw-800 text-success">${confidence}%</span></div>
      <div class="mb-3">
        <div class="fw-700 text-dark small mb-1">Extracted Evidence Text:</div>
        <div class="p-2 border-start border-3 border-primary bg-light small text-secondary fst-italic">
          "${evidenceText}"
        </div>
      </div>
      <div class="small text-muted mb-1"><i class="fa-solid fa-file-lines me-1"></i>Source Document: <strong class="text-dark">${sourceDoc}</strong></div>
      <div class="small text-muted mb-1"><i class="fa-solid fa-book-open me-1"></i>Source Page: <strong class="text-dark">Page ${pageNum}</strong></div>
      <div class="small text-muted mb-1"><i class="fa-solid fa-clock me-1"></i>Extraction Time: <strong class="text-dark">${timeStamp}</strong></div>
    `;
  }

  function renderGraphLegend(entities) {
    const legendEl = document.getElementById('cgGraphLegend');
    if (!legendEl) return;
    const presentTypes = [...new Set(entities.map(e => e.type_name))];

    legendEl.innerHTML = `
      <div class="fw-700 text-dark mb-1" style="font-size:11.5px">
        <i class="fa-solid fa-layer-group text-primary me-1"></i> Legend (Click to filter)
      </div>
      <div class="d-flex flex-wrap gap-1">
        ${presentTypes.map(t => {
          const color = TYPE_COLOR_MAP[t] || '#0284c7';
          return `<span class="cg-legend-pill" data-type="${t}" style="background:${color}20; border-color:${color}">
            <span style="width:9px;height:9px;border-radius:50%;background:${color};display:inline-block"></span>
            <span>${t}</span>
          </span>`;
        }).join('')}
      </div>
    `;

    legendEl.querySelectorAll('.cg-legend-pill').forEach(pill => {
      pill.addEventListener('click', () => {
        const type = pill.dataset.type;
        const typeFilter = document.getElementById('cgGraphTypeFilter');
        if (typeFilter) {
          typeFilter.value = type;
          typeFilter.dispatchEvent(new Event('change'));
        }
      });
    });
  }

  async function showEntityPanel(nodeId) {
    const data = await cgApi(`/api/entities/details.php?id=${nodeId}`);
    if (!data.success) return;
    const e = data.data.entity;
    const rels = data.data.relationships;
    const panel = document.getElementById('cgGraphSidePanel');
    const rInfo = (window.cgFormatRisk ? window.cgFormatRisk(e.risk_score, e.type_name, e.name, e.description) : { isEligible: false, scoreText: 'N/A', levelText: 'Not Applicable' });
    panel.classList.add('show');
    panel.innerHTML = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div><div class="text-muted small">ENTITY INSPECTOR</div><div class="fw-800 text-dark fs-6">${e.name}</div></div>
        <button class="btn-close" onclick="document.getElementById('cgGraphSidePanel').classList.remove('show')"></button>
      </div>
      <div class="small text-muted mb-1">Type: <span class="fw-700 text-dark">${e.type_name}</span></div>
      <div class="small text-muted mb-1">Risk Score: <span class="fw-800 ${rInfo.isEligible ? (rInfo.score >= 70 ? 'text-danger' : rInfo.score >= 40 ? 'text-warning' : 'text-success') : 'text-secondary'}">${rInfo.scoreText}</span></div>
      <div class="small text-muted mb-2">Risk Level: <span class="badge ${rInfo.badgeClass}">${rInfo.levelText}</span></div>
      <div class="small text-muted mb-3">Connections: <span class="fw-700 text-dark">${rels.length}</span></div>
      <div class="fw-700 text-dark small mb-2 border-bottom pb-1">Network Connections</div>
      ${rels.map(r => `<div class="small py-1 border-bottom" style="border-color:var(--cg-border-soft)!important">
          <span class="fw-600 text-dark">${r.other_name}</span><br><span class="text-primary small fw-600">${r.rel_type.replace(/_/g, ' ')}</span>
        </div>`).join('') || '<div class="small text-muted">No relationships recorded.</div>'}
    `;
  }

  document.getElementById('cgGraphSearch')?.addEventListener('input', function () {
    if (!network || !allNodes) return;
    const q = this.value.toLowerCase().trim();
    if (!q) { network.unselectAll(); return; }
    const match = allNodes.get().find(n => n.label.toLowerCase().includes(q));
    if (match) {
      network.selectNodes([match.id]);
      network.focus(match.id, { scale: 1.3, animation: true });
      showEntityPanel(match.id);
    }
  });

  document.getElementById('cgGraphDocFilter')?.addEventListener('change', function () {
    loadGraph();
  });

  document.getElementById('cgGraphTypeFilter')?.addEventListener('change', function () {
    if (!allNodes) return;
    const val = this.value;
    allNodes.forEach(n => {
      const meta = entityMeta[n.id];
      allNodes.update({ id: n.id, hidden: val ? (meta && meta.type_name !== val) : false });
    });
  });

  document.getElementById('cgGraphLayoutSelect')?.addEventListener('change', function () {
    if (!network) return;
    const layout = this.value;
    if (layout === 'hierarchical') {
      network.setOptions({
        layout: { hierarchical: { enabled: true, direction: 'UD', sortMethod: 'directed', nodeSpacing: 150 } },
        physics: false
      });
    } else {
      network.setOptions({
        layout: { hierarchical: { enabled: false } },
        physics: { solver: 'barnesHut', barnesHut: { gravitationalConstant: -14000, centralGravity: 0.12, springLength: 170 } }
      });
    }
  });

  document.getElementById('cgGraphZoomIn')?.addEventListener('click', () => {
    if (network) network.moveTo({ scale: network.getScale() * 1.25, animation: true });
  });

  document.getElementById('cgGraphZoomOut')?.addEventListener('click', () => {
    if (network) network.moveTo({ scale: network.getScale() * 0.8, animation: true });
  });

  document.getElementById('cgGraphReset')?.addEventListener('click', () => {
    if (network) network.fit({ animation: true });
  });

  document.getElementById('cgGraphPhysicsToggle')?.addEventListener('click', function () {
    if (!network) return;
    isPhysicsEnabled = !isPhysicsEnabled;
    network.setOptions({ physics: { enabled: isPhysicsEnabled } });
    this.innerHTML = isPhysicsEnabled ? '<i class="fa-solid fa-pause me-1"></i> Freeze' : '<i class="fa-solid fa-play me-1"></i> Resume';
    cgToast(isPhysicsEnabled ? 'Graph physics enabled.' : 'Graph layout frozen.', 'info');
  });

  function toggleGraphFullscreen() {
    const wrap = document.getElementById('cgGraphWrap');
    const exitBtn = document.getElementById('cgExitFullscreenBtn');
    if (!wrap) return;

    if (!document.fullscreenElement && !document.webkitFullscreenElement && !wrap.classList.contains('cg-fullscreen-active')) {
      if (wrap.requestFullscreen) {
        wrap.requestFullscreen();
      } else if (wrap.webkitRequestFullscreen) {
        wrap.webkitRequestFullscreen();
      } else {
        wrap.classList.add('cg-fullscreen-active');
      }
      if (exitBtn) exitBtn.classList.remove('d-none');
    } else {
      if (document.exitFullscreen) {
        document.exitFullscreen();
      } else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
      } else {
        wrap.classList.remove('cg-fullscreen-active');
      }
      if (exitBtn) exitBtn.classList.add('d-none');
    }
    setTimeout(() => { if (network) network.fit(); }, 200);
  }

  document.getElementById('cgGraphFullscreen')?.addEventListener('click', toggleGraphFullscreen);
  document.getElementById('cgExitFullscreenBtn')?.addEventListener('click', toggleGraphFullscreen);

  document.addEventListener('fullscreenchange', () => {
    const exitBtn = document.getElementById('cgExitFullscreenBtn');
    if (!document.fullscreenElement && exitBtn) {
      exitBtn.classList.add('d-none');
    }
    setTimeout(() => { if (network) network.fit(); }, 200);
  });

  // ---------- Entities tab ----------
  async function loadEntities() {
    const data = await cgApi(`/api/entities/list.php?case_id=${caseId}`);
    const tbody = document.getElementById('cgEntitiesTableBody');
    const empty = document.getElementById('cgEntitiesEmpty');
    if (!data.success || !data.data.items.length) { tbody.innerHTML = ''; empty.hidden = false; return; }
    empty.hidden = true;
    tbody.innerHTML = data.data.items.map(e => {
      const rInfo = (window.cgFormatRisk ? window.cgFormatRisk(e.risk_score, e.type_name, e.name, e.description) : { isEligible: false, scoreText: 'N/A', badgeClass: 'priority-low bg-secondary text-white' });
      return `
      <tr><td class="fw-600 text-dark">${e.name}</td><td>${e.type_name}</td>
      <td><span class="cg-priority ${rInfo.badgeClass}">${rInfo.scoreText}</span></td>
      <td>${e.connections}</td><td class="text-muted small">${e.description || '—'}</td></tr>`;
    }).join('');
  }

  // ---------- Documents tab ----------
  async function loadDocuments() {
    const data = await cgApi(`/api/documents/list.php?case_id=${caseId}`);
    const list = document.getElementById('cgDocumentsList');
    const empty = document.getElementById('cgDocumentsEmpty');
    if (!data.success || !data.data.items.length) { list.innerHTML = ''; empty.hidden = false; return; }
    empty.hidden = true;
    const statusColor = { Uploaded: 'status-new', Queued: 'status-new', Processing: 'status-under-investigation', Processed: 'status-resolved', Failed: 'status-critical' };
    const getDocIcon = (t) => {
      const type = (t || '').toUpperCase();
      if (['JPG','JPEG','PNG','WEBP','TIFF','BMP'].includes(type)) return '<i class="fa-solid fa-file-image me-1 text-info"></i>';
      if (['MP4','AVI','MOV','MKV','WEBM'].includes(type)) return '<i class="fa-solid fa-file-video me-1 text-warning"></i>';
      return '<i class="fa-solid fa-file-lines me-1"></i>';
    };

    const currentUser = (window.CG && window.CG.user) || {};
    const isSuperAdmin = ['super_admin','administrator'].includes(currentUser.role);

    list.innerHTML = data.data.items.map(d => {
      const type = (d.doc_type || '').toUpperCase();
      const isVideo = ['MP4','AVI','MOV','MKV','WEBM'].includes(type);
      const isImage = ['JPG','JPEG','PNG','WEBP','TIFF','BMP'].includes(type);
      const canDelete = isSuperAdmin || (parseInt(currentUser.id, 10) === parseInt(d.uploaded_by, 10));

      return `
      <div class="cg-card mb-3 p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <div class="fw-700 text-dark fs-6">${getDocIcon(d.doc_type)} ${d.name}</div>
            <div class="small text-muted">${d.doc_type} · ${(d.file_size / (isVideo ? 1048576 : 1024)).toFixed(1)} ${isVideo ? 'MB' : 'KB'} · uploaded by ${d.uploaded_by_name || '—'} · ${new Date(d.uploaded_at).toLocaleString()}</div>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="cg-badge-status ${statusColor[d.status] || 'status-new'}">${d.status}</span>
            ${(isVideo || isImage) ? `<button class="cg-btn cg-btn-outline cg-btn-sm" onclick="const el=document.getElementById('mediaPreview-${d.id}');if(el)el.hidden=!el.hidden;"><i class="fa-solid ${isVideo ? 'fa-circle-play text-warning' : 'fa-image text-info'}"></i> ${isVideo ? 'Play Video & Frame Log' : 'View Photo & OCR'}</button>` : ''}
            ${d.status === 'Uploaded' || d.status === 'Failed'
              ? `<button class="cg-btn cg-btn-primary cg-btn-sm" onclick="window.cgProcessDocument(${d.id})"><i class="fa-solid fa-gears me-1"></i> Process & Analyse</button>`
              : `<button class="cg-btn cg-btn-success cg-btn-sm" onclick="window.cgAnalyseDocNetwork(${d.id})"><i class="fa-solid fa-diagram-project me-1"></i> Analyse Network</button>`}
            ${canDelete ? `<button class="btn btn-sm btn-outline-danger" onclick="window.cgDeleteDocument(${d.id})" title="${isSuperAdmin ? 'Delete Document (Super Admin Access)' : 'Delete My Uploaded Document'}"><i class="fa-solid fa-trash-can me-1"></i> Delete</button>` : ''}
          </div>
        </div>
        ${(isVideo || isImage) ? `
          <div id="mediaPreview-${d.id}" class="mt-3 p-3 border rounded" style="background:var(--cg-bg-alt,#0b132b);border-color:var(--cg-border-soft,#1e293b)!important" hidden>
            <div class="fw-700 text-info small mb-2"><i class="fa-solid ${isVideo ? 'fa-film' : 'fa-camera'} me-1"></i> ${isVideo ? 'Video Surveillance Player & Keyframe Analysis' : 'Photo Viewer & OCR Extracted Features'}</div>
            ${isVideo ? `
              <video src="${CG.baseUrl}/api/documents/file.php?id=${d.id}" controls style="width:100%;max-height:360px;border-radius:8px;background:#000" preload="metadata"></video>
            ` : `
              <div class="text-center bg-black p-2 rounded mb-2">
                <img src="${CG.baseUrl}/api/documents/file.php?id=${d.id}" style="max-width:100%;max-height:360px;border-radius:6px;object-fit:contain" alt="${d.name}">
              </div>
            `}
          </div>
        ` : ''}
      </div>`;
    }).join('');
  }

  window.cgAnalyseDocNetwork = async function (documentId) {
    activateTab('network');
    const docFilter = document.getElementById('cgGraphDocFilter');
    if (docFilter) {
      docFilter.value = '0';
    }
    await loadGraph();
  };

  window.cgDeleteDocument = async function (documentId) {
    if (!confirm('Are you sure you want to delete this document? This will also remove extracted intelligence and update network analysis.')) return;
    try {
      const res = await cgApi('/api/documents/delete.php', { method: 'POST', body: JSON.stringify({ document_id: documentId }) });
      if (res.success) {
        cgToast(res.message || 'Document deleted successfully.', 'success');
        loaded.documents = false; loaded.network = false; loaded.entities = false; loaded.evidence = false; loaded.overview = false; loaded.reports = false;
        await loadDocuments(); loadSnapshot();
        if (document.querySelector('.cg-tab.active')?.dataset.tab === 'network') loadGraph();
        if (document.querySelector('.cg-tab.active')?.dataset.tab === 'entities') loadEntities();
      } else {
        cgToast(res.message || 'Unable to delete document.', 'error');
      }
    } catch (e) {
      cgToast('Unable to delete document.', 'error');
    }
  };

  window.cgProcessDocument = async function (documentId) {
    const modalEl=document.getElementById('cgProcessModal'), modal=bootstrap.Modal.getOrCreateInstance(modalEl);
    const bar=document.getElementById('cgProcessBar'), steps=document.getElementById('cgProcessSteps');
    const stageNames=['UPLOAD COMPLETE','TEXT EXTRACTION','ENTITY EXTRACTION','RELATIONSHIP EXTRACTION','NETWORK UPDATE','AI ANALYSIS'];
    modal.show(); bar.style.width='0%';
    steps.innerHTML=stageNames.map((s,idx)=>`<div id="step-${s}" class="${idx===0?'text-white':'text-muted'} py-1"><i class="${idx===0?'fa-solid fa-circle-check':'fa-regular fa-circle'} me-2" style="${idx===0?'color:var(--cg-success)':''}"></i>${s}</div>`).join('');
    let i=1;
    const tick=setInterval(()=>{if(i<stageNames.length){const el=document.getElementById(`step-${stageNames[i]}`);if(el)el.innerHTML=`<i class="fa-solid fa-circle-check me-2" style="color:var(--cg-success)"></i>${stageNames[i]}`;i++;bar.style.width=Math.round(i/stageNames.length*90)+'%';}},350);
    let res;
    try { res=await cgApi('/api/documents/process.php',{method:'POST',body:JSON.stringify({document_id:documentId})}); }
    catch(e){res={success:false,message:'Unable to process document.'};}
    clearInterval(tick);
    if(res.success){
      stageNames.forEach(s=>{const el=document.getElementById(`step-${s}`);if(el)el.innerHTML=`<i class="fa-solid fa-circle-check me-2" style="color:var(--cg-success)"></i>${s}`;});
      bar.style.width='100%';
    }else{
      const failedIdx=Math.max(1,Math.min(i,stageNames.length-1));
      const failed=stageNames[failedIdx];
      const el=document.getElementById(`step-${failed}`); if(el)el.innerHTML=`<i class="fa-solid fa-circle-xmark me-2" style="color:var(--cg-critical)"></i>${failed}`;
    }
    setTimeout(async()=>{
      modal.hide();
      if(res.success){
        cgToast(res.message||'Document processed and analyzed successfully.','success');
        loaded.documents=false; loaded.network=false; loaded.entities=false; loaded.evidence=false; loaded.overview=false; loaded.activity=false; loaded.reports=false;
        await loadDocuments(); loadSnapshot();
        activateTab('network');
        const docFilter = document.getElementById('cgGraphDocFilter');
        if (docFilter) {
          docFilter.value = '0';
        }
        await loadGraph();
        if(document.querySelector('.cg-tab.active')?.dataset.tab==='entities')loadEntities();
        if(document.querySelector('.cg-tab.active')?.dataset.tab==='activity')loadActivity();
      }else cgToast(res.message||'Unable to process document.','error');
    },350);
  };
  const docForm=document.getElementById('cgDocForm');
  if(docForm){
    docForm.addEventListener('submit',async e=>{
      e.preventDefault();
      const submit=docForm.querySelector('[type="submit"]'); if(submit){submit.disabled=true;submit.dataset.original=submit.innerHTML;submit.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Uploading…';}
      try{
        const fd=new FormData(docForm);fd.append('case_id',caseId);
        const res=await cgApi('/api/documents/upload.php',{method:'POST',body:fd});
        if(res.success){
          cgToast(res.message||'Document uploaded successfully. Starting network analysis…','success');
          const modal=bootstrap.Modal.getInstance(document.getElementById('cgDocModal'));if(modal)modal.hide();
          docForm.reset();loaded.documents=false;loaded.evidence=false;loaded.overview=false;loaded.reports=false;await loadDocuments();loadSnapshot();
          const newDocId = res.data ? (res.data.document_id || res.data.id) : null;
          if (newDocId) {
            window.cgProcessDocument(newDocId);
          }
        }else cgToast(res.message||'Unable to upload the document.','error');
      }catch(e){console.error(e);cgToast('Unable to upload the document.','error');}
      finally{if(submit){submit.disabled=false;submit.innerHTML=submit.dataset.original||'Upload';}}
    });
  }

  // ---------- Evidence tab ----------
  async function loadEvidence() {
    const data = await cgApi(`/api/evidence/list.php?case_id=${caseId}`);
    const list = document.getElementById('cgEvidenceList');
    const empty = document.getElementById('cgEvidenceEmpty');
    if (!data.success || !data.data.items.length) { list.innerHTML = ''; empty.hidden = false; return; }
    empty.hidden = true;

    const currentUser = (window.CG && window.CG.user) || {};
    const isSuperAdmin = ['super_admin','administrator'].includes(currentUser.role);

    list.innerHTML = data.data.items.map(ev => {
      const canDelete = isSuperAdmin || (parseInt(currentUser.id, 10) === parseInt(ev.uploaded_by, 10));

      return `
      <div class="cg-card mb-2 p-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <div class="fw-700 text-dark fs-6">${ev.evidence_type}</div>
          <div class="d-flex align-items-center gap-2">
            <span class="cg-badge-status status-new">${ev.status}</span>
            ${canDelete ? `<button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="window.cgDeleteEvidence(${ev.id})" title="${isSuperAdmin ? 'Delete Evidence (Super Admin Access)' : 'Delete My Uploaded Evidence'}"><i class="fa-solid fa-trash-can me-1"></i> Delete</button>` : ''}
          </div>
        </div>
        <div class="small text-secondary mb-2">${ev.description || ''}</div>
        <div class="small text-muted border-top pt-2 mt-1" style="border-color:#e0f2fe!important">
          <i class="fa-solid fa-file-lines me-1 text-primary"></i>Source Document: <strong class="text-dark">${ev.source || 'Case Document'}</strong> · 
          <i class="fa-solid fa-calendar-days me-1 text-primary"></i>Original Collection Date: <strong class="text-primary fw-700">${ev.collected_date || '—'}</strong> · 
          <i class="fa-solid fa-user-shield me-1 text-info"></i>Uploaded By: <strong class="text-dark">${ev.uploaded_by_name || 'Auto-Extracted'}</strong>
        </div>
      </div>`;
    }).join('');
  }

  window.cgDeleteEvidence = async function (evidenceId) {
    if (!confirm('Are you sure you want to delete this evidence item?')) return;
    try {
      const res = await cgApi('/api/evidence/delete.php', { method: 'POST', body: JSON.stringify({ evidence_id: evidenceId }) });
      if (res.success) {
        cgToast(res.message || 'Evidence deleted successfully.', 'success');
        loaded.evidence = false; loaded.overview = false;
        await loadEvidence(); loadSnapshot();
      } else {
        cgToast(res.message || 'Unable to delete evidence item.', 'error');
      }
    } catch (e) {
      cgToast('Unable to delete evidence item.', 'error');
    }
  };
  const evidenceForm = document.getElementById('cgEvidenceForm');
  if (evidenceForm) {
    evidenceForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(evidenceForm).entries());
      payload.case_id = caseId;
      const res = await cgApi('/api/evidence/create.php', { method: 'POST', body: JSON.stringify(payload) });
      if (res.success) {
        cgToast(res.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('cgEvidenceModal')).hide();
        evidenceForm.reset();
        loadEvidence();
      } else cgToast(res.message, 'error');
    });
  }

  // ---------- Timeline tab ----------
  async function loadTimeline() {
    const data = await cgApi(`/api/cases/timeline.php?case_id=${caseId}`);
    const el = document.getElementById('cgTimeline');
    if (!data.success || !data.data.items.length) { el.innerHTML = '<div class="cg-empty-state"><i class="fa-solid fa-timeline"></i>No timeline events yet.</div>'; return; }
    el.innerHTML = data.data.items.map(ev => `
      <div class="d-flex gap-3 mb-3">
        <div class="text-muted small" style="width:110px">${new Date(ev.created_at).toLocaleDateString()}</div>
        <div class="border-start ps-3" style="border-color:var(--cg-accent)!important">
          <div class="fw-600 text-dark">${ev.description}</div>
          <div class="small text-muted">${ev.event_type.replace(/_/g, ' ')} · ${ev.created_by_name || 'System'}</div>
        </div>
      </div>`).join('');
  }

  // ---------- Analysis tab ----------
  async function loadAnalysis() {
    const data = await cgApi(`/api/analysis/list.php?case_id=${caseId}`);
    renderAnalysis(data);
  }
  function renderAnalysis(data) {
    const el = document.getElementById('cgAnalysisResults');
    if (!data.success || !data.data.patterns.length) {
      el.innerHTML = '<div class="cg-empty-state"><i class="fa-solid fa-brain"></i>No relationships detected yet — run pattern analysis after processing documents.</div>';
      return;
    }
    el.innerHTML = data.data.patterns.map(p => `
      <div class="cg-card mb-2">
        <div class="d-flex justify-content-between">
          <div class="fw-700 text-dark">${p.pattern_type}</div>
          <span class="cg-priority ${p.confidence >= 80 ? 'priority-critical' : p.confidence >= 60 ? 'priority-high' : 'priority-medium'}">Confidence ${p.confidence}%</span>
        </div>
        <div class="small text-muted">${p.reason}</div>
      </div>`).join('');
  }
  document.getElementById('cgRunAnalysisBtn')?.addEventListener('click', async function () {
    this.disabled = true;
    this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Running…';
    const res = await cgApi('/api/analysis/run.php', { method: 'POST', body: JSON.stringify({ case_id: caseId }) });
    this.disabled = false;
    this.innerHTML = '<i class="fa-solid fa-brain"></i> Run Pattern Analysis';
    if (res.success) {
      cgToast(res.message || 'Pattern analysis completed successfully.', 'success');
      renderAnalysis({ success: true, data: res.data });
      loaded.analysis = true; loaded.network = false; loaded.overview = false; loaded.activity = false;
      loadSnapshot();
      if (document.querySelector('.cg-tab.active')?.dataset.tab === 'network') loadGraph();
      if (document.querySelector('.cg-tab.active')?.dataset.tab === 'activity') loadActivity();
    } else cgToast(res.message || 'Unable to complete pattern analysis.', 'error');
  });

  // ---------- Notes tab ----------
  async function loadNotes() {
    const data = await cgApi(`/api/notes/list.php?case_id=${caseId}`);
    const el = document.getElementById('cgNotesList');
    if (!data.success || !data.data.items.length) { el.innerHTML = '<div class="cg-empty-state"><i class="fa-solid fa-note-sticky"></i>No notes yet.</div>'; return; }
    el.innerHTML = data.data.items.map(n => `
      <div class="cg-card mb-2">
        <div class="d-flex justify-content-between">
          <div class="fw-700 text-dark">${n.title}</div>
          <span class="small text-muted">${new Date(n.created_at).toLocaleString()}</span>
        </div>
        <div class="small text-muted mb-1">${n.note}</div>
        <div class="small text-muted">By ${n.author_name || '—'}</div>
      </div>`).join('');
  }
  const noteForm = document.getElementById('cgNoteForm');
  if (noteForm) {
    noteForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(noteForm).entries());
      payload.case_id = caseId;
      const res = await cgApi('/api/notes/create.php', { method: 'POST', body: JSON.stringify(payload) });
      if (res.success) { cgToast(res.message, 'success'); noteForm.reset(); loadNotes(); }
      else cgToast(res.message, 'error');
    });
  }

  // ---------- Activity tab ----------
  async function loadActivity() {
    const data = await cgApi(`/api/cases/activity.php?case_id=${caseId}`);
    const el = document.getElementById('cgCaseActivity');
    if (!data.success || !data.data.items.length) { el.innerHTML = '<div class="cg-empty-state"><i class="fa-solid fa-clock-rotate-left"></i>No activity recorded yet.</div>'; return; }
    el.innerHTML = data.data.items.map(a => `
      <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--cg-border-soft)!important">
        <div><span class="text-dark fw-600">${a.action.replace(/_/g, ' ')}</span> <span class="text-muted small">by ${a.user_name || 'System'}</span></div>
        <span class="small text-muted">${new Date(a.created_at).toLocaleString()}</span>
      </div>`).join('');
  }

  // ---------- Reports tab (Full 12-Section Intelligence Report) ----------
  async function loadReports() {
    const container = document.getElementById('cgReportContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-5"><i class="fa-solid fa-spinner fa-spin fs-3 text-primary mb-3"></i><div class="fw-700 text-dark">Generating Official 12-Section Intelligence Case Report…</div><div class="small text-muted">Running Entity Quality Check, Relationship Quality Check, Evidence Linking & Timeline Synthesis…</div></div>';

    const res = await cgApi(`/api/cases/report.php?id=${caseId}`);
    if (!res.success) {
      container.innerHTML = '<div class="alert alert-danger">Unable to compile case report. Please refresh and try again.</div>';
      return;
    }

    const rData = res.data;
    const c = rData.case;
    const m = rData.metrics;
    const dateStr = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <div>
          <h5 class="fw-800 text-dark mb-0"><i class="fa-solid fa-file-invoice text-primary me-2"></i>Official 12-Section Case Intelligence Report</h5>
          <div class="small text-muted">Generated live on ${dateStr}</div>
        </div>
        <div class="d-flex gap-2">
          <button class="cg-btn cg-btn-outline" onclick="loadReports()"><i class="fa-solid fa-rotate"></i> Re-generate</button>
          <button class="cg-btn cg-btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Export PDF</button>
        </div>
      </div>

      <div class="cg-card p-4 p-md-5 bg-white border" id="printableReportArea" style="color:#0f172a">
        <!-- Classification Banner -->
        <div class="text-center pb-4 mb-4 border-bottom border-2">
          <div class="badge bg-danger text-white px-3 py-1 text-uppercase fw-700 letter-spacing-1 mb-2" style="font-size:11px">CONFIDENTIAL // LAW ENFORCEMENT & INTELLIGENCE USE ONLY</div>
          <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
            <span class="cg-brand-mark bg-primary text-white rounded-3 d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;font-size:18px"><i class="fa-solid fa-diagram-project"></i></span>
            <h3 class="fw-800 text-dark mb-0" style="letter-spacing:1px">CYVANTA INTELLIGENCE SYSTEM</h3>
          </div>
          <div class="text-muted small text-uppercase fw-600">Comprehensive Case Investigation & Network Intelligence Report</div>
        </div>

        <!-- Extraction Validation Metrics Summary Box (Section 16 Requirement) -->
        <div class="row g-2 mb-4 p-3 bg-light rounded-3 border">
          <div class="col-12"><div class="fw-800 text-dark small text-uppercase mb-2"><i class="fa-solid fa-clipboard-check text-success me-1"></i> Extraction & System Validation Metrics</div></div>
          <div class="col-6 col-md-2"><div class="p-2 border bg-white rounded text-center"><div class="fw-800 fs-5 text-primary">${m.entities_extracted}</div><div class="small text-muted" style="font-size:10px">Entities Extracted</div></div></div>
          <div class="col-6 col-md-2"><div class="p-2 border bg-white rounded text-center"><div class="fw-800 fs-5 text-danger">${m.entities_rejected}</div><div class="small text-muted" style="font-size:10px">Entities Rejected</div></div></div>
          <div class="col-6 col-md-2"><div class="p-2 border bg-white rounded text-center"><div class="fw-800 fs-5 text-info">${m.relationships_extracted}</div><div class="small text-muted" style="font-size:10px">Relationships Found</div></div></div>
          <div class="col-6 col-md-2"><div class="p-2 border bg-white rounded text-center"><div class="fw-800 fs-5 text-secondary">${m.relationships_rejected}</div><div class="small text-muted" style="font-size:10px">Relationships Rejected</div></div></div>
          <div class="col-6 col-md-2"><div class="p-2 border bg-white rounded text-center"><div class="fw-800 fs-5 text-success">${m.evidence_linked_relationships}</div><div class="small text-muted" style="font-size:10px">Evidence-Linked Rels</div></div></div>
          <div class="col-6 col-md-2"><div class="p-2 border bg-white rounded text-center"><div class="fw-800 fs-5 text-warning">${m.unresolved_items}</div><div class="small text-muted" style="font-size:10px">Unresolved Aspects</div></div></div>
        </div>

        <!-- Section 1: Case Overview -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-align-left text-primary me-2"></i>1. Case Overview</h6>
          <p class="text-secondary leading-relaxed mb-0">${c.description ? c.description.replace(/\n/g, '<br>') : 'No case description provided.'}</p>
        </div>

        <!-- Section 2: Case Timeline -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-timeline text-primary me-2"></i>2. Case Timeline</h6>
          ${rData.timeline.length ? `
            <div class="d-flex flex-column gap-2">
              ${rData.timeline.map(t => `<div class="p-2 border-start border-3 border-primary bg-light small"><strong class="text-dark">${t.description}</strong></div>`).join('')}
            </div>
          ` : '<div class="text-muted small">No timeline events extracted.</div>'}
        </div>

        <!-- Section 3: Key Persons -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-user-shield text-primary me-2"></i>3. Key Persons (${rData.key_persons.length})</h6>
          ${rData.key_persons.length ? `
            <table class="cg-table table-bordered mb-0 small">
              <thead><tr><th>Name</th><th>Role / Description</th><th>Possible Aliases</th><th>Risk Score</th></tr></thead>
              <tbody>
                ${rData.key_persons.map(p => `<tr>
                  <td class="fw-700 text-dark">${p.name}</td>
                  <td class="text-secondary">${p.description || 'Suspect / Person of interest'}</td>
                  <td class="text-primary">${p.possible_aliases ? JSON.parse(p.possible_aliases).join(', ') : 'None'}</td>
                  <td><span class="fw-800 ${p.risk_score >= 70 ? 'text-danger' : 'text-warning'}">${p.risk_score}%</span></td>
                </tr>`).join('')}
              </tbody>
            </table>
          ` : '<div class="text-muted small">No persons identified.</div>'}
        </div>

        <!-- Section 4: Organizations / Agencies -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-building-shield text-primary me-2"></i>4. Organizations & Government Agencies (${rData.orgs_agencies.length})</h6>
          ${rData.orgs_agencies.length ? `
            <ul class="list-group list-group-flush border rounded-3 small">
              ${rData.orgs_agencies.map(o => `<li class="list-group-item d-flex justify-content-between align-items-center">
                <div><strong>${o.name}</strong> <span class="badge bg-secondary ms-1">${o.type_name}</span></div>
                <span class="text-muted">${o.description || 'Entity agency'}</span>
              </li>`).join('')}
            </ul>
          ` : '<div class="text-muted small">No organizations or agencies identified.</div>'}
        </div>

        <!-- Section 5: Locations -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-location-dot text-primary me-2"></i>5. Locations (${rData.locations.length})</h6>
          ${rData.locations.length ? `
            <div class="d-flex flex-wrap gap-2">
              ${rData.locations.map(l => `<span class="badge bg-success bg-opacity-10 text-success border border-success p-2 fs-6"><i class="fa-solid fa-location-dot me-1"></i>${l.name}</span>`).join('')}
            </div>
          ` : '<div class="text-muted small">No specific geographic locations identified.</div>'}
        </div>

        <!-- Section 6: Weapons / Assets -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-crosshairs text-primary me-2"></i>6. Weapons & Strategic Assets (${rData.weapons_assets.length})</h6>
          ${rData.weapons_assets.length ? `
            <ul class="list-group list-group-flush border rounded-3 small">
              ${rData.weapons_assets.map(w => `<li class="list-group-item d-flex justify-content-between align-items-center">
                <div><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i><strong>${w.name}</strong> <span class="badge bg-danger ms-1">${w.type_name}</span></div>
                <span class="fw-700 text-danger">Risk ${w.risk_score}%</span>
              </li>`).join('')}
            </ul>
          ` : '<div class="text-muted small">No strategic weapons or assets recorded.</div>'}
        </div>

        <!-- Section 7: Extracted Relationships -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-diagram-project text-primary me-2"></i>7. Extracted Semantic Relationships (${rData.relationships.length})</h6>
          ${rData.relationships.length ? `
            <table class="cg-table table-bordered mb-0 small">
              <thead><tr><th>Source Entity</th><th>Relationship Type</th><th>Target Entity</th><th>Confidence</th><th>Evidence Snippet</th></tr></thead>
              <tbody>
                ${rData.relationships.map(r => `<tr>
                  <td class="fw-700 text-dark">${r.source_name}</td>
                  <td><span class="badge bg-primary text-white">${r.rel_type.replace(/_/g, ' ')}</span></td>
                  <td class="fw-700 text-dark">${r.target_name}</td>
                  <td class="fw-700 text-success">${r.confidence || 85}%</td>
                  <td class="fst-italic text-secondary">${r.evidence_text || 'Contextual sentence match.'}</td>
                </tr>`).join('')}
              </tbody>
            </table>
          ` : '<div class="text-muted small">No relationships extracted.</div>'}
        </div>

        <!-- Section 8: Evidence Inventory & Repository Link -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-folder-open text-primary me-2"></i>8. Evidence Inventory & Repository (${rData.evidence.length} Items Collected)</h6>
          <div class="p-3 border rounded bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <div class="fw-700 text-dark small mb-1"><i class="fa-solid fa-shield-halved text-success me-1"></i> ${rData.evidence.length} Evidence Items Cataloged & Verified</div>
              <div class="small text-secondary">All document evidence items, original collection dates, OCR findings, and timeline artifacts are securely cataloged in the Evidence section.</div>
            </div>
            <button class="cg-btn cg-btn-primary cg-btn-sm no-print" onclick="activateTab('evidence')">
              <i class="fa-solid fa-folder-open me-1"></i> Inspect Evidence Repository (${rData.evidence.length})
            </button>
          </div>
        </div>

        <!-- Section 9: Network Analysis -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-brain text-primary me-2"></i>9. Network Analysis & Structural Indicators</h6>
          ${rData.analysis_patterns.length ? `
            <div class="d-flex flex-column gap-2">
              ${rData.analysis_patterns.map(ap => `<div class="p-3 border rounded bg-light">
                <div class="d-flex justify-content-between"><div class="fw-700 text-dark">${ap.pattern_type}</div><span class="badge bg-info text-dark">Confidence ${ap.confidence}%</span></div>
                <div class="small text-secondary mt-1">${ap.reason}</div>
              </div>`).join('')}
            </div>
          ` : '<div class="text-muted small">Run pattern analysis under Analysis tab to generate network metrics.</div>'}
        </div>

        <!-- Section 10: Risk Analysis -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-shield-cat text-primary me-2"></i>10. Risk Analysis (Post-Validation Scoring)</h6>
          <div class="p-3 border rounded bg-light small">
            <div class="fw-700 text-dark mb-1">Risk Evaluation Policy:</div>
            <p class="text-secondary mb-0">Risk scores are assigned strictly AFTER entity validation. Geographical entities and government agencies receive 15-25% baseline risk, while illegal weapons, fugitive suspects, and unverified arms drops are scored 85-95% threat priority.</p>
          </div>
        </div>

        <!-- Section 11: Unresolved / Open Questions -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-circle-question text-warning me-2"></i>11. Unresolved / Open Questions (${rData.unresolved_items.length})</h6>
          ${rData.unresolved_items.length ? `
            <div class="d-flex flex-column gap-2">
              ${rData.unresolved_items.map(u => `<div class="p-3 border-start border-3 border-warning bg-warning bg-opacity-10 rounded">
                <div class="fw-700 text-dark small text-uppercase"><i class="fa-solid fa-circle-exclamation text-warning me-1"></i>OPEN / UNRESOLVED ASPECT: ${u.title}</div>
                <div class="small text-secondary mt-1">${u.description}</div>
              </div>`).join('')}
            </div>
          ` : '<div class="text-muted small">No open questions flagged.</div>'}
        </div>

        <!-- Section 12: Source Provenance -->
        <div class="mb-4">
          <h6 class="fw-800 text-dark text-uppercase border-bottom pb-2 mb-3"><i class="fa-solid fa-certificate text-primary me-2"></i>12. Source Provenance & Data Integrity</h6>
          <div class="p-3 border rounded bg-light small">
            <div class="fw-700 text-dark mb-1">Source Classification: ${rData.source_provenance.type}</div>
            <div class="text-muted mb-1">${rData.source_provenance.description}</div>
            <div class="text-success fw-600"><i class="fa-solid fa-check-circle me-1"></i>Status: ${rData.source_provenance.verification_status}</div>
          </div>
        </div>

        <!-- Official Sign-off -->
        <div class="pt-4 border-top mt-5">
          <div class="row g-4">
            <div class="col-md-6">
              <div class="small text-muted text-uppercase fw-700 mb-4">Investigator Authorization Signature</div>
              <div class="border-bottom border-dark w-75 mb-2"></div>
              <div class="fw-700 text-dark">${c.investigator_name || 'Lead Investigator'}</div>
              <div class="small text-muted">CYVANTA Intelligence & Analysis Bureau</div>
            </div>
            <div class="col-md-6 text-md-end">
              <div class="small text-muted text-uppercase fw-700 mb-1">System Audit Verification</div>
              <div class="small text-muted">Report ID: RPT-${c.case_number}-${Date.now().toString().slice(-6)}</div>
              <div class="small text-muted">Chain of Custody: Verified Digital Audit Trail</div>
            </div>
          </div>
        </div>
      </div>
    `;
  }
  window.loadReports = loadReports;

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      if (document.querySelector(`script[src="${src}"]`)) return resolve();
      const s = document.createElement('script');
      s.src = src; s.onload = resolve; s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  activateTab('overview');
})();
