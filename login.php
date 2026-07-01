<?php
// ============================================================
//  login.php — Halaman Login
//  Letakkan file ini di folder: studybuddy/
// ============================================================
require_once 'includes/config.php';

// Kalau sudah login, langsung ke dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        // Cari user berdasarkan email
        $stmt = mysqli_prepare($conn, "SELECT id, full_name, password FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            // Login berhasil
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];

            // Update last_login
            $upd = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($upd, 'i', $user['id']);
            mysqli_stmt_execute($upd);

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Email atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — StudyBuddy</title>
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
    min-height: 520px;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(108,99,255,.15);
  }

  /* Panel kiri — ilustrasi */
  .panel-left {
    flex: 1;
    background: linear-gradient(145deg, #23233e, #968da7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: #fff;
    text-align: center;
  }
  .panel-left .mascot { font-size: 72px; margin-bottom: 20px; }
  .panel-left h1 { font-size: 26px; font-weight: 700; margin-bottom: 10px; }
  .panel-left p  { font-size: 14px; opacity: .85; line-height: 1.7; }
  .feature-list  { margin-top: 24px; text-align: left; display: flex; flex-direction: column; gap: 10px; }
  .feature-list li { list-style: none; font-size: 13px; display: flex; align-items: center; gap: 8px; }
  .feature-list li::before { content: '✓'; background: rgba(255,255,255,.25); width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }

  /* Panel kanan — form */
  .panel-right {
    width: 360px;
    background: #fff;
    padding: 48px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .panel-right h2 { font-size: 22px; font-weight: 700; color: #2D3748; margin-bottom: 6px; }
  .panel-right .sub { font-size: 13px; color: #718096; margin-bottom: 28px; }

  .form-group { margin-bottom: 18px; }
  .form-group label { display: block; font-size: 12px; font-weight: 600; color: #4A5568; margin-bottom: 6px; letter-spacing: .3px; }
  .form-group input {
    width: 100%;
    padding: 11px 14px;
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

  .error-msg {
    background: #FFF5F5;
    border: 1px solid #FED7D7;
    color: #C53030;
    border-radius: 10px;
    padding: 11px 14px;
    font-size: 13px;
    margin-bottom: 18px;
  }

  .btn-submit {
    width: 100%;
    padding: 13px;
    background: #474658;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s, transform .1s;
    margin-top: 4px;
  }
  .btn-submit:hover  { background: #797793; }
  .btn-submit:active { transform: scale(.98); }

  .divider { text-align: center; margin: 20px 0; color: #CBD5E0; font-size: 13px; position: relative; }
  .divider::before, .divider::after { content: ''; position: absolute; top: 50%; width: 38%; height: 1px; background: #E2E8F0; }
  .divider::before { left: 0; }
  .divider::after  { right: 0; }

  .link-register { text-align: center; font-size: 13px; color: #718096; }
  .link-register a { color: #100e30; font-weight: 600; text-decoration: none; }
  .link-register a:hover { text-decoration: underline; }

  @media (max-width: 640px) {
    .panel-left { display: none; }
    .panel-right { width: 100%; padding: 36px 28px; }
  }
</style>
</head>
<body>
<div class="container">

  <div class="panel-left">
    <div class="mascot">🦁</div>
    <h1>StudyBuddy!</h1>
    <p>Have a nice Day dear.. </p>
    <ul class="feature-list">
      <li>Semoga mam enak hari ini</li>
      <li>Semoga udah nyuci baju</li>
      <li>Ayam Geprek Salak</li>
      <li>ig @mujahanatins_</li>
    </ul>
  </div>

  <div class="panel-right">
    <h2>Halooooo!</h2>
    <p class="sub">Masi inget kaann password nya?</p>

    <?php if ($error): ?>
      <div class="error-msg">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="email">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="nama@email.com"
          value="<?= e($_POST['email'] ?? '') ?>"
          required
          autocomplete="email"
        >
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="Masukkan password"
          required
          autocomplete="current-password"
        >
      </div>
      <button type="submit" class="btn-submit">Masuk</button>
    </form>

    <div class="divider">atau</div>
    <p class="link-register">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
  </div>

</div>
</body>
</html>
