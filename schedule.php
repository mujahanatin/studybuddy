<?php
// schedule.php — Jadwal kuliah mingguan
require_once 'includes/config.php';
requireLogin();
$active_page = 'schedule';
$uid = $_SESSION['user_id'];
$initials = strtoupper(substr($_SESSION['full_name'], 0, 2));
$msg = '';

// Tambah jadwal
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_schedule'])) {
    $title      = trim($_POST['title']       ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $day        = (int)($_POST['day_of_week']  ?? 0);
    $start      = trim($_POST['start_time']  ?? '');
    $end        = trim($_POST['end_time']    ?? '');
    $room       = trim($_POST['room']        ?? '');
    $color      = trim($_POST['color']       ?? '#6C63FF');
    $course_id  = (int)($_POST['course_id']  ?? 0) ?: null;

    if ($title && $start) {
        $st = mysqli_prepare($conn,
            "INSERT INTO schedules (user_id,course_id,title,description,day_of_week,start_time,end_time,room,color)
             VALUES (?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($st,'iisssisss',$uid,$course_id,$title,$desc,$day,$start,$end,$room,$color);
        mysqli_stmt_execute($st);
        $msg = 'success:Jadwal berhasil ditambahkan!';
    } else { $msg = 'error:Judul dan jam mulai wajib diisi.'; }
    header('Location: schedule.php?msg='.urlencode($msg)); exit;
}

// Hapus jadwal
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_schedule'])) {
    $sid = (int)$_POST['del_schedule'];
    mysqli_query($conn,"DELETE FROM schedules WHERE id=$sid AND user_id=$uid");
    header('Location: schedule.php?msg='.urlencode('success:Jadwal dihapus.')); exit;
}

$flash = $_GET['msg'] ?? '';
[$flash_type,$flash_text] = $flash ? explode(':',urldecode($flash),2) : ['',''];

// Ambil semua jadwal
$schedules_q = mysqli_query($conn,
    "SELECT s.*, c.name as course_name FROM schedules s
     LEFT JOIN courses c ON s.course_id = c.id
     WHERE s.user_id = $uid ORDER BY s.day_of_week ASC, s.start_time ASC"
);
$all_schedules = [];
while ($row = mysqli_fetch_assoc($schedules_q)) $all_schedules[] = $row;

// Kelompokkan per hari
$by_day = array_fill(0, 7, []);
foreach ($all_schedules as $s) $by_day[(int)$s['day_of_week']][] = $s;

// Ambil matkul untuk dropdown
$courses_q = mysqli_query($conn,"SELECT id,name,icon FROM courses WHERE user_id=$uid ORDER BY name");
$courses_list = [];
while ($c = mysqli_fetch_assoc($courses_q)) $courses_list[] = $c;

$days_label = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
$hours = range(7, 20); // jam 07:00 - 20:00

// Hari ini (0=Senin ... 6=Minggu)
$today_idx = (date('N') - 1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Jadwal — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/main.css">
<script src="assets/js/notif.js" defer></script>
<style>
.schedule-wrap{overflow-x:auto}
.schedule-table{width:100%;border-collapse:collapse;min-width:680px}
.schedule-table th{background:var(--purple-light);color:var(--purple);font-size:12px;font-weight:700;padding:10px 8px;text-align:center;border:1px solid var(--border)}
.schedule-table th.today-col{background:var(--purple);color:#fff}
.schedule-table td{border:1px solid var(--border);vertical-align:top;padding:4px;min-width:100px;min-height:46px}
.schedule-table .time-cell{font-size:11px;color:var(--muted);text-align:right;padding:6px 8px;white-space:nowrap;background:var(--bg);min-width:60px}
.sch-block{border-radius:8px;padding:6px 8px;margin-bottom:3px;cursor:pointer;position:relative}
.sch-block .sb-title{font-size:12px;font-weight:700;color:#fff}
.sch-block .sb-time{font-size:10px;color:rgba(255,255,255,.85)}
.sch-block .sb-room{font-size:10px;color:rgba(255,255,255,.8);margin-top:1px}
.sch-block .sb-del{position:absolute;top:4px;right:5px;background:rgba(0,0,0,.25);border:none;color:#fff;border-radius:4px;font-size:10px;padding:1px 5px;cursor:pointer;opacity:0;transition:opacity .15s}
.sch-block:hover .sb-del{opacity:1}
.today-highlight{background:rgba(108,99,255,.04)}
.list-view{display:none}
.view-toggle{display:flex;gap:6px;margin-bottom:16px}
.view-btn{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--card);color:var(--muted)}
.view-btn.active{background:var(--purple);color:#fff;border-color:var(--purple)}
.list-day{margin-bottom:18px}
.list-day-title{font-size:13px;font-weight:700;color:var(--purple);margin-bottom:8px;display:flex;align-items:center;gap:8px}
.list-item{display:flex;align-items:center;gap:12px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:6px}
.list-color{width:6px;height:40px;border-radius:3px;flex-shrink:0}
.list-info{flex:1}
.list-title{font-size:13px;font-weight:600}
.list-meta{font-size:11px;color:var(--muted);margin-top:2px}
</style>
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <h1>🗓️ Jadwal Kuliah</h1>
      <div class="topbar-right">
        <div class="notif-bell-wrap" id="notif-bell-wrap"></div>
        <button class="btn primary" onclick="openModal('modal-add')">+ Tambah Jadwal</button>
        <?= avatarHtml($_SESSION["full_name"], mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=$uid"))["avatar"] ?? null, 36) ?>
      </div>
    </div>

    <div class="page-body">
      <?php if ($flash_text): ?>
        <div class="alert <?= $flash_type ?>"><?= e($flash_text) ?></div>
      <?php endif; ?>

      <!-- Toggle tampilan -->
      <div class="view-toggle">
        <button class="view-btn active" id="btn-grid" onclick="switchView('grid')">📅 Tampilan Minggu</button>
        <button class="view-btn" id="btn-list" onclick="switchView('list')">📋 Tampilan List</button>
      </div>

      <!-- TAMPILAN GRID MINGGUAN -->
      <div id="view-grid" class="schedule-wrap">
        <table class="schedule-table">
          <thead>
            <tr>
              <th style="min-width:60px">Jam</th>
              <?php foreach ($days_label as $i => $d): ?>
                <th class="<?= $i===$today_idx?'today-col':'' ?>">
                  <?= $d ?>
                  <?php if($i===$today_idx): ?><div style="font-size:10px;font-weight:400;opacity:.8">Hari ini</div><?php endif; ?>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($hours as $h): ?>
            <tr>
              <td class="time-cell"><?= sprintf('%02d:00', $h) ?></td>
              <?php for ($d=0; $d<7; $d++): ?>
              <td class="<?= $d===$today_idx?'today-highlight':'' ?>">
                <?php foreach ($by_day[$d] as $s):
                  $sh = (int)explode(':',$s['start_time'])[0];
                  if ($sh !== $h) continue;
                ?>
                <div class="sch-block" style="background:<?= e($s['color']) ?>" title="<?= e($s['title']) ?>">
                  <div class="sb-title"><?= e($s['title']) ?></div>
                  <div class="sb-time">
                    <?= substr($s['start_time'],0,5) ?>
                    <?= $s['end_time'] ? ' – '.substr($s['end_time'],0,5) : '' ?>
                  </div>
                  <?php if($s['room']): ?><div class="sb-room">📍 <?= e($s['room']) ?></div><?php endif; ?>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Hapus jadwal ini?')">
                    <input type="hidden" name="del_schedule" value="<?= $s['id'] ?>">
                    <button type="submit" class="sb-del">✕</button>
                  </form>
                </div>
                <?php endforeach; ?>
              </td>
              <?php endfor; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- TAMPILAN LIST -->
      <div id="view-list" class="list-view">
        <?php
        $has_any = false;
        foreach ($days_label as $i => $day_name):
          if (empty($by_day[$i])) continue;
          $has_any = true;
        ?>
        <div class="list-day">
          <div class="list-day-title">
            <?= $day_name ?>
            <?php if($i===$today_idx): ?><span style="background:var(--purple);color:#fff;font-size:10px;padding:2px 8px;border-radius:8px">Hari ini</span><?php endif; ?>
          </div>
          <?php foreach ($by_day[$i] as $s): ?>
          <div class="list-item">
            <div class="list-color" style="background:<?= e($s['color']) ?>"></div>
            <div class="list-info">
              <div class="list-title"><?= e($s['title']) ?></div>
              <div class="list-meta">
                🕐 <?= substr($s['start_time'],0,5) ?><?= $s['end_time']?' – '.substr($s['end_time'],0,5):'' ?>
                <?php if($s['room']): ?> · 📍 <?= e($s['room']) ?><?php endif; ?>
                <?php if($s['course_name']): ?> · 📖 <?= e($s['course_name']) ?><?php endif; ?>
              </div>
              <?php if($s['description']): ?><div class="list-meta" style="margin-top:3px"><?= e($s['description']) ?></div><?php endif; ?>
            </div>
            <form method="POST" onsubmit="return confirm('Hapus jadwal ini?')">
              <input type="hidden" name="del_schedule" value="<?= $s['id'] ?>">
              <button type="submit" class="btn sm danger">Hapus</button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$has_any): ?>
          <div class="card" style="text-align:center;padding:40px;color:var(--muted)">Belum ada jadwal. Tekan <b>+ Tambah Jadwal</b> untuk memulai.</div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<!-- Modal tambah jadwal -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <h3>🗓️ Tambah Jadwal Baru</h3>
    <form method="POST">
      <div class="form-group">
        <label>Nama / Judul Jadwal *</label>
        <input type="text" name="title" placeholder="contoh: Analisis Algoritma" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Hari *</label>
          <select name="day_of_week">
            <?php foreach ($days_label as $i => $d): ?>
              <option value="<?= $i ?>" <?= $i===$today_idx?'selected':'' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Matkul (opsional)</label>
          <select name="course_id">
            <option value="">— Tidak dikaitkan —</option>
            <?php foreach ($courses_list as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['icon'].' '.$c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Jam Mulai *</label><input type="time" name="start_time" value="08:00" required></div>
        <div class="form-group"><label>Jam Selesai</label><input type="time" name="end_time" value="10:00"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Ruangan</label><input type="text" name="room" placeholder="contoh: Lab A101"></div>
        <div class="form-group"><label>Warna</label><input type="color" name="color" value="#6C63FF" style="height:42px;padding:4px"></div>
      </div>
      <div class="form-group"><label>Keterangan (opsional)</label><textarea name="description" rows="2" placeholder="Catatan tambahan..."></textarea></div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('modal-add')">Batal</button>
        <button type="submit" name="add_schedule" class="btn primary">Simpan Jadwal</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open')})})

function switchView(v){
  document.getElementById('view-grid').style.display = v==='grid'?'block':'none';
  document.getElementById('view-list').style.display = v==='list'?'block':'none';
  document.getElementById('btn-grid').classList.toggle('active',v==='grid');
  document.getElementById('btn-list').classList.toggle('active',v==='list');
  localStorage.setItem('sch_view',v);
}
// Ingat pilihan tampilan
const savedView = localStorage.getItem('sch_view');
if(savedView) switchView(savedView);
</script>
</body>
</html>
