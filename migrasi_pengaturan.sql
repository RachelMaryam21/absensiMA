-- MIGRASI: tabel Pengaturan (opsional; tabel juga dibuat otomatis saat aplikasi pertama kali dibuka)
CREATE TABLE IF NOT EXISTS pengaturan (
  kunci VARCHAR(50)  NOT NULL PRIMARY KEY,
  nilai VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

