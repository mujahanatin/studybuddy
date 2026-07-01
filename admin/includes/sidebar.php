<?php
$total_users_sb = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM users"))[0];
?>
<aside class="sidebar">
  <div class="sb-logo">
    <span class="sb-logo-icon">🛡️</span>
    <div>
      <div class="sb-logo-title">Admin Panel</div>
      <div class="sb-logo-sub">StudyBuddy</div>
    </div>
  </div>
  <nav class="sb-nav">
    <a href="dashboard.php" class="sb-item <?= ($active_page==='dashboard')?'active':'' ?>">
      <span class="sb-icon">📊</span> Dashboard
    </a>
    <a href="users.php" class="sb-item <?= ($active_page==='users')?'active':'' ?>">
      <span class="sb-icon">👥</span> Kelola User
      <span class="sb-badge"><?= $total_users_sb ?></span>
    </a>
  </nav>
  <div class="sb-footer">
    <div class="sb-admin-name">👤 <?= e($_SESSION['admin_name']) ?></div>
    <a href="logout.php" class="sb-logout">Keluar</a>
  </div>
</aside>
