<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin(ROLE_SUPERADMIN);
require_once __DIR__ . '/includes/layout.php';

$admin = getSessionAdmin();

// ── Handle POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type        = trim($_POST['type'] ?? MISSION_DAILY);
        $actionType  = trim($_POST['action_type'] ?? ACTION_LOGIN);
        $targetCount = (int)($_POST['target_count'] ?? 1);
        $rewardType  = trim($_POST['reward_type'] ?? 'balance_bonus');
        $rewardValue = (float)($_POST['reward_value'] ?? 0);
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (empty($title)) { setFlash('error', 'Judul misi tidak boleh kosong.'); header('Location: ' . $_SERVER['PHP_SELF']); exit; }

        if ($id) {
            db()->execute(
                'UPDATE missions SET title=?,description=?,type=?,action_type=?,target_count=?,reward_type=?,reward_value=?,sort_order=?,is_active=? WHERE id=?',
                'ssssiissii', $title, $description, $type, $actionType, $targetCount, $rewardType, $rewardValue, $sortOrder, $isActive, $id
            );
            logAdminAction($admin['id'], 'edit_mission', 'mission', $id, $title);
            setFlash('success', 'Misi berhasil diperbarui.');
        } else {
            db()->execute(
                'INSERT INTO missions (title,description,type,action_type,target_count,reward_type,reward_value,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,?)',
                'ssssiisii', $title, $description, $type, $actionType, $targetCount, $rewardType, $rewardValue, $sortOrder, $isActive
            );
            logAdminAction($admin['id'], 'add_mission', 'mission', db()->lastInsertId(), $title);
            setFlash('success', 'Misi berhasil ditambahkan.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->execute('UPDATE missions SET is_active = 0 WHERE id = ?', 'i', $id);
        setFlash('success', 'Misi dinonaktifkan.');
    }

    if ($action === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = db()->fetchOne('SELECT is_active FROM missions WHERE id = ?', 'i', $id);
        if ($cur) db()->execute('UPDATE missions SET is_active = ? WHERE id = ?', 'ii', $cur['is_active'] ? 0 : 1, $id);
    }

    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

// ── Data ──────────────────────────────────────────────────
$editMission = isset($_GET['edit']) ? db()->fetchOne('SELECT * FROM missions WHERE id = ?', 'i', (int)$_GET['edit']) : null;
$filterType  = $_GET['type'] ?? 'all';

$where  = $filterType !== 'all' ? 'WHERE type = ?' : '';
$params = $filterType !== 'all' ? [$filterType] : [];
$types  = $filterType !== 'all' ? 's' : '';
$missions = db()->fetchAll("SELECT * FROM missions $where ORDER BY type, sort_order ASC", $types, ...$params);

// Stats
$activeMissions = db()->fetchOne('SELECT COUNT(*) as c FROM missions WHERE is_active = 1')['c'] ?? 0;
$totalCompletions = db()->fetchOne('SELECT COUNT(*) as c FROM user_missions WHERE is_completed = 1')['c'] ?? 0;
$pendingClaims    = db()->fetchOne('SELECT COUNT(*) as c FROM user_missions WHERE is_completed = 1 AND is_claimed = 0')['c'] ?? 0;

$missionTypeLabels  = [MISSION_DAILY=>'Harian', MISSION_WEEKLY=>'Mingguan', MISSION_MILESTONE=>'Pencapaian'];
$actionTypeLabels   = [ACTION_LOGIN=>'Login', ACTION_MINING=>'Mining', ACTION_WATCH_AD=>'Nonton Iklan', ACTION_REFERRAL=>'Referral', ACTION_DEPOSIT=>'Deposit'];

adminHeader('Manajemen Misi', 'missions');
?>

<?php foreach (['success','error'] as $t): foreach (getFlash($t) as $msg): ?>
<div style="margin-bottom:12px;padding:12px 16px;background:<?= $t==='success'?'rgba(0,255,136,.1)':'rgba(255,68,102,.1)' ?>;border:1px solid <?= $t==='success'?'rgba(0,255,136,.3)':'rgba(255,68,102,.3)' ?>;border-radius:var(--radius-md);color:<?= $t==='success'?'var(--text-success)':'var(--text-danger)' ?>"><?= e($msg) ?></div>
<?php endforeach; endforeach; ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px">
  <?php foreach ([
    ['🎯','Misi Aktif',$activeMissions,'var(--cyan)'],
    ['✅','Total Selesai',$totalCompletions,'var(--text-success)'],
    ['⏳','Menunggu Klaim',$pendingClaims,'var(--text-warning)'],
  ] as [$icon,$label,$val,$color]): ?>
  <div class="card" style="text-align:center;padding:14px">
    <div style="font-size:1.5rem"><?= $icon ?></div>
    <div style="font-size:1.4rem;font-weight:800;color:<?= $color ?>"><?= number_format($val) ?></div>
    <div style="font-size:.72rem;color:var(--text-muted)"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

<!-- List + Filters -->
<div>
  <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <?php foreach (array_merge(['all'=>'Semua'], $missionTypeLabels) as $k=>$l): ?>
    <a href="?type=<?= $k ?>" class="btn-sm <?= $filterType===$k?'btn-primary':'btn-ghost' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card" style="padding:0;overflow:hidden">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Judul</th><th>Tipe</th><th>Aksi</th><th>Target</th><th>Reward</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php if (empty($missions)): ?>
          <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted)">Belum ada misi</td></tr>
          <?php else: ?>
          <?php foreach ($missions as $m): ?>
          <tr style="<?= ($editMission && $editMission['id']==$m['id']) ? 'background:var(--cyan-dim)' : '' ?>">
            <td>
              <div style="font-weight:600;font-size:.875rem"><?= e(truncate($m['title'], 35)) ?></div>
              <?php if ($m['description']): ?><div style="font-size:.72rem;color:var(--text-muted)"><?= e(truncate($m['description'], 40)) ?></div><?php endif; ?>
            </td>
            <td>
              <?php $tlbl = $missionTypeLabels[$m['type']] ?? $m['type'];
                    $tclr = match($m['type']){ MISSION_DAILY=>'badge-info', MISSION_WEEKLY=>'badge-purple', default=>'badge-warning'};?>
              <span class="badge <?= $tclr ?>"><?= $tlbl ?></span>
            </td>
            <td><span class="badge badge-muted"><?= e($actionTypeLabels[$m['action_type']] ?? $m['action_type']) ?></span></td>
            <td style="text-align:center;font-weight:700"><?= number_format($m['target_count']) ?>x</td>
            <td style="color:var(--text-success);font-weight:700"><?= formatRupiah($m['reward_value']) ?></td>
            <td>
              <form method="POST" style="display:inline">
                <?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button type="submit" class="badge <?= $m['is_active'] ? 'badge-success' : 'badge-muted' ?>" style="cursor:pointer;border:none"><?= $m['is_active'] ? '✅ ON' : '⏸ OFF' ?></button>
              </form>
            </td>
            <td>
              <a href="?edit=<?= $m['id'] ?>&type=<?= $filterType ?>" class="btn-ghost btn-sm">✏️</a>
              <form method="POST" style="display:inline">
                <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Nonaktifkan misi ini?')">🗑</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Form -->
<div class="card" style="position:sticky;top:20px">
  <h3 class="card-title" style="margin-bottom:16px"><?= $editMission ? '✏️ Edit Misi' : '➕ Tambah Misi' ?></h3>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($editMission): ?><input type="hidden" name="id" value="<?= $editMission['id'] ?>"><?php endif; ?>

    <div class="form-group">
      <label class="form-label">Judul Misi</label>
      <input type="text" name="title" class="form-control" value="<?= e($editMission['title'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label">Deskripsi (opsional)</label>
      <textarea name="description" class="form-control" rows="2"><?= e($editMission['description'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Tipe Misi</label>
      <select name="type" class="form-control">
        <?php foreach ($missionTypeLabels as $k=>$l): ?>
        <option value="<?= $k ?>" <?= ($editMission['type'] ?? MISSION_DAILY) === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Tipe Aksi</label>
      <select name="action_type" class="form-control">
        <?php foreach ($actionTypeLabels as $k=>$l): ?>
        <option value="<?= $k ?>" <?= ($editMission['action_type'] ?? ACTION_LOGIN) === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Target (berapa kali)</label>
      <input type="number" name="target_count" class="form-control" value="<?= (int)($editMission['target_count'] ?? 1) ?>" min="1" required>
    </div>
    <div class="form-group">
      <label class="form-label">Tipe Reward</label>
      <select name="reward_type" class="form-control">
        <option value="balance_bonus" <?= ($editMission['reward_type'] ?? 'balance_bonus') === 'balance_bonus' ? 'selected' : '' ?>>💰 Saldo Bonus</option>
        <option value="voucher" <?= ($editMission['reward_type'] ?? '') === 'voucher' ? 'selected' : '' ?>>🎫 Voucher</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Nilai Reward (Rp)</label>
      <input type="number" name="reward_value" class="form-control" value="<?= $editMission['reward_value'] ?? 0 ?>" min="0" step="0.01">
    </div>
    <div class="form-group">
      <label class="form-label">Sort Order</label>
      <input type="number" name="sort_order" class="form-control" value="<?= (int)($editMission['sort_order'] ?? 0) ?>">
    </div>
    <div class="form-check" style="margin-bottom:16px">
      <input type="checkbox" name="is_active" id="missionActive" <?= (!$editMission || $editMission['is_active']) ? 'checked' : '' ?>>
      <label for="missionActive">Aktif</label>
    </div>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn-primary"><?= $editMission ? '💾 Simpan' : '➕ Tambah' ?></button>
      <?php if ($editMission): ?><a href="<?= BASE_URL ?>/admin/missions.php" class="btn-ghost">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

</div>

<?php adminFooter(); ?>
