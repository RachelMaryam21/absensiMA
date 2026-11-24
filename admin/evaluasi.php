<?php
/**
 * Evaluasi bulanan kehadiran (jurnal hlm. 323-324):
 * pegawai berkehadiran < 90% dipanggil Kepala Madrasah -> Panggilan 1 -> SP 1 -> rekomendasi pemberhentian
 * bila terjadi berturut-turut. Sistem menghitung & mencatat; keputusan akhir tetap di Kepala Madrasah.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$u = require_role(['admin', 'kepala']);
$pdo = db();

// Hanya bulan yang sudah selesai yang dievaluasi
$opsi = [];
for ($i = 1; $i <= 12; $i++) { $k = bulan_sebelum(date('Y-m'), $i); $opsi[$k] = nama_bulan_tahun($k); }
$bulan = $_GET['bulan'] ?? ($_POST['bulan'] ?? array_key_first($opsi));
if (!isset($opsi[$bulan])) $bulan = array_key_first($opsi);
$back = 'admin/evaluasi.php?bulan=' . $bulan;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id_evaluasi'] ?? 0);

    if ($aksi === 'proses') {
        $n = proses_evaluasi($bulan);
        flash('ok', $n ? "Evaluasi " . nama_bulan_tahun($bulan) . " diproses: $n pegawai di bawah " . MIN_KEHADIRAN . '%.' : 'Evaluasi diproses: tidak ada pegawai di bawah ' . MIN_KEHADIRAN . '%.');
    } elseif ($aksi === 'tindak') {
        $tgl = $_POST['tanggal'] ?? ''; $cat = trim($_POST['catatan'] ?? '');
        $dt = DateTime::createFromFormat('Y-m-d', $tgl);
        if (!$dt || $dt->format('Y-m-d') !== $tgl) flash('err', 'Tanggal tindak lanjut tidak valid.');
        elseif (mb_strlen($cat) > 255) flash('err', 'Catatan maksimal 255 karakter.');
        else {
            $pdo->prepare("UPDATE evaluasi_bulanan SET status_tindak='sudah', tanggal_tindak=?, catatan=?, dicatat_oleh=? WHERE id_evaluasi=? AND dikecualikan=0")->execute([$tgl, $cat, $u['id'], $id]);
            flash('ok', 'Tindak lanjut dicatat.');
        }
    } elseif ($aksi === 'kecualikan') {
        $alasan = trim($_POST['alasan'] ?? '');
        if ($alasan === '' || mb_strlen($alasan) > 255) flash('err', 'Alasan pengecualian wajib diisi (maks. 255 karakter), mis. ada surat keterangan sakit/izin.');
        else {
            $pdo->prepare('UPDATE evaluasi_bulanan SET dikecualikan=1, catatan=?, dicatat_oleh=? WHERE id_evaluasi=?')->execute([$alasan, $u['id'], $id]);
            flash('ok', 'Bulan ini dikecualikan dari hitungan berturut-turut.');
        }
    } elseif ($aksi === 'batal_kecuali') {
        $pdo->prepare('UPDATE evaluasi_bulanan SET dikecualikan=0, catatan=NULL, dicatat_oleh=? WHERE id_evaluasi=?')->execute([$u['id'], $id]);
        flash('ok', 'Pengecualian dibatalkan. Proses ulang evaluasi bulan-bulan berikutnya agar hitungan diperbarui.');
    }
    redirect($back);
}

$st = $pdo->prepare('SELECT e.*, p.nama, p.nip, p.peran, d.nama AS pencatat FROM evaluasi_bulanan e
                     JOIN pengguna p ON p.id_pengguna = e.id_pengguna LEFT JOIN pengguna d ON d.id_pengguna = e.dicatat_oleh
                     WHERE e.bulan = ? ORDER BY e.berturut DESC, e.persen, p.nama');
$st->execute([$bulan]);
$rows = $st->fetchAll();

p_head('Evaluasi Bulanan', 'evaluasi', $u);
?>
<form method="get" class="filters">
  <div><label>Bulan evaluasi</label><select class="input" name="bulan" onchange="this.form.submit()">
    <?php foreach ($opsi as $k => $v): ?><option value="<?= e($k) ?>" <?= $k === $bulan ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select></div>
  <noscript><button class="btn btn-primary">Tampilkan</button></noscript>
</form>

<div class="steps">
  <div class="step"><b>1</b><div><strong>Panggilan 1</strong><span>Kehadiran &lt; <?= MIN_KEHADIRAN ?>% pertama kali: dipanggil Kepala Madrasah.</span></div></div>
  <div class="step"><b>2</b><div><strong>Surat Peringatan 1</strong><span>Berturut-turut bulan ke-2.</span></div></div>
  <div class="step s3"><b>3</b><div><strong>Rekomendasi pemberhentian</strong><span>Berturut-turut 3 bulan. Keputusan tetap di Kepala Madrasah.</span></div></div>
</div>

<div class="card" style="margin-bottom:18px;display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap">
  <div>
    <b>Evaluasi <?= e(nama_bulan_tahun($bulan)) ?></b>
    <div class="muted small"><?= $rows ? count($rows) . ' pegawai berkehadiran di bawah ' . MIN_KEHADIRAN . '%.' : 'Belum ada hasil. Tekan "Proses evaluasi" untuk menghitung dari data presensi.' ?></div>
  </div>
  <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="bulan" value="<?= e($bulan) ?>">
    <button class="btn btn-primary" name="aksi" value="proses"><?= icon('shield', 18) ?> <?= $rows ? 'Proses ulang' : 'Proses evaluasi' ?></button></form>
</div>

<div class="table-wrap">
<table class="tbl">
  <thead><tr><th>Pegawai</th><th>Kehadiran</th><th>Berturut-turut</th><th>Tingkat</th><th>Status</th><th>Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['dikecualikan'] ? 'nonaktif' : '' ?>">
      <td><div class="usr"><span class="av av-<?= crc32($r['nip']) % 5 ?>"><?= e(mb_strtoupper(mb_substr($r['nama'], 0, 1))) ?></span>
        <div><b><?= e($r['nama']) ?></b><div class="muted small"><?= e(NAMA_PERAN[$r['peran']] ?? $r['peran']) ?></div></div></div></td>
      <td><b><?= (int)round($r['persen']) ?>%</b><div class="muted small"><?= (int)$r['hadir'] ?> dari <?= (int)$r['hari_kerja'] ?> hari</div></td>
      <td><?= (int)$r['berturut'] ?> bulan</td>
      <td><?= $r['dikecualikan'] ? '<span class="badge badge-muted">Dikecualikan</span>' : badge_tingkat($r['tingkat']) ?></td>
      <td>
        <?php if ($r['dikecualikan']): ?><span class="small">Alasan: <?= e($r['catatan']) ?></span>
        <?php elseif ($r['status_tindak'] === 'sudah'): ?><span class="badge badge-ok">Sudah <?= e(fmt_tgl($r['tanggal_tindak'])) ?></span><?php if ($r['catatan']): ?><div class="muted small"><?= e($r['catatan']) ?></div><?php endif; ?>
        <?php else: ?><span class="badge badge-warn">Belum</span><?php endif; ?>
      </td>
      <td class="aksi">
        <?php if ($r['dikecualikan']): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="bulan" value="<?= e($bulan) ?>"><input type="hidden" name="id_evaluasi" value="<?= (int)$r['id_evaluasi'] ?>">
            <button class="btn btn-sm btn-line" name="aksi" value="batal_kecuali">Batalkan</button></form>
        <?php else: ?>
          <button class="btn btn-sm btn-primary btn-tindak" data-id="<?= (int)$r['id_evaluasi'] ?>" data-nama="<?= e($r['nama']) ?>" data-tingkat="<?= e(NAMA_TINGKAT[$r['tingkat']]) ?>">Tindak lanjut</button>
          <button class="btn btn-sm btn-line btn-kecuali" data-id="<?= (int)$r['id_evaluasi'] ?>" data-nama="<?= e($r['nama']) ?>">Kecualikan</button>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted center" style="padding:28px">Tidak ada data evaluasi untuk bulan ini.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<p class="muted small">Pegawai yang akunnya dibuat pada bulan evaluasi tidak dihitung. "Kecualikan" dipakai bila ketidakhadiran memiliki surat keterangan yang diterima; bulan itu memutus hitungan berturut-turut.</p>

<dialog id="dTindak" class="modal"><form method="post">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="tindak"><input type="hidden" name="bulan" value="<?= e($bulan) ?>"><input type="hidden" name="id_evaluasi" id="t_id">
  <h3>Catat tindak lanjut</h3><p class="muted" id="t_sub"></p>
  <label>Tanggal dilakukan</label><input class="input" type="date" name="tanggal" id="t_tgl" max="<?= date('Y-m-d') ?>" required>
  <label>Catatan (opsional)</label><textarea class="input" name="catatan" rows="3" maxlength="255" placeholder="Mis. dipanggil Kepala Madrasah, SP 1 diterbitkan no. …"></textarea>
  <div class="btn-row end"><button type="button" class="btn btn-line" data-tutup>Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></dialog>

<dialog id="dKecuali" class="modal"><form method="post">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="kecualikan"><input type="hidden" name="bulan" value="<?= e($bulan) ?>"><input type="hidden" name="id_evaluasi" id="k_id">
  <h3>Kecualikan bulan ini</h3><p class="muted" id="k_sub"></p>
  <label>Alasan (wajib)</label><textarea class="input" name="alasan" rows="3" maxlength="255" required placeholder="Mis. sakit dengan surat dokter, tugas dinas…"></textarea>
  <div class="btn-row end"><button type="button" class="btn btn-line" data-tutup>Batal</button><button class="btn btn-primary">Kecualikan</button></div>
</form></dialog>
<script>
(function () {
  var $ = function (i) { return document.getElementById(i); };
  document.querySelectorAll('.btn-tindak').forEach(function (b) { b.addEventListener('click', function () {
    $('t_id').value = b.dataset.id; $('t_sub').textContent = b.dataset.nama + ' · ' + b.dataset.tingkat; $('t_tgl').value = '<?= date('Y-m-d') ?>'; $('dTindak').showModal(); }); });
  document.querySelectorAll('.btn-kecuali').forEach(function (b) { b.addEventListener('click', function () {
    $('k_id').value = b.dataset.id; $('k_sub').textContent = b.dataset.nama; $('dKecuali').showModal(); }); });
  document.querySelectorAll('[data-tutup]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });
})();
</script>
<?php p_foot(); ?>
