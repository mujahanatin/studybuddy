<?php
// notif_poll.php — API notifikasi (dipanggil JS setiap 15 detik)
require_once 'includes/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['error'=>'unauthorized']); exit; }

$uid = $_SESSION['user_id'];

// ── Auto-generate notifikasi deadline tugas ──
// Cek tugas yang deadlinenya ≤ 3 hari dan belum ada notifnya hari ini
$deadline_tasks = mysqli_query($conn,
    "SELECT t.id, t.title, t.deadline, c.name as course_name
     FROM tasks t
     JOIN courses c ON c.id = t.course_id
     WHERE t.user_id=$uid AND t.status='pending'
       AND t.deadline BETWEEN NOW() AND NOW() + INTERVAL 3 DAY
       AND NOT EXISTS (
           SELECT 1 FROM notifications n
           WHERE n.user_id=$uid AND n.type='deadline'
             AND n.url LIKE '%task_id=$t.id%'
             AND DATE(n.created_at) = CURDATE()
       )"
);
while ($t = mysqli_fetch_assoc($deadline_tasks)) {
    $days    = (int)((strtotime($t['deadline']) - time()) / 86400);
    $label   = $days <= 0 ? 'Hari ini!' : ($days === 1 ? 'Besok bangeett..!' : $days.' hari lagi niihhh');
    $title   = 'Deadline: '.$t['title'];
    $body    = $t['course_name'].' · '.$label.' ('.$t['deadline'].')';
    $url     = 'dashboard.php';
    $st = mysqli_prepare($conn,"INSERT INTO notifications (user_id,type,title,body,url) VALUES (?,?,?,?,?)");
    mysqli_stmt_bind_param($st,'issss',$uid,'deadline',$title,$body,$url);
    mysqli_stmt_execute($st);
}

// ── Auto-generate notifikasi chat baru ──
$new_chats = mysqli_query($conn,
    "SELECT m.id, m.content, m.sent_at, u.full_name as sender_name, u.id as sender_id
     FROM messages m
     JOIN users u ON u.id = m.sender_id
     WHERE m.receiver_id=$uid AND m.is_read=0
       AND NOT EXISTS (
           SELECT 1 FROM notifications n
           WHERE n.user_id=$uid AND n.type='chat'
             AND n.url='chat.php?with='.m.sender_id
             AND n.created_at >= m.sent_at - INTERVAL 1 MINUTE
       )
     ORDER BY m.sent_at ASC"
);

// Kelompokkan per sender
$chat_senders = [];
while ($m = mysqli_fetch_assoc($new_chats)) {
    $sid = $m['sender_id'];
    if (!isset($chat_senders[$sid])) {
        $chat_senders[$sid] = ['name'=>$m['sender_name'], 'count'=>0, 'last'=>''];
    }
    $chat_senders[$sid]['count']++;
    $chat_senders[$sid]['last'] = $m['content'];
}
foreach ($chat_senders as $sid => $info) {
    $title = ''.$info['name'];
    $body  = $info['count'] > 1
           ? $info['count'].' pesan baru: '.mb_substr($info['last'],0,60)
           : mb_substr($info['last'],0,80);
    $url   = 'chat.php?with='.$sid;
    // Cek belum ada notif chat dari sender ini dalam 5 menit terakhir
    $exists = mysqli_fetch_row(mysqli_query($conn,
        "SELECT id FROM notifications WHERE user_id=$uid AND type='chat'
         AND url='$url' AND created_at >= NOW() - INTERVAL 5 MINUTE"));
    if (!$exists) {
        $st = mysqli_prepare($conn,"INSERT INTO notifications (user_id,type,title,body,url) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($st,'issss',$uid,'chat',$title,$body,$url);
        mysqli_stmt_execute($st);
    }
}

// ── Ambil notifikasi belum dibaca (maks 10 terbaru) ──
$notifs = mysqli_fetch_all(mysqli_query($conn,
    "SELECT id, type, title, body, url, is_read, created_at
     FROM notifications
     WHERE user_id=$uid
     ORDER BY is_read ASC, created_at DESC
     LIMIT 10"), MYSQLI_ASSOC);

$unread_count = (int)mysqli_fetch_row(mysqli_query($conn,
    "SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0"))[0];

echo json_encode([
    'unread_count' => $unread_count,
    'notifications' => $notifs,
]);
