<?php
/**
 * NOXARA - Footer + JS + Toast + Popup System
 */
?>
  </main><!-- end .page-content -->
</div><!-- end .main-wrapper -->

<!-- ── MOBILE BOTTOM NAV ── -->
<?php include INCLUDES_PATH . '/mobile_nav.php'; ?>

<!-- ── GLOBAL TOAST CONTAINER ── -->
<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<!-- ── POPUP MODAL ── -->
<div class="popup-overlay" id="popupOverlay" style="display:none" onclick="closePopup()">
  <div class="popup-modal" onclick="event.stopPropagation()">
    <button class="popup-close" onclick="closePopup()">✕</button>
    <div class="popup-icon" id="popupIcon"></div>
    <h3 class="popup-title" id="popupTitle"></h3>
    <p class="popup-message" id="popupMessage"></p>
    <button class="btn-primary popup-btn" onclick="closePopup()">Oke, Mengerti</button>
  </div>
</div>

<!-- ── BOTTOM SHEET ── -->
<div class="bottom-sheet-overlay" id="bsOverlay" onclick="closeBottomSheet()"></div>
<div class="bottom-sheet" id="bottomSheet">
  <div class="bottom-sheet-handle"></div>
  <div class="bottom-sheet-content" id="bsContent"></div>
</div>

<!-- ── CONFIRM MODAL ── -->
<div class="modal-overlay" id="confirmOverlay" style="display:none">
  <div class="modal-box">
    <h3 id="confirmTitle">Konfirmasi</h3>
    <p id="confirmMessage"></p>
    <div class="modal-footer">
      <button class="btn-ghost" onclick="closeConfirm()">Batal</button>
      <button class="btn-danger" id="confirmBtn">Ya, Lanjutkan</button>
    </div>
  </div>
</div>

<!-- ── SCRIPTS ── -->
<script src="<?= ASSETS_URL ?>/js/main.js?v=<?= filemtime(ASSETS_PATH.'/js/main.js') ?>"></script>
<script src="<?= ASSETS_URL ?>/js/animations.js?v=1"></script>

<script>
// CSRF token for AJAX
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
const BASE_URL   = '<?= BASE_URL ?>';

// ── Popup system ──────────────────────────────────────
function showPopup(icon, title, message) {
  document.getElementById('popupIcon').textContent = icon || '✨';
  document.getElementById('popupTitle').textContent = title;
  document.getElementById('popupMessage').textContent = message;
  const overlay = document.getElementById('popupOverlay');
  overlay.style.display = 'flex';
  overlay.querySelector('.popup-modal').classList.add('popup-zoom-in');
}
function closePopup() {
  const overlay = document.getElementById('popupOverlay');
  overlay.querySelector('.popup-modal').classList.remove('popup-zoom-in');
  overlay.querySelector('.popup-modal').classList.add('popup-zoom-out');
  setTimeout(() => {
    overlay.style.display = 'none';
    overlay.querySelector('.popup-modal').classList.remove('popup-zoom-out');
    // Show next pending popup
    if (window.__pendingPopups && window.__pendingPopups.length > 0) {
      const next = window.__pendingPopups.shift();
      showPopup(next.icon, next.title, next.message);
    }
  }, 250);
}

// Auto-show server-pushed popups
if (window.__pendingPopups && window.__pendingPopups.length > 0) {
  const first = window.__pendingPopups.shift();
  setTimeout(() => showPopup(first.icon, first.title, first.message), 500);
}

// ── Toast notification ────────────────────────────────
function showToast(message, type = 'info', duration = 4000) {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = `toast toast-${type} toast-slide-in`;
  toast.innerHTML = `<span>${message}</span><button onclick="this.parentElement.remove()">✕</button>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.classList.add('toast-slide-out');
    setTimeout(() => toast.remove(), 400);
  }, duration);
}

// Auto-dismiss flash toasts
document.querySelectorAll('.toast-auto').forEach(t => {
  setTimeout(() => {
    t.classList.add('toast-slide-out');
    setTimeout(() => t.remove(), 400);
  }, 5000);
});

// ── Notification panel ────────────────────────────────
function toggleNotifPanel() {
  const panel = document.getElementById('notifPanel');
  const overlay = document.getElementById('notifOverlay');
  const isOpen = panel.classList.contains('open');
  if (isOpen) {
    closeNotifPanel();
  } else {
    panel.classList.add('open');
    overlay.classList.add('active');
    loadNotifications();
  }
}
function closeNotifPanel() {
  document.getElementById('notifPanel').classList.remove('open');
  document.getElementById('notifOverlay').classList.remove('active');
}
function loadNotifications() {
  fetch(`${BASE_URL}/api/notification.php?action=list&limit=10`, {
    headers: {'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest'}
  })
  .then(r => r.json())
  .then(data => {
    const list = document.getElementById('notifPanelList');
    if (!data.success || !data.data.notifications?.length) {
      list.innerHTML = '<div class="notif-empty">Belum ada notifikasi</div>';
      return;
    }
    list.innerHTML = data.data.notifications.map(n => `
      <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick="readNotif(${n.id}, this)">
        <div class="notif-item-icon notif-type-${n.type}">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/></svg>
        </div>
        <div class="notif-item-body">
          <div class="notif-item-title">${escHtml(n.title)}</div>
          <div class="notif-item-msg">${escHtml(n.message)}</div>
          <div class="notif-item-time">${n.time_ago}</div>
        </div>
      </div>`).join('');
  })
  .catch(() => {
    document.getElementById('notifPanelList').innerHTML = '<div class="notif-empty">Gagal memuat notifikasi</div>';
  });
}
function readNotif(id, el) {
  el.classList.remove('unread');
  fetch(`${BASE_URL}/api/notification.php?action=read&id=${id}`, {
    headers: {'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest'}
  });
}
function markAllRead() {
  fetch(`${BASE_URL}/api/notification.php?action=read_all`, {
    headers: {'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest'}
  }).then(() => {
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
    document.querySelectorAll('.badge-dot').forEach(el => el.remove());
    showToast('Semua notifikasi ditandai dibaca.', 'success');
  });
}

// ── Sidebar ───────────────────────────────────────────
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  sidebar.classList.toggle('open');
  overlay.classList.toggle('active');
  document.body.classList.toggle('sidebar-open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
  document.body.classList.remove('sidebar-open');
}

// ── Bottom Sheet ──────────────────────────────────────
function openBottomSheet(html) {
  document.getElementById('bsContent').innerHTML = html;
  document.getElementById('bottomSheet').classList.add('open');
  document.getElementById('bsOverlay').classList.add('active');
}
function closeBottomSheet() {
  document.getElementById('bottomSheet').classList.remove('open');
  document.getElementById('bsOverlay').classList.remove('active');
}

// ── Confirm dialog ────────────────────────────────────
function showConfirm(title, message, callback) {
  document.getElementById('confirmTitle').textContent = title;
  document.getElementById('confirmMessage').textContent = message;
  document.getElementById('confirmOverlay').style.display = 'flex';
  document.getElementById('confirmBtn').onclick = () => { closeConfirm(); callback(); };
}
function closeConfirm() {
  document.getElementById('confirmOverlay').style.display = 'none';
}

// ── HTML escape ───────────────────────────────────────
function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML;
}

// ── Count-up animation ────────────────────────────────
function countUp(el, target, duration = 1000) {
  const start     = 0;
  const startTime = performance.now();
  const step = (timestamp) => {
    const elapsed  = timestamp - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const eased    = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.floor(start + (target - start) * eased).toLocaleString('id-ID');
    if (progress < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

// Auto init count-up
document.querySelectorAll('[data-countup]').forEach(el => {
  const target = parseFloat(el.dataset.countup) || 0;
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) { countUp(el, target); obs.unobserve(el); }
    });
  });
  obs.observe(el);
});

// ── Swipe gesture for sidebar ─────────────────────────
let touchStartX = 0;
document.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].clientX; }, { passive: true });
document.addEventListener('touchend', e => {
  const diff = e.changedTouches[0].clientX - touchStartX;
  if (touchStartX < 30 && diff > 60) toggleSidebar();
  else if (diff < -60 && document.getElementById('sidebar').classList.contains('open')) closeSidebar();
}, { passive: true });
</script>
</body>
</html>
