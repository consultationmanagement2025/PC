// Lightweight announcements loader for public portal
(function(){
  function loadAnnouncements(){
    let anns = [];
    try{
      const raw = localStorage.getItem('llrm_announcements');
      if(raw) anns = JSON.parse(raw);
    }catch(e){ console.warn('Failed to parse announcements', e); }

    const feed = document.getElementById('announcements-feed');
    const stat = document.getElementById('stat-announcements');
    if(!feed) return;

    const published = anns.filter(a => a.published !== false);
    if(stat) stat.textContent = published.length;

    if(published.length === 0){
      feed.innerHTML = '<div class="empty-state">No announcements at the moment.</div>';
      return;
    }

    feed.innerHTML = published.map(a => {
      return `
        <div class="announcement-card" style="background:white;padding:12px;border-radius:8px;margin-bottom:10px;box-shadow:0 1px 4px rgba(0,0,0,0.04)">
          <div style="font-weight:700;margin-bottom:6px">${escapeHtml(a.title)}</div>
          <div style="color:#6b7280;font-size:13px;margin-bottom:8px">${escapeHtml(a.message)}</div>
          <div style="font-size:12px;color:#9ca3af">${new Date(a.createdAt).toLocaleString()}</div>
        </div>
      `.trim();
    }).join('');
  }

  function escapeHtml(str){
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  document.addEventListener('DOMContentLoaded', loadAnnouncements);
  // refresh periodically
  setInterval(loadAnnouncements, 30000);
})();
