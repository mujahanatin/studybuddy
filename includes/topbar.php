<?php
// includes/topbar.php — Topbar dengan bell notifikasi
// Panggil dengan: require_once 'includes/topbar.php';
// Variabel $page_title harus di-set sebelumnya
?>
<div class="topbar">
  <h1><?= $page_title ?? '' ?></h1>
  <div class="topbar-right">
    <!-- Bell notifikasi -->
    <div class="notif-bell-wrap" id="notif-bell-wrap"></div>
    <?= avatarHtml($_SESSION['full_name'], mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=".$_SESSION['user_id']))['avatar'] ?? null, 36) ?>
  </div>
</div>
