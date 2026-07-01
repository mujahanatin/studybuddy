-- ============================================================
--  TAMBAHAN DATABASE — Avatar profil
--  Jalankan di phpMyAdmin > database studybuddy > tab SQL
-- ============================================================

-- Kolom avatar sudah ada di tabel users sejak awal,
-- tapi jalankan ini untuk memastikan kolomnya ada:
ALTER TABLE users 
  ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL;
