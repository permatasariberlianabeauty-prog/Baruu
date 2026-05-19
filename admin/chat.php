<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/bootstrap.php';
define('ADMIN_AREA', true);
initAdminSession();
requireAdmin();
require_once __DIR__ . '/includes/layout.php';
$admin = getSessionAdmin();

// AJAX endpoints
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $roomId = (int)($_GET['room_id'] ?? 0);

    if ($action === 'poll' && $roomId) {
        $since = (int)($_GET['since'] ?? 0);
        $msgs  = db()->fetchAll(
            "SELECT cm.*, COALESCE(au.full_name, u.full_name) as sender_name, cm.sender_type
             FROM chat_messages cm
             LEFT JOIN users u ON cm.sender_id = u.id AND cm.sender_type = 'user'
             LEFT JOIN admin_users au ON cm.sender_id = au.id AND cm.sender_type = 'admin'
             WHERE cm.room_id = ? AND cm.id > ? ORDER BY cm.id ASC LIMIT 30",
            'ii', $roomId, $since
        );
        db()->execute('UPDATE chat_rooms SET unread_admin = 0 WHERE id = ?', 'i', $roomId);
        echo json_encode(['success' => true, 'messages' => $msgs]); exit;
    }

    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>'CSRF invalid']); exit; }
        $msg = trim($_POST['message'] ?? '');
        $rid = (int)($_POST['room_id'] ?? 0);
        if (!$msg || !$rid) { echo json_encode(['success'=>false]); exit; }
        db()->execute("INSERT INTO chat_messages(room_id,sender_type,sender_id,message) VALUES(?,'admin',?,?)","isi",$rid,$admin['id'],$msg);
        $newId = db()->lastInsertId();
        db()->execute('UPDATE chat_rooms SET last_message_at=NOW(),unread_user=unread_user+1 WHERE id=?','i',$rid);
        $room = db()->fetchOne('SELECT user_id FROM chat_rooms WHERE id=?','i',$rid);
        if ($room) sendNotification($room['user_id'],'Pesan dari CS',truncate($msg,80),NOTIF_INFO,BASE_URL.'/pages/chat.php');
        echo json_encode(['success'=>true,'id'=>$newId]); exit;
    }

    echo json_encode(['success'=>false]); exit;
}

$activeRoomId = (int)($_GET['room'] ?? 0);
$activeRoom   = null; $messages = [];

if ($activeRoomId) {
    $activeRoom = db()->fetchOne("SELECT cr.*,u.full_name,u.username,u.avatar,u.vip_level FROM chat_rooms cr JOIN users u ON cr.user_id=u.id WHERE cr.id=?", 'i', $activeRoomId);
    if ($activeRoom) {
        $messages = db()->fetchAll("SELECT cm.*,COALESCE(au.full_name,u.full_name) as sname,cm.sender_type FROM chat_messages cm LEFT JOIN users u ON cm.sender_id=u.id AND cm.sender_type='user' LEFT JOIN admin_users au ON cm.sender_id=au.id AND cm.sender_type='admin' WHERE cm.room_id=? ORDER BY cm.created_at ASC LIMIT 100",'i',$activeRoomId);
        db()->execute('UPDATE chat_rooms SET unread_admin=0 WHERE id=?','i',$activeRoomId);
    }
}

$rooms = db()->fetchAll("SELECT cr.*,u.full_name,u.username,u.avatar,u.vip_level FROM chat_rooms cr JOIN users u ON cr.user_id=u.id ORDER BY cr.unread_admin DESC,cr.last_message_at DESC LIMIT 60");
$templates = db()->fetchAll('SELECT * FROM chat_templates WHERE is_active=1 ORDER BY sort_order LIMIT 10');

adminHeader('Live Chat','chat');
?>
<style>
.cl{display:grid;grid-template-columns:260px 1fr;gap:0;height:calc(100vh - 120px);min-height:500px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
.cs{border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.crl{flex:1;overflow-y:auto}.cri{display:flex;align-items:center;gap:8px;padding:10px 12px;cursor:pointer;border-bottom:1px solid var(--border);text-decoration:none;color:inherit;transition:var(--transition)}
.cri:hover{background:var(--bg-hover)}.cri.act{background:var(--cyan-dim);border-left:3px solid var(--cyan)}
.cm{display:flex;flex-direction:column;overflow:hidden}
.ch{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;background:var(--bg-card2);flex-shrink:0}
.cmsg{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:8px}
.bw{display:flex;gap:8px;align-items:flex-end}.bw.adm{flex-direction:row-reverse}
.bubble{max-width:70%;padding:10px 14px;border-radius:16px;font-size:.875rem;word-break:break-word}
.ubb{background:var(--bg-card2);border-bottom-left-radius:4px}.abb{background:var(--gradient);color:#fff;border-bottom-right-radius:4px}
.bt{font-size:.65rem;color:var(--text-muted);flex-shrink:0;margin-bottom:4px}
.cf{padding:12px 16px;border-top:1px solid var(--border);background:var(--bg-card2);flex-shrink:0}
</style>
<div class="cl">
<div class="cs">
  <div style="padding:10px;border-bottom:1px solid var(--border)"><input type="text" class="form-control" placeholder="Cari member..." style="font-size:.8rem"></div>
  <div class="crl">
    <?php foreach($rooms as $r): ?>
    <a href="?room=<?=$r['id']?>" class="cri <?=$activeRoomId==$r['id']?'act':''?>">
      <img src="<?=avatarUrl($r['avatar'])?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0">
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e($r['full_name'])?></div>
        <div style="font-size:.68rem;color:var(--text-muted)"><?=timeAgo($r['last_message_at']??$r['created_at'])?></div>
      </div>
      <?php if((int)$r['unread_admin']>0):?><div style="min-width:18px;height:18px;background:var(--text-danger);color:#fff;font-size:.65rem;font-weight:700;border-radius:50%;display:flex;align-items:center;justify-content:center"><?=$r['unread_admin']?></div><?php endif;?>
    </a>
    <?php endforeach;if(empty($rooms)):?><div style="padding:20px;text-align:center;color:var(--text-muted);font-size:.85rem">Tidak ada chat</div><?php endif;?>
  </div>
</div>
<div class="cm">
  <?php if($activeRoom): ?>
  <div class="ch">
    <img src="<?=avatarUrl($activeRoom['avatar'])?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover">
    <div style="flex:1"><div style="font-weight:700;font-size:.875rem"><?=e($activeRoom['full_name'])?> <span style="color:var(--text-muted);font-weight:400">@<?=e($activeRoom['username'])?></span></div><div style="font-size:.72rem"><?=vipBadge($activeRoom['vip_level'])?></div></div>
    <a href="<?=BASE_URL?>/admin/members.php?id=<?=$activeRoom['user_id']?>" target="_blank" class="btn-ghost btn-sm">👤</a>
  </div>
  <div class="cmsg" id="chatMsgs">
    <?php foreach($messages as $m): $isA=$m['sender_type']==='admin'; ?>
    <div class="bw <?=$isA?'adm':''?>" data-id="<?=$m['id']?>">
      <div class="bubble <?=$isA?'abb':'ubb'?>"><?=nl2br(e($m['message']??''))?><?php if($m['image']):?><img src="<?=UPLOADS_URL?>/chat/<?=e($m['image'])?>" style="max-width:160px;border-radius:6px;display:block;margin-top:4px"><?php endif;?></div>
      <div class="bt"><?=formatDatetime($m['created_at'])?></div>
    </div>
    <?php endforeach;if(empty($messages)):?><div style="text-align:center;color:var(--text-muted);padding:32px;font-size:.875rem">Belum ada pesan</div><?php endif;?>
  </div>
  <div class="cf">
    <?php if(!empty($templates)): ?>
    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px">
      <?php foreach($templates as $t):?><button type="button" onclick="document.getElementById('ci').value='<?=addslashes(e($t['message']))?>'" style="font-size:.7rem;padding:3px 8px;background:var(--bg-input);border:1px solid var(--border);border-radius:var(--radius-full);cursor:pointer;color:var(--text-secondary)"><?=e(truncate($t['title'],20))?></button><?php endforeach;?>
    </div>
    <?php endif;?>
    <div style="display:flex;gap:8px">
      <textarea id="ci" style="flex:1;background:var(--bg-input);border:1px solid var(--border);border-radius:var(--radius-full);padding:10px 16px;color:var(--text-primary);font-family:var(--font-main);font-size:.875rem;outline:none;resize:none" rows="2" placeholder="Tulis balasan..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"></textarea>
      <button class="btn-primary btn-sm" onclick="sendMsg()" style="border-radius:var(--radius-full);padding:0 18px">Kirim</button>
    </div>
  </div>
  <?php else:?>
  <div style="display:flex;align-items:center;justify-content:center;flex:1;flex-direction:column;gap:10px;color:var(--text-muted)"><div style="font-size:3rem">💬</div><div>Pilih chat room dari daftar</div></div>
  <?php endif;?>
</div>
</div>
<?php if($activeRoom): ?>
<script>
const ROOM_ID=<?=$activeRoomId?>,CSRFT=CSRF_TOKEN;
let lastId=<?=!empty($messages)?end($messages)['id']:0?>;
const box=document.getElementById('chatMsgs');
function scrollB(f=false){if(box){const nb=box.scrollHeight-box.scrollTop-box.clientHeight<100;if(f||nb)box.scrollTop=box.scrollHeight;}}
scrollB(true);
function appendMsg(m){const w=document.createElement('div');w.className='bw'+(m.sender_type==='admin'?' adm':'');w.dataset.id=m.id;w.innerHTML=`<div class="bubble ${m.sender_type==='admin'?'abb':'ubb'}">${(m.message||'').replace(/\n/g,'<br>')}</div><div class="bt">${m.created_at||''}</div>`;box.appendChild(w);}
function pollMsgs(){fetch(`<?=BASE_URL?>/admin/chat.php?action=poll&room_id=${ROOM_ID}&since=${lastId}`).then(r=>r.json()).then(d=>{if(d.success&&d.messages.length){d.messages.forEach(m=>{appendMsg(m);lastId=m.id;});scrollB();}}).catch(()=>{});setTimeout(pollMsgs,<?=CHAT_POLL_INTERVAL*1000?>);}
setTimeout(pollMsgs,<?=CHAT_POLL_INTERVAL*1000?>);
function sendMsg(){const ci=document.getElementById('ci');const msg=ci.value.trim();if(!msg)return;ci.value='';const fd=new FormData();fd.append('message',msg);fd.append('room_id',ROOM_ID);fd.append('csrf_token',CSRFT);fetch('<?=BASE_URL?>/admin/chat.php?action=send',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.success){appendMsg({id:d.id,message:msg,sender_type:'admin',created_at:'Baru saja'});lastId=d.id;scrollB(true);}}).catch(()=>{});}
</script>
<?php endif;?>
<?php adminFooter(); ?>
