<?php
// admin/users.php — Kelola user: lihat, edit, hapus
require_once 'includes/config.php';
requireAdminLogin();
$active_page = 'users';
$msg = '';

// ── EDIT USER ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_user'])) {
    $target_id = (int)$_POST['user_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $new_pass  = $_POST['new_password']   ?? '';

    if (empty($full_name) || empty($username) || empty($email)) {
        $msg = 'error:Semua kolom wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'error:Format email tidak valid.';
    } else {
        // Cek username/email dipakai user lain
        $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE (username=? OR email=?) AND id != ?");
        mysqli_stmt_bind_param($chk, 'ssi', $username, $email, $target_id);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);

        if (mysqli_stmt_num_rows($chk) > 0) {
            $msg = 'error:Username atau email sudah dipakai user lain.';
        } else {
            if (!empty($new_pass)) {
                if (strlen($new_pass) < 6) {
                    $msg = 'error:Password baru minimal 6 karakter.';
                } else {
                    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                    $st = mysqli_prepare($conn, "UPDATE users SET full_name=?, username=?, email=?, password=? WHERE id=?");
                    mysqli_stmt_bind_param($st, 'ssssi', $full_name, $username, $email, $hashed, $target_id);
                    mysqli_stmt_execute($st);
                    $msg = 'success:User berhasil diperbarui (termasuk password baru).';
                }
            } else {
                $st = mysqli_prepare($conn, "UPDATE users SET full_name=?, username=?, email=? WHERE id=?");
                mysqli_stmt_bind_param($st, 'sssi', $full_name, $username, $email, $target_id);
                mysqli_stmt_execute($st);
                $msg = 'success:User berhasil diperbarui.';
            }
        }
    }
}

// ── HAPUS USER ──
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_user'])) {
    $target_id = (int)$_POST['user_id'];

    // Hapus file foto profil & gambar catatan milik user ini
    $avatar_row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT avatar FROM users WHERE id=$target_id"));
    if ($avatar_row && $avatar_row['avatar'] && file_exists(__DIR__.'/../'.$avatar_row['avatar'])) {
        unlink(__DIR__.'/../'.$avatar_row['avatar']);
    }
    $note_imgs = mysqli_query($conn,"SELECT image FROM notes WHERE user_id=$target_id AND image IS NOT NULL");
    while ($img = mysqli_fetch_assoc($note_imgs)) {
        $path = __DIR__.'/../'.$img['image'];
        if (file_exists($path)) unlink($path);
    }

    // Hapus user (CASCADE di DB akan otomatis hapus courses, notes, tasks, messages, friends, pets miliknya)
    mysqli_query($conn, "DELETE FROM users WHERE id=$target_id");
    $msg = 'success:User dan seluruh datanya berhasil dihapus.';
}

[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];

// ── PENCARIAN & PAGINASI ──
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where = '';
if ($search) {
    $esc = mysqli_real_escape_string($conn, $search);
    $where = "WHERE full_name LIKE '%$esc%' OR username LIKE '%$esc%' OR email LIKE '%$esc%'";
}

$total_rows = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users $where"))[0];
$total_pages = max(1, ceil($total_rows / $per_page));

$users_q = mysqli_query($conn,
    "SELECT u.*,
            (SELECT COUNT(*) FROM courses WHERE user_id=u.id) as course_count,
            (SELECT COUNT(*) FROM notes WHERE user_id=u.id) as note_count,
            (SELECT name FROM pets WHERE user_id=u.id) as pet_name,
            (SELECT level FROM pets WHERE user_id=u.id) as pet_level
     FROM users u $where
     ORDER BY u.created_at DESC
     LIMIT $per_page OFFSET $offset"
);
$users = mysqli_fetch_all($users_q, MYSQLI_ASSOC);

function avatarColor($str) { return '#'.substr(md5($str),0,6); }
function avatarInit($str)  { return strtoupper(substr($str,0,2)); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kelola User — Admin StudyBuddy</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="app-layout">
  <?php require_once 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <h1>👥 Kelola User</h1>
      <span style="font-size:13px;color:var(--muted)"><?= $total_rows ?> total user</span>
    </div>

    <div class="page-body">
      <?php if ($msg_text): ?>
        <div class="alert <?= $msg_type ?>"><?= e($msg_text) ?></div>
      <?php endif; ?>

      <div class="table-card">
        <div class="table-header">
          <h3>Daftar User</h3>
          <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Cari nama, username, email..." value="<?= e($search) ?>">
            <button type="submit" class="btn primary sm">Cari</button>
            <?php if ($search): ?><a href="users.php" class="btn sm">Reset</a><?php endif; ?>
          </form>
        </div>

        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Username</th>
              <th>Matkul</th>
              <th>Catatan</th>
              <th>Pet</th>
              <th>Terdaftar</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr class="empty-row"><td colspan="7">Tidak ada user ditemukan.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <div class="user-cell">
                  <?php if ($u['avatar']): ?>
                    <img src="../<?= e($u['avatar']) ?>" class="user-avatar" alt="">
                  <?php else: ?>
                    <div class="user-avatar" style="background:<?= avatarColor($u['username']) ?>"><?= avatarInit($u['full_name']) ?></div>
                  <?php endif; ?>
                  <div>
                    <div class="user-name"><?= e($u['full_name']) ?></div>
                    <div class="user-email"><?= e($u['email']) ?></div>
                  </div>
                </div>
              </td>
              <td>@<?= e($u['username']) ?></td>
              <td><span class="badge purple"><?= $u['course_count'] ?></span></td>
              <td><span class="badge green"><?= $u['note_count'] ?></span></td>
              <td>
                <?php if ($u['pet_name']): ?>
                  <span class="badge gray">🐾 <?= e($u['pet_name']) ?> (Lv<?= $u['pet_level'] ?>)</span>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:11px">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:11px;color:var(--muted)"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div class="action-btns">
                  <button class="btn sm" onclick='openEditModal(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️ Edit</button>
                  <form method="POST" onsubmit="return confirm('Hapus user \'<?= e(addslashes($u['full_name'])) ?>\'?\n\nSemua data (matkul, catatan, tugas, chat, pertemanan, pet) milik user ini akan dihapus PERMANEN.\n\nTindakan ini tidak bisa dibatalkan!')">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" name="delete_user" class="btn sm danger">🗑 Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
          <?php for ($p=1; $p<=$total_pages; $p++): ?>
            <?php if ($p == $page): ?>
              <span class="current"><?= $p ?></span>
            <?php else: ?>
              <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal edit user -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <h3>✏️ Edit User</h3>
    <form method="POST">
      <input type="hidden" name="user_id" id="edit-id">
      <div class="form-group">
        <label>Nama Lengkap</label>
        <input type="text" name="full_name" id="edit-full-name" required>
      </div>
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" id="edit-username" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" id="edit-email" required>
      </div>
      <div class="form-group">
        <label>Password Baru <span style="font-weight:400;color:var(--muted)">(kosongkan jika tidak diubah)</span></label>
        <input type="password" name="new_password" placeholder="Min. 6 karakter">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeEditModal()">Batal</button>
        <button type="submit" name="edit_user" class="btn primary">💾 Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(user) {
  document.getElementById('edit-id').value = user.id;
  document.getElementById('edit-full-name').value = user.full_name;
  document.getElementById('edit-username').value = user.username;
  document.getElementById('edit-email').value = user.email;
  document.getElementById('modal-edit').classList.add('open');
}
function closeEditModal() {
  document.getElementById('modal-edit').classList.remove('open');
}
document.getElementById('modal-edit').addEventListener('click', e => {
  if (e.target === document.getElementById('modal-edit')) closeEditModal();
});
</script>
</body>
</html>
