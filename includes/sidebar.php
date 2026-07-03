<?php
$unread = 0;
$q = mysqli_prepare($conn,"SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0");
mysqli_stmt_bind_param($q,'i',$_SESSION['user_id']); mysqli_stmt_execute($q);
mysqli_stmt_bind_result($q,$unread); mysqli_stmt_fetch($q); mysqli_stmt_close($q);

$tasks_soon = 0;
$q2 = mysqli_prepare($conn,"SELECT COUNT(*) FROM tasks WHERE user_id=? AND status='pending' AND deadline BETWEEN NOW() AND NOW()+INTERVAL 7 DAY");
mysqli_stmt_bind_param($q2,'i',$_SESSION['user_id']); mysqli_stmt_execute($q2);
mysqli_stmt_bind_result($q2,$tasks_soon); mysqli_stmt_fetch($q2); mysqli_stmt_close($q2);

$pending_req = 0;
$q3 = mysqli_prepare($conn,"SELECT COUNT(*) FROM friends WHERE friend_id=? AND status='pending'");
mysqli_stmt_bind_param($q3,'i',$_SESSION['user_id']); mysqli_stmt_execute($q3);
mysqli_stmt_bind_result($q3,$pending_req); mysqli_stmt_fetch($q3); mysqli_stmt_close($q3);

// Notifikasi kiriman catatan & tugas belum dibaca
$unread_shares = 0;
$q4 = mysqli_prepare($conn,"SELECT (SELECT COUNT(*) FROM shared_notes WHERE receiver_id=? AND is_read=0) + (SELECT COUNT(*) FROM shared_tasks WHERE receiver_id=? AND is_read=0)");
mysqli_stmt_bind_param($q4,'ii',$_SESSION['user_id'],$_SESSION['user_id']); mysqli_stmt_execute($q4);
mysqli_stmt_bind_result($q4,$unread_shares); mysqli_stmt_fetch($q4); mysqli_stmt_close($q4);

$pet = null;
$pq = mysqli_prepare($conn,"SELECT name,level,stage,DATEDIFF(NOW(),born_at) as age_days FROM pets WHERE user_id=?");
mysqli_stmt_bind_param($pq,'i',$_SESSION['user_id']); mysqli_stmt_execute($pq);
$pet = mysqli_fetch_assoc(mysqli_stmt_get_result($pq));
$pet_emoji = ['egg'=>'🥚','baby'=>'🐣','teen'=>'🐥','adult'=>'🐦'][$pet['stage']??'egg']??'🥚';

$me = mysqli_fetch_assoc(mysqli_query($conn,"SELECT full_name,avatar FROM users WHERE id=".$_SESSION['user_id']));
?>
<aside class="sidebar">
  <div class="sb-logo">
    <span class="sb-logo-icon">📚</span>
    <div>
      <div class="sb-logo-title">StudyBuddy</div>
      <div class="sb-logo-sub">Halo, <?= e($_SESSION['full_name']) ?>!</div>
    </div>
  </div>
  <nav class="sb-nav">
    <a href="dashboard.php"  class="sb-item <?= ($active_page==='dashboard')?'active':'' ?>"><span class="sb-icon">🏠</span> Dashboard<?php if($tasks_soon>0): ?><span class="sb-badge red"><?= $tasks_soon ?></span><?php endif; ?></a>
    <a href="courses.php"    class="sb-item <?= ($active_page==='courses')  ?'active':'' ?>"><span class="sb-icon">📖</span> Matkul</a>
    <a href="schedule.php"   class="sb-item <?= ($active_page==='schedule') ?'active':'' ?>"><span class="sb-icon">🗓️</span> Jadwal</a>
    <a href="shared.php"     class="sb-item <?= ($active_page==='shared')   ?'active':'' ?>"><span class="sb-icon">📤</span> Kiriman<?php if($unread_shares>0): ?><span class="sb-badge red"><?= $unread_shares ?></span><?php endif; ?></a>
    <a href="friends.php"    class="sb-item <?= ($active_page==='friends')  ?'active':'' ?>"><span class="sb-icon">👥</span> Teman<?php if($pending_req>0): ?><span class="sb-badge red"><?= $pending_req ?></span><?php endif; ?></a>
    <a href="chat.php"       class="sb-item <?= ($active_page==='chat')     ?'active':'' ?>"><span class="sb-icon">💬</span> Chat<?php if($unread>0): ?><span class="sb-badge"><?= $unread ?></span><?php endif; ?></a>
    <a href="pet.php"        class="sb-item <?= ($active_page==='pet')      ?'active':'' ?>"><span class="sb-icon"><?= $pet_emoji ?></span> Pet-ku<?php if($pet): ?><span class="sb-badge green">Lv<?= $pet['level'] ?></span><?php endif; ?></a>
    <a href="profile.php"    class="sb-item <?= ($active_page==='profile')  ?'active':'' ?>"><span class="sb-icon">👤</span> Profil</a>
  </nav>
  <div class="sb-footer">
    <?php if($pet): ?>
    <div class="sb-pet-mini"><span><?= $pet_emoji ?> <?= e($pet['name']) ?></span><span class="sb-pet-age"><?= $pet['age_days'] ?> hari</span></div>
    <?php endif; ?>
    <a href="profile.php" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--text);margin-bottom:10px;padding:6px;border-radius:8px;transition:background .15s" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='none'">
      <?= avatarHtml($me['full_name'], $me['avatar'], 30) ?>
      <span style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($me['full_name']) ?></span>
    </a>
    <a href="logout.php" class="sb-logout">Keluar</a>
  </div>
</aside>
