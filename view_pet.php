<?php
// view_pet.php — Lihat pet milik teman
require_once 'includes/config.php';
requireLogin();
$active_page = 'friends';
$uid = $_SESSION['user_id'];
$initials = strtoupper(substr($_SESSION['full_name'], 0, 2));

$friend_id = (int)($_GET['user'] ?? 0);
if (!$friend_id || $friend_id === $uid) {
    header('Location: friends.php'); exit;
}

// Pastikan mereka berteman
$is_friend = mysqli_fetch_row(mysqli_query($conn,
    "SELECT id FROM friends
     WHERE ((user_id=$uid AND friend_id=$friend_id) OR (user_id=$friend_id AND friend_id=$uid))
     AND status='accepted'"));
if (!$is_friend) {
    header('Location: friends.php'); exit;
}

// Data teman
$friend = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT id, full_name, username FROM users WHERE id=$friend_id"));
if (!$friend) { header('Location: friends.php'); exit; }

// Data pet teman
$pet = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM pets WHERE user_id=$friend_id"));

// Kalau teman belum punya pet
if (!$pet) {
    $no_pet = true;
} else {
    $no_pet = false;
    $pid = $pet['id'];

    $stages = [
        ['min'=>0,  'stage'=>'egg',   'emoji'=>'🥚', 'label'=>'Telur'],
        ['min'=>2,  'stage'=>'baby',  'emoji'=>'🐣', 'label'=>'Bayi'],
        ['min'=>5,  'stage'=>'teen',  'emoji'=>'🐥', 'label'=>'Remaja'],
        ['min'=>10, 'stage'=>'adult', 'emoji'=>'🐦', 'label'=>'Dewasa'],
    ];
    function getStage($level, $stages) {
        foreach (array_reverse($stages) as $s) {
            if ($level >= $s['min']) return $s;
        }
        return $stages[0];
    }

    $stage_info  = getStage($pet['level'], $stages);
    $XP_PER_LEVEL = 100;
    $xp_current  = $pet['xp'] % $XP_PER_LEVEL;
    $xp_pct      = min(100, round(($xp_current / $XP_PER_LEVEL) * 100));

    // Umur pet
    $age_days  = max(0,(int)mysqli_fetch_row(mysqli_query($conn,"SELECT DATEDIFF(NOW(),born_at) FROM pets WHERE id=$pid"))[0]);
    $age_hours = (int)mysqli_fetch_row(mysqli_query($conn,"SELECT TIMESTAMPDIFF(HOUR,born_at,NOW()) FROM pets WHERE id=$pid"))[0];
    if ($age_days === 0) $age_str = $age_hours < 1 ? 'Baru lahir!' : $age_hours.' jam';
    else $age_str = $age_days.' hari';

    // Mood
    $last_any = mysqli_fetch_row(mysqli_query($conn,
        "SELECT TIMESTAMPDIFF(HOUR,interaction_at,NOW()) FROM pet_interactions WHERE pet_id=$pid ORDER BY interaction_at DESC LIMIT 1"));
    $hours_since = $last_any ? (int)$last_any[0] : 999;
    if ($hours_since < 1)      { $mood = '😄'; $mood_label = 'Sangat Senang'; }
    elseif ($hours_since < 6)  { $mood = '😊'; $mood_label = 'Senang'; }
    elseif ($hours_since < 24) { $mood = '😐'; $mood_label = 'Biasa'; }
    else                        { $mood = '😢'; $mood_label = 'Kangen pemiliknya!'; }

    // Total interaksi
    $total_interactions = (int)mysqli_fetch_row(mysqli_query($conn,
        "SELECT COUNT(*) FROM pet_interactions WHERE pet_id=$pid"))[0];

    // Log aktivitas (hanya tipe, bukan isi pesan)
    $interactions = mysqli_fetch_all(mysqli_query($conn,
        "SELECT type, xp_gained, interaction_at FROM pet_interactions
         WHERE pet_id=$pid ORDER BY interaction_at DESC LIMIT 6"), MYSQLI_ASSOC);

    // Pesan pet
    $pet_messages = [
        'egg'   => ['Aku masih di dalam telur... 🥚', 'Kapan aku menetas ya? 🤔'],
        'baby'  => ['Halo tamu! Aku pet-nya '.e($friend['full_name']).'! 🐣', 'Minta snack dong~ 🍎', 'Senang ada yang main kesini! 💕'],
        'teen'  => ['Hai! Aku sudah mulai besar! 🐥', 'Majikanku rajin merawat aku lho! ✨'],
        'adult' => ['Selamat datang! Aku sudah dewasa 🐦', 'Terima kasih sudah mampir! 🌟'],
    ];
    $current_messages = $pet_messages[$stage_info['stage']] ?? $pet_messages['baby'];
    $random_msg = $current_messages[array_rand($current_messages)];
}

function avatarColor($str) { return '#'.substr(md5($str),0,6); }
function avatarInit($str)  { return strtoupper(substr($str,0,2)); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pet <?= e($friend['full_name']) ?> — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/main.css">
<script src="assets/js/notif.js" defer></script>
<style>
.pet-page{display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start}
.pet-main-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;text-align:center}

/* Owner info */
.owner-bar{display:flex;align-items:center;gap:12px;background:var(--purple-light);border:1px solid #c7d2fe;border-radius:12px;padding:12px 16px;margin-bottom:20px}
.owner-avatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0}
.owner-name{font-size:13px;font-weight:700;color:var(--purple)}
.owner-sub{font-size:11px;color:var(--muted);margin-top:2px}

.pet-emoji{font-size:88px;display:block;line-height:1;margin-bottom:8px;animation:float 3s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}

.pet-stage-badge{display:inline-block;background:var(--purple-light);color:var(--purple);font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;margin-bottom:10px}
.pet-name-display{font-size:22px;font-weight:700;margin-bottom:4px}
.pet-age-display{font-size:13px;color:var(--muted);margin-bottom:6px}
.pet-mood{font-size:13px;margin-bottom:18px}

.xp-wrap{margin:16px 0}
.xp-labels{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:6px;font-weight:600}
.xp-bar{height:10px;background:var(--border);border-radius:5px;overflow:hidden}
.xp-fill{height:100%;border-radius:5px;background:linear-gradient(90deg,var(--purple),#9F7AEA)}

.pet-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:16px 0}
.pet-stat-item{background:var(--bg);border-radius:10px;padding:12px 8px;text-align:center}
.pet-stat-val{font-size:20px;font-weight:700;color:var(--purple)}
.pet-stat-lbl{font-size:10px;color:var(--muted);margin-top:3px}

.pet-speech{background:var(--purple-light);border:1px solid #c7d2fe;border-radius:12px;border-bottom-left-radius:4px;padding:12px 16px;font-size:13px;color:var(--purple);font-style:italic;margin:14px 0;text-align:left}

/* Readonly badge */
.readonly-badge{display:flex;align-items:center;gap:8px;background:#FFFAF0;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;font-size:12px;color:#C05621;margin-top:14px}

/* Right panel */
.pet-right{display:flex;flex-direction:column;gap:16px}

.stages-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px}
.stages-track{display:flex;align-items:center;gap:0;margin-top:14px;position:relative}
.stage-step{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;z-index:1}
.stage-circle{width:48px;height:48px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:22px;border:3px solid var(--border)}
.stage-circle.done{background:var(--purple-light);border-color:var(--purple)}
.stage-circle.current{background:var(--purple);border-color:var(--purple);box-shadow:0 0 0 4px rgba(108,99,255,.2)}
.stage-label{font-size:11px;color:var(--muted);margin-top:6px;font-weight:600}
.stage-label.current{color:var(--purple)}
.stage-connector{flex:1;height:3px;background:var(--border);margin-top:-24px;position:relative;z-index:0}
.stage-connector.done{background:var(--purple)}

.activity-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px}
.activity-item{display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border)}
.activity-item:last-child{border-bottom:none}
.act-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.act-icon.chat{background:#EEF2FF}.act-icon.feed{background:#FFFAF0}
.act-icon.play{background:var(--green-light)}.act-icon.poke{background:#FFF5F5}
.act-text{flex:1;font-size:13px}
.act-xp{font-size:12px;font-weight:700;color:var(--purple)}
.act-time{font-size:11px;color:var(--muted)}

.no-pet-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:60px 28px;text-align:center;color:var(--muted)}
.no-pet-card .ico{font-size:52px;margin-bottom:16px}

@media(max-width:900px){.pet-page{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <a href="friends.php" class="btn sm">← Kembali</a>
        <h1><?= $no_pet ? '🐾' : $stage_info['emoji'] ?> Pet milik <?= e($friend['full_name']) ?></h1>
      </div>
      <div class="topbar-right">
        <div class="notif-bell-wrap" id="notif-bell-wrap"></div>
        <a href="chat.php?with=<?= $friend_id ?>" class="btn primary">💬 Chat</a>
        <div class="avatar-circle"><?= $initials ?></div>
      </div>
    </div>

    <div class="page-body">

      <?php if ($no_pet): ?>
        <div class="no-pet-card">
          <div class="ico">🥚</div>
          <h3 style="font-size:16px;font-weight:700;margin-bottom:8px;color:var(--text)"><?= e($friend['full_name']) ?> belum punya pet</h3>
          <p style="font-size:13px">Teman kamu belum memiliki pet. Suruh dia login dulu agar petnya terbuat!</p>
        </div>

      <?php else: ?>
      <div class="pet-page">

        <!-- Kolom kiri -->
        <div>
          <!-- Info pemilik -->
          <div class="owner-bar">
            <div class="owner-avatar" style="background:<?= avatarColor($friend['username']) ?>">
              <?= avatarInit($friend['full_name']) ?>
            </div>
            <div>
              <div class="owner-name">👤 <?= e($friend['full_name']) ?></div>
              <div class="owner-sub">@<?= e($friend['username']) ?> · Pemilik pet ini</div>
            </div>
          </div>

          <div class="pet-main-card">
            <!-- Emoji dengan animasi float -->
            <span class="pet-emoji"><?= $stage_info['emoji'] ?></span>

            <!-- Bubble pesan -->
            <div class="pet-speech" id="pet-speech"><?= e($random_msg) ?></div>

            <div class="pet-stage-badge"><?= $stage_info['label'] ?></div>
            <div class="pet-name-display"><?= e($pet['name']) ?></div>
            <div class="pet-age-display">🎂 Umur: <b><?= $age_str ?></b></div>
            <div class="pet-mood"><?= $mood ?> Mood: <b><?= $mood_label ?></b></div>

            <!-- XP Bar -->
            <div class="xp-wrap">
              <div class="xp-labels">
                <span>Level <?= $pet['level'] ?></span>
                <span><?= $xp_current ?> / <?= $XP_PER_LEVEL ?> XP</span>
              </div>
              <div class="xp-bar">
                <div class="xp-fill" style="width:<?= $xp_pct ?>%"></div>
              </div>
            </div>

            <!-- Stats -->
            <div class="pet-stats">
              <div class="pet-stat-item">
                <div class="pet-stat-val"><?= $pet['level'] ?></div>
                <div class="pet-stat-lbl">Level</div>
              </div>
              <div class="pet-stat-item">
                <div class="pet-stat-val"><?= $total_interactions ?></div>
                <div class="pet-stat-lbl">Interaksi</div>
              </div>
              <div class="pet-stat-item">
                <div class="pet-stat-val"><?= $age_days ?></div>
                <div class="pet-stat-lbl">Hari Hidup</div>
              </div>
            </div>

            <!-- Readonly notice -->
            <div class="readonly-badge">
              👀 Kamu hanya bisa melihat pet ini. Hanya pemiliknya yang bisa berinteraksi.
            </div>
          </div>
        </div>

        <!-- Kolom kanan -->
        <div class="pet-right">

          <!-- Track pertumbuhan -->
          <div class="stages-card">
            <div class="section-title">🌱 Pertumbuhan <?= e($pet['name']) ?></div>
            <div class="stages-track">
              <?php
              $all_stages = [
                ['min'=>0,  'stage'=>'egg',   'emoji'=>'🥚', 'label'=>'Telur'],
                ['min'=>2,  'stage'=>'baby',  'emoji'=>'🐣', 'label'=>'Bayi'],
                ['min'=>5,  'stage'=>'teen',  'emoji'=>'🐥', 'label'=>'Remaja'],
                ['min'=>10, 'stage'=>'adult', 'emoji'=>'🐦', 'label'=>'Dewasa'],
              ];
              foreach ($all_stages as $i => $s):
                $is_current = $stage_info['stage'] === $s['stage'];
                $is_done    = $pet['level'] >= $s['min'] && !$is_current;
              ?>
              <?php if ($i > 0): ?>
                <div class="stage-connector <?= ($pet['level']>=$s['min'])?'done':'' ?>"></div>
              <?php endif; ?>
              <div class="stage-step">
                <div class="stage-circle <?= $is_current?'current':($is_done?'done':'') ?>"><?= $s['emoji'] ?></div>
                <div class="stage-label <?= $is_current?'current':'' ?>"><?= $s['label'] ?></div>
                <div style="font-size:10px;color:var(--muted)">Lv<?= $s['min'] ?>+</div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Progress ke stage berikutnya -->
            <?php
            $next_stage = null;
            foreach ($all_stages as $s) {
                if ($pet['level'] < $s['min']) { $next_stage = $s; break; }
            }
            ?>
            <?php if ($next_stage): ?>
            <div style="margin-top:16px;background:var(--bg);border-radius:10px;padding:12px 14px;font-size:13px">
              <span style="color:var(--muted)">Menuju </span>
              <b><?= $next_stage['emoji'].' '.$next_stage['label'] ?></b>
              <span style="color:var(--muted)"> butuh Level <?= $next_stage['min'] ?></span>
              <span style="float:right;color:var(--purple);font-weight:700">
                <?= max(0, ($next_stage['min'] - $pet['level'])) ?> level lagi
              </span>
            </div>
            <?php else: ?>
            <div style="margin-top:16px;background:var(--green-light);border-radius:10px;padding:12px 14px;font-size:13px;color:#276749;font-weight:600">
              🎉 <?= e($pet['name']) ?> sudah mencapai tahap tertinggi!
            </div>
            <?php endif; ?>
          </div>

          <!-- Log aktivitas -->
          <div class="activity-card">
            <div class="section-title">📋 Aktivitas Terakhir <?= e($pet['name']) ?></div>
            <?php if (empty($interactions)): ?>
              <div style="text-align:center;padding:20px;color:var(--muted);font-size:13px">
                Belum ada aktivitas tercatat.
              </div>
            <?php endif; ?>
            <?php
            $act_icons  = ['chat'=>'💬','feed'=>'🍎','play'=>'🎮','poke'=>'🤏'];
            $act_labels = ['chat'=>'Chatting','feed'=>'Kasih makan','play'=>'Ajak main','poke'=>'Mengelus'];
            foreach ($interactions as $act):
              $diff = time() - strtotime($act['interaction_at']);
              if ($diff < 60)        $time_str = 'Baru saja';
              elseif ($diff < 3600)  $time_str = floor($diff/60).' mnt lalu';
              elseif ($diff < 86400) $time_str = floor($diff/3600).' jam lalu';
              else                   $time_str = date('d M', strtotime($act['interaction_at']));
            ?>
            <div class="activity-item">
              <div class="act-icon <?= $act['type'] ?>"><?= $act_icons[$act['type']] ?? '⭐' ?></div>
              <div class="act-text"><?= $act_labels[$act['type']] ?? $act['type'] ?></div>
              <div>
                <div class="act-xp">+<?= $act['xp_gained'] ?> XP</div>
                <div class="act-time"><?= $time_str ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Bandingkan dengan pet sendiri -->
          <?php
          $my_pet = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM pets WHERE user_id=$uid"));
          if ($my_pet):
            $my_stage = getStage($my_pet['level'], $stages);
          ?>
          <div class="stages-card">
            <div class="section-title">⚔️ Perbandingan Pet</div>
            <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:center;margin-top:12px;text-align:center">
              <div>
                <div style="font-size:32px"><?= $my_stage['emoji'] ?></div>
                <div style="font-size:13px;font-weight:700;margin-top:4px"><?= e($my_pet['name']) ?></div>
                <div style="font-size:11px;color:var(--muted)">Pet-mu · Lv<?= $my_pet['level'] ?></div>
              </div>
              <div style="font-size:20px;color:var(--muted)">VS</div>
              <div>
                <div style="font-size:32px"><?= $stage_info['emoji'] ?></div>
                <div style="font-size:13px;font-weight:700;margin-top:4px"><?= e($pet['name']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= e($friend['full_name']) ?> · Lv<?= $pet['level'] ?></div>
              </div>
            </div>
            <div style="margin-top:14px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px">
              <div style="background:var(--bg);border-radius:8px;padding:8px;text-align:center">
                <b style="color:var(--purple)"><?= $my_pet['level'] >= $pet['level'] ? '👑 Lebih tinggi' : '💪 Semangat!' ?></b>
                <div style="color:var(--muted);margin-top:2px">Level-mu</div>
              </div>
              <div style="background:var(--bg);border-radius:8px;padding:8px;text-align:center">
                <b style="color:var(--purple)"><?= $age_days ?> hari</b>
                <div style="color:var(--muted);margin-top:2px">Umur <?= e($pet['name']) ?></div>
              </div>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Ganti pesan bubble setiap 6 detik
const msgs = <?= json_encode($no_pet ? [] : $current_messages) ?>;
if (msgs.length > 0) {
  let mi = 0;
  setInterval(()=>{
    mi = (mi+1) % msgs.length;
    const bubble = document.getElementById('pet-speech');
    if (!bubble) return;
    bubble.style.opacity = '0';
    bubble.style.transition = 'opacity .3s';
    setTimeout(()=>{ bubble.textContent = msgs[mi]; bubble.style.opacity = '1'; }, 300);
  }, 6000);
}
</script>
</body>
</html>
