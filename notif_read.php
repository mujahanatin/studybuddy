<?php
// notif_read.php — Tandai notifikasi sudah dibaca
require_once 'includes/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['ok'=>false]); exit; }
$uid = $_SESSION['user_id'];

$id  = (int)($_POST['id']  ?? 0);
$all = (int)($_POST['all'] ?? 0);

if ($all) {
    mysqli_query($conn,"UPDATE notifications SET is_read=1 WHERE user_id=$uid");
} elseif ($id) {
    mysqli_query($conn,"UPDATE notifications SET is_read=1 WHERE id=$id AND user_id=$uid");
}

echo json_encode(['ok'=>true]);
