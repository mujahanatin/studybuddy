<?php
// ============================================================
//  logout.php — Keluar dari akun
//  Letakkan file ini di folder: studybuddy/
// ============================================================
require_once 'includes/config.php';

// Hapus semua data session
$_SESSION = [];
session_destroy();

// Redirect ke halaman login
header('Location: login.php');
exit;
?>
