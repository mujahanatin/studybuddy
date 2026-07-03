<?php
// pet.php
require_once 'includes/config.php';
requireLogin();
$active_page = 'pet';
$uid = $_SESSION['user_id'];
$initials = strtoupper(substr($_SESSION['full_name'], 0, 2));

// Ambil data pet, buat otomatis kalau belum ada
$pet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pets WHERE user_id=$uid"));
if (!$pet) {
    mysqli_query($conn, "INSERT INTO pets (user_id,name,level,xp,stage,born_at) VALUES ($uid,'Pet',1,0,'egg',NOW())");
    $pet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pets WHERE user_id=$uid"));
}
$pid = $pet['id'];

// ── Konstanta growth ──
$XP_PER_LEVEL = 100;
$stages = [
    ['min'=>0,  'max'=>1,  'stage'=>'amink',   'emoji'=>'🥚', 'label'=>'amink'],
    ['min'=>2,  'max'=>4,  'stage'=>'ponyo',  'emoji'=>'🐣', 'label'=>'ponyo'],
    ['min'=>5,  'max'=>9,  'stage'=>'shuihi',  'emoji'=>'🐥', 'label'=>'shuihi'],
    ['min'=>10, 'max'=>999,'stage'=>'felix', 'emoji'=>'🐦', 'label'=>'felix'],
];

function getStage($level, $stages) {
    foreach (array_reverse($stages) as $s) {
        if ($level >= $s['min']) return $s;
    }
    return $stages[0];
}

// Hitung umur pet
$age_days   = max(0, (int)mysqli_fetch_row(mysqli_query($conn,"SELECT DATEDIFF(NOW(),born_at) FROM pets WHERE id=$pid"))[0]);
$age_hours  = (int)mysqli_fetch_row(mysqli_query($conn,"SELECT TIMESTAMPDIFF(HOUR,born_at,NOW()) FROM pets WHERE id=$pid"))[0];

// Hitung XP progress ke level berikutnya
$xp_needed  = $XP_PER_LEVEL;
$xp_current = $pet['xp'] % $XP_PER_LEVEL;
$xp_pct     = min(100, round(($xp_current / $xp_needed) * 100));

// Stage info
$stage_info = getStage($pet['level'], $stages);

// Log interaksi terakhir
$interactions = mysqli_fetch_all(mysqli_query($conn,
    "SELECT type, xp_gained, interaction_at FROM pet_interactions
     WHERE pet_id=$pid ORDER BY interaction_at DESC LIMIT 8"), MYSQLI_ASSOC);

$msg = '';
$xp_gained_msg = 0;

// ── Aksi pet ──
if ($_SERVER['REQUEST_METHOD']==='POST') {

    // Ganti nama
    if (isset($_POST['rename'])) {
        $new_name = trim($_POST['pet_name'] ?? '');
        if ($new_name && strlen($new_name) <= 30) {
            mysqli_query($conn,"UPDATE pets SET name='".mysqli_real_escape_string($conn,$new_name)."' WHERE id=$pid AND user_id=$uid");
            $msg = 'success:Nama pet berhasil diganti!';
        } else { $msg = 'error:Nama tidak valid (maks 30 karakter).'; }
    }

    // Kasih makan
    if (isset($_POST['feed'])) {
        $last_fed = $pet['last_fed'] ? strtotime($pet['last_fed']) : 0;
        $cooldown = 3600; // 1 jam cooldown
        if (time() - $last_fed < $cooldown) {
            $wait = ceil(($cooldown - (time()-$last_fed)) / 60);
            $msg = 'error:Pet masih kenyang! Tunggu '.$wait.' menit lagi.';
        } else {
            $xp = 20;
            mysqli_query($conn,"UPDATE pets SET xp=xp+$xp, last_fed=NOW() WHERE id=$pid");
            mysqli_query($conn,"INSERT INTO pet_interactions (pet_id,type,xp_gained) VALUES ($pid,'feed',$xp)");
            $xp_gained_msg = $xp;
            $msg = 'success:+20 XP! aku kenyang!';
        }
    }

    // Ajak main
    if (isset($_POST['play'])) {
        $last_played = $pet['last_played'] ? strtotime($pet['last_played']) : 0;
        $cooldown = 1800; // 30 menit cooldown
        if (time() - $last_played < $cooldown) {
            $wait = ceil(($cooldown - (time()-$last_played)) / 60);
            $msg = 'error:Aku kelelahan! Tunggu '.$wait.' menit lagi yaa.';
        } else {
            $xp = 25;
            mysqli_query($conn,"UPDATE pets SET xp=xp+$xp, last_played=NOW() WHERE id=$pid");
            mysqli_query($conn,"INSERT INTO pet_interactions (pet_id,type,xp_gained) VALUES ($pid,'play',$xp)");
            $xp_gained_msg = $xp;
            $msg = 'success:+25 XP! Aku senang diajak main!';
        }
    }

    // Elus / poke
    if (isset($_POST['poke'])) {
        // Cek apakah sudah poke dalam 10 menit
        $last_poke = mysqli_fetch_row(mysqli_query($conn,
            "SELECT interaction_at FROM pet_interactions WHERE pet_id=$pid AND type='poke' ORDER BY interaction_at DESC LIMIT 1"));
        $can_poke = !$last_poke || (time() - strtotime($last_poke[0])) > 600;
        if ($can_poke) {
            $xp = 5;
            mysqli_query($conn,"UPDATE pets SET xp=xp+$xp WHERE id=$pid");
            mysqli_query($conn,"INSERT INTO pet_interactions (pet_id,type,xp_gained) VALUES ($pid,'poke',$xp)");
            $xp_gained_msg = $xp;
            $msg = 'success:+5 XP! Aku suka dielus!';
        } else {
            $msg = 'error:Aku malu-malu, tunggu sebentar ya!';
        }
    }

    // Cek level up setelah aksi
    if (!$msg || strpos($msg,'success')===0) {
        $pet = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM pets WHERE id=$pid"));
        $new_level = max(1, (int)floor($pet['xp'] / $XP_PER_LEVEL) + 1);
        if ($new_level != $pet['level']) {
            $new_stage = getStage($new_level, $stages);
            mysqli_query($conn,"UPDATE pets SET level=$new_level, stage='".$new_stage['stage']."' WHERE id=$pid");
            $msg = 'success:LEVEL UP!'.$new_level.' dan menjadi '.$new_stage['label'].'! '.$new_stage['emoji'];
        }
    }

    // Reload data terbaru setelah aksi
    $pet = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM pets WHERE id=$pid"));
    $xp_current = $pet['xp'] % $XP_PER_LEVEL;
    $xp_pct = min(100, round(($xp_current / $xp_needed) * 100));
    $stage_info = getStage($pet['level'], $stages);
    $interactions = mysqli_fetch_all(mysqli_query($conn,
        "SELECT type, xp_gained, interaction_at FROM pet_interactions
         WHERE pet_id=$pid ORDER BY interaction_at DESC LIMIT 8"), MYSQLI_ASSOC);
}

// Cooldown status
$last_fed_sec    = $pet['last_fed']    ? time()-strtotime($pet['last_fed'])    : 99999;
$last_played_sec = $pet['last_played'] ? time()-strtotime($pet['last_played']) : 99999;
$can_feed   = $last_fed_sec    >= 3600;
$can_play   = $last_played_sec >= 1800;
$feed_wait  = $can_feed  ? 0 : ceil((3600  - $last_fed_sec)    / 60);
$play_wait  = $can_play  ? 0 : ceil((1800  - $last_played_sec) / 60);

// Total XP dari chat
$total_chat_xp = (int)mysqli_fetch_row(mysqli_query($conn,
    "SELECT COALESCE(SUM(xp_gained),0) FROM pet_interactions WHERE pet_id=$pid AND type='chat'"))[0];
$total_interactions = (int)mysqli_fetch_row(mysqli_query($conn,
    "SELECT COUNT(*) FROM pet_interactions WHERE pet_id=$pid"))[0];

// Mood berdasarkan interaksi terakhir
$last_any = mysqli_fetch_row(mysqli_query($conn,
    "SELECT TIMESTAMPDIFF(HOUR,interaction_at,NOW()) FROM pet_interactions WHERE pet_id=$pid ORDER BY interaction_at DESC LIMIT 1"));
$hours_since = $last_any ? (int)$last_any[0] : 999;
if ($hours_since < 1)      { $mood = '૮₍ ˃ ⤙ ˂ ₎ა'; $mood_label = 'Seneng bangeet'; }
elseif ($hours_since < 6)  { $mood = '૮₍ ˶ᵔ ᵕ ᵔ˶ ₎ა'; $mood_label = 'Seneng'; }
elseif ($hours_since < 24) { $mood = '૮₍´˶• . • ⑅ ₎ა'; $mood_label = 'Biasa ajaa'; }
else                        { $mood = '૮◞ ‸ ◟ ა'; $mood_label = 'Kangen bangeet huhuu!'; }

// Pesan random pet
$pet_messages = [
    'egg'   => ['Aku masih di dalam telur...','Kapan aku menetas ya?','Hmm, hangat sekali di sini...'],
    'baby'  => ['Hai! Aku baru lahir!','Aku lapar! Kasih makan dong~ ','Main yuk! Main yuk! ','Kamu baik banget deh! '],
    'teen'  => ['Aku udah mulai besar nih! ','Terus semangat belajarnya ya! ','Aku senang bisa tumbuh bersamamu! ','Hari ini belajar apa? '],
    'adult' => ['Aku sudah dewasa! Terima kasih selalu merawatku 🐦','Kita sudah lama bersama ya~ 💖','Semangat terus kuliahnya! Aku selalu di sini 🌟','Kamu adalah teman terbaikku! 🥰'],
];
$current_messages = $pet_messages[$stage_info['stage']] ?? $pet_messages['baby'];
$random_msg = $current_messages[array_rand($current_messages)];

[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];

// Format umur
if ($age_days === 0) {
    $age_str = $age_hours < 1 ? 'Baru lahir!' : $age_hours.' jam';
} else {
    $age_str = $age_days.' hari';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pet-ku — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/main.css">
<script src="assets/js/notif.js" defer></script>
<style>
.pet-page{display:grid;grid-template-columns:360px 1fr;gap:20px;align-items:start}

/* Kartu utama pet */
.pet-main-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;text-align:center}
.pet-emoji-wrap{position:relative;display:inline-block;margin-bottom:8px}
.pet-emoji{font-size:88px;display:block;cursor:pointer;transition:transform .2s;line-height:1;user-select:none}
.pet-emoji:hover{transform:scale(1.08)}
.pet-emoji:active{transform:scale(.95)}
.xp-pop{position:absolute;top:-10px;right:-10px;background:var(--purple);color:#fff;font-size:12px;font-weight:700;padding:3px 8px;border-radius:10px;opacity:0;transform:translateY(0);transition:all .6s}
.xp-pop.show{opacity:1;transform:translateY(-20px)}

.pet-stage-badge{display:inline-block;background:var(--purple-light);color:var(--purple);font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;margin-bottom:10px}
.pet-name-display{font-size:22px;font-weight:700;margin-bottom:4px}
.pet-age-display{font-size:13px;color:var(--muted);margin-bottom:6px}
.pet-mood{font-size:13px;margin-bottom:18px}

/* XP bar */
.xp-wrap{margin:16px 0}
.xp-labels{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:6px;font-weight:600}
.xp-bar{height:10px;background:var(--border);border-radius:5px;overflow:hidden}
.xp-fill{height:100%;border-radius:5px;background:linear-gradient(90deg,var(--purple),#9F7AEA);transition:width .6s ease}

/* Stats */
.pet-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:16px 0}
.pet-stat-item{background:var(--bg);border-radius:10px;padding:12px 8px;text-align:center}
.pet-stat-val{font-size:20px;font-weight:700;color:var(--purple)}
.pet-stat-lbl{font-size:10px;color:var(--muted);margin-top:3px}

/* Tombol aksi */
.pet-actions{display:flex;flex-direction:column;gap:8px;margin-top:18px}
.pet-action-btn{width:100%;padding:11px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;border:1.5px solid var(--border);background:var(--card);color:var(--text);transition:all .15s;display:flex;align-items:center;justify-content:center;gap:8px}
.pet-action-btn:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.08)}
.pet-action-btn:disabled{opacity:.45;cursor:not-allowed}
.pet-action-btn.feed{border-color:#F6AD55;color:#C05621}
.pet-action-btn.feed:hover:not(:disabled){background:#FFFAF0}
.pet-action-btn.play{border-color:var(--green);color:#276749}
.pet-action-btn.play:hover:not(:disabled){background:var(--green-light)}
.pet-action-btn.poke{border-color:var(--purple);color:var(--purple)}
.pet-action-btn.poke:hover:not(:disabled){background:var(--purple-light)}
.pet-action-btn.rename{border-color:var(--muted);color:var(--muted)}
.pet-action-btn.rename:hover:not(:disabled){background:var(--bg)}
.cooldown-hint{font-size:11px;color:var(--muted);margin-top:2px}

/* Bubble pesan pet */
.pet-speech{background:var(--purple-light);border:1px solid #c7d2fe;border-radius:12px;border-bottom-left-radius:4px;padding:12px 16px;font-size:13px;color:var(--purple);font-style:italic;margin:14px 0;text-align:left;position:relative}
.pet-speech::before{content:'';position:absolute;bottom:-8px;left:20px;width:0;height:0;border-left:8px solid transparent;border-right:8px solid transparent;border-top:8px solid #c7d2fe}

/* Panel kanan */
.pet-right{display:flex;flex-direction:column;gap:16px}

/* Growth stages */
.stages-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px}
.stages-track{display:flex;align-items:center;gap:0;margin-top:14px;position:relative}
.stage-step{display:flex;flex-direction:column;align-items:center;flex:1;position:relative;z-index:1}
.stage-circle{width:48px;height:48px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:22px;border:3px solid var(--border);transition:all .3s}
.stage-circle.done{background:var(--purple-light);border-color:var(--purple)}
.stage-circle.current{background:var(--purple);border-color:var(--purple);box-shadow:0 0 0 4px rgba(108,99,255,.2)}
.stage-label{font-size:11px;color:var(--muted);margin-top:6px;font-weight:600}
.stage-label.current{color:var(--purple)}
.stage-connector{flex:1;height:3px;background:var(--border);margin-top:-24px;position:relative;z-index:0}
.stage-connector.done{background:var(--purple)}

/* Aktivitas */
.activity-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px}
.activity-item{display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border)}
.activity-item:last-child{border-bottom:none}
.act-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.act-icon.chat{background:#EEF2FF}
.act-icon.feed{background:#FFFAF0}
.act-icon.play{background:var(--green-light)}
.act-icon.poke{background:#FFF5F5}
.act-text{flex:1;font-size:13px}
.act-xp{font-size:12px;font-weight:700;color:var(--purple)}
.act-time{font-size:11px;color:var(--muted)}

/* Tips */
.tips-card{background:linear-gradient(135deg,var(--purple-light),#f0e6ff);border:1px solid #c7d2fe;border-radius:16px;padding:20px}
.tip-item{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;font-size:13px}
.tip-item:last-child{margin-bottom:0}
.tip-icon{font-size:16px;flex-shrink:0;margin-top:1px}

/* Modal ganti nama */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:var(--card);border-radius:16px;padding:28px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal h3{font-size:17px;font-weight:700;margin-bottom:18px}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}

@media(max-width:900px){.pet-page{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <h1><?= $stage_info['emoji'] ?> Pet-ku</h1>
      <div class="topbar-right">
        <div class="notif-bell-wrap" id="notif-bell-wrap"></div>
        <?= avatarHtml($_SESSION["full_name"], mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=$uid"))["avatar"] ?? null, 36) ?>
      </div>
    </div>

    <div class="page-body">
      <?php if ($msg_text): ?>
        <div class="alert <?= $msg_type ?>" id="alert-msg"><?= e($msg_text) ?></div>
      <?php endif; ?>

      <div class="pet-page">

        <!-- Kolom kiri: kartu utama pet -->
        <div>
          <div class="pet-main-card">
            <!-- Emoji pet + animasi XP -->
            <div class="pet-emoji-wrap">
              <form method="POST" style="display:inline">
                <button type="submit" name="poke" style="background:none;border:none;padding:0;cursor:pointer" title="Elus pet!">
                  <span class="pet-emoji" id="pet-emoji"><?= $stage_info['emoji'] ?></span>
                </button>
              </form>
              <span class="xp-pop" id="xp-pop"></span>
            </div>

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
                <span><?= $xp_current ?> / <?= $xp_needed ?> XP</span>
              </div>
              <div class="xp-bar">
                <div class="xp-fill" style="width:<?= $xp_pct ?>%"></div>
              </div>
              <div style="font-size:11px;color:var(--muted);margin-top:4px">
                <?= $xp_needed - $xp_current ?> XP lagi untuk level berikutnya
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

            <!-- Tombol aksi -->
            <div class="pet-actions">
              <form method="POST">
                <button type="submit" name="feed" class="pet-action-btn feed" <?= !$can_feed?'disabled':'' ?>>
                  🍎 Kasih Makan <?= !$can_feed ? '('.$feed_wait.' mnt lagi)' : '' ?>
                </button>
              </form>
              <form method="POST">
                <button type="submit" name="play" class="pet-action-btn play" <?= !$can_play?'disabled':'' ?>>
                  🎮 Ajak Main <?= !$can_play ? '('.$play_wait.' mnt lagi)' : '' ?>
                </button>
              </form>
              <button type="button" class="pet-action-btn poke" onclick="openModal('modal-rename')">
                ✏️ Ganti Nama
              </button>
            </div>

            <div class="cooldown-hint" style="margin-top:10px">
              💡 Klik emoji pet di atas untuk mengelus (+5 XP)
            </div>
          </div>
        </div>

        <!-- Kolom kanan -->
        <div class="pet-right">

          <!-- Track pertumbuhan -->
          <div class="stages-card">
            <div class="section-title">🌱 Pertumbuhan Pet</div>
            <div class="stages-track">
              <?php foreach ($stages as $i => $s):
                $is_current = $stage_info['stage'] === $s['stage'];
                $is_done    = $pet['level'] >= $s['min'] && !$is_current;
              ?>
              <?php if ($i > 0): ?>
                <div class="stage-connector <?= ($pet['level']>=$s['min'])?'done':'' ?>"></div>
              <?php endif; ?>
              <div class="stage-step">
                <div class="stage-circle <?= $is_current?'current':($is_done?'done':'') ?>">
                  <?= $s['emoji'] ?>
                </div>
                <div class="stage-label <?= $is_current?'current':'' ?>"><?= $s['label'] ?></div>
                <div style="font-size:10px;color:var(--muted)">Lv<?= $s['min'] ?>+</div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Cara mendapat XP -->
          <div class="tips-card">
            <div class="section-title">⚡ Cara Menambah XP</div>
            <div class="tip-item"><span class="tip-icon">💬</span><span><b>Chat dengan teman</b> → +5 XP setiap pesan yang dikirim</span></div>
            <div class="tip-item"><span class="tip-icon">🍎</span><span><b>Kasih makan</b> → +20 XP (cooldown 1 jam)</span></div>
            <div class="tip-item"><span class="tip-icon">🎮</span><span><b>Ajak main</b> → +25 XP (cooldown 30 menit)</span></div>
            <div class="tip-item"><span class="tip-icon">🤏</span><span><b>Elus / klik emoji</b> → +5 XP (cooldown 10 menit)</span></div>
            <div class="tip-item"><span class="tip-icon">📈</span><span>Setiap <b>100 XP</b> = naik 1 level</span></div>
          </div>

          <!-- Log aktivitas -->
          <div class="activity-card">
            <div class="section-title">📋 Aktivitas Terakhir</div>
            <?php if (empty($interactions)): ?>
              <div style="text-align:center;padding:20px;color:var(--muted);font-size:13px">
                Belum ada aktivitas. Mulai interaksi dengan pet!
              </div>
            <?php endif; ?>
            <?php
            $act_icons  = ['chat'=>'💬','feed'=>'🍎','play'=>'🎮','poke'=>'🤏'];
            $act_labels = ['chat'=>'Chatting','feed'=>'Kasih makan','play'=>'Ajak main','poke'=>'Mengelus'];
            foreach ($interactions as $act):
              $diff = time() - strtotime($act['interaction_at']);
              if ($diff < 60)         $time_str = 'Baru saja';
              elseif ($diff < 3600)   $time_str = floor($diff/60).' mnt lalu';
              elseif ($diff < 86400)  $time_str = floor($diff/3600).' jam lalu';
              else                    $time_str = date('d M', strtotime($act['interaction_at']));
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

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal ganti nama -->
<div class="modal-overlay" id="modal-rename">
  <div class="modal">
    <h3>✏️ Ganti Nama Pet</h3>
    <form method="POST">
      <div class="form-group">
        <label>Nama baru untuk <?= e($pet['name']) ?></label>
        <input type="text" name="pet_name" value="<?= e($pet['name']) ?>" maxlength="30" placeholder="Masukkan nama..." required autofocus>
        <div style="font-size:11px;color:var(--muted);margin-top:4px">Maks 30 karakter</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('modal-rename')">Batal</button>
        <button type="submit" name="rename" class="btn primary">Simpan Nama</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.modal-overlay').forEach(o=>{
  o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open')})
});

// Hilangkan alert setelah 3 detik
const alertEl = document.getElementById('alert-msg');
if (alertEl) setTimeout(()=>{ alertEl.style.opacity='0'; alertEl.style.transition='opacity .5s'; setTimeout(()=>alertEl.remove(),500); }, 3000);

// Animasi XP saat halaman dimuat setelah aksi berhasil
<?php if ($xp_gained_msg > 0): ?>
(function(){
  const pop = document.getElementById('xp-pop');
  const emoji = document.getElementById('pet-emoji');
  pop.textContent = '+<?= $xp_gained_msg ?> XP';
  pop.classList.add('show');
  emoji.style.transform = 'scale(1.2)';
  setTimeout(()=>{
    pop.classList.remove('show');
    emoji.style.transform = '';
  }, 1200);
})();
<?php endif; ?>

// Pesan acak setiap 8 detik
const msgs = <?= json_encode($current_messages) ?>;
let mi = 0;
setInterval(()=>{
  mi = (mi+1) % msgs.length;
  const bubble = document.getElementById('pet-speech');
  bubble.style.opacity = '0';
  bubble.style.transition = 'opacity .3s';
  setTimeout(()=>{
    bubble.textContent = msgs[mi];
    bubble.style.opacity = '1';
  }, 300);
}, 8000);
</script>
</body>
</html>
