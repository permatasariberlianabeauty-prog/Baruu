<?php
/**
 * NOXARA - Sidebar Navigation
 */
$_currentPage = basename($_SERVER['PHP_SELF'], '.php');
$_userId      = $_SESSION['user_id'] ?? 0;
$_vipLevel    = $_SESSION['vip_level'] ?? 0;
$_wallet      = $_userId ? getUserWallet($_userId) : null;

$_navItems = [
    ['href' => 'dashboard',      'label' => 'Dashboard',      'icon' => 'dashboard'],
    ['href' => 'deposit',        'label' => 'Deposit',        'icon' => 'deposit'],
    ['href' => 'withdraw',       'label' => 'Penarikan',      'icon' => 'withdraw'],
    ['href' => 'products',       'label' => 'Produk Mining',  'icon' => 'mining'],
    ['href' => 'my_packages',    'label' => 'Paket Aktif',    'icon' => 'package'],
    ['href' => 'referral',       'label' => 'Referral',       'icon' => 'referral'],
    ['href' => 'ads',            'label' => 'Tonton Iklan',   'icon' => 'ads'],
    ['href' => 'daily_reward',   'label' => 'Hadiah Harian',  'icon' => 'gift'],
    ['href' => 'missions',       'label' => 'Misi',           'icon' => 'mission'],
    ['href' => 'leaderboard',    'label' => 'Leaderboard',    'icon' => 'trophy'],
    ['href' => 'profit_calendar','label' => 'Kalender Profit','icon' => 'calendar'],
    ['href' => 'vip',            'label' => 'VIP',            'icon' => 'vip'],
    ['href' => 'voucher',        'label' => 'Voucher',        'icon' => 'voucher'],
    ['href' => 'history',        'label' => 'Riwayat',        'icon' => 'history'],
    ['href' => 'notifications',  'label' => 'Notifikasi',     'icon' => 'bell'],
    ['href' => 'chat',           'label' => 'Live Chat',      'icon' => 'chat'],
    ['hr'   => true],
    ['href' => 'profile',        'label' => 'Profil Saya',    'icon' => 'user'],
    ['href' => 'security',       'label' => 'Keamanan',       'icon' => 'security'],
    ['href' => 'bank_account',   'label' => 'Rekening Bank',  'icon' => 'bank'],
    ['hr'   => true],
    ['href' => 'faq',            'label' => 'FAQ',            'icon' => 'faq'],
    ['href' => 'info',           'label' => 'Info Platform',  'icon' => 'info'],
    ['href' => 'contact',        'label' => 'Hubungi Kami',   'icon' => 'contact'],
];

$_svgIcons = [
    'dashboard'  => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="1.7" fill="none"/><polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="1.7" fill="none"/>',
    'deposit'    => '<path d="M12 2v20M17 7H9.5a2.5 2.5 0 000 5h5a2.5 2.5 0 010 5H7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'withdraw'   => '<path d="M20 12V22H4V12" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M22 7H2v5h20V7z" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M12 22V7M12 7l-4-4M12 7l4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'mining'     => '<path d="M3 21l4-4 4.5-4.5M17 3l4 4-11 11-4-4 11-11z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/><circle cx="21" cy="3" r="1" fill="currentColor"/>',
    'package'    => '<path d="M3 9l9-5 9 5v6l-9 5-9-5V9z" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M12 4v16M3 9l9 5 9-5" stroke="currentColor" stroke-width="1.7" fill="none"/>',
    'referral'   => '<circle cx="17" cy="5" r="3" stroke="currentColor" stroke-width="1.7" fill="none"/><circle cx="7" cy="12" r="3" stroke="currentColor" stroke-width="1.7" fill="none"/><circle cx="17" cy="19" r="3" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M10 12h4M14 6.4l-4 3.6M14 17.6l-4-3.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'ads'        => '<rect x="2" y="7" width="20" height="15" rx="2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2M12 12v5M9.5 14.5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'gift'       => '<path d="M20 12v10H4V12M22 7H2v5h20V7zM12 22V7M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'mission'    => '<path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'trophy'     => '<path d="M8 21h8M12 17v4M17 3H7v8a5 5 0 0010 0V3z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/><path d="M17 5h3v3a3 3 0 01-3 3M7 5H4v3a3 3 0 003 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'vip'        => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="1.7" fill="none"/>',
    'voucher'    => '<path d="M20 5H4a2 2 0 00-2 2v2a2 2 0 000 4v2a2 2 0 002 2h16a2 2 0 002-2v-2a2 2 0 000-4V7a2 2 0 00-2-2z" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M9 5v14M12 9h3M12 12h3M12 15h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'history'    => '<path d="M3 12a9 9 0 1018 0A9 9 0 003 12zM12 7v5l3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'bell'       => '<path d="M12 2a7 7 0 00-7 7v6l-2 2v1h18v-1l-2-2V9a7 7 0 00-7-7zM10 19c0 1.1.9 2 2 2s2-.9 2-2" stroke="currentColor" stroke-width="1.7" fill="none"/>',
    'chat'       => '<path d="M4 4h16c1.1 0 2 .9 2 2v10c0 1.1-.9 2-2 2H6l-4 4V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="1.7" fill="none"/>',
    'user'       => '<circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'security'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="1.7" fill="none"/>',
    'bank'       => '<rect x="2" y="19" width="20" height="2" stroke="currentColor" stroke-width="1.7" fill="none"/><rect x="6" y="10" width="2" height="9" stroke="currentColor" stroke-width="1.7" fill="none"/><rect x="11" y="10" width="2" height="9" stroke="currentColor" stroke-width="1.7" fill="none"/><rect x="16" y="10" width="2" height="9" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M2 10l10-7 10 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'faq'        => '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/><circle cx="12" cy="17" r=".5" fill="currentColor"/>',
    'info'       => '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M12 8h.01M11 11h1v5h1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>',
    'contact'    => '<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.95 9.5a19.79 19.79 0 01-3.07-8.67A2 2 0 012.85 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L7.09 9.91A16 16 0 0013 15.8l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z" stroke="currentColor" stroke-width="1.7" fill="none"/>',
];
?>

<aside class="sidebar" id="sidebar">
  <!-- Sidebar Header -->
  <div class="sidebar-header">
    <a href="<?= BASE_URL ?>/pages/dashboard.php" class="sidebar-brand">
      <svg width="32" height="32" viewBox="0 0 28 28" fill="none">
        <polygon points="14,2 26,8 26,20 14,26 2,20 2,8" fill="none" stroke="url(#slg)" stroke-width="2"/>
        <path d="M9 11l5 3 5-3M14 14v7" stroke="url(#slg)" stroke-width="1.5" stroke-linecap="round"/>
        <defs><linearGradient id="slg" x1="0" y1="0" x2="28" y2="28"><stop stop-color="#00D4FF"/><stop offset="1" stop-color="#7B2FFF"/></linearGradient></defs>
      </svg>
      <span class="orbitron"><?= SITE_NAME ?></span>
    </a>
    <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  </div>

  <!-- User Info Card -->
  <div class="sidebar-user-card">
    <img src="<?= avatarUrl($_SESSION['avatar'] ?? null) ?>" alt="Avatar" class="sidebar-avatar" onerror="this.src='<?= ASSETS_URL ?>/img/default-avatar.png'">
    <div class="sidebar-user-info">
      <div class="sidebar-user-name"><?= e($_SESSION['full_name'] ?? '') ?></div>
      <div class="sidebar-user-meta">
        <?= vipBadge($_vipLevel) ?>
      </div>
    </div>
  </div>

  <!-- Wallet Summary -->
  <?php if ($_wallet): ?>
  <div class="sidebar-wallet">
    <div class="sidebar-wallet-item">
      <span class="sidebar-wallet-label">Saldo Utama</span>
      <span class="sidebar-wallet-value"><?= formatRupiah($_wallet['main_balance']) ?></span>
    </div>
    <div class="sidebar-wallet-item">
      <span class="sidebar-wallet-label">Saldo Profit</span>
      <span class="sidebar-wallet-value profit"><?= formatRupiah($_wallet['profit_balance']) ?></span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Navigation -->
  <nav class="sidebar-nav" role="navigation">
    <ul>
      <?php foreach ($_navItems as $item): ?>
        <?php if (!empty($item['hr'])): ?>
        <li class="nav-divider"></li>
        <?php else:
          $isActive = $_currentPage === $item['href'];
          $href     = BASE_URL . '/pages/' . $item['href'] . '.php';
          $icon     = $_svgIcons[$item['icon']] ?? '';
        ?>
        <li>
          <a href="<?= $href ?>" class="nav-item<?= $isActive ? ' active' : '' ?>">
            <span class="nav-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <?= $icon ?>
              </svg>
            </span>
            <span class="nav-label"><?= e($item['label']) ?></span>
            <?php if ($item['icon'] === 'bell' && $_unreadNotif > 0): ?>
            <span class="nav-badge"><?= $_unreadNotif ?></span>
            <?php endif; ?>
            <?php if ($item['icon'] === 'chat' && $_unreadChat > 0): ?>
            <span class="nav-badge"><?= $_unreadChat ?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endif; ?>
      <?php endforeach; ?>

      <li class="nav-divider"></li>
      <li>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-item nav-logout"
           onclick="return confirm('Yakin ingin keluar?')">
          <span class="nav-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/>
            </svg>
          </span>
          <span class="nav-label">Keluar</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>
