<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
initSession();
requireLogin();

$userId  = $_SESSION['user_id'];
$vipInfo = getUserVipInfo($userId);
$allVips = getAllVipLevels();

// VIP Codes (for current and below level)
$currentLevel = $vipInfo['level'];

$pageTitle = 'VIP';
include INCLUDES_PATH . '/header.php';
?>
<div class="page-header">
  <h1>Program VIP</h1>
  <div class="breadcrumb"><a href="<?= BASE_URL ?>/pages/dashboard.php">Dashboard</a> › VIP</div>
</div>

<!-- Current VIP Status -->
<div class="card" style="margin-bottom:24px;background:linear-gradient(135deg,var(--bg-card2),var(--bg-card));border-color:var(--border-active);overflow:hidden;position:relative">
  <div style="position:absolute;right:-20px;top:-20px;font-size:8rem;opacity:.05;pointer-events:none">
    <?= ['🥉','🥈','🥇','🏅','💎','👑'][$currentLevel] ?? '🏅' ?>
  </div>
  <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
    <div style="font-size:4rem"><?= ['🥉','🥈','🥇','🏅','💎','👑'][$currentLevel] ?? '🏅' ?></div>
    <div style="flex:1">
      <div style="font-size:.8rem;color:var(--text-muted)">Level VIP Saat Ini</div>
      <?= vipBadge($currentLevel) ?>
      <div style="font-size:1.8rem;font-weight:900;font-family:var(--font-brand);margin:4px 0">
        <?= $vipInfo['info']['name'] ?? 'Pemula' ?>
      </div>
      <div style="font-size:.85rem;color:var(--text-secondary)">Total Deposit: <strong style="color:var(--cyan)"><?= formatRupiah($vipInfo['total_deposit']) ?></strong></div>
    </div>
    <?php if (!$vipInfo['is_max'] && $vipInfo['next']): ?>
    <div style="min-width:200px">
      <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:6px">
        Menuju VIP <?= $currentLevel + 1 ?> (<?= e($vipInfo['next']['name']) ?>)
      </div>
      <div class="progress-wrap">
        <div class="progress-bar" data-progress="<?= $vipInfo['progress_percent'] ?>" style="width:0%"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:.75rem;color:var(--text-muted);margin-top:4px">
        <span><?= formatRupiah($vipInfo['total_deposit']) ?></span>
        <span><?= formatRupiah($vipInfo['next']['min_deposit_required']) ?></span>
      </div>
      <div style="margin-top:8px;font-size:.8rem">
        Kurang: <strong style="color:var(--text-warning)"><?= formatRupiah(max(0, $vipInfo['next']['min_deposit_required'] - $vipInfo['total_deposit'])) ?></strong>
      </div>
    </div>
    <?php else: ?>
    <div class="badge badge-success" style="font-size:.9rem;padding:10px 16px">🏆 Level Tertinggi!</div>
    <?php endif; ?>
  </div>
</div>

<!-- VIP Benefits Grid -->
<h2 style="font-size:1.1rem;font-weight:700;margin-bottom:16px">Perbandingan Level VIP</h2>
<div style="overflow-x:auto;margin-bottom:24px">
  <table style="width:100%;border-collapse:collapse;font-size:.85rem;min-width:700px">
    <thead>
      <tr style="background:var(--bg-card2)">
        <th style="padding:12px 14px;text-align:left;border-bottom:1px solid var(--border)">Fitur</th>
        <?php foreach ($allVips as $vip): ?>
        <th style="padding:12px 14px;text-align:center;border-bottom:1px solid var(--border);<?= $vip['level'] === $currentLevel ? 'background:var(--cyan-dim);color:var(--cyan)' : '' ?>">
          <div style="font-weight:800;color:<?= e($vip['color']) ?>"><?= e($vip['name']) ?></div>
          <div style="font-size:.72rem;font-weight:400">VIP <?= $vip['level'] ?></div>
        </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php
      $rows = [
        ['label'=>'Min. Deposit','key'=>'min_deposit_required','format'=>'rupiah'],
        ['label'=>'Min. Withdraw','key'=>'min_withdraw','format'=>'rupiah'],
        ['label'=>'Fee Withdraw','key'=>'withdraw_fee_percent','format'=>'percent'],
        ['label'=>'Maks WD/Hari','key'=>'max_withdraw_per_day','format'=>'count'],
        ['label'=>'Limit WD/Hari','key'=>'daily_withdraw_limit','format'=>'rupiah'],
      ];
      foreach ($rows as $row):
      ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:10px 14px;font-weight:600;color:var(--text-secondary)"><?= $row['label'] ?></td>
        <?php foreach ($allVips as $vip):
          $val = $vip[$row['key']];
          $formatted = match($row['format']) {
            'rupiah'  => formatRupiah($val),
            'percent' => $val . '%',
            'count'   => $val . 'x',
            default   => $val,
          };
          $isCurrent = $vip['level'] === $currentLevel;
          $isLocked  = $vip['level'] > $currentLevel;
        ?>
        <td style="padding:10px 14px;text-align:center;<?= $isCurrent ? 'background:var(--cyan-dim);font-weight:700;color:var(--cyan)' : '' ?><?= $isLocked ? 'opacity:.5' : '' ?>">
          <?= $formatted ?>
          <?php if ($isCurrent): ?><span style="display:block;font-size:.65rem;margin-top:2px">✅ Anda</span><?php endif; ?>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- VIP Codes -->
<h2 style="font-size:1.1rem;font-weight:700;margin-bottom:16px">Kode Eksklusif VIP</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:24px">
  <?php foreach ($allVips as $vip):
    $codeInfo    = getVipCode($vip['level'], $userId);
    $isAccessible = $codeInfo['accessible'];
  ?>
  <div class="card" style="text-align:center;border-color:<?= e($vip['color']) ?>44;<?= !$isAccessible ? 'opacity:.5' : '' ?>">
    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:var(--text-muted);margin-bottom:4px"><?= e($vip['name']) ?></div>
    <?php if ($isAccessible): ?>
    <div style="font-family:var(--font-mono);font-weight:700;font-size:1rem;color:<?= e($vip['color']) ?>;letter-spacing:.1em;margin-bottom:8px">
      <?= e($codeInfo['code']) ?>
    </div>
    <button class="copy-btn" onclick="copyText('<?= e($codeInfo['code']) ?>',this)">Salin Kode</button>
    <?php else: ?>
    <div style="font-size:1.5rem;margin:8px 0">🔒</div>
    <div style="font-size:.78rem;color:var(--text-muted)">Butuh VIP <?= $vip['level'] ?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Upgrade CTA -->
<?php if (!$vipInfo['is_max']): ?>
<div class="card" style="text-align:center;background:linear-gradient(135deg,var(--cyan-dim),var(--purple-dim));border-color:var(--border-active)">
  <div style="font-size:2rem;margin-bottom:8px">⬆️</div>
  <h3 style="font-size:1.1rem;margin-bottom:8px">Naik ke VIP <?= $currentLevel + 1 ?></h3>
  <p style="color:var(--text-secondary);font-size:.875rem;margin-bottom:16px">
    Deposit total <?= formatRupiah($vipInfo['next']['min_deposit_required']) ?> untuk naik level
    dan nikmati fee withdraw lebih rendah serta limit yang lebih besar!
  </p>
  <a href="<?= BASE_URL ?>/pages/deposit.php" class="btn-primary">Deposit Sekarang</a>
</div>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
