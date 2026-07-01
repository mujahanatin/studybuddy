<?php
// admin/login.php
require_once 'includes/config.php';

if (isAdminLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, full_name, password FROM admins WHERE username = ?");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];

            $upd = mysqli_prepare($conn, "UPDATE admins SET last_login=NOW() WHERE id=?");
            mysqli_stmt_bind_param($upd, 'i', $admin['id']);
            mysqli_stmt_execute($upd);

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin — StudyBuddy</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:linear-gradient(145deg,#1A1A2E,#2D2D54);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#fff;border-radius:18px;padding:40px;max-width:380px;width:100%;box-shadow:0 25px 70px rgba(0,0,0,.3)}
.icon{text-align:center;font-size:44px;margin-bottom:10px}
h2{font-size:20px;color:#1A1A2E;text-align:center;margin-bottom:4px}
p.sub{font-size:13px;color:#718096;text-align:center;margin-bottom:28px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:12px;font-weight:600;color:#4A5568;margin-bottom:6px}
.form-group input{width:100%;padding:11px 14px;border:1.5px solid #E2E8F0;border-radius:10px;font-size:14px;outline:none;transition:border-color .2s}
.form-group input:focus{border-color:#6C63FF}
.btn{width:100%;padding:13px;background:#1A1A2E;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:background .2s}
.btn:hover{background:#2D2D54}
.error-msg{background:#FFF5F5;border:1px solid #FED7D7;color:#C53030;border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:18px}
.back-link{text-align:center;margin-top:20px;font-size:12px}
.back-link a{color:#6C63FF;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="box">
  <div class="icon">🛡️</div>
  <h2>Admin Panel</h2>
  <p class="sub">StudyBuddy — Khusus Administrator</p>

  <?php if ($error): ?>
    <div class="error-msg">⚠️ <?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" placeholder="Username admin" required autofocus>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Password" required>
    </div>
    <button type="submit" class="btn">Masuk sebagai Admin</button>
  </form>

  <div class="back-link"><a href="../login.php">← Kembali ke login user</a></div>
</div>
</body>
</html>
