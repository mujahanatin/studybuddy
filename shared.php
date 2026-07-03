<?php
// shared.php — Kotak masuk kiriman catatan & tugas dari teman
require_once 'includes/config.php';
requireLogin();
$active_page = 'shared';
$uid = $_SESSION['user_id'];
$initials = strtoupper(substr($_SESSION['full_name'], 0, 2));
$msg = '';

// Simpan catatan ke matkul sendiri
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_note'])) {
    $sn_id    = (int)$_POST['shared_note_id'];
    $course_id = (int)$_POST['save_to_course'];

    // Ambil isi catatan asli
    $sn = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT n.* FROM shared_notes sn
         JOIN notes n ON n.id = sn.note_id
         WHERE sn.id=$sn_id AND sn.receiver_id=$uid"));

    if ($sn && $course_id) {
        // Cek course milik user ini
        $course_ok = mysqli_fetch_row(mysqli_query($conn,"SELECT id FROM courses WHERE id=$course_id AND user_id=$uid"));
        if ($course_ok) {
            $title   = $sn['title'];
            $content = $sn['content'];
            $pert    = $sn['pertemuan'];
            $img     = null; // tidak ikut copy gambar
            $st = mysqli_prepare($conn,"INSERT INTO notes (course_id,user_id,pertemuan,title,content) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($st,'iisss',$course_id,$uid,$pert,$title,$content);
            mysqli_stmt_execute($st);
            $msg = 'success:Catatan berhasil disimpan ke matkul kamu!';
        } else { $msg = 'error:Matkul tidak ditemukan.'; }
    } else { $msg = 'error:Data tidak valid.'; }
}

// Simpan tugas ke matkul sendiri
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_task'])) {
    $st_id     = (int)$_POST['shared_task_id'];
    $course_id = (int)$_POST['save_to_course'];

    $stask = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT t.* FROM shared_tasks st
         JOIN tasks t ON t.id = st.task_id
         WHERE st.id=$st_id AND st.receiver_id=$uid"));

    if ($stask && $course_id) {
        $course_ok = mysqli_fetch_row(mysqli_query($conn,"SELECT id FROM courses WHERE id=$course_id AND user_id=$uid"));
        if ($course_ok) {
            $ttitle = $stask['title'];
            $tdesc  = $stask['description'];
            $tdl    = $stask['deadline'];
            $st2 = mysqli_prepare($conn,"INSERT INTO tasks (course_id,user_id,title,description,deadline) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($st2,'iisss',$course_id,$uid,$ttitle,$tdesc,$tdl);
            mysqli_stmt_execute($st2);
            $msg = 'success:Tugas berhasil disimpan ke matkul kamu!';
        } else { $msg = 'error:Matkul tidak ditemukan.'; }
    } else { $msg = 'error:Data tidak valid.'; }
}

// Hapus kiriman
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_share'])) {
    $sid  = (int)$_POST['share_id'];
    $type = $_POST['share_type'] ?? '';
    if ($type === 'note') mysqli_query($conn,"DELETE FROM shared_notes WHERE id=$sid AND receiver_id=$uid");
    if ($type === 'task') mysqli_query($conn,"DELETE FROM shared_tasks WHERE id=$sid AND receiver_id=$uid");
    $msg = 'success:Kiriman dihapus.';
}

// Tandai semua sebagai sudah dibaca
mysqli_query($conn,"UPDATE shared_notes SET is_read=1 WHERE receiver_id=$uid AND is_read=0");
mysqli_query($conn,"UPDATE shared_tasks SET is_read=1 WHERE receiver_id=$uid AND is_read=0");

// Ambil semua matkul milik user (untuk dropdown simpan)
$my_courses = mysqli_fetch_all(mysqli_query($conn,
    "SELECT id,name,icon FROM courses WHERE user_id=$uid ORDER BY name"), MYSQLI_ASSOC);

// Ambil catatan yang diterima
$shared_notes = mysqli_fetch_all(mysqli_query($conn,
    "SELECT sn.id as sn_id, sn.message as share_msg, sn.created_at as shared_at,
            n.id as note_id, n.pertemuan, n.title, n.content, n.image,
            u.full_name as sender_name, u.username as sender_username,
            c.name as course_name, c.icon as course_icon
     FROM shared_notes sn
     JOIN notes n ON n.id = sn.note_id
     JOIN users u ON u.id = sn.sender_id
     JOIN courses c ON c.id = n.course_id
     WHERE sn.receiver_id=$uid
     ORDER BY sn.created_at DESC"), MYSQLI_ASSOC);

// Ambil tugas yang diterima
$shared_tasks = mysqli_fetch_all(mysqli_query($conn,
    "SELECT st.id as st_id, st.message as share_msg, st.created_at as shared_at,
            t.id as task_id, t.title, t.description, t.deadline,
            u.full_name as sender_name, u.username as sender_username,
            c.name as course_name, c.icon as course_icon
     FROM shared_tasks st
     JOIN tasks t ON t.id = st.task_id
     JOIN users u ON u.id = st.sender_id
     JOIN courses c ON c.id = t.course_id
     WHERE st.receiver_id=$uid
     ORDER BY st.created_at DESC"), MYSQLI_ASSOC);

// Kiriman yang SAYA kirim
$sent_notes = mysqli_fetch_all(mysqli_query($conn,
    "SELECT sn.id as sn_id, sn.created_at as shared_at, sn.message as share_msg,
            n.pertemuan, n.title,
            u.full_name as receiver_name,
            c.name as course_name, c.icon as course_icon
     FROM shared_notes sn
     JOIN notes n ON n.id = sn.note_id
     JOIN users u ON u.id = sn.receiver_id
     JOIN courses c ON c.id = n.course_id
     WHERE sn.sender_id=$uid
     ORDER BY sn.created_at DESC LIMIT 20"), MYSQLI_ASSOC);

$sent_tasks = mysqli_fetch_all(mysqli_query($conn,
    "SELECT st.id as st_id, st.created_at as shared_at, st.message as share_msg,
            t.title, t.deadline,
            u.full_name as receiver_name,
            c.name as course_name, c.icon as course_icon
     FROM shared_tasks st
     JOIN tasks t ON t.id = st.task_id
     JOIN users u ON u.id = st.receiver_id
     JOIN courses c ON c.id = t.course_id
     WHERE st.sender_id=$uid
     ORDER BY st.created_at DESC LIMIT 20"), MYSQLI_ASSOC);

[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];

function avatarColor($str) { return '#'.substr(md5($str),0,6); }
function avatarInit($str)  { return strtoupper(substr($str,0,2)); }
function timeAgo($dt) {
    $diff = time()-strtotime($dt);
    if ($diff<60)    return 'Baru saja';
    if ($diff<3600)  return floor($diff/60).' mnt lalu';
    if ($diff<86400) return floor($diff/3600).' jam lalu';
    return date('d M Y', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kiriman — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/main.css">
<script src="assets/js/notif.js" defer></script>
<style>
.tabs{display:flex;gap:0;margin-bottom:24px;border-bottom:2px solid var(--border)}
.tab-btn{padding:10px 20px;font-size:13px;font-weight:600;color:var(--muted);background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;margin-bottom:-2px;transition:all .15s}
.tab-btn.active{color:var(--purple);border-bottom-color:var(--purple)}
.tab-content{display:none}.tab-content.active{display:block}

.share-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:12px;transition:border-color .15s}
.share-card:hover{border-color:var(--purple)}
.share-card-header{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.share-sender-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0}
.share-sender-name{font-size:13px;font-weight:700}
.share-meta{font-size:11px;color:var(--muted);margin-top:2px}
.share-time{margin-left:auto;font-size:11px;color:var(--muted);flex-shrink:0}

.share-content{background:var(--bg);border-radius:10px;padding:14px;margin-bottom:12px}
.share-course-tag{display:inline-flex;align-items:center;gap:4px;background:var(--purple-light);color:var(--purple);font-size:11px;font-weight:700;padding:3px 10px;border-radius:8px;margin-bottom:8px}
.share-title{font-size:14px;font-weight:700;margin-bottom:6px}
.share-pertemuan{font-size:11px;color:var(--muted);margin-bottom:6px}
.share-body{font-size:13px;color:var(--muted);line-height:1.6;max-height:80px;overflow:hidden;position:relative}
.share-body.expanded{max-height:none}
.share-body-fade{position:absolute;bottom:0;left:0;right:0;height:30px;background:linear-gradient(transparent,var(--bg))}
.share-expand{font-size:12px;color:var(--purple);cursor:pointer;font-weight:600;margin-top:4px;display:inline-block}
.share-image{margin-top:8px;max-width:100%;max-height:180px;object-fit:cover;border-radius:8px;cursor:pointer;border:1px solid var(--border)}

.share-msg-bubble{background:#EEF2FF;border:1px solid #c7d2fe;border-radius:10px;padding:8px 12px;font-size:12px;color:var(--purple);font-style:italic;margin-bottom:12px;display:flex;align-items:center;gap:6px}

.share-actions{display:flex;gap:8px;flex-wrap:wrap}

.task-preview{display:flex;align-items:flex-start;gap:10px}
.task-preview-icon{font-size:20px;flex-shrink:0;margin-top:2px}
.task-deadline-badge{display:inline-block;background:var(--red-light);color:#C53030;font-size:11px;padding:2px 8px;border-radius:6px;margin-top:5px;font-weight:600}

.empty-box{text-align:center;padding:48px 20px;background:var(--card);border:1px solid var(--border);border-radius:12px;color:var(--muted)}
.empty-box .ico{font-size:42px;margin-bottom:12px}
.empty-box p{font-size:13px}

/* Lightbox */
.lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:200;align-items:center;justify-content:center;cursor:pointer}
.lightbox.open{display:flex}
.lightbox img{max-width:90vw;max-height:90vh;border-radius:10px;object-fit:contain}
</style>
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <h1>📤 Kiriman</h1>
      <div class="topbar-right">
        <div class="notif-bell-wrap" id="notif-bell-wrap"></div>
        <?= avatarHtml($_SESSION['full_name'], mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=$uid"))['avatar'] ?? null, 36) ?>
      </div>
    </div>

    <div class="page-body">
      <?php if ($msg_text): ?>
        <div class="alert <?= $msg_type ?>"><?= e($msg_text) ?></div>
      <?php endif; ?>

      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('notes-in')" id="tbtn-notes-in">
          📝 Catatan Masuk <span style="color:var(--muted);font-weight:400">(<?= count($shared_notes) ?>)</span>
        </button>
        <button class="tab-btn" onclick="switchTab('tasks-in')" id="tbtn-tasks-in">
          📋 Tugas Masuk <span style="color:var(--muted);font-weight:400">(<?= count($shared_tasks) ?>)</span>
        </button>
        <button class="tab-btn" onclick="switchTab('sent')" id="tbtn-sent">
          📨 Sudah Dikirim
        </button>
      </div>

      <!-- Catatan masuk -->
      <div class="tab-content active" id="tab-notes-in">
        <?php if (empty($shared_notes)): ?>
          <div class="empty-box"><div class="ico">📭</div><p>Belum ada catatan yang dikirim temanmu.</p></div>
        <?php endif; ?>
        <?php foreach ($shared_notes as $sn): ?>
        <div class="share-card">
          <div class="share-card-header">
            <div class="share-sender-avatar" style="background:<?= avatarColor($sn['sender_username']) ?>"><?= avatarInit($sn['sender_name']) ?></div>
            <div>
              <div class="share-sender-name"><?= e($sn['sender_name']) ?></div>
              <div class="share-meta">mengirimkan catatan kepadamu</div>
            </div>
            <div class="share-time"><?= timeAgo($sn['shared_at']) ?></div>
          </div>

          <?php if ($sn['share_msg']): ?>
          <div class="share-msg-bubble">💬 "<?= e($sn['share_msg']) ?>"</div>
          <?php endif; ?>

          <div class="share-content">
            <div class="share-course-tag"><?= e($sn['course_icon']) ?> <?= e($sn['course_name']) ?></div>
            <?php if ($sn['pertemuan']): ?><div class="share-pertemuan"><?= e($sn['pertemuan']) ?></div><?php endif; ?>
            <?php if ($sn['title']): ?><div class="share-title"><?= e($sn['title']) ?></div><?php endif; ?>
            <div class="share-body" id="note-body-<?= $sn['sn_id'] ?>"><?= nl2br(e($sn['content'])) ?>
              <div class="share-body-fade" id="fade-<?= $sn['sn_id'] ?>"></div>
            </div>
            <span class="share-expand" onclick="expandBody(<?= $sn['sn_id'] ?>)" id="expand-<?= $sn['sn_id'] ?>">Baca selengkapnya ▼</span>
            <?php if ($sn['image']): ?>
              <img src="<?= e($sn['image']) ?>" class="share-image" alt="Foto catatan" onclick="openLightbox(this.src)">
            <?php endif; ?>
          </div>

          <div class="share-actions">
            <?php if (!empty($my_courses)): ?>
            <button class="btn primary sm" onclick="openSaveModal('note', <?= $sn['sn_id'] ?>)">💾 Simpan ke Matkul Saya</button>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirm('Hapus kiriman ini?')">
              <input type="hidden" name="share_id" value="<?= $sn['sn_id'] ?>">
              <input type="hidden" name="share_type" value="note">
              <button type="submit" name="del_share" class="btn sm danger">🗑 Hapus</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Tugas masuk -->
      <div class="tab-content" id="tab-tasks-in">
        <?php if (empty($shared_tasks)): ?>
          <div class="empty-box"><div class="ico">📭</div><p>Belum ada tugas yang dikirim temanmu.</p></div>
        <?php endif; ?>
        <?php foreach ($shared_tasks as $st): ?>
        <div class="share-card">
          <div class="share-card-header">
            <div class="share-sender-avatar" style="background:<?= avatarColor($st['sender_username']) ?>"><?= avatarInit($st['sender_name']) ?></div>
            <div>
              <div class="share-sender-name"><?= e($st['sender_name']) ?></div>
              <div class="share-meta">mengirimkan tugas kepadamu</div>
            </div>
            <div class="share-time"><?= timeAgo($st['shared_at']) ?></div>
          </div>

          <?php if ($st['share_msg']): ?>
          <div class="share-msg-bubble">💬 "<?= e($st['share_msg']) ?>"</div>
          <?php endif; ?>

          <div class="share-content">
            <div class="share-course-tag"><?= e($st['course_icon']) ?> <?= e($st['course_name']) ?></div>
            <div class="task-preview">
              <div class="task-preview-icon">📋</div>
              <div>
                <div class="share-title"><?= e($st['title']) ?></div>
                <?php if ($st['description']): ?>
                  <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= e($st['description']) ?></div>
                <?php endif; ?>
                <?php if ($st['deadline']): ?>
                  <div class="task-deadline-badge">📅 Deadline: <?= date('d M Y, H:i', strtotime($st['deadline'])) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="share-actions">
            <?php if (!empty($my_courses)): ?>
            <button class="btn primary sm" onclick="openSaveModal('task', <?= $st['st_id'] ?>)">💾 Simpan ke Matkul Saya</button>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirm('Hapus kiriman ini?')">
              <input type="hidden" name="share_id" value="<?= $st['st_id'] ?>">
              <input type="hidden" name="share_type" value="task">
              <button type="submit" name="del_share" class="btn sm danger">🗑 Hapus</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Sudah dikirim -->
      <div class="tab-content" id="tab-sent">
        <?php if (empty($sent_notes) && empty($sent_tasks)): ?>
          <div class="empty-box"><div class="ico">📤</div><p>Belum ada catatan atau tugas yang kamu kirim.</p></div>
        <?php endif; ?>

        <?php if (!empty($sent_notes)): ?>
        <div class="section-title" style="margin-bottom:12px">📝 Catatan yang dikirim</div>
        <?php foreach ($sent_notes as $sn): ?>
        <div class="share-card" style="border-color:#c7d2fe">
          <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:20px">📝</span>
            <div style="flex:1">
              <div style="font-size:13px;font-weight:700"><?= e($sn['title'] ?: $sn['pertemuan'] ?: 'Catatan') ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= e($sn['course_icon'].' '.$sn['course_name']) ?> → ke <b><?= e($sn['receiver_name']) ?></b></div>
              <?php if ($sn['share_msg']): ?><div style="font-size:11px;color:var(--purple);margin-top:3px">"<?= e($sn['share_msg']) ?>"</div><?php endif; ?>
            </div>
            <div style="font-size:11px;color:var(--muted)"><?= timeAgo($sn['shared_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($sent_tasks)): ?>
        <div class="section-title" style="margin:16px 0 12px">📋 Tugas yang dikirim</div>
        <?php foreach ($sent_tasks as $st): ?>
        <div class="share-card" style="border-color:#fde68a">
          <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:20px">📋</span>
            <div style="flex:1">
              <div style="font-size:13px;font-weight:700"><?= e($st['title']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= e($st['course_icon'].' '.$st['course_name']) ?> → ke <b><?= e($st['receiver_name']) ?></b></div>
              <?php if ($st['share_msg']): ?><div style="font-size:11px;color:#C05621;margin-top:3px">"<?= e($st['share_msg']) ?>"</div><?php endif; ?>
            </div>
            <div style="font-size:11px;color:var(--muted)"><?= timeAgo($st['shared_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<!-- Modal simpan ke matkul -->
<div class="modal-overlay" id="modal-save">
  <div class="modal">
    <h3 id="save-modal-title">💾 Simpan ke Matkul Saya</h3>
    <p style="font-size:13px;color:var(--muted);margin-bottom:18px">Pilih matkul untuk menyimpan kiriman ini ke catatan/tugasmu.</p>
    <form method="POST">
      <input type="hidden" name="shared_note_id" id="save-note-id">
      <input type="hidden" name="shared_task_id" id="save-task-id">
      <div class="form-group">
        <label>Simpan ke Matkul</label>
        <select name="save_to_course" required>
          <option value="">— Pilih matkul —</option>
          <?php foreach ($my_courses as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['icon'].' '.$c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('modal-save')">Batal</button>
        <button type="submit" id="save-submit-btn" name="save_note" class="btn primary">💾 Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Lightbox gambar -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <img id="lightbox-img" src="" alt="Foto catatan">
</div>

<script>
function switchTab(name) {
  ['notes-in','tasks-in','sent'].forEach(t=>{
    document.getElementById('tbtn-'+t).classList.remove('active');
    document.getElementById('tab-'+t).classList.remove('active');
  });
  document.getElementById('tbtn-'+name).classList.add('active');
  document.getElementById('tab-'+name).classList.add('active');
}

function expandBody(id) {
  const body  = document.getElementById('note-body-'+id);
  const fade  = document.getElementById('fade-'+id);
  const btn   = document.getElementById('expand-'+id);
  body.classList.add('expanded');
  if (fade) fade.style.display = 'none';
  btn.style.display = 'none';
}

function openSaveModal(type, id) {
  document.getElementById('save-note-id').value = type==='note' ? id : '';
  document.getElementById('save-task-id').value = type==='task' ? id : '';
  document.getElementById('save-modal-title').textContent = type==='note' ? '💾 Simpan Catatan ke Matkul Saya' : '💾 Simpan Tugas ke Matkul Saya';
  document.getElementById('save-submit-btn').name = type==='note' ? 'save_note' : 'save_task';
  document.getElementById('modal-save').classList.add('open');
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o=>{
  o.addEventListener('click', e=>{ if(e.target===o) o.classList.remove('open'); });
});

function openLightbox(src){document.getElementById('lightbox-img').src=src;document.getElementById('lightbox').classList.add('open')}
function closeLightbox(){document.getElementById('lightbox').classList.remove('open')}
</script>
</body>
</html>
