// assets/js/notif.js — Sistem notifikasi StudyBuddy

(function () {
  const POLL_INTERVAL = 15000; // 15 detik
  let lastUnread = 0;
  let notifOpen = false;

  // ── Buat elemen UI notifikasi ──
  function buildUI() {
    // Bell button di topbar (diinject via sidebar/topbar)
    const bellWrap = document.getElementById('notif-bell-wrap');
    if (!bellWrap) return;

    bellWrap.innerHTML = `
      <div class="notif-bell-btn" id="notif-btn" onclick="toggleNotifPanel()" title="Notifikasi">
        🔔
        <span class="notif-count-badge" id="notif-badge" style="display:none">0</span>
      </div>
      <div class="notif-panel" id="notif-panel">
        <div class="notif-panel-header">
          <span>🔔 Notifikasi</span>
          <button onclick="markAllRead()" class="notif-mark-all">Tandai semua dibaca</button>
        </div>
        <div class="notif-list" id="notif-list">
          <div class="notif-empty">Tidak ada notifikasi</div>
        </div>
      </div>
    `;

    // Tutup panel kalau klik di luar
    document.addEventListener('click', function (e) {
      const panel = document.getElementById('notif-panel');
      const btn   = document.getElementById('notif-btn');
      if (panel && !panel.contains(e.target) && !btn.contains(e.target)) {
        panel.classList.remove('open');
        notifOpen = false;
      }
    });
  }

  // ── Toggle panel notifikasi ──
  window.toggleNotifPanel = function () {
    const panel = document.getElementById('notif-panel');
    if (!panel) return;
    notifOpen = !notifOpen;
    panel.classList.toggle('open', notifOpen);
    if (notifOpen) poll(); // refresh saat dibuka
  };

  // ── Tandai semua dibaca ──
  window.markAllRead = function () {
    fetch('notif_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'all=1'
    }).then(() => {
      document.querySelectorAll('.notif-item').forEach(el => el.classList.remove('unread'));
      updateBadge(0);
    });
  };

  // ── Tandai satu notif dibaca ──
  window.markOneRead = function (id, url) {
    fetch('notif_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + id
    }).then(() => {
      const el = document.getElementById('notif-item-' + id);
      if (el) el.classList.remove('unread');
      const badge = document.getElementById('notif-badge');
      const cur = parseInt(badge.textContent || '0');
      updateBadge(Math.max(0, cur - 1));
      if (url) window.location.href = url;
    });
  };

  // ── Update badge angka ──
  function updateBadge(count) {
    const badge = document.getElementById('notif-badge');
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
  }

  // ── Render daftar notifikasi ──
  function renderNotifs(notifs) {
    const list = document.getElementById('notif-list');
    if (!list) return;

    if (!notifs || notifs.length === 0) {
      list.innerHTML = '<div class="notif-empty">✨ Tidak ada notifikasi</div>';
      return;
    }

    const icons = { chat: '💬', deadline: '⏰', share: '📤', friend: '👥' };
    const colors = {
      chat:     '#EEF2FF',
      deadline: '#FFF5F5',
      share:    '#F0FFF4',
      friend:   '#FFFAF0',
    };

    list.innerHTML = notifs.map(n => {
      const timeStr = timeAgo(n.created_at);
      const isUnread = n.is_read == 0;
      return `
        <div class="notif-item ${isUnread ? 'unread' : ''}" id="notif-item-${n.id}"
             onclick="markOneRead(${n.id}, '${n.url || ''}')">
          <div class="notif-icon" style="background:${colors[n.type] || '#EEF2FF'}">${icons[n.type] || '🔔'}</div>
          <div class="notif-body">
            <div class="notif-title">${escHtml(n.title)}</div>
            ${n.body ? `<div class="notif-desc">${escHtml(n.body)}</div>` : ''}
            <div class="notif-time">${timeStr}</div>
          </div>
        </div>`;
    }).join('');
  }

  // ── Toast notifikasi (muncul di pojok layar) ──
  function showToast(notif) {
    const icons = { chat: '💬', deadline: '⏰', share: '📤', friend: '👥' };
    const toast = document.createElement('div');
    toast.className = 'notif-toast';
    toast.innerHTML = `
      <div class="toast-icon">${icons[notif.type] || '🔔'}</div>
      <div class="toast-body">
        <div class="toast-title">${escHtml(notif.title)}</div>
        ${notif.body ? `<div class="toast-desc">${escHtml(notif.body)}</div>` : ''}
      </div>
      <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
    `;
    toast.style.cursor = 'pointer';
    toast.addEventListener('click', function (e) {
      if (e.target.classList.contains('toast-close')) return;
      markOneRead(notif.id, notif.url);
      toast.remove();
    });
    document.body.appendChild(toast);
    // Animasi masuk
    setTimeout(() => toast.classList.add('show'), 10);
    // Auto hilang setelah 5 detik
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 400);
    }, 5000);
  }

  // ── Polling ke server ──
  function poll() {
    fetch('notif_poll.php')
      .then(r => r.json())
      .then(data => {
        if (!data || data.error) return;

        const newUnread = data.unread_count || 0;
        updateBadge(newUnread);
        renderNotifs(data.notifications);

        // Tampilkan toast hanya untuk notif baru (unread lebih banyak dari sebelumnya)
        if (newUnread > lastUnread && data.notifications) {
          const newNotifs = data.notifications.filter(n => n.is_read == 0);
          // Toast hanya untuk yang paling baru (maks 3)
          newNotifs.slice(0, 3).forEach(n => showToast(n));
        }
        lastUnread = newUnread;
      })
      .catch(() => {}); // silent fail kalau koneksi putus
  }

  // ── Helper ──
  function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function timeAgo(dt) {
    const diff = Math.floor((Date.now() - new Date(dt).getTime()) / 1000);
    if (diff < 60)    return 'Baru saja';
    if (diff < 3600)  return Math.floor(diff / 60) + ' mnt lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
    const d = new Date(dt);
    return d.getDate() + ' ' + ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][d.getMonth()];
  }

  // ── Init ──
  document.addEventListener('DOMContentLoaded', function () {
    buildUI();
    poll(); // pertama kali langsung
    setInterval(poll, POLL_INTERVAL);
  });

})();
