<?php
// ============================================================
//  register.php — Halaman Daftar Akun Baru
//  Letakkan file ini di folder: studybuddy/
// ============================================================
require_once 'includes/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $confirm   = $_POST['confirm']        ?? '';

    // Validasi
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = 'Semua kolom wajib diisi.';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        // Cek username & email sudah dipakai
        $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR username = ?");
        mysqli_stmt_bind_param($chk, 'ss', $email, $username);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);

        if (mysqli_stmt_num_rows($chk) > 0) {
            $error = 'Email atau username sudah digunakan.';
        } else {
            // Simpan user baru
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = mysqli_prepare($conn,
                "INSERT INTO users (full_name, username, email, password) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($ins, 'ssss', $full_name, $username, $email, $hashed);

            if (mysqli_stmt_execute($ins)) {
                $new_user_id = mysqli_insert_id($conn);

                // Buat pet otomatis untuk user baru
                $pet_name = 'Pet';
                $pet = mysqli_prepare($conn,
                    "INSERT INTO pets (user_id, name, level, xp, stage, born_at) VALUES (?, ?, 1, 0, 'egg', NOW())"
                );
                mysqli_stmt_bind_param($pet, 'is', $new_user_id, $pet_name);
                mysqli_stmt_execute($pet);

                $success = 'Selamat.. Akun sudah di buat, waktunya login';
            } else {
                $error = 'Ada salah jir, lu coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar — StudyBuddy</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: #F0F2FF;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .container {
    display: flex;
    width: 100%;
    max-width: 860px;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(108,99,255,.15);
  }

  .panel-left {
    flex: 1;
    background: linear-gradient(145deg, #1a173b, #9d98a6);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: #fff;
    text-align: center;
  }
  .panel-left .mascot { font-size: 64px; margin-bottom: 16px; }
  .panel-left h1 { font-size: 24px; font-weight: 700; margin-bottom: 10px; }
  .panel-left p  { font-size: 13px; opacity: .85; line-height: 1.7; }

  .steps { margin-top: 28px; text-align: left; display: flex; flex-direction: column; gap: 14px; }
  .step  { display: flex; align-items: center; gap: 12px; }
  .step-num { width: 28px; height: 28px; background: rgba(255,255,255,.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
  .step-text { font-size: 13px; }

  .panel-right {
    width: 380px;
    background: #fff;
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .panel-right h2 { font-size: 22px; font-weight: 700; color: #2D3748; margin-bottom: 6px; }
  .panel-right .sub { font-size: 13px; color: #718096; margin-bottom: 24px; }

  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

  .form-group { margin-bottom: 15px; }
  .form-group label { display: block; font-size: 12px; font-weight: 600; color: #4A5568; margin-bottom: 5px; letter-spacing: .3px; }
  .form-group input {
    width: 100%;
    padding: 10px 13px;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    font-size: 14px;
    color: #2D3748;
    background: #F7F8FC;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
  }
  .form-group input:focus {
    border-color: #6C63FF;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(108,99,255,.12);
  }

  .hint { font-size: 11px; color: #A0AEC0; margin-top: 4px; }

  .error-msg {
    background: #FFF5F5;
    border: 1px solid #FED7D7;
    color: #8e0000;
    border-radius: 10px;
    padding: 11px 14px;
    font-size: 13px;
    margin-bottom: 16px;
  }
  .success-msg {
    background: #F0FFF4;
    border: 1px solid #9AE6B4;
    color: #276749;
    border-radius: 10px;
    padding: 11px 14px;
    font-size: 13px;
    margin-bottom: 16px;
  }
  .success-msg a { color: #276749; font-weight: 700; }

  .btn-submit {
    width: 100%;
    padding: 13px;
    background: #3a385a;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s, transform .1s;
    margin-top: 4px;
  }
  .btn-submit:hover  { background: #5a52e0; }
  .btn-submit:active { transform: scale(.98); }

  .link-login { text-align: center; font-size: 13px; color: #718096; margin-top: 18px; }
  .link-login a { color: #6C63FF; font-weight: 600; text-decoration: none; }
  .link-login a:hover { text-decoration: underline; }

  @media (max-width: 640px) {
    .panel-left { display: none; }
    .panel-right { width: 100%; padding: 36px 28px; }
    .form-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="container">

  <div class="panel-left">
    <div class="mascot">🐣</div>
    <h1>Selamat Datang!</h1>
    <p>Have a good Day</p>
    <div class="steps"> 
      <div class="step"><div class="step-num">1</div><div class="step-text">Just for fun gw jir</div></div>
      <div class="step"><div class="step-num">2</div><div class="step-text">Bisa merangkum Matkul hari ini</div></div>
      <div class="step"><div class="step-num">3</div><div class="step-text">Ayam Geprek Salak!</div></div>
    </div>
  </div>

  <div class="panel-right">
    <h2>Buat akun baru</h2>
    <p class="sub">Isi dulu yaa kalo belum bikin Akun</p>

    <?php if ($error): ?>
      <div class="error-msg">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="success-msg">
        ✅ <?= e($success) ?> <a href="login.php">Login sekarang →</a>
      </div>
    <?php else: ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="full_name">Nama</label>
        <input type="text" id="full_name" name="full_name"
          placeholder="Nama"
          value="<?= e($_POST['full_name'] ?? '') ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username"
            placeholder="username_kamu"
            value="<?= e($_POST['username'] ?? '') ?>" required>
          <div class="hint">Min. 3 karakter</div>
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email"
            placeholder="nama@email.com"
            value="<?= e($_POST['email'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
            placeholder="Min. 6 karakter" required>
        </div>
        <div class="form-group">
          <label for="confirm">Konfirmasi</label>
          <input type="password" id="confirm" name="confirm"
            placeholder="Ulangi password" required>
        </div>
      </div>

      <button type="submit" class="btn-submit">Daftar sekarang</button>
    </form>

    <?php endif; ?>

    <p class="link-login">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
  </div>

</div>
</body>
</html>
