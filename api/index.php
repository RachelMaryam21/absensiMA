<?php
/**
 * Router untuk Vercel (runtime vercel-php menjalankan PHP built-in server dengan file ini sebagai router).
 * SEMUA permintaan lewat sini dan hanya jalur dalam daftar putih yang boleh diakses,
 * sehingga config.php, includes/, *.sql, dll tidak pernah terbuka ke publik.
 * Di hosting biasa (XAMPP/Apache) file ini tidak dipakai.
 */
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (strpos($path, "\0") !== false || strpos($path, '..') !== false || strpos($path, '//') !== false) { http_response_code(400); exit('Bad request'); }

if ($path === '/' || $path === '/index.html') {                       // halaman login (HTML statis)
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    readfile($root . '/index.html');
    return true;
}
if (rtrim($path, '/') === '/admin') { require $root . '/admin/index.php'; return true; }

$php  = preg_match('#^/(m|admin)/[a-z_]+\.php$#', $path) || preg_match('#^/api/(presensi|login)\.php$#', $path);
$aset = preg_match('#^/assets/(css|js)/[\w.\-]+\.(css|js)$#', $path);
if ($php || $aset) {
    if (is_file($root . $path)) return false;                          // layani file apa adanya (PHP dijalankan)
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Halaman tidak ditemukan.';
return true;
