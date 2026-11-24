<?php
/**
 * Endpoint login mobile (dipanggil dari index.html lewat fetch JSON) - KF-02.
 * GET  : cek apakah sesi masih aktif.
 * POST : {nip, sandi, device} -> ikat akun ke perangkat / ajukan pergantian perangkat.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ok = user_fresh() && perangkat_sesi();
    json_out(['ok' => true, 'sudah_login' => (bool)$ok, 'redirect' => url('m/presensi.php')]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'pesan' => 'Metode tidak diizinkan.'], 405);

/* Perlindungan CSRF untuk form HTML statis: wajib JSON + asal permintaan sama dengan situs ini. */
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) json_out(['ok' => false, 'pesan' => 'Permintaan tidak valid.'], 415);
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
if (!empty($_SERVER['HTTP_ORIGIN']) && parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) . (parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_PORT) ? ':' . parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_PORT) : '') !== $host) {
    json_out(['ok' => false, 'pesan' => 'Asal permintaan tidak diizinkan.'], 403);
}

$in  = json_decode(file_get_contents('php://input'), true) ?: [];
$nip = trim((string)($in['nip'] ?? ''));
$pw  = (string)($in['sandi'] ?? '');
$dev = strtolower(trim((string)($in['device'] ?? '')));

$gagal = fn(string $pesan, string $tipe = 'err') => json_out(['ok' => false, 'tipe' => $tipe, 'pesan' => $pesan]);

$_SESSION['gagal'] = $_SESSION['gagal'] ?? ['n' => 0, 't' => time()];
if ($_SESSION['gagal']['n'] >= 5 && time() - $_SESSION['gagal']['t'] < 60) $gagal('Terlalu banyak percobaan. Tunggu 1 menit lalu coba lagi.');
if (!preg_match('/^[a-f0-9\-]{32,64}$/', $dev)) $gagal('Identitas perangkat tidak terbaca. Aktifkan JavaScript lalu muat ulang halaman.');

$pdo = db();
$st = $pdo->prepare('SELECT * FROM pengguna WHERE nip = ?');
$st->execute([$nip]);
$u = $st->fetch();

if (!$u || !$u['aktif'] || !password_verify($pw, $u['kata_sandi_hash'])) {
    $_SESSION['gagal']['n']++; $_SESSION['gagal']['t'] = time();
    $gagal('NIP atau kata sandi salah.');
}
$_SESSION['gagal'] = ['n' => 0, 't' => time()];

$st = $pdo->prepare('SELECT * FROM perangkat WHERE id_pengguna = ? AND identitas_perangkat = ? ORDER BY id_perangkat DESC LIMIT 1');
$st->execute([$u['id_pengguna'], $dev]);
$p = $st->fetch();
$st = $pdo->prepare("SELECT COUNT(*) FROM perangkat WHERE id_pengguna = ? AND status_persetujuan = 'aktif'");
$st->execute([$u['id_pengguna']]);
$punyaAktif = (int)$st->fetchColumn() > 0;
$nama = nama_perangkat_dari_ua($_SERVER['HTTP_USER_AGENT'] ?? '');
$masuk = function () use ($u, $dev) { login_session($u, $dev); json_out(['ok' => true, 'redirect' => url('m/presensi.php')]); };

if ($p && $p['status_persetujuan'] === 'aktif') $masuk();                      // perangkat terikat

if (!$punyaAktif) {                                                            // login pertama / setelah direset Admin
    if ($p) $pdo->prepare("UPDATE perangkat SET status_persetujuan='aktif', credential_id=NULL, public_key=NULL, nama_perangkat=? WHERE id_perangkat=?")->execute([$nama, $p['id_perangkat']]);
    else    $pdo->prepare("INSERT INTO perangkat (id_pengguna, identitas_perangkat, nama_perangkat, status_persetujuan) VALUES (?,?,?, 'aktif')")->execute([$u['id_pengguna'], $dev, $nama]);
    $masuk();
}
if ($p && $p['status_persetujuan'] === 'menunggu') $gagal('Permintaan pergantian perangkat masih menunggu persetujuan Admin.', 'warn');
if ($p && $p['status_persetujuan'] === 'ditolak')  $gagal('Pergantian ke perangkat ini ditolak Admin. Hubungi Admin Tata Usaha.');

$pdo->prepare("INSERT INTO perangkat (id_pengguna, identitas_perangkat, nama_perangkat, status_persetujuan) VALUES (?,?,?, 'menunggu')")->execute([$u['id_pengguna'], $dev, $nama]);
$gagal('Akun ini terikat pada perangkat lain. Permintaan pergantian perangkat telah dikirim ke Admin; presensi dapat dilakukan setelah disetujui.', 'warn');
