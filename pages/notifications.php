<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'read_all') {
        markAllNotificationsRead($userId);
        setFlash('success', 'Semua notifikasi ditandai sudah dibaca.');
    } elseif ($action === 'delete' && isset($_POST['notif_id'])) {
        deleteNotification((int)$_POST['notif_id'], $userId);
    } elseif ($action === 'delete_all') {
        db()->execute('DELETE FROM notifications WHERE user_id = ?', 'i', $userId);
        setFlash('success', 'Semua notifikasi dihapus.');
    }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

$filter = $_GET['filter'] ?? 'all';
$page   = getCurrentPage();
$validFilters = ['all','unread','deposit','withdraw','mining','vip','system'];
if (!in_array($filter, $validFilters)) $filter = 'all';

$where  = 'WHERE user_id = ?';
$params = [$userId];
$types  = 'i';

if ($filter === 'unread') { $where .= ' AND is_read = 0'; }
elseif ($filter !== 'all') { $where .= ' AND type = ?'; $params[] = $filter; $types .= 's'; }

$total = db()->fetchOne("SELECT COUNT(*) as cnt FROM notifications $where", $types, ...$params)['cnt'] ?? 0;
$pag   = paginate($total, 25, $page, BASE_URL . '/pages/notifications.php?filter=' . $filter);
$notifs = db()->fetchAll("SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT 25 OFFSET ?", $types . 'i', ...[...$params, $pag['offset']]);
$unreadCount = getUnreadNotifCount($userId);

// Mark as read on page view
if (!empty($notifs)) {
    $ids = array_column($notifs, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $idTypes = str_repeat('i', count($ids));
    db()->execute("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders) AND user_id = ?", $idTypes . 'i', ...[...$ids, $userId]);
}

$typeIcons = [
    'info'    => '💡', 'success' => '✅', 'warning' => '⚠️', 'error' => '❌',
    'deposit' => '💳', 'withdraw'=> '💸', 'mining'  => '⛏️', 'vip'   => '🏆',
    'system'  => '🔔',
];

$pageTitle = 'Notifikasi';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Notifikasi</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Notifikasi</div>
</div>

<!-- Actions -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach (['all'=>'Semua','unread'=>'Belum Dibaca','deposit'=>'Deposit','withdraw'=>'Penarikan','mining'=>'Mining','vip'=>'VIP'] as $k=>$l): ?>
    <a href="?filter=<?= $k ?>" class="btn-sm <?= $filter===$k ? 'btn-primary' : 'btn-ghost' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:8px">
    <?php if ($unreadCount > 0): ?>
    <form method="POST" style="display:inline">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="read_all">
      <button type="submit" class="btn-secondary btn-sm">Tandai Semua Dibaca</button>
    </form>
    <?php endif; ?>
    <form method="POST" style="display:inline" onsubmit="return confirm('Hapus semua notifikasi?')">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="delete_all">
      <button type="submit" class="btn-danger btn-sm">Hapus Semua</button>
    </form>
  </div>
</div>

<!-- Notification List -->
<div style="display:flex;flex-direction:column;gap:8px">
  <?php if (empty($notifs)): ?>
  <div class="empty-state card" style="padding:48px">
    <div style="font-size:3rem;margin-bottom:10px">🔔</div>
    <p>Tidak ada notifikasi</p>
  </div>
  <?php else: ?>
  <?php foreach ($notifs as $n): ?>
  <div class="card" style="padding:14px 16px;display:flex;gap:12px;align-items:flex-start">
    <div style="width:40px;height:40px;border-radius:50%;background:var(--bg-card2);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0">
      <?= $typeIcons[$n['type']] ?? '🔔' ?>
    </div>
    <div style="flex:1">
      <div style="font-weight:700;font-size:.9rem"><?= e($n['title']) ?></div>
      <div style="color:var(--text-secondary);font-size:.85rem;margin-top:3px;line-height:1.5"><?= e($n['message']) ?></div>
      <div style="font-size:.75rem;color:var(--text-muted);margin-top:6px"><?= timeAgo($n['created_at']) ?> · <?= formatDatetime($n['created_at']) ?></div>
      <?php if ($n['action_url']): ?>
      <a href="<?= e($n['action_url']) ?>" class="btn-text-sm" style="margin-top:6px;padding-left:0">Lihat Detail →</a>
      <?php endif; ?>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"   value="delete">
      <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
      <button type="submit" class="btn-icon btn-ghost" style="width:30px;height:30px;min-height:auto" title="Hapus">✕</button>
    </form>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="pagination">
  <?php if ($pag['has_prev']): ?><a href="<?= $pag['prev_url'] ?>" class="page-btn">‹</a><?php endif; ?>
  <?php foreach ($pag['pages'] as $pg): ?><a href="<?= $pg['url'] ?>" class="page-btn <?= $pg['active'] ? 'active' : '' ?>"><?= $pg['page'] ?></a><?php endforeach; ?>
  <?php if ($pag['has_next']): ?><a href="<?= $pag['next_url'] ?>" class="page-btn">›</a><?php endif; ?>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
