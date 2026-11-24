<?php
/**
 * Konfigurasi Sistem Presensi MA Nurul Jadid
 * Sesuaikan nilai di bawah ini dengan server Anda.
 */

// --- Database MySQL / MariaDB ---
define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306');
define('DB_NAME', getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'presensi_ma');
define('DB_USER', getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
// Database online (Vercel, dll) biasanya wajib TLS: set DB_SSL=1 (opsional DB_SSL_CA=/path/ca.pem)
define('DB_SSL', getenv('DB_SSL') === '1');
define('DB_SSL_CA', getenv('DB_SSL_CA') ?: '');

// --- Pengaturan awal ---
// Nilai ini hanya DEFAULT. Setelah aplikasi berjalan, ubah lewat menu "Pengaturan" di Web Panel (khusus Admin);
// nilai yang disimpan di database akan menggantikan nilai di bawah.
define('PENGATURAN_AWAL', [
    'jam_masuk'      => '06:30',   // jam kedatangan
    'toleransi_mnt'  => 30,        // toleransi keterlambatan (menit) -> batas terlambat = jam masuk + toleransi
    'jam_pulang'     => '15:00',   // pulang sebelum jam ini = "pulang awal"
    'min_kehadiran'  => 90,        // kehadiran minimal per bulan (%); di bawah ini masuk evaluasi bulanan
    // 'simulasi' = tombol simulasi (demo, tanpa HTTPS) | 'webauthn' = sidik jari asli perangkat (WAJIB HTTPS)
    'biometrik_mode' => getenv('BIOMETRIK_MODE') ?: 'simulasi',
]);
define('HARI_KERJA', [1, 2, 3, 4, 5]); // 1=Senin ... 7=Minggu
// Tanggal libur (format Y-m-d) yang tidak dihitung sebagai hari kerja di rekapitulasi
define('HARI_LIBUR', []);              // contoh: ['2026-11-20']

// --- Umum ---
define('APP_NAME',  'Presensi MA');
define('APP_SCHOOL','MA Nurul Jadid');
define('APP_TZ',    'Asia/Jakarta');   // WIB
define('MIN_PASSWORD', 8);

// Kosongkan agar terdeteksi otomatis. Isi jika aplikasi berada di balik proxy,
// contoh: 'https://presensi.sekolah.sch.id'
define('APP_ORIGIN', getenv('APP_ORIGIN') ?: '');

// Path URL aplikasi (otomatis). Contoh hasil: '' atau '/presensi-ma'
$__docroot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''), '/');
$__root    = str_replace('\\', '/', __DIR__);
$__auto = ($__docroot !== '' && strpos($__root, $__docroot) === 0) ? substr($__root, strlen($__docroot)) : '';
define('BASE_URL', getenv('APP_BASE_URL') !== false ? rtrim(getenv('APP_BASE_URL'), '/') : $__auto);
