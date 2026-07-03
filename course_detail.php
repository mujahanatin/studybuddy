<?php
// course_detail.php — Detail matkul + catatan dengan foto + tugas
require_once 'includes/config.php';
requireLogin();
$active_page = 'courses';
$uid = $_SESSION['user_id'];
$initials = strtoupper(substr($_SESSION['full_name'], 0, 2));

$cid = (int)($_GET['id'] ?? 0);
if (!$cid) { header('Location: courses.php'); exit; }

$course_q = mysqli_prepare($conn,"SELECT * FROM courses WHERE id=? AND user_id=?");
mysqli_stmt_bind_param($course_q,'ii',$cid,$uid); mysqli_stmt_execute($course_q);
$course = mysqli_fetch_assoc(mysqli_stmt_get_result($course_q));
if (!$course) { header('Location: courses.php'); exit; }

$msg = '';

// Tambah catatan + foto
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_note'])) {
    $pertemuan = trim($_POST['pertemuan'] ?? '');
    $title     = trim($_POST['title']     ?? '');
    $content   = trim($_POST['content']   ?? '');
    $img_path  = null;

    if (!empty($_FILES['note_image']['name'])) {
        $res = uploadNoteImage($_FILES['note_image']);
        if (isset($res['error'])) { $msg = 'error:'.$res['error']; }
        else { $img_path = $res['path']; }
    }

    if ($content && !$msg) {
        $st = mysqli_prepare($conn,"INSERT INTO notes (course_id,user_id,pertemuan,title,content,image) VALUES (?,?,?,?,?,?)");
        mysqli_stmt_bind_param($st,'iissss',$cid,$uid,$pertemuan,$title,$content,$img_path);
        mysqli_stmt_execute($st);
        $msg = 'success:Catatan berhasil disimpan!';
    } elseif (!$content && !$msg) { $msg = 'error:Isi catatan tidak boleh kosong.'; }
}

// Hapus catatan
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_note'])) {
    $nid = (int)$_POST['del_note'];
    $row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT image FROM notes WHERE id=$nid AND user_id=$uid"));
    if ($row) {
        if ($row['image'] && file_exists(__DIR__.'/'.$row['image'])) unlink(__DIR__.'/'.$row['image']);
        mysqli_query($conn,"DELETE FROM notes WHERE id=$nid AND user_id=$uid");
        $msg = 'success:Catatan dihapus.';
    }
}

// Tambah tugas
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_task'])) {
    $ttitle    = trim($_POST['task_title']    ?? '');
    $tdesc     = trim($_POST['task_desc']     ?? '');
    $tdeadline = trim($_POST['task_deadline'] ?? '');
    if ($ttitle) {
        $dl = $tdeadline ?: null;
        $st = mysqli_prepare($conn,"INSERT INTO tasks (course_id,user_id,title,description,deadline) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($st,'iisss',$cid,$uid,$ttitle,$tdesc,$dl);
        mysqli_stmt_execute($st);
        $msg = 'success:Tugas berhasil ditambahkan!';
    } else { $msg = 'error:Judul tugas tidak boleh kosong.'; }
}

// Selesai tugas
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['done_task'])) {
    $tid = (int)$_POST['done_task'];
    mysqli_query($conn,"UPDATE tasks SET status='done' WHERE id=$tid AND user_id=$uid");
    $msg = 'success:Tugas selesai!';
}

// Kirim catatan ke teman
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['share_note'])) {
    $nid      = (int)$_POST['note_id'];
    $to_id    = (int)$_POST['to_friend'];
    $shr_msg  = trim($_POST['share_msg'] ?? '');
    // Pastikan catatan milik user & target adalah teman
    $note_ok = mysqli_fetch_row(mysqli_query($conn,"SELECT id FROM notes WHERE id=$nid AND user_id=$uid"));
    $friend_ok = mysqli_fetch_row(mysqli_query($conn,"SELECT id FROM friends WHERE user_id=$uid AND friend_id=$to_id AND status='accepted'"));
    if ($note_ok && $friend_ok) {
        $st = mysqli_prepare($conn,"INSERT INTO shared_notes (note_id,sender_id,receiver_id,message) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($st,'iiis',$nid,$uid,$to_id,$shr_msg);
        mysqli_stmt_execute($st);
        $msg = 'success:Catatan berhasil dikirim ke teman!';
    } else { $msg = 'error:Tidak bisa mengirim catatan ini.'; }
}

// Kirim tugas ke teman
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['share_task'])) {
    $tid      = (int)$_POST['task_id'];
    $to_id    = (int)$_POST['to_friend'];
    $shr_msg  = trim($_POST['share_msg'] ?? '');
    $task_ok   = mysqli_fetch_row(mysqli_query($conn,"SELECT id FROM tasks WHERE id=$tid AND user_id=$uid"));
    $friend_ok = mysqli_fetch_row(mysqli_query($conn,"SELECT id FROM friends WHERE user_id=$uid AND friend_id=$to_id AND status='accepted'"));
    if ($task_ok && $friend_ok) {
        $st = mysqli_prepare($conn,"INSERT INTO shared_tasks (task_id,sender_id,receiver_id,message) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($st,'iiis',$tid,$uid,$to_id,$shr_msg);
        mysqli_stmt_execute($st);
        $msg = 'success:Tugas berhasil dikirim ke teman!';
    } else { $msg = 'error:Tidak bisa mengirim tugas ini.'; }
}

// Hapus tugas (Bagian ini yang sebelumnya error & sudah diperbaiki)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_task'])) {
    $tid = (int)$_POST['del_task'];
    mysqli_query($conn,"DELETE FROM tasks WHERE id=$tid AND user_id=$uid");
    $msg = 'success:Tugas dihapus.';
}

$notes = mysqli_query($conn,"SELECT * FROM notes WHERE course_id=$cid AND user_id=$uid ORDER BY created_at DESC");
$tasks = mysqli_query($conn,"SELECT * FROM tasks WHERE course_id=$cid AND user_id=$uid ORDER BY status ASC, deadline ASC");

// Daftar teman untuk dropdown kirim
$friends_list = mysqli_fetch_all(mysqli_query($conn,
    "SELECT u.id, u.full_name FROM friends f
     JOIN users u ON u.id=f.friend_id
     WHERE f.user_id=$uid AND f.status='accepted'
     ORDER BY u.full_name ASC"), MYSQLI_ASSOC);
[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($course['name']) ?> — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/main.css">
<script src="assets/js/notif.js" defer></script>
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <a href="courses.php" class="btn sm">← Kembali</a>
        <div style="display:flex;align-items:center;gap:10px">
          <span style="font-size:22px"><?= e($course['icon']) ?></span>
          <div>
            <h1 style="font-size:16px"><?= e($course['name']) ?></h1>
            <div style="font-size:11px;color:var(--muted)"><?= e($course['code']) ?><?= $course['semester']?' · '.e($course['semester']):'' ?></div>
          </div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="notif-bell-wrap" id="notif-bell-wrap"></div>
        <button class="btn primary" onclick="openModal('modal-note')">+ Catatan</button>
        <button class="btn" onclick="openModal('modal-task')">+ Tugas</button>
        <?= avatarHtml($_SESSION["full_name"], mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=$uid"))["avatar"] ?? null, 36) ?>
      </div>
    </div>

    <div class="page-body">
      <?php if ($msg_text): ?>
        <div class="alert <?= $msg_type ?>"><?= e($msg_text) ?></div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

        <!-- Catatan -->
        <div>
          <div class="section-title">Catatan Kuliah</div>
          <?php if (mysqli_num_rows($notes)===0): ?>
            <div class="card" style="text-align:center;padding:30px;color:var(--muted)">
              Belum ada catatan. Tekan <b>+ Catatan</b> untuk mulai.
            </div>
          <?php endif; ?>
          <?php while ($n = mysqli_fetch_assoc($notes)): ?>
          <div class="note-item">
            <div class="note-meta">
              <span class="note-pertemuan"><?= e($n['pertemuan']?:'Catatan') ?></span>
              <span class="note-date"><?= date('d M Y, H:i',strtotime($n['created_at'])) ?></span>
            </div>
            <?php if ($n['title']): ?>
              <div class="note-title"><?= e($n['title']) ?></div>
            <?php endif; ?>
            <div class="note-body"><?= nl2br(e($n['content'])) ?></div>
            <?php if ($n['image']): ?>
              <img src="<?= e($n['image']) ?>" alt="Foto catatan" class="note-image"
                   onclick="openLightbox(this.src)">
            <?php endif; ?>
            <div class="note-actions">
              <form method="POST" onsubmit="return confirm('Hapus catatan ini?')">
                <input type="hidden" name="del_note" value="<?= $n['id'] ?>">
                <button type="submit" class="btn sm danger">🗑 Hapus</button>
              </form>
              <?php if (!empty($friends_list)): ?>
              <button type="button" class="btn sm" onclick="openShareModal('note',<?= $n['id'] ?>, '<?= e(addslashes($n['pertemuan']?:$n['title']?:'Catatan')) ?>')">📤 Kirim ke Teman</button>
              <?php endif; ?>
            </div>
          </div>
          <?php endwhile; ?>
        </div>

        <!-- Tugas -->
        <div>
          <div class="section-title">Tugas & Deadline</div>
          <?php if (mysqli_num_rows($tasks)===0): ?>
            <div class="card" style="text-align:center;padding:20px;color:var(--muted);font-size:13px">
              Tidak ada tugas untuk matkul ini.
            </div>
          <?php endif; ?>
          <?php while ($t = mysqli_fetch_assoc($tasks)):
            $done = $t['status']==='done';
            $days = $t['deadline'] ? (int)((strtotime($t['deadline'])-time())/86400) : null;
          ?>
          <div class="task-item <?= $done?'done':'' ?>">
            <form method="POST" style="display:contents">
              <input type="hidden" name="done_task" value="<?= $t['id'] ?>">
              <input type="checkbox" class="task-checkbox" <?= $done?'checked':'' ?> onchange="this.form.submit()" <?= $done?'disabled':'' ?>>
            </form>
            <div style="flex:1">
              <div class="task-title" style="<?= $done?'text-decoration:line-through;opacity:.6':'' ?>"><?= e($t['title']) ?></div>
              <?php if ($t['description']): ?>
                <div class="task-deadline" style="margin-top:2px"><?= e($t['description']) ?></div>
              <?php endif; ?>
              <?php if ($t['deadline']): ?>
                <div class="task-deadline">📅 <?= date('d M Y',strtotime($t['deadline'])) ?>
                  <?php if (!$done && $days!==null): ?>
                    · <span style="color:<?= $days<=2?'var(--red)':($days<=7?'#C05621':'var(--green)') ?>">
                        <?= $days<0?'Terlambat!':($days===0?'Hari ini!':$days.' hari lagi') ?>
                      </span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
            <form method="POST" onsubmit="return confirm('Hapus tugas ini?')">
              <input type="hidden" name="del_task" value="<?= $t['id'] ?>">
              <button type="submit" class="task-del">✕</button>
            </form>
            <?php if (!empty($friends_list)): ?>
            <button type="button" class="btn sm" style="margin-left:4px" onclick="openShareModal('task',<?= $t['id'] ?>, '<?= e(addslashes($t['title'])) ?>')">📤</button>
            <?php endif; ?>
          </div>
          <?php endwhile; ?>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Modal catatan + foto -->
<div class="modal-overlay" id="modal-note">
  <div class="modal">
    <h3>Tambah Catatan</h3>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-row">
        <div class="form-group"><label>Pertemuan</label><input type="text" name="pertemuan" placeholder="contoh: Pertemuan 5"></div>
        <div class="form-group"><label>Judul (opsional)</label><input type="text" name="title" placeholder="contoh: Divide and Conquer"></div>
      </div>
      <div class="form-group">
        <label>Isi Rangkuman *</label>
        <textarea name="content" rows="6" placeholder="Tulis rangkuman kuliah hari ini..." required></textarea>
      </div>
      <div class="form-group">
        <label>Tambah Foto (opsional)</label>
        <input type="file" name="note_image" accept="image/*" onchange="previewImg(this)" style="background:#fff;padding:8px">
        <img id="img-preview" class="img-preview" alt="Preview foto">
        <div style="font-size:11px;color:var(--muted);margin-top:4px">Format: JPG, PNG, GIF, WEBP · Maks. 5MB</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('modal-note')">Batal</button>
        <button type="submit" name="add_note" class="btn primary">Simpan Catatan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal tugas -->
<div class="modal-overlay" id="modal-task">
  <div class="modal">
    <h3>Tambah Tugas</h3>
    <form method="POST">
      <div class="form-group"><label>Judul Tugas *</label><input type="text" name="task_title" placeholder="contoh: Implementasi Merge Sort" required></div>
      <div class="form-group"><label>Keterangan (opsional)</label><textarea name="task_desc" rows="2" placeholder="Format, cara pengumpulan..."></textarea></div>
      <div class="form-group"><label>Deadline</label><input type="datetime-local" name="task_deadline"></div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('modal-task')">Batal</button>
        <button type="submit" name="add_task" class="btn primary">Simpan Tugas</button>
      </div>
    </form>
  </div>
</div>

<!-- Lightbox gambar -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <img id="lightbox-img" src="" alt="Foto catatan">
</div>

<!-- Modal kirim catatan/tugas ke teman -->
<div class="modal-overlay" id="modal-share">
  <div class="modal">
    <h3 id="share-modal-title">Kirim</h3>
    <form method="POST">
      <input type="hidden" name="share_type" id="share-type">
      <input type="hidden" name="note_id"    id="share-note-id">
      <input type="hidden" name="task_id"    id="share-task-id">

      <div style="background:var(--purple-light);border:1px solid #c7d2fe;border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:var(--purple)">
        <b id="share-item-name"></b>
      </div>

      <div class="form-group">
        <label>Kirim ke</label>
        <select name="to_friend" id="share-to-friend" required>
          <option value="">— Pilih teman —</option>
          <?php foreach ($friends_list as $f): ?>
            <option value="<?= $f['id'] ?>"><?= e($f['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Pesan (opsional)</label>
        <input type="text" name="share_msg" placeholder="Contoh: Ini catatan yang tadi kita bahas..." maxlength="255">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('modal-share')">Batal</button>
        <button type="submit" id="share-submit-btn" class="btn primary">Kirim</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open')})})

function previewImg(input){
  const prev=document.getElementById('img-preview');
  if(input.files&&input.files[0]){const r=new FileReader();r.onload=e=>{prev.src=e.target.result;prev.classList.add('show')};r.readAsDataURL(input.files[0])}
  else prev.classList.remove('show')
}

function openLightbox(src){document.getElementById('lightbox-img').src=src;document.getElementById('lightbox').classList.add('open')}
function closeLightbox(){document.getElementById('lightbox').classList.remove('open')}

function openShareModal(type, id, name) {
  document.getElementById('share-type').value = type;
  document.getElementById('share-note-id').value = type==='note' ? id : '';
  document.getElementById('share-task-id').value = type==='task' ? id : '';
  document.getElementById('share-item-name').textContent = (type==='note' ? 'Catatan: ' : 'Tugas: ') + name;
  document.getElementById('share-modal-title').textContent = type==='note' ? 'Kirim Catatan ke Teman' : 'Kirim Tugas ke Teman';
  // Set hidden input name sesuai type supaya PHP bisa baca
  document.querySelector('#modal-share [name="note_id"]').name = type==='note' ? 'note_id' : 'note_id_unused';
  document.querySelector('#modal-share [name^="task_id"]').name = type==='task' ? 'task_id' : 'task_id_unused';
  document.querySelector('#modal-share [type="submit"]').name = type==='note' ? 'share_note' : 'share_task';
  openModal('modal-share');
}
</script>
</body>
</html>