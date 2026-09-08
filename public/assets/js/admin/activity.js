(function () {
  let latestId = 0;
  const feed = document.getElementById('cgActivityFeed');
  let items = [];

  async function poll() {
    const data = await cgApi(`/api/admin/activity-feed.php?since_id=${latestId}`);
    if (!data.success) return;
    if (data.data.items.length) {
      items = [...data.data.items, ...items].slice(0, 100);
      latestId = data.data.latest_id;
      render();
    } else if (items.length === 0) {
      feed.innerHTML = '<div class="cg-empty-state"><i class="fa-solid fa-bolt"></i>No activity recorded yet.</div>';
    }
  }

  function render() {
    feed.innerHTML = items.map(i => `
      <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--cg-border-soft)!important">
        <span class="text-white">${i.description}</span><span class="small text-muted">${i.time_ago}</span>
      </div>`).join('');
  }

  poll();
  setInterval(poll, 5000);
})();
