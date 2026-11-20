-- ============================================================================
--  Hardening level database (dijalankan setelah migrasi)
--
--  Audit log dibuat benar-benar APPEND-ONLY di level database:
--  baris yang sudah tercatat tidak bisa diubah atau dihapus oleh SIAPA PUN lewat
--  UPDATE/DELETE, termasuk dari aplikasi, phpMyAdmin, atau query langsung.
--  User aplikasi (gie_app) juga tidak punya hak TRIGGER/DROP, jadi tidak bisa
--  mematikan perlindungan ini.
-- ============================================================================

DROP TRIGGER IF EXISTS activity_logs_no_update;
DROP TRIGGER IF EXISTS activity_logs_no_delete;

CREATE TRIGGER activity_logs_no_update
BEFORE UPDATE ON activity_logs
FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log bersifat append-only: tidak boleh diubah';

CREATE TRIGGER activity_logs_no_delete
BEFORE DELETE ON activity_logs
FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log bersifat append-only: tidak boleh dihapus';
