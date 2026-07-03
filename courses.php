<?php
// courses.php — Daftar matkul + hapus matkul
require_once 'includes/config.php';
requireLogin();
$active_page = 'courses';
$uid = $_SESSION['user_id'];
$initials = strtoupper(substr($_SESSION['full_name'], 0, 2));
$msg = '';

// Tambah matkul
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_course'])) {
    $name     = trim($_POST['name']     ?? '');
    $code     = trim($_POST['code']     ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $icon     = trim($_POST['icon']     ?? '📚');
    $color    = trim($_POST['color']    ?? '#6C63FF');
    if ($name) {
        $st = mysqli_prepare($conn,"INSERT INTO courses (user_id,name,code,semester,icon,color) VALUES (?,?,?,?,?,?)");
        mysqli_stmt_bind_param($st,'isssss',$uid,$name,$code,$semester,$icon,$color);
        mysqli_stmt_execute($st);
        $msg = 'success:Matkul berhasil ditambahkan!';
    } else { $msg = 'error:Nama matkul tidak boleh kosong.'; }
    header('Location: courses.php?msg='.urlencode($msg)); exit;
}

// Hapus matkul (beserta semua catatan & tugas terkait — CASCADE di DB)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_course'])) {
    $cid = (int)$_POST['del_course'];
    // Pastikan matkul milik user ini
    $chk = mysqli_query($conn,"SELECT id FROM courses WHERE id=$cid AND user_id=$uid");
    if (mysqli_num_rows($chk) > 0) {
        // Hapus gambar catatan dulu
        $imgs = mysqli_query($conn,"SELECT image FROM notes WHERE course_id=$cid AND image IS NOT NULL");
        while ($img = mysqli_fetch_assoc($imgs)) {
            $path = __DIR__ . '/' . $img['image'];
            if (file_exists($path)) unlink($path);
        }
        mysqli_query($conn,"DELETE FROM courses WHERE id=$cid AND user_id=$uid");
        $msg = 'success:Matkul dan semua datanya berhasil dihapus.';
    }
    header('Location: courses.php?msg='.urlencode($msg)); exit;
}

$flash = $_GET['msg'] ?? '';
[$flash_type, $flash_text] = $flash ? explode(':', urldecode($flash), 2) : ['',''];

$courses = mysqli_query($conn,
    "SELECT c.*,
            (SELECT COUNT(*) FROM notes n WHERE n.course_id=c.id) as note_count,
            (SELECT COUNT(*) FROM tasks t WHERE t.course_id=c.id AND t.status='pending') as task_count
     FROM courses c WHERE c.user_id=$uid ORDER BY c.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Matkul — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/main.css">
<script src="assets/js/notif.js" defer></script>
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <h1>Matkul</h1>
      <div class="topbar-right">
        <div class="notif-bell-wrap" id="notif-bell-wrap"></div>
        <button class="btn primary" onclick="openModal('modal-add')">+ Tambah Matkul</button>
        <?= avatarHtml($_SESSION["full_name"], mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=$uid"))["avatar"] ?? null, 36) ?>
      </div>
    </div>
    <div class="page-body">
      <?php if ($flash_text): ?>
        <div class="alert <?= $flash_type ?>"><?= e($flash_text) ?></div>
      <?php endif; ?>

      <div class="course-grid">
        <?php while ($c = mysqli_fetch_assoc($courses)):
          $pct = min(100, $c['note_count'] * 8);
        ?>
        <div style="position:relative">
          <!-- Tombol hapus -->
          <form method="POST" onsubmit="return confirm('Hapus matkul \'<?= e(addslashes($c['name'])) ?>\'?\n\nSemua catatan dan tugas di matkul ini juga akan dihapus permanen.')">
            <input type="hidden" name="del_course" value="<?= $c['id'] ?>">
            <button type="submit" class="del-course-btn">✕ Hapus</button>
          </form>
          <a href="course_detail.php?id=<?= $c['id'] ?>" class="course-card">
            <div class="cc-header">
              <div class="cc-icon" style="background:<?= e($c['color']) ?>22"><?= e($c['icon']) ?></div>
              <div>
                <div class="cc-name"><?= e($c['name']) ?></div>
                <div class="cc-code"><?= e($c['code']) ?><?= $c['semester']?' · '.e($c['semester']):'' ?></div>
              </div>
            </div>
            <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= e($c['color']) ?>"></div></div>
            <div class="cc-meta">
              <span>📝<?= $c['note_count'] ?> catatan</span>
              <?php if($c['task_count']>0): ?><span style="color:var(--red)">📋<?= $c['task_count'] ?> tugas</span><?php endif; ?>
            </div>
          </a>
        </div>
        <?php endwhile; ?>
        <div class="course-add-card" onclick="openModal('modal-add')">
          <span class="plus">＋</span><span>Tambah Matkul</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal tambah matkul -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <h3>Tambah Matkul Baru</h3>
    <form method="POST">
      <div class="form-group">
        <label>Nama Matkul *</label>
        <input type="text" name="name" placeholder="contoh: Analisis Algoritma" required>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Kode Matkul</label><input type="text" name="code" placeholder="contoh: IF-302"></div>
        <div class="form-group"><label>Semester</label><input type="text" name="semester" placeholder="contoh: Semester 5"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Ikon (emoji)</label><input type="text" name="icon" value="📚" maxlength="5"></div>
        <div class="form-group"><label>Warna</label><input type="color" name="color" value="#6C63FF" style="height:42px;padding:4px"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('modal-add')">Batal</button>
        <button type="submit" name="add_course" class="btn primary">Simpan Matkul</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open')})})
</script>
</body>
</html>
