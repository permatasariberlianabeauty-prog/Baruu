<?php
/** Partial: Product Card
 * Requires: $p (product row with cat_name, cat_color), $user (vip_level)
 */
$isLocked = ($user['vip_level'] < $p['min_vip_level']);
$totalProfit = $p['profit_per_day'] * $p['duration_days'];
$roi = round(($totalProfit / $p['price']) * 100);
?>
<div class="product-card <?= $isLocked ? 'opacity:0.6' : '' ?>" style="<?= $isLocked ? 'opacity:.6' : '' ?>">
  <?php if ($p['image']): ?>
  <img src="<?= UPLOADS_URL ?>/products/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" class="product-card-img">
  <?php else: ?>
  <div class="product-card-img" style="display:flex;align-items:center;justify-content:center;font-size:2.5rem;background:linear-gradient(135deg,<?= e($p['cat_color'] ?? '#00D4FF') ?>22,var(--bg-card2))">⛏️</div>
  <?php endif; ?>
  <div class="product-card-body">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <span class="badge" style="background:<?= e($p['cat_color'] ?? '#00D4FF') ?>22;color:<?= e($p['cat_color'] ?? '#00D4FF') ?>"><?= e($p['cat_name']) ?></span>
      <?php if ($isLocked): ?>
      <span class="badge badge-muted">🔒 VIP <?= $p['min_vip_level'] ?>+</span>
      <?php endif; ?>
    </div>
    <div class="product-card-name"><?= e($p['name']) ?></div>
    <div class="product-card-price"><?= formatRupiah($p['price']) ?></div>
    <div class="product-card-meta">
      <div class="product-meta-item">
        <label>Profit/Hari</label>
        <span style="color:var(--text-success)"><?= formatRupiah($p['profit_per_day']) ?></span>
      </div>
      <div class="product-meta-item">
        <label>Total Profit</label>
        <span style="color:var(--cyan)"><?= formatRupiah($totalProfit) ?></span>
      </div>
      <div class="product-meta-item">
        <label>ROI</label>
        <span style="color:var(--text-warning)"><?= $roi ?>%</span>
      </div>
      <div class="product-meta-item">
        <label>Durasi</label>
        <span><?= $p['duration_days'] ?> Hari</span>
      </div>
    </div>
    <?php if ($p['description']): ?>
    <p style="font-size:.8rem;color:var(--text-muted);margin:8px 0"><?= e(truncate($p['description'], 80)) ?></p>
    <?php endif; ?>
    <div class="product-card-footer">
      <?php if ($isLocked): ?>
      <a href="<?= BASE_URL ?>/pages/vip.php" class="btn-ghost btn-block btn-sm">Upgrade VIP untuk Akses</a>
      <?php else: ?>
      <button class="btn-primary btn-block btn-sm"
        onclick="openBuyModal(<?= $p['id'] ?>, '<?= addslashes(e($p['name'])) ?>', '<?= formatRupiah($p['price']) ?>', '<?= formatRupiah($p['profit_per_day']) ?>', <?= $roi ?>)">
        Beli Sekarang
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>
