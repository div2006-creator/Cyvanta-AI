/**
 * CYVANTA - core client behaviours shared by every page.
 */
(function () {
  const CG = window.CG || {};

  function toast(message, type = 'info') {
    const stack = document.getElementById('cgToastStack');
    if (!stack) return;
    const el = document.createElement('div');
    el.className = `cg-toast ${type}`;
    el.textContent = message;
    stack.appendChild(el);
    setTimeout(() => el.remove(), 4500);
  }
  window.cgToast = toast;

  window.cgFormatRisk = function (riskScore, typeName, name, description) {
    const typeClean = (typeName || '').trim().toLowerCase();
    const nameClean = (name || '').trim().toLowerCase();

    // Front-end vehicle, court, agency, location safety check
    const isVehicle = /\b(tata|safari|maruti|suzuki|toyota|fortuner|innova|honda|city|civic|hyundai|creta|verna|mahindra|scorpio|bolero|thar|bmw|audi|mercedes|benz|ford|chevrolet|nissan|car|cars|vehicle|vehicles|suv|sedan|truck|trucks|van|vans|motorcycle|motorcycles|bike|bikes|jeep)\b/i.test(nameClean);
    const isCourtOrAgency = /\b(court|police|cbi|investigation agency|interpol|crime branch|special cell|tribunal)\b/i.test(nameClean) && !/\b(tamarind court)\b/i.test(nameClean);

    let isEligible = (typeClean === 'person') && !isVehicle && !isCourtOrAgency && riskScore !== null && riskScore !== undefined && riskScore !== '' && (parseInt(riskScore, 10) >= 0);

    if (isEligible) {
      const text = (nameClean + ' ' + (description || '')).toLowerCase();
      if (text.match(/\b(victim|deceased|witness|eyewitness|judge|justice|advocate|lawyer|counsel|prosecutor|investigator|officer)\b/i)) {
        if (!text.match(/\b(accused|suspect|prime suspect|involved|co-accused|conspirator|mastermind)\b/i)) {
          isEligible = false;
        }
      }
    }

    if (!isEligible) {
      return {
        isEligible: false,
        score: null,
        scoreText: 'N/A',
        levelText: 'Not Applicable',
        badgeClass: 'priority-low bg-secondary text-white'
      };
    }

    const s = parseInt(riskScore, 10);
    let level = 'LOW';
    let badgeClass = 'priority-low';
    if (s > 80) { level = 'CRITICAL'; badgeClass = 'priority-critical'; }
    else if (s > 60) { level = 'HIGH'; badgeClass = 'priority-high'; }
    else if (s > 30) { level = 'MEDIUM'; badgeClass = 'priority-medium'; }

    return {
      isEligible: true,
      score: s,
      scoreText: `${s}%`,
      levelText: level,
      badgeClass: badgeClass
    };
  };

  async function api(path, options = {}) {
    const opts = Object.assign({ headers: {} }, options);
    opts.headers = Object.assign({}, opts.headers);
    if (CG.csrfToken) opts.headers['X-CSRF-Token'] = CG.csrfToken;
    if (opts.body && !(opts.body instanceof FormData)) opts.headers['Content-Type'] = 'application/json';

    try {
      const res = await fetch(CG.baseUrl + path, opts);
      const raw = await res.text();
      let data;
      try { data = raw ? JSON.parse(raw) : null; }
      catch (e) {
        console.error('CYVANTA API returned non-JSON data', {path, status: res.status, body: raw.slice(0,500)});
        return { success:false, message: res.ok ? 'The server returned an invalid response.' : `Server request failed (${res.status}).`, data:{}, httpStatus:res.status };
      }
      if (!data || typeof data !== 'object') return {success:false,message:'The server returned an invalid response.',data:{},httpStatus:res.status};
      data.httpStatus=res.status;
      if (!res.ok && data.success !== false) {
        data.success=false;
        data.message=data.message||`Request failed (${res.status}).`;
      }
      return data;
    } catch (e) {
      console.error('CYVANTA API request failed', path, e);
      return {success:false,message:'Unable to reach the server. Please try again.',data:{},networkError:true};
    }
  }
  window.cgApi = api;

  // Mobile sidebar toggle
  const menuBtn = document.getElementById('cgMobileMenuBtn');
  if (menuBtn) {
    menuBtn.addEventListener('click', () => document.body.classList.toggle('cg-sidebar-open'));
  }

  // Notifications
  const notifBtn = document.getElementById('cgNotifBtn');
  const notifDropdown = document.getElementById('cgNotifDropdown');
  const notifBadge = document.getElementById('cgNotifBadge');

  async function loadNotifications() {
    if (!notifDropdown) return;
    const data = await api('/api/notifications/list.php?limit=10');
    if (!data.success) return;
    const items = data.data.items || [];
    const unread = data.data.unread_count || 0;
    if (notifBadge) {
      notifBadge.hidden = unread === 0;
      notifBadge.textContent = unread > 9 ? '9+' : unread;
    }
    let html = '<div class="cg-notif-header"><span><i class="fa-solid fa-bell me-1 text-primary"></i> Notifications</span><a href="' + CG.baseUrl + '/alerts.php" class="small text-primary text-decoration-none fw-600">View All</a></div>';
    if (items.length) {
      html += items.map(n => `
        <a href="${n.link ? (n.link.startsWith('http') ? n.link : CG.baseUrl + '/' + n.link) : '#'}" class="cg-notif-item d-block text-decoration-none">
          <div class="title">${n.title}</div>
          <div class="text-secondary small mb-1">${n.message}</div>
          <div class="time"><i class="fa-regular fa-clock me-1"></i>${n.time_ago}</div>
        </a>`).join('');
    } else {
      html += '<div class="cg-notif-item text-center text-muted py-4">No notifications yet.</div>';
    }
    notifDropdown.innerHTML = html;
  }

  if (notifBtn) {
    notifBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      notifDropdown.classList.toggle('show');
      if (notifDropdown.classList.contains('show')) {
        api('/api/notifications/mark-read.php', { method: 'POST' }).then(loadNotifications);
      }
    });
    document.addEventListener('click', (e) => {
      if (notifDropdown && !notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
        notifDropdown.classList.remove('show');
      }
    });
    loadNotifications();
    setInterval(loadNotifications, 15000);
  }

  // Global search
  const searchInput = document.getElementById('cgGlobalSearch');
  const searchResults = document.getElementById('cgSearchResults');
  let searchTimer = null;
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      const q = searchInput.value.trim();
      if (q.length < 2) { searchResults.classList.remove('show'); return; }
      searchTimer = setTimeout(async () => {
        const data = await api('/api/search.php?q=' + encodeURIComponent(q));
        if (!data.success) return;
        const items = data.data.items || [];
        searchResults.innerHTML = items.length
          ? items.map(i => `<a href="${CG.baseUrl}/${i.link}" class="cg-search-result-item d-block text-decoration-none text-reset">
              <div class="type">${i.type}</div><div>${i.label}</div><div class="text-muted small">${i.meta || ''}</div>
            </a>`).join('')
          : '<div class="cg-search-result-item">No matches found.</div>';
        searchResults.classList.add('show');
      }, 300);
    });
    document.addEventListener('click', (e) => {
      if (!searchResults.contains(e.target) && e.target !== searchInput) searchResults.classList.remove('show');
    });
  }

  /**
   * Real-time bridge: tries a native WebSocket connection to the CYVANTA
   * WebSocket server (see /websocket/server.php). If it cannot connect
   * (server not running / blocked network), it falls back to lightweight
   * AJAX polling of /api/notifications/activity-feed.php so the UI still
   * behaves correctly without a live socket.
   */
  const wsStatusEl = document.getElementById('cgWsStatus');
  function setWsStatus(online) {
    if (!wsStatusEl) return;
    wsStatusEl.classList.toggle('online', online);
    wsStatusEl.classList.toggle('offline', !online);
    wsStatusEl.title = online ? 'Live updates connected' : 'Live updates unavailable — using periodic refresh';
  }

  function startPollingFallback() {
    setWsStatus(false);
    setInterval(loadNotifications, 10000);
  }

  window.cgRealtime = { handlers: {} };
  window.cgOnRealtime = (event, cb) => { window.cgRealtime.handlers[event] = cb; };

  try {
    if (CG.wsUrl) {
      const ws = new WebSocket(CG.wsUrl);
      let opened = false;
      ws.onopen = () => { opened = true; setWsStatus(true); };
      ws.onmessage = (evt) => {
        try {
          const msg = JSON.parse(evt.data);
          if (msg.event === 'NOTIFICATION_CREATED') loadNotifications();
          if (window.cgRealtime.handlers[msg.event]) window.cgRealtime.handlers[msg.event](msg.payload);
        } catch (e) { /* ignore malformed frame */ }
      };
      ws.onerror = () => { if (!opened) startPollingFallback(); };
      ws.onclose = () => { setWsStatus(false); };
      setTimeout(() => { if (!opened) startPollingFallback(); }, 2500);
    } else {
      startPollingFallback();
    }
  } catch (e) {
    startPollingFallback();
  }
})();
