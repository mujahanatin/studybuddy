<?php
// admin/dashboard.php
require_once 'includes/config.php';
requireAdminLogin();
$active_page = 'dashboard';

$total_users    = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM users"))[0];
$total_courses  = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM courses"))[0];
$total_notes    = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM notes"))[0];
$total_tasks    = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM tasks"))[0];
$total_messages = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM messages"))[0];
$total_pets     = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM pets"))[0];
$total_friends  = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM friends WHERE status='accepted'"))[0] / 2;

// User terbaru
$recent_users = mysqli_fetch_all(mysqli_query($conn,
    "SELECT id, full_name, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5"), MYSQLI_ASSOC);

function avatarColor($str) { return '#'.substr(md5($str),0,6); }
function avatarInit($str)  { return strtoupper(substr($str,0,2)); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Admin — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <h1>📊 Dashboard Admin</h1>
      <span style="font-size:13px;color:var(--muted)"><?= date('l, d F Y') ?></span>
    </div>

    <div class="page-body">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="icon">👥</div>
          <div class="num" style="color:var(--purple)"><?= $total_users ?></div>
          <div class="lbl">Total User</div>
        </div>
        <div class="stat-card">
          <div class="icon">📖</div>
          <div class="num" style="color:var(--green)"><?= $total_courses ?></div>
          <div class="lbl">Total Matkul</div>
        </div>
        <div class="stat-card">
          <div class="icon">📝</div>
          <div class="num" style="color:var(--orange)"><?= $total_notes ?></div>
          <div class="lbl">Total Catatan</div>
        </div>
        <div class="stat-card">
          <div class="icon">📋</div>
          <div class="num" style="color:var(--red)"><?= $total_tasks ?></div>
          <div class="lbl">Total Tugas</div>
        </div>
        <div class="stat-card">
          <div class="icon">💬</div>
          <div class="num" style="color:var(--purple)"><?= $total_messages ?></div>
          <div class="lbl">Total Pesan</div>
        </div>
        <div class="stat-card">
          <div class="icon">🐾</div>
          <div class="num" style="color:var(--green)"><?= $total_pets ?></div>
          <div class="lbl">Total Pet</div>
        </div>
        <div class="stat-card">
          <div class="icon">🤝</div>
          <div class="num" style="color:var(--orange)"><?= (int)$total_friends ?></div>
          <div class="lbl">Pertemanan</div>
        </div>
      </div>

      <div class="table-card">
        <div class="table-header">
          <h3>👋 User Terbaru Mendaftar</h3>
          <a href="users.php" class="btn sm">Lihat Semua →</a>
        </div>
        <table>
          <thead><tr><th>User</th><th>Username</th><th>Tanggal Daftar</th></tr></thead>
          <tbody>
            <?php if (empty($recent_users)): ?>
              <tr class="empty-row"><td colspan="3">Belum ada user terdaftar.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent_users as $u): ?>
            <tr>
              <td>
                <div class="user-cell">
                  <div class="user-avatar" style="background:<?= avatarColor($u['username']) ?>"><?= avatarInit($u['full_name']) ?></div>
                  <div>
                    <div class="user-name"><?= e($u['full_name']) ?></div>
                    <div class="user-email"><?= e($u['email']) ?></div>
                  </div>
                </div>
              </td>
              <td>@<?= e($u['username']) ?></td>
              <td><?= date('d M Y, H:i', strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
