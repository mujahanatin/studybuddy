<?php
define('DB_HOST', '0xxegx.h.filess.io');
define('DB_USER', 'studybuddy_anglefolks');
define('DB_PORT', '3307');
define('DB_PASS', '8b0d4c839d7807ae913759a415cd7bd6baa9c574');
define('DB_NAME', 'studybuddy_anglefolks');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PORT, DB_PASS, DB_NAME);
if (!$conn) die('Koneksi gagal: ' . mysqli_connect_error());
mysqli_set_charset($conn, 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn()  { return isset($_SESSION['user_id']); }
function requireLogin() {
    if (!isLoggedIn()) { header('Location: /studybuddy/login.php'); exit; }
}
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// Render avatar: foto kalau ada, kalau tidak inisial nama
function avatarHtml($name, $avatarPath, $size = 36) {
    $initials = strtoupper(substr($name, 0, 2));
    if ($avatarPath && file_exists(__DIR__.'/../'.$avatarPath)) {
        return '<img src="'.e($avatarPath).'" alt="'.e($name).'" style="width:'.$size.'px;height:'.$size.'px;border-radius:50%;object-fit:cover">';
    }
    $fontSize = round($size * 0.36);
    return '<div style="width:'.$size.'px;height:'.$size.'px;border-radius:50%;background:linear-gradient(135deg,#6C63FF,#9F7AEA);color:#fff;font-size:'.$fontSize.'px;font-weight:700;display:flex;align-items:center;justify-content:center">'.e($initials).'</div>';
}

function uploadNoteImage($file) {
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed)) return ['error'=>'Format tidak didukung.'];
    if ($file['size'] > 5*1024*1024) return ['error'=>'Maks 5MB.'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('note_').'.'.strtolower($ext);
    $dest = __DIR__.'/../uploads/notes/'.$filename;
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return ['error'=>'Gagal menyimpan.'];
    return ['path'=>'uploads/notes/'.$filename];
}

function uploadAvatar($file) {
    $allowed = ['image/jpeg','image/png','image/webp'];
    if (!in_array($file['type'], $allowed)) return ['error'=>'Format foto harus JPG, PNG, atau WEBP.'];
    if ($file['size'] > 3*1024*1024) return ['error'=>'Ukuran foto maksimal 3MB.'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('avatar_').'.'.strtolower($ext);
    $dest = __DIR__.'/../uploads/avatars/'.$filename;
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return ['error'=>'Gagal menyimpan foto.'];
    return ['path'=>'uploads/avatars/'.$filename];
}
