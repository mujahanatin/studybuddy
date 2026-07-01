<?php
// admin/includes/config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'studybuddy');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) die('Koneksi gagal: ' . mysqli_connect_error());
mysqli_set_charset($conn, 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) session_start();

function isAdminLoggedIn() { return isset($_SESSION['admin_id']); }
function requireAdminLogin() {
    if (!isAdminLoggedIn()) { header('Location: login.php'); exit; }
}
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
