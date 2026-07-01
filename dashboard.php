<?php
// dashboard.php
require_once 'includes/config.php';
requireLogin();
$active_page = 'dashboard';
$uid = $_SESSION['user_id'];
$initials = strtoupper(substr($_SESSION['full_name'], 0, 2));

// Statistik
$total_courses = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM courses WHERE user_id=$uid"))[0];
$total_notes   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM notes WHERE user_id=$uid"))[0];
$total_friends = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM friends WHERE user_id=$uid AND status='accepted'"))[0];
$pending_tasks = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM tasks WHERE user_id=$uid AND status='pending'"))[0];

// Tugas mendekati deadline
$tasks_q = mysqli_query($conn,
    "SELECT t.*, c.name as course_name, c.icon as course_icon,
            DATEDIFF(t.deadline, NOW()) as days_left
     FROM tasks t
     JOIN courses c ON t.course_id = c.id
     WHERE t.user_id = $uid AND t.status = 'pending'
     ORDER BY t.deadline ASC LIMIT 6"
);

// Tandai tugas selesai
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['done_task'])) {
    $tid = (int)$_POST['done_task'];
    mysqli_query($conn, "UPDATE tasks SET status='done' WHERE id=$tid AND user_id=$uid");
    header('Location: dashboard.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <h1>Dashboard</h1>
      <div class="topbar-right">
        <span style="font-size:13px;color:var(--muted)"><?= date('l, d F Y') ?></span>
        <?= avatarHtml($_SESSION['full_name'], mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=$uid"))['avatar'] ?? null, 36) ?>
      </div>
    </div>

    <div class="page-body">

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="num" style="color:var(--purple)"><?= $total_courses ?></div>
          <div class="lbl">Matkul Aktif</div>
        </div>
        <div class="stat-card">
          <div class="num" style="color:var(--red)"><?= $pending_tasks ?></div>
          <div class="lbl">Tugas Belum Selesai</div>
        </div>
        <div class="stat-card">
          <div class="num" style="color:var(--green)"><?= $total_notes ?></div>
          <div class="lbl">Catatan Dibuat</div>
        </div>
        <div class="stat-card">
          <div class="num" style="color:var(--orange)"><?= $total_friends ?></div>
          <div class="lbl">Teman</div>
        </div>
      </div>

      <!-- Tugas & Deadline -->
      <div class="section-title">Tugas & Deadline</div>
      <div class="deadline-list">
        <?php if (mysqli_num_rows($tasks_q) === 0): ?>
          <div class="card" style="text-align:center;padding:30px;color:var(--muted)">
            Tidak ada tugas menunggu. Keren!
          </div>
        <?php endif; ?>
        <?php while ($t = mysqli_fetch_assoc($tasks_q)):
          $days = (int)$t['days_left'];
          $cls  = $days <= 2 ? 'urgent' : ($days <= 7 ? 'soon' : 'ok');
          $icon = $days <= 2 ? '🔴' : ($days <= 7 ? '🟡' : '🟢');
          $label = $days < 0 ? 'Terlambat!' : ($days === 0 ? 'Hari ini!' : ($days === 1 ? 'Besok' : "$days hari lagi"));
        ?>
        <div class="deadline-item">
          <div class="dl-icon <?= $cls ?>"><?= $icon ?></div>
          <div>
            <div class="dl-title"><?= e($t['title']) ?></div>
            <div class="dl-sub"><?= e($t['course_icon'].' '.$t['course_name']) ?> · <?= date('d M Y', strtotime($t['deadline'])) ?></div>
          </div>
          <span class="dl-badge <?= $cls ?>"><?= $label ?></span>
          <div class="dl-done">
            <form method="POST">
              <input type="hidden" name="done_task" value="<?= $t['id'] ?>">
              <button type="submit" class="btn sm" title="Tandai selesai">✓ Selesai</button>
            </form>
          </div>
        </div>
        <?php endwhile; ?>
      </div>

    </div>
  </div>
</div>
</body>
</html>
