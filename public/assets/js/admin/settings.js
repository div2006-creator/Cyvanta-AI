(async function () {
  const form = document.getElementById('cgSettingsForm');
  const data = await cgApi('/api/settings/get.php');
  if (data.success) {
    const s = data.data.settings;
    Object.keys(s).forEach(key => {
      const el = form.elements[key];
      if (!el) return;
      if (el.type === 'checkbox') el.checked = s[key] === '1';
      else el.value = s[key];
    });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {};
    new FormData(form).forEach((v, k) => payload[k] = v);
    // unchecked checkboxes are omitted by FormData; force them to '0'
    form.querySelectorAll('input[type=checkbox]').forEach(cb => { if (!cb.checked) payload[cb.name] = '0'; });
    const res = await cgApi('/api/settings/update.php', { method: 'POST', body: JSON.stringify(payload) });
    cgToast(res.message, res.success ? 'success' : 'error');
  });
})();
