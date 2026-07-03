-- ============================================================
--  TAMBAHAN DATABASE — Sistem Notifikasi
--  Jalankan di phpMyAdmin > database studybuddy > tab SQL
-- ============================================================

CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    type        ENUM('chat','deadline','share','friend') NOT NULL,
    title       VARCHAR(150) NOT NULL,
    body        VARCHAR(255) DEFAULT NULL,
    url         VARCHAR(255) DEFAULT NULL,  -- link tujuan saat diklik
    is_read     TINYINT(1)   DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Index supaya query cepat
CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, is_read, created_at);
