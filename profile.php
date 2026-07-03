<?php
// profile.php — Edit profil: nama lengkap & foto profil
require_once 'includes/config.php';
requireLogin();
$active_page = 'profile';
$uid = $_SESSION['user_id'];

$user = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM users WHERE id=$uid"));
$initials = strtoupper(substr($user['full_name'], 0, 2));
$msg = '';

// Update profil
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');

    if (empty($full_name)) {
        $msg = 'error:Nama lengkap tidak boleh kosong.';
    } elseif (strlen($full_name) > 100) {
        $msg = 'error:Nama lengkap maksimal 100 karakter.';
    } else {
        $avatar_path = $user['avatar']; // tetap pakai yang lama kalau tidak upload baru

        // Cek apakah ada foto baru diupload
        if (!empty($_FILES['avatar']['name'])) {
            $res = uploadAvatar($_FILES['avatar']);
            if (isset($res['error'])) {
                $msg = 'error:'.$res['error'];
            } else {
                // Hapus foto lama kalau ada
                if ($user['avatar'] && file_exists(__DIR__.'/'.$user['avatar'])) {
                    unlink(__DIR__.'/'.$user['avatar']);
                }
                $avatar_path = $res['path'];
            }
        }

        if (!$msg) {
            $st = mysqli_prepare($conn,"UPDATE users SET full_name=?, avatar=? WHERE id=?");
            mysqli_stmt_bind_param($st,'ssi',$full_name,$avatar_path,$uid);
            mysqli_stmt_execute($st);

            // Update session
            $_SESSION['full_name'] = $full_name;

            $msg = 'success:Profil berhasil diperbarui!';
            $user = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM users WHERE id=$uid"));
            $initials = strtoupper(substr($user['full_name'], 0, 2));
        }
    }
}

// Hapus foto profil (kembali ke inisial)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['remove_avatar'])) {
    if ($user['avatar'] && file_exists(__DIR__.'/'.$user['avatar'])) {
        unlink(__DIR__.'/'.$user['avatar']);
    }
    mysqli_query($conn,"UPDATE users SET avatar=NULL WHERE id=$uid");
    $msg = 'success:Foto profil dihapus.';
    $user = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM users WHERE id=$uid"));
}

[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Profil Saya — StudyBuddy</title>
<link rel="stylesheet" href="assets/css/main.css">
<script src="assets/js/notif.js" defer></script>
<style>
.profile-wrap{max-width:560px;margin:0 auto}
.profile-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:32px}
.avatar-section{display:flex;flex-direction:column;align-items:center;margin-bottom:28px}
.avatar-preview-wrap{position:relative;margin-bottom:14px}
.avatar-preview{width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid var(--purple-light);display:block;background:linear-gradient(135deg,var(--purple),#9F7AEA);color:#fff;font-size:42px;font-weight:700;align-items:center;justify-content:center}
.avatar-img{display:flex}
.avatar-initials{display:flex;align-items:center;justify-content:center}
.avatar-upload-btn{position:absolute;bottom:4px;right:4px;width:36px;height:36px;border-radius:50%;background:var(--purple);color:#fff;border:3px solid var(--card);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:15px;transition:background .15s}
.avatar-upload-btn:hover{background:#5a52e0}
.avatar-upload-btn input{display:none}
.avatar-hint{font-size:11px;color:var(--muted);text-align:center}
.avatar-remove-link{font-size:12px;color:var(--red);cursor:pointer;text-decoration:none;margin-top:8px;display:inline-block}
.avatar-remove-link:hover{text-decoration:underline}

.profile-info-readonly{background:var(--bg);border-radius:10px;padding:14px 16px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
.profile-info-readonly .lbl{font-size:12px;color:var(--muted)}
.profile-info-readonly .val{font-size:13px;font-weight:600}

.profile-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:24px}
</style>
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <h1>👤 Profil Saya</h1>
      <div class="topbar-right">
        <div class="notif-bell-wrap" id="notif-bell-wrap"></div>
        <?= avatarHtml($user['full_name'], $user['avatar'], 36) ?>
      </div>
    </div>

    <div class="page-body">
      <div class="profile-wrap">
        <?php if ($msg_text): ?>
          <div class="alert <?= $msg_type ?>"><?= e($msg_text) ?></div>
        <?php endif; ?>

        <div class="profile-card">
          <form method="POST" enctype="multipart/form-data" id="profile-form">

            <!-- Foto profil -->
            <div class="avatar-section">
              <div class="avatar-preview-wrap">
                <?php if ($user['avatar']): ?>
                  <img src="<?= e($user['avatar']) ?>?t=<?= time() ?>" class="avatar-preview avatar-img" id="avatar-preview-img" alt="Foto profil">
                <?php else: ?>
                  <div class="avatar-preview avatar-initials" id="avatar-preview-initials"><?= $initials ?></div>
                  <img src="" class="avatar-preview avatar-img" id="avatar-preview-img" alt="Foto profil" style="display:none">
                <?php endif; ?>

                <label class="avatar-upload-btn" title="Ganti foto">
                  📷
                  <input type="file" name="avatar" id="avatar-input" accept="image/jpeg,image/png,image/webp" onchange="previewAvatar(this)">
                </label>
              </div>
              <div class="avatar-hint">JPG, PNG, atau WEBP · Maks 3MB</div>
              <?php if ($user['avatar']): ?>
                <a href="#" class="avatar-remove-link" onclick="document.getElementById('remove-form').submit();return false;">🗑 Hapus foto profil</a>
              <?php endif; ?>
            </div>

            <!-- Info readonly -->
            <div class="profile-info-readonly">
              <span class="lbl">Username</span>
              <span class="val">@<?= e($user['username']) ?></span>
            </div>
            <div class="profile-info-readonly">
              <span class="lbl">Email</span>
              <span class="val"><?= e($user['email']) ?></span>
            </div>

            <!-- Nama lengkap -->
            <div class="form-group">
              <label>Nama Lengkap</label>
              <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" maxlength="100" required>
            </div>

            <div class="profile-actions">
              <button type="submit" name="update_profile" class="btn primary">💾 Simpan Perubahan</button>
            </div>
          </form>

          <!-- Form terpisah untuk hapus foto -->
          <form method="POST" id="remove-form" style="display:none">
            <input type="hidden" name="remove_avatar" value="1">
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const imgEl = document.getElementById('avatar-preview-img');
      const initEl = document.getElementById('avatar-preview-initials');
      imgEl.src = e.target.result;
      imgEl.style.display = 'flex';
      if (initEl) initEl.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>
