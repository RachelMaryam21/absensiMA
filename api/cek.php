<?php
/**
 * Halaman diagnosa: memeriksa PHP, ekstensi, variabel lingkungan, dan koneksi database.
 * Tidak menampilkan sandi. HAPUS file ini (dan barisnya di api/index.php) setelah sistem berjalan normal.
 */
define('SKIP_SESSION', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$baris = [];
$tambah = function (string $nama, bool $ok, string $ket = '') use (&$baris) { $baris[] = [$nama, $ok, $ket]; };

$tambah('Versi PHP', version_compare(PHP_VERSION, '8.0', '>='), PHP_VERSION);
foreach (['pdo_mysql', 'openssl', 'json', 'mbstring'] as $x) {
    $tambah("Ekstensi $x", extension_loaded($x) || $x === 'mbstring', extension_loaded($x) ? 'aktif' : ($x === 'mbstring' ? 'tidak aktif (aman, sudah ada pengganti)' : 'TIDAK AKTIF'));
}
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $v) {
    $ada = getenv($v) !== false && getenv($v) !== '';
    $tambah("Variabel $v", $ada || $v === 'DB_PASS', $ada ? 'terisi' : 'KOSONG (memakai nilai bawaan lokal)');
}
$tambah('DB_SSL', true, DB_SSL ? 'aktif (1)' : 'mati (isi 1 untuk database online)');
$tambah('BIOMETRIK_MODE', true, BIOMETRIK_MODE);
$tambah('APP_ORIGIN', BIOMETRIK_MODE !== 'webauthn' || APP_ORIGIN !== '', APP_ORIGIN !== '' ? APP_ORIGIN : 'kosong');

try {
    $t0 = microtime(true);
    $pdo = db();
    $tambah('Koneksi database', true, 'berhasil (' . round((microtime(true) - $t0) * 1000) . ' ms)');
    foreach (['pengguna', 'perangkat', 'lokasi', 'presensi', 'koreksi_presensi'] as $tb) {
        try { $n = (int)$pdo->query("SELECT COUNT(*) FROM `$tb`")->fetchColumn(); $tambah("Tabel $tb", true, "$n baris"); }
        catch (Throwable $e) { $tambah("Tabel $tb", false, 'tidak ada: jalankan database_online.sql'); }
    }
    try { $n = (int)$pdo->query("SELECT COUNT(*) FROM pengguna WHERE aktif = 1")->fetchColumn(); $tambah('Pengguna aktif', $n > 0, "$n akun"); } catch (Throwable $e) {}
} catch (Throwable $e) {
    $m = str_replace([DB_HOST, DB_USER], ['[host]', '[user]'], $e->getMessage());
    $tambah('Koneksi database', false, __pesan_galat($e) . ' — Rincian: ' . $m);
}
$semua = !in_array(false, array_column($baris, 1), true);
?><!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnosa</title>
<body style="font-family:system-ui,sans-serif;max-width:720px;margin:24px auto;padding:0 16px">
<h2>Diagnosa Presensi MA</h2>
<p style="padding:10px 14px;border-radius:8px;background:<?= $semua ? '#e6f6ec' : '#fdeaea' ?>"><b><?= $semua ? 'Semua pemeriksaan lolos.' : 'Ada yang perlu diperbaiki (baris merah).' ?></b></p>
<table style="border-collapse:collapse;width:100%">
<?php foreach ($baris as [$n, $ok, $k]): ?>
<tr style="border-bottom:1px solid #ddd"><td style="padding:8px"><?= $ok ? '✅' : '❌' ?></td><td style="padding:8px"><b><?= e($n) ?></b></td><td style="padding:8px;color:<?= $ok ? '#333' : '#b00020' ?>"><?= e($k) ?></td></tr>
<?php endforeach; ?>
</table>
<p style="color:#666;font-size:14px">Hapus file <code>api/cek.php</code> setelah selesai.</p>
</body>
