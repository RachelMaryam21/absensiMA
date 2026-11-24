<?php
/* Pengaturan jam kerja, batas terlambat, kehadiran minimal, dan mode sidik jari (khusus Admin) */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$admin = require_role(['admin']);
$pdo = db();
$sekarang = pengaturan_semua();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $baru = [
        'jam_masuk'      => trim($_POST['jam_masuk'] ?? ''),
        'toleransi_mnt'  => trim($_POST['toleransi_mnt'] ?? ''),
        'jam_pulang'     => trim($_POST['jam_pulang'] ?? ''),
        'min_kehadiran'  => trim($_POST['min_kehadiran'] ?? ''),
        'biometrik_mode' => $_POST['biometrik_mode'] ?? '',
    ];
    $err = pengaturan_valid($baru);
    if (!$err && $baru['biometrik_mode'] === 'webauthn' && $sekarang['biometrik_mode'] !== 'webauthn') {
        $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
        if (!is_https() && !in_array($host, ['localhost', '127.0.0.1'], true)) {
            $err = 'Sidik jari asli (WebAuthn) hanya bisa diaktifkan lewat HTTPS. Buka panel ini dengan alamat https:// lalu coba lagi.';
        }
    }
    if ($err) flash('err', $err);
    else {
        $st = $pdo->prepare('REPLACE INTO pengaturan (kunci, nilai) VALUES (?, ?)');
        foreach ($baru as $k => $v) $st->execute([$k, (string)$v]);
        flash('ok', 'Pengaturan disimpan. Berlaku untuk presensi berikutnya; status presensi yang sudah tercatat tidak diubah.');
    }
    redirect('admin/pengaturan.php');
}

p_head('Pengaturan', 'pengaturan', $admin);
$v = $sekarang;
?>
<form method="post" class="card" style="max-width:640px" autocomplete="off">
  <?= csrf_field() ?>

  <h3>Jam kerja</h3>
  <div class="two">
    <div><label>Jam masuk</label><input class="input" type="time" name="jam_masuk" id="jm" value="<?= e($v['jam_masuk']) ?>" required></div>
    <div><label>Jam pulang</label><input class="input" type="time" name="jam_pulang" value="<?= e($v['jam_pulang']) ?>" required></div>
  </div>
  <label>Toleransi keterlambatan (menit)</label>
  <input class="input" type="number" name="toleransi_mnt" id="tol" min="0" max="180" value="<?= e($v['toleransi_mnt']) ?>" required>
  <div class="alert alert-warn" style="margin-bottom:0">Batas terlambat saat ini: <b id="batas">--.--</b>. Presensi masuk setelah jam ini berstatus <b>Terlambat</b>.</div>

  <h3 style="margin-top:26px">Evaluasi kehadiran</h3>
  <label>Kehadiran minimal per bulan (%)</label>
  <input class="input" type="number" name="min_kehadiran" min="1" max="100" value="<?= e($v['min_kehadiran']) ?>" required>
  <p class="muted small">Pegawai di bawah angka ini ditandai pada Rekapitulasi dan masuk Evaluasi Bulanan.</p>

  <h3 style="margin-top:26px">Sidik jari</h3>
  <label>Mode verifikasi</label>
  <select class="input" name="biometrik_mode">
    <option value="simulasi" <?= $v['biometrik_mode'] === 'simulasi' ? 'selected' : '' ?>>Simulasi (tombol di layar, untuk uji coba)</option>
    <option value="webauthn" <?= $v['biometrik_mode'] === 'webauthn' ? 'selected' : '' ?>>Sidik jari asli perangkat (WebAuthn)</option>
  </select>
  <p class="muted small">Mode asli wajib HTTPS. Setelah diaktifkan, setiap pegawai harus menekan <b>Daftarkan sidik jari</b> sekali di HP-nya (HP harus sudah memiliki sidik jari terdaftar).</p>

  <div class="btn-row"><button class="btn btn-primary">Simpan pengaturan</button></div>
</form>
<script>
(function () {
  var jm = document.getElementById('jm'), tol = document.getElementById('tol'), out = document.getElementById('batas');
  function hitung() {
    var p = (jm.value || '').split(':'); if (p.length < 2) { out.textContent = '--.--'; return; }
    var m = parseInt(p[0], 10) * 60 + parseInt(p[1], 10) + (parseInt(tol.value, 10) || 0);
    out.textContent = ('0' + Math.floor(m / 60) % 24).slice(-2) + '.' + ('0' + m % 60).slice(-2);
  }
  jm.addEventListener('input', hitung); tol.addEventListener('input', hitung); hitung();
})();
</script>
<?php p_foot(); ?>
