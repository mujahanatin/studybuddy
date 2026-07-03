<?php
// chat.php
require_once 'includes/config.php';
requireLogin();
$active_page = 'chat';
$uid = $_SESSION['user_id'];
$initials = strtoupper(substr($_SESSION['full_name'], 0, 2));

// Kirim pesan
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_msg'])) {
    $to  = (int)$_POST['to_id'];
    $msg = trim($_POST['message'] ?? '');
    if ($to && $msg) {
        // Pastikan mereka berteman
        $chk = mysqli_fetch_row(mysqli_query($conn,
            "SELECT id FROM friends WHERE ((user_id=$uid AND friend_id=$to) OR (user_id=$to AND friend_id=$uid)) AND status='accepted'"));
        if ($chk) {
            $st = mysqli_prepare($conn,"INSERT INTO messages (sender_id,receiver_id,content) VALUES (?,?,?)");
            mysqli_stmt_bind_param($st,'iis',$uid,$to,$msg);
            mysqli_stmt_execute($st);

            // Tambah XP ke pet karena chatting
            mysqli_query($conn,"UPDATE pets SET xp=xp+5 WHERE user_id=$uid");
            mysqli_query($conn,"UPDATE pets SET level=FLOOR(xp/200)+1, stage=CASE WHEN level>=10 THEN 'adult' WHEN level>=5 THEN 'teen' WHEN level>=2 THEN 'baby' ELSE 'egg' END WHERE user_id=$uid");

            // Catat interaksi pet
            $pet_row = mysqli_fetch_row(mysqli_query($conn,"SELECT id FROM pets WHERE user_id=$uid"));
            if ($pet_row) {
                $pid = $pet_row[0];
                $st2 = mysqli_prepare($conn,"INSERT INTO pet_interactions (pet_id,type,xp_gained) VALUES (?,'chat',5)");
                mysqli_stmt_bind_param($st2,'i',$pid); mysqli_stmt_execute($st2);
            }
        }
    }
    // Redirect supaya tidak double submit
    header('Location: chat.php?with='.$to);
    exit;
}

// Tandai pesan sebagai dibaca
$with_id = (int)($_GET['with'] ?? 0);
if ($with_id) {
    mysqli_query($conn,"UPDATE messages SET is_read=1 WHERE sender_id=$with_id AND receiver_id=$uid AND is_read=0");
}

// Daftar teman untuk panel kiri
$friends_list = mysqli_fetch_all(mysqli_query($conn,
    "SELECT u.id, u.full_name, u.username,
            (SELECT content FROM messages
             WHERE (sender_id=u.id AND receiver_id=$uid) OR (sender_id=$uid AND receiver_id=u.id)
             ORDER BY sent_at DESC LIMIT 1) as last_msg,
            (SELECT sent_at FROM messages
             WHERE (sender_id=u.id AND receiver_id=$uid) OR (sender_id=$uid AND receiver_id=u.id)
             ORDER BY sent_at DESC LIMIT 1) as last_time,
            (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=$uid AND is_read=0) as unread_count
     FROM friends f JOIN users u ON u.id=f.friend_id
     WHERE f.user_id=$uid AND f.status='accepted'
     ORDER BY last_time DESC, u.full_name ASC"), MYSQLI_ASSOC);

// Data teman yang sedang dibuka
$with_user = null;
$messages   = [];
if ($with_id) {
    $wq = mysqli_prepare($conn,"SELECT id,full_name,username FROM users WHERE id=?");
    mysqli_stmt_bind_param($wq,'i',$with_id); mysqli_stmt_execute($wq);
    $with_user = mysqli_fetch_assoc(mysqli_stmt_get_result($wq));

    if ($with_user) {
        $mq = mysqli_query($conn,
            "SELECT * FROM messages
             WHERE (sender_id=$uid AND receiver_id=$with_id) OR (sender_id=$with_id AND receiver_id=$uid)
             ORDER BY sent_at ASC");
        $messages = mysqli_fetch_all($mq, MYSQLI_ASSOC);
    }
}

function avatarColor($str) { return '#'.substr(md5($str),0,6); }
function avatarInit($str)  { return strtoupper(substr($str,0,2)); }
function timeAgo($dt) {
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'baru saja';
    if ($diff < 3600) return floor($diff/60).' mnt lalu';
    if ($diff < 86400) return floor($diff/3600).' jam lalu';
    return date('d M', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Chat — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/main.css">
<script src="assets/js/notif.js" defer></script>
<style>
/* Override page-body padding untuk chat */
.chat-layout{display:flex;height:calc(100vh - 61px);overflow:hidden}

/* Panel kiri: daftar teman */
.chat-list{width:280px;min-width:280px;border-right:1px solid var(--border);display:flex;flex-direction:column;background:var(--card)}
.chat-list-header{padding:16px;font-size:14px;font-weight:700;border-bottom:1px solid var(--border)}
.chat-list-body{flex:1;overflow-y:auto}
.chat-friend-item{display:flex;align-items:center;gap:12px;padding:12px 16px;cursor:pointer;text-decoration:none;color:var(--text);transition:background .15s;border-left:3px solid transparent}
.chat-friend-item:hover{background:var(--bg)}
.chat-friend-item.active{background:var(--purple-light);border-left-color:var(--purple)}
.cf-avatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0}
.cf-name{font-size:13px;font-weight:600}
.cf-last{font-size:11px;color:var(--muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px}
.cf-time{font-size:10px;color:var(--muted);margin-left:auto;flex-shrink:0;align-self:flex-start;margin-top:2px}
.cf-unread{background:var(--purple);color:#fff;font-size:10px;font-weight:700;border-radius:10px;padding:1px 6px;min-width:18px;text-align:center}

/* Area chat kanan */
.chat-area{flex:1;display:flex;flex-direction:column;overflow:hidden}
.chat-header{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;background:var(--card)}
.chat-messages{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:4px;background:var(--bg)}
.msg-group{display:flex;flex-direction:column;gap:3px;margin-bottom:8px}
.msg-group.me{align-items:flex-end}
.msg-group.them{align-items:flex-start}
.msg-bubble{max-width:65%;padding:10px 14px;border-radius:14px;font-size:14px;line-height:1.55;word-break:break-word}
.msg-group.me .msg-bubble{background:var(--purple);color:#fff;border-bottom-right-radius:4px}
.msg-group.them .msg-bubble{background:var(--card);border:1px solid var(--border);border-bottom-left-radius:4px}
.msg-time{font-size:10px;color:var(--muted);margin-top:3px;padding:0 4px}
.msg-date-divider{text-align:center;font-size:11px;color:var(--muted);margin:12px 0;display:flex;align-items:center;gap:10px}
.msg-date-divider::before,.msg-date-divider::after{content:'';flex:1;height:1px;background:var(--border)}

/* Input area */
.chat-input-area{padding:14px 20px;border-top:1px solid var(--border);background:var(--card);display:flex;gap:10px;align-items:flex-end}
.chat-input-area textarea{flex:1;border:1.5px solid var(--border);border-radius:12px;padding:10px 14px;font-size:14px;font-family:inherit;background:var(--bg);color:var(--text);outline:none;resize:none;max-height:120px;line-height:1.5;transition:border-color .2s}
.chat-input-area textarea:focus{border-color:var(--purple);background:#fff}
.send-btn{width:42px;height:42px;border-radius:50%;background:var(--purple);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;transition:background .15s}
.send-btn:hover{background:#5a52e0}

/* Empty chat */
.chat-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--muted);gap:12px}
.chat-empty .ico{font-size:52px}
.chat-empty h3{font-size:16px;font-weight:600;color:var(--text)}
.chat-empty p{font-size:13px}
</style>
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content" style="overflow:hidden">
    <div class="topbar">
      <h1>Chat</h1>
      <div class="topbar-right">
        <div class="notif-bell-wrap" id="notif-bell-wrap"></div>
        <?= avatarHtml($_SESSION["full_name"], mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=$uid"))["avatar"] ?? null, 36) ?>
      </div>
    </div>

    <div class="chat-layout">
      <!-- Panel kiri: daftar teman -->
      <div class="chat-list">
        <div class="chat-list-header">Percakapan</div>
        <div class="chat-list-body">
          <?php if (empty($friends_list)): ?>
            <div style="padding:20px;text-align:center;color:var(--muted);font-size:13px">
              Belum ada teman.<br>
              <a href="friends.php" style="color:var(--purple);font-weight:600">Cari teman →</a>
            </div>
          <?php endif; ?>
          <?php foreach ($friends_list as $f): ?>
          <a href="chat.php?with=<?= $f['id'] ?>" class="chat-friend-item <?= ($with_id==$f['id'])?'active':'' ?>">
            <div class="cf-avatar" style="background:<?= avatarColor($f['username']) ?>"><?= avatarInit($f['full_name']) ?></div>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:6px">
                <span class="cf-name"><?= e($f['full_name']) ?></span>
                <?php if($f['unread_count']>0): ?><span class="cf-unread"><?= $f['unread_count'] ?></span><?php endif; ?>
              </div>
              <div class="cf-last"><?= $f['last_msg'] ? e(substr($f['last_msg'],0,40)) : 'Belum ada pesan' ?></div>
            </div>
            <?php if($f['last_time']): ?><div class="cf-time"><?= timeAgo($f['last_time']) ?></div><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Area chat kanan -->
      <div class="chat-area">
        <?php if (!$with_user): ?>
          <div class="chat-empty">
            <div class="ico">💬</div>
            <h3>Pilih teman untuk mulai chat</h3>
            <p>Atau <a href="friends.php" style="color:var(--purple);font-weight:600">tambah teman baru</a> terlebih dahulu</p>
          </div>

        <?php else: ?>
          <!-- Header chat -->
          <div class="chat-header">
            <div class="cf-avatar" style="background:<?= avatarColor($with_user['username']) ?>;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff">
              <?= avatarInit($with_user['full_name']) ?>
            </div>
            <div>
              <div style="font-size:14px;font-weight:700"><?= e($with_user['full_name']) ?></div>
              <div style="font-size:12px;color:var(--muted)">@<?= e($with_user['username']) ?></div>
            </div>
            <div style="margin-left:auto">
              <a href="friends.php" class="btn sm">Profil</a>
            </div>
          </div>

          <!-- Pesan -->
          <div class="chat-messages" id="chat-messages">
            <?php if (empty($messages)): ?>
              <div style="text-align:center;color:var(--muted);font-size:13px;margin:auto">
                Belum ada pesan. Mulai percakapan!
              </div>
            <?php endif; ?>

            <?php
            $last_date = '';
            $i = 0;
            $n = count($messages);
            while ($i < $n):
              $m = $messages[$i];
              $is_me = $m['sender_id'] == $uid;
              $msg_date = date('d M Y', strtotime($m['sent_at']));

              // Tampilkan divider tanggal
              if ($msg_date !== $last_date):
                $last_date = $msg_date;
            ?>
              <div class="msg-date-divider"><?= $msg_date ?></div>
            <?php endif; ?>

            <?php
              // Kumpulkan pesan berurutan dari orang yang sama
              $group_msgs = [$m];
              while ($i+1 < $n && $messages[$i+1]['sender_id'] == $m['sender_id'] && date('d M Y',strtotime($messages[$i+1]['sent_at']))===$msg_date):
                $i++;
                $group_msgs[] = $messages[$i];
              endwhile;
            ?>
            <div class="msg-group <?= $is_me?'me':'them' ?>">
              <?php foreach ($group_msgs as $gm): ?>
                <div class="msg-bubble"><?= nl2br(e($gm['content'])) ?></div>
              <?php endforeach; ?>
              <div class="msg-time"><?= date('H:i', strtotime(end($group_msgs)['sent_at'])) ?></div>
            </div>

            <?php $i++; endwhile; ?>
          </div>

          <!-- Input pesan -->
          <div class="chat-input-area">
            <form method="POST" id="chat-form" style="display:contents">
              <input type="hidden" name="to_id" value="<?= $with_id ?>">
              <textarea name="message" id="msg-input" placeholder="Tulis pesan..." rows="1"
                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('chat-form').submit()}"
                oninput="autoResize(this)"></textarea>
              <button type="submit" name="send_msg" class="send-btn" title="Kirim (Enter)">➤</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// Scroll ke bawah otomatis
const chatMessages = document.getElementById('chat-messages');
if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;

// Auto resize textarea
function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// Auto refresh chat setiap 5 detik
<?php if ($with_id): ?>
setInterval(() => {
  const params = new URLSearchParams(window.location.search);
  if (params.get('with')) {
    fetch('chat_poll.php?with=<?= $with_id ?>&last=<?= empty($messages)?0:end($messages)['id'] ?>')
      .then(r => r.json())
      .then(data => {
        if (data.new_messages && data.new_messages.length > 0) {
          location.reload();
        }
      }).catch(()=>{});
  }
}, 5000);
<?php endif; ?>
</script>
</body>
</html>
