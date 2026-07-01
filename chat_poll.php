<?php
// chat_poll.php — cek pesan baru (dipanggil via JS setiap 5 detik)
require_once 'includes/config.php';
requireLogin();
header('Content-Type: application/json');

$uid     = $_SESSION['user_id'];
$with_id = (int)($_GET['with'] ?? 0);
$last_id = (int)($_GET['last'] ?? 0);

if (!$with_id) { echo json_encode(['new_messages'=>[]]); exit; }

$q = mysqli_query($conn,
    "SELECT id, sender_id, content, sent_at FROM messages
     WHERE id > $last_id
       AND ((sender_id=$with_id AND receiver_id=$uid) OR (sender_id=$uid AND receiver_id=$with_id))
     ORDER BY sent_at ASC"
);
$new = mysqli_fetch_all($q, MYSQLI_ASSOC);
echo json_encode(['new_messages' => $new]);
