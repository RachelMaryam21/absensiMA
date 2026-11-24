-- MIGRASI: tambah peran Tenaga Kebersihan/Keamanan + tabel Evaluasi Bulanan
-- Jalankan SEKALI pada database yang sudah terlanjur diimpor (tanpa menghapus data).
ALTER TABLE pengguna MODIFY peran ENUM('guru','tendik','kebersihan','keamanan','admin','kepala') NOT NULL;

-- Evaluasi bulanan & tindak lanjut kehadiran < 90% (jurnal hlm. 323-324)
CREATE TABLE IF NOT EXISTS evaluasi_bulanan (
  id_evaluasi    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pengguna    INT UNSIGNED NOT NULL,
  bulan          CHAR(7)      NOT NULL,                    -- 'YYYY-MM'
  hadir          SMALLINT UNSIGNED NOT NULL,
  hari_kerja     SMALLINT UNSIGNED NOT NULL,
  persen         DECIMAL(5,2) NOT NULL,
  berturut       TINYINT UNSIGNED NOT NULL DEFAULT 1,      -- berapa bulan berturut-turut < 90%
  tingkat        ENUM('panggilan_1','sp_1','rekom_berhenti') NOT NULL,
  status_tindak  ENUM('belum','sudah') NOT NULL DEFAULT 'belum',
  tanggal_tindak DATE NULL,
  catatan        VARCHAR(255) NULL,
  dikecualikan   TINYINT(1)   NOT NULL DEFAULT 0,          -- 1 = ada surat keterangan, memutus hitungan
  dicatat_oleh   INT UNSIGNED NULL,
  dibuat_pada    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_eval (id_pengguna, bulan),
  CONSTRAINT fk_eval_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE CASCADE,
  CONSTRAINT fk_eval_pencatat FOREIGN KEY (dicatat_oleh) REFERENCES pengguna(id_pengguna) ON DELETE SET NULL
) ENGINE=InnoDB;
