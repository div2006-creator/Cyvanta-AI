(function () {
  const search = document.getElementById('cgUserSearch');
  const roleFilter = document.getElementById('cgUserRoleFilter');
  const statusFilter = document.getElementById('cgUserStatusFilter');

  function roleLabel(r) { return r.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()); }

  async function load() {
    const params = new URLSearchParams({ q: search.value.trim(), role: roleFilter.value, status: statusFilter.value });
    const data = await cgApi('/api/users/list.php?' + params.toString());
    if (!data.success) return;
    const tbody = document.getElementById('cgUsersTableBody');
    const empty = document.getElementById('cgUsersEmpty');
    empty.hidden = data.data.items.length > 0;
    tbody.innerHTML = data.data.items.map(u => `
      <tr>
        <td class="fw-700 text-dark">${u.full_name}</td>
        <td class="text-muted">@${u.username}</td>
        <td><span class="cg-badge-status status-new">${roleLabel(u.role)}</span></td>
        <td class="text-muted">${u.department || '—'}</td>
        <td>${u.is_active == 1 ? '<span class="cg-badge-status status-resolved">Active</span>' : '<span class="cg-badge-status status-critical">Disabled</span>'}</td>
        <td class="text-muted small">${u.last_login ? new Date(u.last_login).toLocaleString() : 'Never'}</td>
        <td class="d-flex gap-1 flex-wrap">
          <button class="cg-btn cg-btn-outline cg-btn-sm" onclick="cgToggleUser(${u.id}, ${u.is_active == 1 ? 0 : 1})" title="${u.is_active == 1 ? 'Disable' : 'Enable'}">
            <i class="fa-solid ${u.is_active == 1 ? 'fa-user-slash' : 'fa-user-check'}"></i>
          </button>
          <button class="cg-btn cg-btn-outline cg-btn-sm" onclick="cgResetUserPassword(${u.id})" title="Reset Password"><i class="fa-solid fa-key"></i></button>
        </td>
      </tr>`).join('');
  }

  window.cgToggleUser = async (id, active) => {
    const res = await cgApi('/api/users/toggle-status.php', { method: 'POST', body: JSON.stringify({ id, is_active: active }) });
    cgToast(res.message, res.success ? 'success' : 'error');
    load();
  };
  window.cgResetUserPassword = async (id) => {
    if (!confirm('Generate a new temporary password for this user?')) return;
    const res = await cgApi('/api/users/reset-password.php', { method: 'POST', body: JSON.stringify({ id }) });
    if (res.success) alert('Temporary password: ' + res.data.temp_password + '\n\nShare this securely with the user — they will be required to change it on next login.');
    else cgToast(res.message, 'error');
    load();
  };

  let t; search.addEventListener('input', () => { clearTimeout(t); t = setTimeout(load, 300); });
  [roleFilter, statusFilter].forEach(el => el.addEventListener('change', load));

  document.getElementById('cgUserForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(this).entries());
    const res = await cgApi('/api/users/create.php', { method: 'POST', body: JSON.stringify(payload) });
    if (res.success) {
      cgToast(res.message, 'success');
      bootstrap.Modal.getInstance(document.getElementById('cgUserModal')).hide();
      this.reset();
      load();
    } else cgToast(res.message, 'error');
  });

  load();
})();
