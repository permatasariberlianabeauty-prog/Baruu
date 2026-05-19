<?php
/**
 * NOXARA - Bottom Navigation Bar (Mobile)
 */
$_currentPage = basename($_SERVER['PHP_SELF'], '.php');

$_fabItems = [
    ['href' => BASE_URL . '/pages/products.php',    'icon' => 'shopping-bag', 'label' => 'Beli Paket'],
    ['href' => BASE_URL . '/pages/my_packages.php', 'icon' => 'package',      'label' => 'Paket Aktif'],
    ['href' => BASE_URL . '/pages/ads.php',         'icon' => 'play-circle',  'label' => 'Tonton Iklan'],
    ['href' => BASE_URL . '/pages/daily_reward.php','icon' => 'gift',         'label' => 'Hadiah Harian'],
];

$_mobileNav = [
    ['href' => 'dashboard',    'icon' => 'home',     'label' => 'Home'],
    ['href' => 'deposit',      'icon' => 'wallet',   'label' => 'Keuangan'],
    ['fab' => true],
    ['href' => 'referral',     'icon' => 'referral', 'label' => 'Referral'],
    ['href' => 'profile',      'icon' => 'user',     'label' => 'Akun'],
];
?>

<nav class="mobile-nav" role="navigation" aria-label="Navigasi bawah">
  <?php foreach ($_mobileNav as $item): ?>
    <?php if (!empty($item['fab'])): ?>
    <!-- FAB Center Button -->
    <div class="mobile-nav-fab-wrapper">
      <button class="mobile-fab" id="mobileFab" onclick="toggleFabMenu()" aria-label="Menu cepat" aria-expanded="false">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M12 5v14M5 12h14" stroke="white" stroke-width="2.5" stroke-linecap="round" id="fabPlusIcon"/>
        </svg>
      </button>
      <!-- FAB Menu -->
      <div class="fab-menu" id="fabMenu">
        <?php foreach ($_fabItems as $fi): ?>
        <a href="<?= $fi['href'] ?>" class="fab-menu-item">
          <span class="fab-menu-icon">
            <?php if ($fi['icon'] === 'shopping-bag'): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4zM3 6h18M16 10a4 4 0 01-8 0" stroke="currentColor" stroke-width="1.7" fill="none"/></svg>
            <?php elseif ($fi['icon'] === 'package'): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 9l9-5 9 5v6l-9 5-9-5V9z" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M12 4v16M3 9l9 5 9-5" stroke="currentColor" stroke-width="1.7" fill="none"/></svg>
            <?php elseif ($fi['icon'] === 'play-circle'): ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.7" fill="none"/><polygon points="10 8 16 12 10 16 10 8" stroke="currentColor" stroke-width="1.7" fill="none"/></svg>
            <?php else: ?>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 12v10H4V12M22 7H2v5h20V7zM12 22V7M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/></svg>
            <?php endif; ?>
          </span>
          <span class="fab-menu-label"><?= e($fi['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else:
      $isActive = $_currentPage === $item['href'];
      $href     = BASE_URL . '/pages/' . $item['href'] . '.php';
    ?>
    <a href="<?= $href ?>" class="mobile-nav-item<?= $isActive ? ' active' : '' ?>">
      <span class="mobile-nav-icon">
        <?php if ($item['icon'] === 'home'): ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="1.8" fill="none"/><polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="1.8" fill="none"/></svg>
        <?php elseif ($item['icon'] === 'wallet'): ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="1.8" fill="none"/><path d="M16 12h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <?php elseif ($item['icon'] === 'referral'): ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="17" cy="5" r="3" stroke="currentColor" stroke-width="1.8" fill="none"/><circle cx="7" cy="12" r="3" stroke="currentColor" stroke-width="1.8" fill="none"/><circle cx="17" cy="19" r="3" stroke="currentColor" stroke-width="1.8" fill="none"/><path d="M10 12h4M14 6.4l-4 3.6M14 17.6l-4-3.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none"/></svg>
        <?php else: ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8" fill="none"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none"/></svg>
        <?php endif; ?>
      </span>
      <span class="mobile-nav-label"><?= e($item['label']) ?></span>
    </a>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>

<!-- FAB Overlay -->
<div class="fab-overlay" id="fabOverlay" onclick="closeFabMenu()"></div>

<script>
function toggleFabMenu() {
  const fab     = document.getElementById('mobileFab');
  const menu    = document.getElementById('fabMenu');
  const overlay = document.getElementById('fabOverlay');
  const isOpen  = menu.classList.contains('open');

  if (isOpen) {
    menu.classList.remove('open');
    overlay.classList.remove('active');
    fab.setAttribute('aria-expanded', 'false');
    fab.style.transform = 'rotate(0deg)';
  } else {
    menu.classList.add('open');
    overlay.classList.add('active');
    fab.setAttribute('aria-expanded', 'true');
    fab.style.transform = 'rotate(45deg)';
  }
}
function closeFabMenu() {
  document.getElementById('fabMenu').classList.remove('open');
  document.getElementById('fabOverlay').classList.remove('active');
  document.getElementById('mobileFab').setAttribute('aria-expanded', 'false');
  document.getElementById('mobileFab').style.transform = 'rotate(0deg)';
}
</script>
