<?php
/**
 * NOXARA Admin - Shared Layout Helper
 * Usage: call adminHeader($pageTitle) at top, adminFooter() at bottom
 */

function adminHeader(string $pageTitle, string $activeMenu = ''): void
{
    global $_adminUser;
    $_adminUser = getSessionAdmin();
    $unreadDeposits  = db()->fetchOne("SELECT COUNT(*) as cnt FROM deposits WHERE status='pending'")['cnt'] ?? 0;
    $unreadWithdraws = db()->fetchOne("SELECT COUNT(*) as cnt FROM withdrawals WHERE status='pending'")['cnt'] ?? 0;
    $unreadChats     = db()->fetchOne("SELECT SUM(unread_admin) as cnt FROM chat_rooms")['cnt'] ?? 0;

    echo '<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="' . getCsrfToken() . '">
<title>' . e($pageTitle) . ' — ' . SITE_NAME . ' Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Orbitron:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="' . ASSETS_URL . '/css/style.css">
<link rel="stylesheet" href="' . ASSETS_URL . '/css/animations.css">
<link rel="stylesheet" href="' . ASSETS_URL . '/css/mobile.css">
</head>
<body>
<div class="admin-layout">';

    // Admin Sidebar
    $menus = [
        ['url'=>'index',           'label'=>'Dashboard',      'icon'=>'📊', 'roles'=>['superadmin','cs','finance']],
        ['url'=>'members',         'label'=>'Member',         'icon'=>'👥', 'roles'=>['superadmin','cs']],
        ['url'=>'deposits',        'label'=>'Deposit',        'icon'=>'💳', 'roles'=>['superadmin','cs','finance'], 'badge'=>$unreadDeposits],
        ['url'=>'withdrawals',     'label'=>'Penarikan',      'icon'=>'💸', 'roles'=>['superadmin','finance'], 'badge'=>$unreadWithdraws],
        ['url'=>'products',        'label'=>'Produk Mining',  'icon'=>'⛏️', 'roles'=>['superadmin']],
        ['url'=>'ads',             'label'=>'Iklan',          'icon'=>'📺', 'roles'=>['superadmin']],
        ['url'=>'chat',            'label'=>'Live Chat',      'icon'=>'💬', 'roles'=>['superadmin','cs'], 'badge'=>$unreadChats],
        ['url'=>'vip_settings',    'label'=>'Setting VIP',    'icon'=>'🏆', 'roles'=>['superadmin']],
        ['url'=>'commission_settings','label'=>'Komisi',      'icon'=>'💰', 'roles'=>['superadmin']],
        ['url'=>'popup_settings',  'label'=>'Popup',          'icon'=>'🎉', 'roles'=>['superadmin']],
        ['url'=>'banners',         'label'=>'Banner',         'icon'=>'🖼️', 'roles'=>['superadmin']],
        ['url'=>'daily_rewards',   'label'=>'Hadiah Harian',  'icon'=>'🎁', 'roles'=>['superadmin']],
        ['url'=>'missions',        'label'=>'Misi',           'icon'=>'📋', 'roles'=>['superadmin']],
        ['url'=>'vouchers',        'label'=>'Voucher',        'icon'=>'🎫', 'roles'=>['superadmin']],
        ['url'=>'notifications',   'label'=>'Notifikasi',     'icon'=>'🔔', 'roles'=>['superadmin','cs']],
        ['url'=>'settings',        'label'=>'Pengaturan',     'icon'=>'⚙️', 'roles'=>['superadmin']],
        ['url'=>'reports',         'label'=>'Laporan',        'icon'=>'📈', 'roles'=>['superadmin','finance']],
    ];

    echo '<aside class="admin-sidebar">';
    echo '<div style="padding:16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px">
      <span style="font-family:\'Orbitron\',sans-serif;font-size:.9rem;font-weight:900;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent">' . SITE_NAME . '</span>
      <span style="font-size:.7rem;background:var(--purple-dim);color:var(--purple);padding:2px 6px;border-radius:4px;font-weight:700">ADMIN</span>
    </div>';
    echo '<nav class="admin-nav">';

    foreach ($menus as $m) {
        if (!in_array($_adminUser['role'], $m['roles'])) continue;
        $isActive = $activeMenu === $m['url'];
        $badge    = !empty($m['badge']) && $m['badge'] > 0 ? '<span class="nav-badge">' . $m['badge'] . '</span>' : '';
        echo '<a href="' . BASE_URL . '/admin/' . $m['url'] . '.php" class="admin-nav-item' . ($isActive ? ' active' : '') . '">';
        echo '<span style="font-size:1rem">' . $m['icon'] . '</span>';
        echo '<span>' . $m['label'] . '</span>';
        echo $badge;
        echo '</a>';
    }

    echo '<div class="admin-nav-divider"></div>';
    echo '<a href="' . BASE_URL . '/admin/logout.php" class="admin-nav-item" style="color:var(--text-danger)" onclick="return confirm(\'Keluar dari panel admin?\')">🚪 Logout</a>';
    echo '</nav></aside>';

    // Main area
    echo '<div class="admin-main">
    <header class="admin-topbar">
      <div style="font-weight:700">' . e($pageTitle) . '</div>
      <div style="display:flex;align-items:center;gap:12px;font-size:.85rem">
        <span style="color:var(--text-muted)">👋 ' . e($_adminUser['full_name']) . '</span>
        <span class="badge badge-purple">' . e(strtoupper($_adminUser['role'])) . '</span>
        <a href="' . BASE_URL . '/" target="_blank" class="btn-ghost btn-sm">🌐 Website</a>
      </div>
    </header>
    <div class="admin-content">';
}

function adminFooter(): void
{
    echo '</div></div></div>';
    echo '<script src="' . ASSETS_URL . '/js/main.js"></script>';
    echo '<script>const CSRF_TOKEN=document.querySelector(\'meta[name="csrf-token"]\')?.content||"";const BASE_URL="' . BASE_URL . '";</script>';
    echo '</body></html>';
}
