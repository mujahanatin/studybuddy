-- ============================================================
--  TAMBAHAN DATABASE — Sistem Admin
--  Jalankan di phpMyAdmin > database studybuddy > tab SQL
-- ============================================================

CREATE TABLE IF NOT EXISTS admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(100) NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login  DATETIME DEFAULT NULL
);

-- Catatan: akun admin pertama dibuat lewat file setup_admin.php
-- (jangan insert manual di sini supaya password ter-hash dengan benar)
