<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin();
require_once __DIR__ . '/includes/layout.php';

$admin = getSessionAdmin();

// ── AJAX / API Endpoints ──────────────────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_GET['action'];
    $roomId = (int)($_GET['room_id'] ?? 0);

    // Poll new messages
    if ($action === 'poll' && $roomId) {
        $since  = (int)($_GET['since'] ?? 0);
        $msgs   = db()->fetchAll(
            'SELECT cm.*, COALESCE(au.full_name, u.full_name) as sender_name, cm.is_admin
             FROM chat_messages cm
             LEFT JOIN users u ON cm.user_id = u.id AND cm.is_admin = 0
             LEFT JOIN admin_users au ON cm.admin_id = au.id AND cm.is_admin = 1
             WHERE cm.room_id = ? AND cm.id > ?
             ORDER BY cm.id ASC LIMIT 50',
            'ii', $roomId, $since
        );
        // Mark admin-unread as read
        db()->execute('UPDATE chat_rooms SET unread_admin = 0 WHERE id = ?', 'i', $roomId);
        echo json_encode(['success' => true, 'messages' => $msgs]);
        exit;
    }

    // Send reply
    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) { echo json_encode(['success'=>false,'message'=>'CSRF invalid']); exit; }
        $msg  = trim($_POST['message'] ?? '');
        $rid  = (int)($_POST['room_id'] ?? 0);
        if (empty($msg) || !$rid) { echo json_encode(['success'=>false,'message'=>'Pesan kosong']); exit; }

        db()->execute(
            'INSERT INTO chat_messages (room_id, admin_id, message, is_admin) VALUES (?,?,?,1)',
            'iis', $rid, $admin['id'], $msg
        );
        $newId = db()->lastInsertId();

        // Update room
        db()->execute(
            'UPDATE chat_rooms SET last_message = ?, last_message_at = NOW(), unread_user = unread_user + 1 WHERE id = ?',
            'si', $msg, $rid
        );

        // Notify user
        $room = db()->fetchOne('SELECT user_id FROM chat_rooms WHERE id = ?', 'i', $rid);
        if ($room) {
            sendNotification($room['user_id'], 'Pesan Baru dari CS', truncate($msg, 80), NOTIF_INFO, BASE_URL . '/pages/chat.php');
        }

        logAdminAction($admin['id'], 'chat_reply', 'chat_room', $rid);
        echo json_encode(['success' => true, 'id' => $newId]);
        exit;
    }

    // Mark read
    if ($action === 'mark_read' && $roomId) {
        db()->execute('UPDATE chat_rooms SET unread_admin = 0 WHERE id = ?', 'i', $roomId);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ── Current room ──────────────────────────────────────────
$activeRoomId = (int)($_GET['room'] ?? 0);
$activeRoom   = null;
$messages     = [];

if ($activeRoomId) {
    $activeRoom = db()->fetchOne(
        'SELECT cr.*, u.full_name, u.username, u.avatar, u.vip_level, u.is_blocked
         FROM chat_rooms cr JOIN users u ON cr.user_id = u.id
         WHERE cr.id = ?', 'i', $activeRoomId
    );
    if ($activeRoom) {
        $messages = db()->fetchAll(
            'SELECT cm.*, COALESCE(au.full_name, u.full_name) as sender_name, cm.is_admin
             FROM chat_messages cm
             LEFT JOIN users u ON cm.user_id = u.id AND cm.is_admin = 0
             LEFT JOIN admin_users au ON cm.admin_id = au.id AND cm.is_admin = 1
             WHERE cm.room_id = ?
             ORDER BY cm.created_at ASC LIMIT 100',
            'i', $activeRoomId
        );
        db()->execute('UPDATE chat_rooms SET unread_admin = 0 WHERE id = ?', 'i', $activeRoomId);
    }
}

// ── Room list ─────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$where  = $search ? 'AND (u.full_name LIKE ? OR u.username LIKE ?)' : '';
$sParam = $search ? ['%'.$search.'%', '%'.$search.'%'] : [];
$sTypes = $search ? 'ss' : '';

$rooms = db()->fetchAll(
    "SELECT cr.*, u.full_name, u.username, u.avatar, u.vip_level
     FROM chat_rooms cr JOIN users u ON cr.user_id = u.id
     WHERE 1=1 $where
     ORDER BY cr.unread_admin DESC, cr.last_message_at DESC
     LIMIT 60",
    $sTypes, ...$sParam
);

$quickReplies = [
    'Halo! Ada yang bisa kami bantu? 😊',
    'Baik, kami akan segera proses.',
    'Mohon tunggu sebentar ya.',
    'Deposit Anda sudah kami konfirmasi ✅',
    'Penarikan sedang diproses, mohon tunggu 1x24 jam.',
    'Silakan kirim bukti transfer ya.',
    'Terima kasih sudah menghubungi CS NOXARA! 🙏',
];

adminHeader('Live Chat', 'chat');
?>

<style>
.chat-layout { display:grid; grid-template-columns:280px 1fr; gap:0; height:calc(100vh - 140px); min-height:500px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.chat-sidebar { border-right:1px solid var(--border); display:flex; flex-direction:column; overflow:hidden; }
.chat-search { padding:10px; border-bottom:1px solid var(--border); }
.chat-room-list { flex:1; overflow-y:auto; }
.chat-room-item { display:flex; align-items:center; gap:10px; padding:10px 12px; cursor:pointer; border-bottom:1px solid var(--border); transition:var(--transition); text-decoration:none; color:inherit; }
.chat-room-item:hover { background:var(--bg-hover); }
.chat-room-item.active { background:var(--cyan-dim); border-left:3px solid var(--cyan); }
.chat-room-avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; flex-shrink:0; }
.chat-room-info { flex:1; min-width:0; }
.chat-room-name { font-weight:600; font-size:.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.chat-room-preview { font-size:.72rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.chat-unread { min-width:20px; height:20px; background:var(--text-danger); color:#fff; font-size:.65rem; font-weight:700; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.chat-main { display:flex; flex-direction:column; overflow:hidden; }
.chat-header { padding:12px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; background:var(--bg-card2); flex-shrink:0; }
.chat-messages { flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:10px; }
.chat-bubble-wrap { display:flex; gap:8px; align-items:flex-end; }
.chat-bubble-wrap.admin { flex-direction:row-reverse; }
.chat-bubble { max-width:70%; padding:10px 14px; border-radius:18px; font-size:.875rem; line-height:1.5; word-break:break-word; }
.chat-bubble.user-msg { background:var(--bg-card2); border-bottom-left-radius:4px; }
.chat-bubble.admin-msg { background:var(--gradient); color:#fff; border-bottom-right-radius:4px; }
.chat-bubble-time { font-size:.65rem; color:var(--text-muted); flex-shrink:0; margin-bottom:4px; }
.chat-footer { padding:12px 16px; border-top:1px solid var(--border); background:var(--bg-card2); flex-shrink:0; }
.chat-quick-replies { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px; }
.quick-reply-btn { font-size:.72rem; padding:3px 10px; background:var(--bg-input); border:1px solid var(--border); border-radius:var(--radius-full); cursor:pointer; color:var(--text-secondary); transition:var(--transition); }
.quick-reply-btn:hover { border-color:var(--cyan); color:var(--cyan); }
.chat-input-row { display:flex; gap:8px; }
.chat-input { flex:1; background:var(--bg-input); border:1px solid var(--border); border-radius:var(--radius-full); padding:10px 16px; color:var(--text-primary); font-family:var(--font-main); font-size:.875rem; outline:none; resize:none; }
.chat-input:focus { border-color:var(--cyan); }
.chat-empty { display:flex; align-items:center; justify-content:center; flex:1; color:var(--text-muted); flex-direction:column; gap:12px; }
</style>

<div class="chat-layout">

  <!-- Sidebar: room list -->
  <div class="chat-sidebar">
    <div class="chat-search">
      <input type="text" id="chatSearch" class="form-control" placeholder="Cari member..." style="font-size:.8rem" value="<?= e($search) ?>" onkeydown="if(event.key==='Enter'){ window.location='?q='+encodeURIComponent(this.value)+'<?= $activeRoomId ? '&room='.$activeRoomId : '' ?>'; }">
    </div>
    <div class="chat-room-list" id="roomList">
      <?php if (empty($rooms)): ?>
      <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:.85rem">Tidak ada chat</div>
      <?php endif; ?>
      <?php foreach ($rooms as $r): ?>
      <a href="?room=<?= $r['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="chat-room-item <?= $activeRoomId === (int)$r['id'] ? 'active' : '' ?>">
        <img src="<?= avatarUrl($r['avatar']) ?>" class="chat-room-avatar" alt="">
        <div class="chat-room-info">
          <div class="chat-room-name"><?= e($r['full_name']) ?></div>
          <div class="chat-room-preview"><?= e(truncate($r['last_message'] ?? 'Belum ada pesan', 32)) ?></div>
          <?php if ($r['last_message_at']): ?><div style="font-size:.65rem;color:var(--text-muted)"><?= timeAgo($r['last_message_at']) ?></div><?php endif; ?>
        </div>
        <?php if ((int)$r['unread_admin'] > 0): ?>
        <div class="chat-unread"><?= $r['unread_admin'] ?></div>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Main chat area -->
  <div class="chat-main">
    <?php if ($activeRoom): ?>

    <!-- Chat header -->
    <div class="chat-header">
      <img src="<?= avatarUrl($activeRoom['avatar']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
      <div style="flex:1">
        <div style="font-weight:700;font-size:.9rem"><?= e($activeRoom['full_name']) ?> <span style="color:var(--text-muted);font-weight:400">@<?= e($activeRoom['username']) ?></span></div>
        <div style="font-size:.72rem;color:var(--text-muted)"><?= vipBadge($activeRoom['vip_level']) ?> <?= $activeRoom['is_blocked'] ? '<span class="badge badge-danger">Diblokir</span>' : '' ?></div>
      </div>
      <a href="<?= BASE_URL ?>/admin/members.php?id=<?= $activeRoom['user_id'] ?>" target="_blank" class="btn-ghost btn-sm">👤 Profil</a>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages">
      <?php if (empty($messages)): ?>
      <div style="text-align:center;color:var(--text-muted);padding:32px;font-size:.875rem">Belum ada pesan dalam room ini</div>
      <?php endif; ?>
      <?php foreach ($messages as $m): ?>
      <div class="chat-bubble-wrap <?= $m['is_admin'] ? 'admin' : '' ?>" data-msg-id="<?= $m['id'] ?>">
        <div class="chat-bubble <?= $m['is_admin'] ? 'admin-msg' : 'user-msg' ?>">
          <?= nl2br(e($m['message'])) ?>
        </div>
        <div class="chat-bubble-time"><?= formatDatetime($m['created_at']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Footer: quick replies + input -->
    <div class="chat-footer">
      <div class="chat-quick-replies">
        <?php foreach ($quickReplies as $qr): ?>
        <button type="button" class="quick-reply-btn" onclick="setQuickReply(this.dataset.msg)" data-msg="<?= e($qr) ?>"><?= e(truncate($qr, 35)) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="chat-input-row">
        <textarea id="chatInput" class="chat-input" rows="2" placeholder="Tulis pesan..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"></textarea>
        <button type="button" class="btn-primary btn-sm" onclick="sendMessage()" style="border-radius:var(--radius-full);padding:0 18px">Kirim</button>
      </div>
    </div>

    <?php else: ?>
    <div class="chat-empty">
      <div style="font-size:3rem">💬</div>
      <div style="font-weight:600">Pilih chat room dari daftar</div>
      <div style="font-size:.85rem;color:var(--text-muted)">Klik nama member untuk membuka percakapan</div>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php if ($activeRoom): ?>
<script>
const ROOM_ID   = <?= $activeRoomId ?>;
const BASE      = '<?= BASE_URL ?>/admin/chat.php';
let lastMsgId   = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
let pollTimer   = null;

function scrollBottom(force = false) {
  const el = document.getElementById('chatMessages');
  if (el) { const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 120; if (force || nearBottom) el.scrollTop = el.scrollHeight; }
}
scrollBottom(true);

function appendMessage(m) {
  const wrap = document.createElement('div');
  wrap.className = 'chat-bubble-wrap' + (m.is_admin == 1 ? ' admin' : '');
  wrap.dataset.msgId = m.id;
  wrap.innerHTML = `<div class="chat-bubble ${m.is_admin == 1 ? 'admin-msg' : 'user-msg'}">${m.message.replace(/\n/g,'<br>')}</div><div class="chat-bubble-time">${m.created_at ?? ''}</div>`;
  document.getElementById('chatMessages').appendChild(wrap);
}

function pollMessages() {
  fetch(`${BASE}?action=poll&room_id=${ROOM_ID}&since=${lastMsgId}`)
    .then(r => r.json())
    .then(data => {
      if (data.success && data.messages.length) {
        data.messages.forEach(m => { appendMessage(m); lastMsgId = m.id; });
        scrollBottom();
      }
    }).catch(() => {});
  pollTimer = setTimeout(pollMessages, <?= CHAT_POLL_INTERVAL * 1000 ?>);
}
pollTimer = setTimeout(pollMessages, <?= CHAT_POLL_INTERVAL * 1000 ?>);

function sendMessage() {
  const input = document.getElementById('chatInput');
  const msg   = input.value.trim();
  if (!msg) return;
  input.value = '';
  const fd = new FormData();
  fd.append('message', msg);
  fd.append('room_id', ROOM_ID);
  fd.append('csrf_token', CSRF_TOKEN);
  fetch(`${BASE}?action=send`, { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        appendMessage({ id: data.id, message: msg, is_admin: 1, created_at: 'Baru saja' });
        lastMsgId = data.id;
        scrollBottom(true);
      }
    }).catch(() => {});
}

function setQuickReply(msg) {
  const input = document.getElementById('chatInput');
  input.value = msg;
  input.focus();
}
</script>
<?php endif; ?>

<?php adminFooter(); ?>
