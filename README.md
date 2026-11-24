# Sistem Presensi Biometrik Berbasis Lokasi — MA Nurul Jadid
PHP native (tanpa framework) + MySQL/MariaDB. Mengikuti dokumen TPKI (KF-01 s.d. KF-14) dan wireframe.

## Instalasi (±5 menit)
1. Salin folder ini ke web server (Apache/Nginx + PHP ≥ 8.0, ekstensi `pdo_mysql`).
2. Impor database: `mysql -u root -p < database.sql`
3. Edit `config.php` (user/sandi DB). Jam kerja, toleransi, kehadiran minimal, dan mode sidik jari diatur dari menu **Pengaturan** di Web Panel (Admin).
4. Buka `http://server/presensi-ma/` (mobile, index.html) dan `.../admin/` (web panel).

**Akun contoh** (sandi semua: `password123` — segera ganti):
| NIP | Peran |
|---|---|
| 199203142018 | Admin Tata Usaha |
| 197809202005 | Kepala Madrasah |
| 198504122010 | Guru |
| 199011052015 | Tenaga Kependidikan |

**Wajib setelah instal:** login Admin → menu **Lokasi** → tekan *Lokasi saat ini* (saat berada di madrasah) → *Simpan*.
Koordinat bawaan hanyalah contoh.

## Sidik jari: dua mode (menu Pengaturan -> Mode verifikasi)
- `simulasi` — tombol "Sentuh sensor" di layar. Untuk demo/uji; **bukan** keamanan sungguhan.
- `webauthn` — sidik jari asli perangkat (Chrome Android). Server hanya memverifikasi tanda tangan
  kriptografis dari sensor (flag *user verified*); data sidik jari tidak pernah dikirim/disimpan (KNF-02).
  **Wajib HTTPS** (GPS & WebAuthn ditolak browser di HTTP biasa, kecuali `localhost`).

## Struktur
```
index.html           Halaman login mobile (HTML statis) -> api/login.php (KF-02)
m/presensi.php       Presensi masuk/pulang (KF-03/04/08)      m/riwayat.php  Riwayat pribadi (KF-08)
api/login.php        Login + pengikatan perangkat
api/index.php        Router khusus Vercel (daftar putih jalur)
api/presensi.php     Validasi server: GPS, sidik jari, Haversine, ganda, status (KF-05/06/07)
admin/pengguna.php   Pengguna & perangkat (KF-01, KF-10)      admin/lokasi.php       Lokasi (KF-09)
admin/koreksi.php    Koreksi presensi + jejak audit (KF-14)    admin/rekapitulasi.php Rekap + CSV (KF-11/12)
includes/            bootstrap, helpers (Haversine, status), auth (KF-13), webauthn, layout
```

## Aturan yang diterapkan
- Masuk ≤ 07.00 WIB = *Tepat waktu*, > 07.00 = *Terlambat* (tetap tercatat). Pulang < 15.00 = *Pulang awal*
  (jika sudah terlambat, status tetap *Terlambat*).
- Diterima hanya bila jarak (Haversine) ≤ radius (default 100 m). Pulang hanya setelah masuk. Satu catatan per hari
  (dijamin `UNIQUE` di database).
- Satu akun = satu perangkat aktif. Perangkat lain → status *menunggu* sampai Admin menyetujui.
- Rekap: hari kerja Senin–Jumat (ubah `HARI_KERJA`/`HARI_LIBUR`), hari berjalan baru dihitung setelah jam pulang.
  Kehadiran < 90% ditandai.

## Hasil uji (black box, Tabel 3.1) — dijalankan terhadap server PHP + MariaDB
Skenario 1, 2, 3, 4, 5, 6, 8, 9, 10, 11 lulus (termasuk batas 90/100/110 m dan batas 07.00.00 vs 07.00.01).
Skenario 7 (sidik jari belum terdaftar) dan jalur WebAuthn asli perlu diuji di HP Android + HTTPS;
logika verifikasinya sudah diuji dengan tanda tangan ECDSA sungguhan (valid, tanpa UV, origin salah, kunci lain, replay).

## Batasan (sesuai Bab 1.5)
Tidak ada deteksi *mock location*. Ini versi **web mobile** (PHP native), bukan aplikasi Kotlin; dapat ditambahkan ke layar
utama lewat menu browser. Pengikatan perangkat memakai ID acak di `localStorage` — menghapus data browser membuat
perangkat dianggap baru (perlu persetujuan Admin).

## Deploy ke Vercel
Lihat `PANDUAN-VERCEL.md`.

## Kesesuaian dengan jurnal (Susilo & Abdurrahman, 2023)
| Aturan di jurnal | Di sistem |
|---|---|
| Presensi semua karyawan: kepala madrasah, guru, tenaga administrasi, kebersihan, keamanan (hlm. 322) | 6 peran, termasuk Tenaga Kebersihan & Tenaga Keamanan |
| Jam masuk 06.30, pulang 15.00; toleransi 30 menit (hlm. 322-323) | `JAM_MASUK`, `JAM_PULANG`, `TOLERANSI_MNT` |
| Kehadiran minimal 90% (hlm. 323) | `MIN_KEHADIRAN` di config.php |
| Evaluasi bulanan oleh Kepala Madrasah; <90% dipanggil, panggilan ke-2 SP 1, 3x berturut-turut diberhentikan (hlm. 324) | Menu **Evaluasi Bulanan** (`admin/evaluasi.php`) |
| Tanpa surat keterangan dihitung tidak hadir (hlm. 324) | Tombol **Kecualikan** (alasan wajib) memutus hitungan berturut-turut |
| Terlambat/pulang awal tidak terekam di mesin lama (hlm. 323) | Sistem baru TETAP mencatat dengan status Terlambat/Pulang awal (perbaikan) |

Cara kerja evaluasi: pilih bulan yang sudah selesai -> **Proses evaluasi** -> sistem menandai pegawai <90% dan menghitung
bulan berturut-turut (1 = Panggilan 1, 2 = SP 1, 3+ = rekomendasi pemberhentian). Kepala Madrasah/Admin mencatat tindak lanjut.
Sistem hanya menghitung dan mencatat; **keputusan sanksi tetap di Kepala Madrasah**. Bulan tanpa data presensi sama sekali
(sistem belum dipakai) dan akun yang baru dibuat pada bulan itu tidak dihitung.

## Memperbarui database lama
Database yang sudah terlanjur diimpor: jalankan `migrasi_evaluasi.sql` sekali (data tidak hilang).

## Menu Pengaturan (khusus Admin)
Mengubah jam masuk, jam pulang, toleransi keterlambatan (batas terlambat = jam masuk + toleransi), kehadiran minimal (%),
dan mode sidik jari tanpa mengedit kode. Disimpan di tabel `pengaturan` (dibuat otomatis). Perubahan berlaku untuk presensi
berikutnya; status presensi yang sudah tercatat tidak dihitung ulang. Nilai di `config.php` hanya default awal.
