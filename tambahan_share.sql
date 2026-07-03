-- ============================================================
--  TAMBAHAN DATABASE — Kirim Catatan & Tugas ke Teman
--  Jalankan di phpMyAdmin > database studybuddy > tab SQL
-- ============================================================

CREATE TABLE IF NOT EXISTS shared_notes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    note_id     INT NOT NULL,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    message     VARCHAR(255) DEFAULT NULL,  -- pesan opsional saat kirim
    is_read     TINYINT(1)   DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id)     REFERENCES notes(id)  ON DELETE CASCADE,
    FOREIGN KEY (sender_id)   REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id)  ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS shared_tasks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    task_id     INT NOT NULL,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    message     VARCHAR(255) DEFAULT NULL,
    is_read     TINYINT(1)   DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id)     REFERENCES tasks(id)  ON DELETE CASCADE,
    FOREIGN KEY (sender_id)   REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id)  ON DELETE CASCADE
);
