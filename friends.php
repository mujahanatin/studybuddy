<?php
// friends.php
require_once 'includes/config.php';
requireLogin();
$active_page = 'friends';
$uid = $_SESSION['user_id'];
$initials = strtoupper(substr($_SESSION['full_name'], 0, 2));
$msg = '';

// Kirim permintaan teman
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_request'])) {
    $target = (int)$_POST['target_id'];
    if ($target && $target !== $uid) {
        $chk = mysqli_prepare($conn,"SELECT id FROM friends WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)");
        mysqli_stmt_bind_param($chk,'iiii',$uid,$target,$target,$uid);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) > 0) {
            $msg = 'error:Permintaan sudah pernah dikirim atau sudah berteman.';
        } else {
            $st = mysqli_prepare($conn,"INSERT INTO friends (user_id,friend_id,status) VALUES (?,?,'pending')");
            mysqli_stmt_bind_param($st,'ii',$uid,$target);
            mysqli_stmt_execute($st);
            $msg = 'success:Permintaan pertemanan berhasil dikirim!';
        }
    }
}

// Terima permintaan
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accept_request'])) {
    $rid = (int)$_POST['request_id'];
    mysqli_query($conn,"UPDATE friends SET status='accepted' WHERE id=$rid AND friend_id=$uid");
    $row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT user_id FROM friends WHERE id=$rid"));
    if ($row) {
        $from = (int)$row['user_id'];
        $chk2 = mysqli_fetch_row(mysqli_query($conn,"SELECT id FROM friends WHERE user_id=$uid AND friend_id=$from"));
        if (!$chk2) {
            $st2 = mysqli_prepare($conn,"INSERT INTO friends (user_id,friend_id,status) VALUES (?,?,'accepted')");
            mysqli_stmt_bind_param($st2,'ii',$uid,$from); mysqli_stmt_execute($st2);
        } else {
            mysqli_query($conn,"UPDATE friends SET status='accepted' WHERE user_id=$uid AND friend_id=$from");
        }
    }
    $msg = 'success:Permintaan pertemanan diterima!';
}

// Tolak permintaan
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reject_request'])) {
    $rid = (int)$_POST['request_id'];
    mysqli_query($conn,"DELETE FROM friends WHERE id=$rid AND friend_id=$uid");
    $msg = 'success:Permintaan ditolak.';
}

// Hapus teman
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['remove_friend'])) {
    $fid = (int)$_POST['friend_id'];
    mysqli_query($conn,"DELETE FROM friends WHERE (user_id=$uid AND friend_id=$fid) OR (user_id=$fid AND friend_id=$uid)");
    $msg = 'success:Teman dihapus.';
}

// Cari user
$search_results = [];
$search_query = trim($_GET['search'] ?? '');
if ($search_query) {
    $like = '%'.$search_query.'%';
    $sq = mysqli_prepare($conn,
        "SELECT u.id, u.full_name, u.username,
                p.name as pet_name, p.stage as pet_stage, p.level as pet_level,
                (SELECT status FROM friends WHERE (user_id=? AND friend_id=u.id) OR (user_id=u.id AND friend_id=?) LIMIT 1) as friend_status
         FROM users u LEFT JOIN pets p ON p.user_id=u.id
         WHERE u.id != ? AND (u.full_name LIKE ? OR u.username LIKE ?) LIMIT 10");
    mysqli_stmt_bind_param($sq,'iiiss',$uid,$uid,$uid,$like,$like);
    mysqli_stmt_execute($sq);
    $search_results = mysqli_fetch_all(mysqli_stmt_get_result($sq), MYSQLI_ASSOC);
}

// Daftar teman
$friends = mysqli_fetch_all(mysqli_query($conn,
    "SELECT u.id,u.full_name,u.username,p.name as pet_name,p.stage as pet_stage,p.level as pet_level,DATEDIFF(NOW(),p.born_at) as pet_age
     FROM friends f JOIN users u ON u.id=f.friend_id LEFT JOIN pets p ON p.user_id=u.id
     WHERE f.user_id=$uid AND f.status='accepted' ORDER BY u.full_name ASC"), MYSQLI_ASSOC);

// Permintaan masuk
$requests = mysqli_fetch_all(mysqli_query($conn,
    "SELECT f.id as req_id,u.id,u.full_name,u.username,f.created_at
     FROM friends f JOIN users u ON u.id=f.user_id
     WHERE f.friend_id=$uid AND f.status='pending' ORDER BY f.created_at DESC"), MYSQLI_ASSOC);

// Permintaan terkirim
$sent = mysqli_fetch_all(mysqli_query($conn,
    "SELECT f.id as req_id,u.id,u.full_name,u.username,f.created_at
     FROM friends f JOIN users u ON u.id=f.friend_id
     WHERE f.user_id=$uid AND f.status='pending' ORDER BY f.created_at DESC"), MYSQLI_ASSOC);

$pe_map = ['Amink'=>'🥚','Ponyo'=>'🐣','Shuihi'=>'🐥','Felix'=>'🦁'];
[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];

function avatarColor($str) { return '#'.substr(md5($str),0,6); }
function avatarInit($str) { return strtoupper(substr($str,0,2)); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Teman — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/main.css">
<style>
.tabs{display:flex;gap:0;margin-bottom:24px;border-bottom:2px solid var(--border)}
.tab-btn{padding:10px 20px;font-size:13px;font-weight:600;color:var(--muted);background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;margin-bottom:-2px;transition:all .15s;display:flex;align-items:center;gap:6px}
.tab-btn:hover{color:var(--text)}
.tab-btn.active{color:var(--purple);border-bottom-color:var(--purple)}
.tab-content{display:none}.tab-content.active{display:block}
.friend-card{display:flex;align-items:center;gap:14px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:10px;transition:border-color .15s}
.friend-card:hover{border-color:var(--purple)}
.f-avatar{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:#fff;flex-shrink:0}
.f-name{font-size:14px;font-weight:700}
.f-uname{font-size:12px;color:var(--muted);margin-top:2px}
.f-pet{font-size:12px;color:var(--muted);margin-top:5px}
.f-actions{display:flex;gap:8px;margin-left:auto;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end}
.search-row{display:flex;gap:10px;margin-bottom:20px}
.search-row input{flex:1;padding:11px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;font-family:inherit;background:var(--bg);color:var(--text);outline:none;transition:border-color .2s}
.search-row input:focus{border-color:var(--purple);background:#fff}
.req-card{display:flex;align-items:center;gap:14px;background:#EEF2FF;border:1px solid #c7d2fe;border-radius:12px;padding:14px 16px;margin-bottom:10px}
.sent-card{display:flex;align-items:center;gap:14px;background:#FFFAF0;border:1px solid #fde68a;border-radius:12px;padding:14px 16px;margin-bottom:10px}
.empty-state{text-align:center;padding:48px 20px;background:var(--card);border:1px solid var(--border);border-radius:12px;color:var(--muted)}
.empty-state .ico{font-size:40px;margin-bottom:12px}
.empty-state p{font-size:13px}
.notif-dot{background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;min-width:18px;text-align:center}
</style>
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <h1>👥 Teman</h1>
      <div class="topbar-right">
        <button class="btn primary" onclick="switchTab('search')">+ Cari Teman</button>
        <?= avatarHtml($_SESSION["full_name"], mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=$uid"))["avatar"] ?? null, 36) ?>
      </div>
    </div>
    <div class="page-body">
      <?php if ($msg_text): ?>
        <div class="alert <?= $msg_type ?>"><?= e($msg_text) ?></div>
      <?php endif; ?>

      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('list')" id="tbtn-list">
          Teman Saya <span style="color:var(--muted);font-weight:400">(<?= count($friends) ?>)</span>
        </button>
        <button class="tab-btn" onclick="switchTab('search')" id="tbtn-search">🔍 Cari Teman</button>
        <button class="tab-btn" onclick="switchTab('requests')" id="tbtn-requests">
          Permintaan Masuk
          <?php if(count($requests)>0): ?><span class="notif-dot"><?= count($requests) ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('sent')" id="tbtn-sent">
          Terkirim <span style="color:var(--muted);font-weight:400">(<?= count($sent) ?>)</span>
        </button>
      </div>

      <!-- Daftar teman -->
      <div class="tab-content active" id="tab-list">
        <?php if (empty($friends)): ?>
          <div class="empty-state"><div class="ico">👀</div><p>Belum ada teman.<br>Gunakan tab <b>Cari Teman</b> untuk mencari teman!</p></div>
        <?php else: foreach ($friends as $f):
          $pe = $pe_map[$f['pet_stage']??'Amink']??'🥚'; ?>
          <div class="friend-card">
            <div class="f-avatar" style="background:<?= avatarColor($f['username']) ?>"><?= avatarInit($f['full_name']) ?></div>
            <div>
              <div class="f-name"><?= e($f['full_name']) ?></div>
              <div class="f-uname">@<?= e($f['username']) ?></div>
              <?php if($f['pet_name']): ?>
                <a href="view_pet.php?user_id=<?= $f['id'] ?>" class="f-pet" style="color:var(--muted);text-decoration:none;display:inline-flex;align-items:center;gap:4px">
                  <?= $pe ?> <?= e($f['pet_name']) ?> · Lv<?= $f['pet_level'] ?> · <?= $f['pet_age'] ?> hari <span style="color:var(--purple);font-weight:600">→ Lihat</span>
                </a>
              <?php endif; ?>
            </div>
            <div class="f-actions">
              <a href="chat.php?with=<?= $f['id'] ?>" class="btn primary sm">💬 Chat</a>
              <form method="POST" onsubmit="return confirm('Hapus <?= e(addslashes($f['full_name'])) ?> dari teman?')">
                <input type="hidden" name="remove_friend" value="1">
                <input type="hidden" name="friend_id" value="<?= $f['id'] ?>">
                <button type="submit" class="btn sm danger">Hapus</button>
              </form>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Cari teman -->
      <div class="tab-content" id="tab-search">
        <form method="GET" class="search-row">
          <input type="text" name="search" placeholder="Ketik nama lengkap atau username..." value="<?= e($search_query) ?>" id="search-input">
          <button type="submit" class="btn primary">Cari</button>
        </form>
        <?php if ($search_query && empty($search_results)): ?>
          <div class="empty-state"><div class="ico">🔍</div><p>Tidak ada pengguna "<b><?= e($search_query) ?></b>"</p></div>
        <?php elseif (!$search_query): ?>
          <div class="empty-state"><div class="ico">🔍</div><p>Masukkan nama atau username teman yang ingin dicari</p></div>
        <?php else: foreach ($search_results as $u):
          $pe = $pe_map[$u['pet_stage']??'Amink']??'🥚'; ?>
          <div class="friend-card">
            <div class="f-avatar" style="background:<?= avatarColor($u['username']) ?>"><?= avatarInit($u['full_name']) ?></div>
            <div>
              <div class="f-name"><?= e($u['full_name']) ?></div>
              <div class="f-uname">@<?= e($u['username']) ?></div>
              <?php if($u['pet_name']): ?><div class="f-pet"><?= $pe ?> <?= e($u['pet_name']) ?> · Lv<?= $u['pet_level'] ?></div><?php endif; ?>
            </div>
            <div class="f-actions">
              <?php if ($u['friend_status']==='accepted'): ?>
                <span class="btn sm" style="color:var(--green);border-color:var(--green);cursor:default">Berteman</span>
                <a href="chat.php?with=<?= $u['id'] ?>" class="btn primary sm">Chat</a>
              <?php elseif ($u['friend_status']==='pending'): ?>
                <span class="btn sm" style="color:var(--orange);border-color:var(--orange);cursor:default">Menunggu</span>
              <?php else: ?>
                <form method="POST">
                  <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                  <button type="submit" name="send_request" class="btn primary sm">+ Tambah Teman</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Permintaan masuk -->
      <div class="tab-content" id="tab-requests">
        <?php if (empty($requests)): ?>
          <div class="empty-state"><div class="ico">📭</div><p>Tidak ada permintaan pertemanan masuk.</p></div>
        <?php else: foreach ($requests as $r): ?>
          <div class="req-card">
            <div class="f-avatar" style="background:<?= avatarColor($r['username']) ?>"><?= avatarInit($r['full_name']) ?></div>
            <div style="flex:1">
              <div class="f-name"><?= e($r['full_name']) ?></div>
              <div class="f-uname">@<?= e($r['username']) ?></div>
              <div style="font-size:11px;color:var(--muted);margin-top:4px">Dikirim <?= date('d M Y', strtotime($r['created_at'])) ?></div>
            </div>
            <div class="f-actions">
              <form method="POST">
                <input type="hidden" name="request_id" value="<?= $r['req_id'] ?>">
                <button type="submit" name="accept_request" class="btn primary sm">Terima</button>
              </form>
              <form method="POST" onsubmit="return confirm('Tolak permintaan ini?')">
                <input type="hidden" name="request_id" value="<?= $r['req_id'] ?>">
                <button type="submit" name="reject_request" class="btn sm danger">Tolak</button>
              </form>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Permintaan terkirim -->
      <div class="tab-content" id="tab-sent">
        <?php if (empty($sent)): ?>
          <div class="empty-state"><div class="ico">📤</div><p>Belum ada permintaan yang dikirim.</p></div>
        <?php else: foreach ($sent as $s): ?>
          <div class="sent-card">
            <div class="f-avatar" style="background:<?= avatarColor($s['username']) ?>"><?= avatarInit($s['full_name']) ?></div>
            <div style="flex:1">
              <div class="f-name"><?= e($s['full_name']) ?></div>
              <div class="f-uname">@<?= e($s['username']) ?></div>
              <div style="font-size:11px;color:#C05621;margin-top:4px">Menunggu konfirmasi · <?= date('d M Y', strtotime($s['created_at'])) ?></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

    </div>
  </div>
</div>
<script>
function switchTab(name) {
  ['list','search','requests','sent'].forEach(t => {
    document.getElementById('tbtn-'+t).classList.remove('active');
    document.getElementById('tab-'+t).classList.remove('active');
  });
  document.getElementById('tbtn-'+name).classList.add('active');
  document.getElementById('tab-'+name).classList.add('active');
  if (name==='search') setTimeout(()=>document.getElementById('search-input').focus(),50);
}
<?php if ($search_query): ?>switchTab('search');<?php endif; ?>
<?php if (!$search_query && count($requests)>0): ?>switchTab('requests');<?php endif; ?>
</script>
</body>
</html>
