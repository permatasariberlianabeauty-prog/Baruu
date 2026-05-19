<?php
/**
 * NOXARA - Top Header Bar (include di setiap halaman member)
 * Requires: $pageTitle, session sudah dimulai
 */
$_currentUser  = getSessionUser();
$_userId       = $_currentUser['id'] ?? 0;
$_unreadNotif  = $_userId ? getUnreadNotifCount($_userId) : 0;
$_unreadChat   = $_userId ? getUnreadChatCount($_userId) : 0;
$_wallet       = $_userId ? getUserWallet($_userId) : null;
$_vipInfo      = $_userId ? getUserVipInfo($_userId) : null;
$_marqueeItems = getMarqueeItems();
$_csrfToken    = getCsrfToken();

// Popup triggers
$_popups = [];
if (!empty($_SESSION['show_popup'])) {
    $popupKey = $_SESSION['show_popup'];
    unset($_SESSION['show_popup']);
    $p = getPopup($popupKey);
    if ($p) $_popups[] = $p;
}
if (!empty($_SESSION['popup_vip_upgrade'])) {
    $vipUpgrade = $_SESSION['popup_vip_upgrade'];
    unset($_SESSION['popup_vip_upgrade']);
    $p = getPopup('vip_upgrade');
    if ($p) { $p['message'] = 'Selamat! Anda naik ke VIP ' . $vipUpgrade['level'] . ' (' . $vipUpgrade['name'] . ')!'; $_popups[] = $p; }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= e($_currentUser['theme'] ?? 'dark') ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="theme-color" content="#0A0E1A">
<meta name="csrf-token" content="<?= e($_csrfToken) ?>">
<title><?= e($pageTitle ?? 'Dashboard') ?> — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&family=Orbitron:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(ASSETS_PATH.'/css/style.css') ?>">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/animations.css?v=1">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/mobile.css?v=1">
</head>
<body class="app-body">

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ── SIDEBAR ── -->
<?php include INCLUDES_PATH . '/sidebar.php'; ?>

<!-- ── MAIN WRAPPER ── -->
<div class="main-wrapper" id="mainWrapper">

  <!-- ── TOP HEADER ── -->
  <header class="topbar" id="topbar">
    <div class="topbar-left">
      <button class="btn-menu" onclick="toggleSidebar()" aria-label="Menu">
        <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
          <rect x="2" y="5" width="18" height="2" rx="1" fill="currentColor"/>
          <rect x="2" y="10" width="12" height="2" rx="1" fill="currentColor"/>
          <rect x="2" y="15" width="18" height="2" rx="1" fill="currentColor"/>
        </svg>
      </button>
      <a href="<?= BASE_URL ?>/pages/dashboard.php" class="topbar-brand">
        <span class="brand-logo">
          <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
            <polygon points="14,2 26,8 26,20 14,26 2,20 2,8" fill="none" stroke="url(#lg1)" stroke-width="2"/>
            <polygon points="14,7 21,11 21,17 14,21 7,17 7,11" fill="url(#lg1)" opacity=".15"/>
            <path d="M9 11l5 3 5-3M14 14v7" stroke="url(#lg1)" stroke-width="1.5" stroke-linecap="round"/>
            <defs>
              <linearGradient id="lg1" x1="0" y1="0" x2="28" y2="28">
                <stop stop-color="#00D4FF"/>
                <stop offset="1" stop-color="#7B2FFF"/>
              </linearGradient>
            </defs>
          </svg>
        </span>
        <span class="brand-name orbitron"><?= SITE_NAME ?></span>
      </a>
    </div>

    <div class="topbar-right">
      <!-- Notification Bell -->
      <button class="topbar-icon-btn notif-btn" id="notifBtn" onclick="toggleNotifPanel()" aria-label="Notifikasi">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M12 2C8.13 2 5 5.13 5 9v6l-2 2v1h18v-1l-2-2V9c0-3.87-3.13-7-7-7z" stroke="currentColor" stroke-width="1.8" fill="none"/>
          <path d="M10 19c0 1.1.9 2 2 2s2-.9 2-2" stroke="currentColor" stroke-width="1.8" fill="none"/>
        </svg>
        <?php if ($_unreadNotif > 0): ?>
        <span class="badge-dot"><?= $_unreadNotif > 99 ? '99+' : $_unreadNotif ?></span>
        <?php endif; ?>
      </button>

      <!-- Chat icon -->
      <a href="<?= BASE_URL ?>/pages/chat.php" class="topbar-icon-btn" aria-label="Chat">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M4 4h16c1.1 0 2 .9 2 2v10c0 1.1-.9 2-2 2H6l-4 4V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="1.8" fill="none"/>
        </svg>
        <?php if ($_unreadChat > 0): ?>
        <span class="badge-dot"><?= $_unreadChat ?></span>
        <?php endif; ?>
      </a>

      <!-- User Avatar -->
      <a href="<?= BASE_URL ?>/pages/profile.php" class="topbar-avatar" aria-label="Profil">
        <img src="<?= avatarUrl($_currentUser['avatar'] ?? null) ?>" alt="Avatar" onerror="this.src='<?= ASSETS_URL ?>/img/default-avatar.png'">
        <?php if ($_vipInfo): ?>
        <span class="avatar-vip-badge vip-color-<?= $_vipInfo['level'] ?>">V<?= $_vipInfo['level'] ?></span>
        <?php endif; ?>
      </a>
    </div>
  </header>

  <!-- ── MARQUEE ── -->
  <?php if (!empty($_marqueeItems)): ?>
  <div class="marquee-bar">
    <div class="marquee-inner">
      <div class="marquee-track" id="marqueeTrack">
        <?php foreach ($_marqueeItems as $item): ?>
        <span class="marquee-item"><?= $item ?>&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;</span>
        <?php endforeach; ?>
        <?php foreach ($_marqueeItems as $item): // duplicate for seamless loop ?>
        <span class="marquee-item"><?= $item ?>&nbsp;&nbsp;&nbsp;•&nbsp;&nbsp;&nbsp;</span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── NOTIFICATION PANEL ── -->
  <div class="notif-panel" id="notifPanel">
    <div class="notif-panel-header">
      <h3>Notifikasi</h3>
      <div class="notif-panel-actions">
        <?php if ($_unreadNotif > 0): ?>
        <button class="btn-text-sm" onclick="markAllRead()">Tandai Semua Dibaca</button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/pages/notifications.php" class="btn-text-sm">Lihat Semua</a>
      </div>
    </div>
    <div class="notif-panel-list" id="notifPanelList">
      <div class="notif-loading">
        <div class="skeleton-line"></div>
        <div class="skeleton-line" style="width:70%"></div>
      </div>
    </div>
  </div>
  <div class="notif-panel-overlay" id="notifOverlay" onclick="closeNotifPanel()"></div>

  <!-- ── PAGE CONTENT starts after this ── -->
  <main class="page-content" id="pageContent">

<?php
// Render flash messages
foreach (['success','error','info','warning'] as $type) {
    $msgs = getFlash($type);
    foreach ($msgs as $msg): ?>
    <div class="toast toast-<?= $type ?> toast-auto" role="alert">
      <span><?= e($msg) ?></span>
      <button onclick="this.parentElement.remove()">✕</button>
    </div>
    <?php endforeach;
}
?>

<?php if (!empty($_popups)): ?>
<script>
window.__pendingPopups = <?= json_encode($_popups, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
