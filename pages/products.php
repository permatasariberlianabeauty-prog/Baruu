<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId = $_SESSION['user_id'];
$user   = db()->fetchOne('SELECT vip_level FROM users WHERE id = ?', 'i', $userId);
$wallet = getUserWallet($userId);

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $productId  = (int)($_POST['product_id']  ?? 0);
    $walletType = $_POST['wallet_type'] ?? WALLET_MAIN;
    $result     = purchaseProduct($userId, $productId, $walletType);
    if ($result['success']) {
        setFlash('success', $result['message']);
        $_SESSION['show_popup'] = 'buy_package';
    } else {
        setFlash('error', $result['message']);
    }
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

$categories = db()->fetchAll('SELECT * FROM product_categories WHERE is_active = 1 ORDER BY sort_order');
$products   = db()->fetchAll('SELECT p.*, pc.name as cat_name, pc.color as cat_color, pc.slug as cat_slug FROM products p JOIN product_categories pc ON p.category_id = pc.id WHERE p.is_active = 1 ORDER BY pc.sort_order, p.sort_order');

$pageTitle = 'Produk Mining';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Produk Mining</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › Produk Mining</div>
</div>

<!-- Wallet Summary -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">
  <div class="card-sm card" style="flex:1;min-width:150px">
    <div style="font-size:.72rem;color:var(--text-muted)">Saldo Utama</div>
    <div style="font-weight:700;font-size:1.1rem"><?= formatRupiah($wallet['main_balance']) ?></div>
  </div>
  <div class="card-sm card" style="flex:1;min-width:150px">
    <div style="font-size:.72rem;color:var(--text-muted)">Saldo Bonus</div>
    <div style="font-weight:700;font-size:1.1rem;color:var(--text-warning)"><?= formatRupiah($wallet['bonus_balance']) ?></div>
    <div style="font-size:.68rem;color:var(--text-muted)">Bisa pakai untuk beli paket</div>
  </div>
  <div class="card-sm card" style="flex:1;min-width:150px">
    <div style="font-size:.72rem;color:var(--text-muted)">Level VIP</div>
    <div style="font-weight:700"><?= vipBadge($user['vip_level']) ?></div>
  </div>
</div>

<!-- Category tabs -->
<div class="tabs-wrapper" data-tabs>
  <div class="tabs" id="productTabs">
    <button class="tab-btn active" data-tab="tab-all">Semua</button>
    <?php foreach ($categories as $cat): ?>
    <button class="tab-btn" data-tab="tab-<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></button>
    <?php endforeach; ?>
  </div>

  <div id="tab-all" class="tab-content active">
    <?php foreach ($categories as $cat):
      $catProds = array_filter($products, fn($p) => $p['category_id'] == $cat['id']);
      if (empty($catProds)) continue;
    ?>
    <h3 style="font-size:1rem;font-weight:700;margin:20px 0 12px;display:flex;align-items:center;gap:8px">
      <span style="width:10px;height:10px;border-radius:50%;background:<?= e($cat['cat_color'] ?? $cat['color'] ?? '#00D4FF') ?>;display:inline-block"></span>
      <?= e($cat['name']) ?>
    </h3>
    <div class="products-grid" style="margin-bottom:24px">
      <?php foreach ($catProds as $p): ?>
      <?php include __DIR__ . '/../includes/product_card.php'; ?>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php foreach ($categories as $cat):
    $catProds = array_filter($products, fn($p) => $p['category_id'] == $cat['id']);
  ?>
  <div id="tab-<?= e($cat['slug']) ?>" class="tab-content">
    <div class="products-grid" style="margin-top:16px">
      <?php foreach ($catProds as $p): ?>
      <?php include __DIR__ . '/../includes/product_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Buy Modal -->
<div class="modal-overlay" id="buyModal" style="display:none">
  <div class="modal-box" style="max-width:440px">
    <h3 id="buyModalTitle">Beli Paket</h3>
    <div id="buyModalBody" style="margin:14px 0;font-size:.9rem;color:var(--text-secondary)"></div>
    <form method="POST" id="buyForm">
      <?= csrfField() ?>
      <input type="hidden" name="product_id" id="buyProductId">
      <div class="form-group">
        <label class="form-label">Gunakan Saldo</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <label style="cursor:pointer">
            <input type="radio" name="wallet_type" value="main" checked style="display:none" class="buy-wallet-radio">
            <div class="card-sm" style="text-align:center;border:2px solid var(--cyan)" id="buyWalletMain">
              <div style="font-size:.72rem;color:var(--text-muted)">Saldo Utama</div>
              <div style="font-weight:700"><?= formatRupiahShort($wallet['main_balance']) ?></div>
            </div>
          </label>
          <label style="cursor:pointer">
            <input type="radio" name="wallet_type" value="bonus" style="display:none" class="buy-wallet-radio">
            <div class="card-sm" style="text-align:center;border:2px solid var(--border)" id="buyWalletBonus">
              <div style="font-size:.72rem;color:var(--text-muted)">Saldo Bonus</div>
              <div style="font-weight:700;color:var(--text-warning)"><?= formatRupiahShort($wallet['bonus_balance']) ?></div>
            </div>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-ghost" onclick="document.getElementById('buyModal').style.display='none'">Batal</button>
        <button type="submit" class="btn-primary">Konfirmasi Beli</button>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelectorAll('.buy-wallet-radio').forEach(r => {
  r.addEventListener('change', () => {
    document.getElementById('buyWalletMain').style.borderColor  = 'var(--border)';
    document.getElementById('buyWalletBonus').style.borderColor = 'var(--border)';
    document.getElementById(r.value === 'main' ? 'buyWalletMain' : 'buyWalletBonus').style.borderColor = 'var(--cyan)';
  });
});

function openBuyModal(id, name, price, profitDay, roi) {
  document.getElementById('buyProductId').value = id;
  document.getElementById('buyModalTitle').textContent = 'Beli Paket ' + name;
  document.getElementById('buyModalBody').innerHTML = `
    <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:14px;margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;padding:4px 0"><span>Harga</span><strong>${price}</strong></div>
      <div style="display:flex;justify-content:space-between;padding:4px 0"><span>Profit/Hari</span><strong style="color:var(--text-success)">${profitDay}</strong></div>
      <div style="display:flex;justify-content:space-between;padding:4px 0"><span>ROI</span><strong style="color:var(--text-warning)">${roi}%</strong></div>
      <div style="display:flex;justify-content:space-between;padding:4px 0"><span>Durasi</span><strong>30 Hari</strong></div>
    </div>`;
  document.getElementById('buyModal').style.display = 'flex';
}
</script>
<?php include INCLUDES_PATH . '/footer.php'; ?>
