# Tutorial Deploy ke Vercel (dari nol)

Gambaran: **Vercel** menjalankan kode PHP-nya, **TiDB Cloud** (MySQL online) menyimpan datanya, **GitHub** jadi tempat menaruh kode.
Waktu sekitar 30–45 menit untuk pertama kali.

## A. Buat 3 akun gratis
1. github.com  2. vercel.com (daftar pakai akun GitHub)  3. tidbcloud.com

## B. Siapkan database (TiDB Cloud)
**B1. Buat instance.** Login tidbcloud.com → halaman **My TiDB** → buat instance **Starter**, pilih **MySQL-compatible** (bukan PostgreSQL), paket gratis. Pilih penyedia/wilayah **AWS** (fitur SQL Editor hanya ada di Starter AWS).

**B2. Ambil data koneksi.** Klik nama instance → di kanan atas klik **Connect** → buat password (**Generate Password**, hanya tampil sekali, langsung salin). Catat: Host, Port `4000`, Username (berakhiran `.root`), Password, Database `test`.

**B3. Masukkan data (pilih SALAH SATU):**
- **Cara 1 – SQL Editor (di dalam TiDB Cloud):** klik nama instance → menu **SQL Editor** di panel kiri. Tempel seluruh isi **`database_tidb.sql`**, tekan **Ctrl+A** (pilih semua), lalu klik **Run**.
- **Cara 2 – DBeaver (jika menu SQL Editor tidak ada):** unduh DBeaver Community (gratis) → *New Database Connection* → **MySQL** → isi Host, Port 4000, Database `test`, Username, Password → tab **SSL** centang **Use SSL** → Test Connection → Finish. Klik kanan koneksi → *SQL Editor → Open SQL script*, tempel isi `database_tidb.sql`, tekan **Alt+X** (Execute Script).

Berhasil jika tabel `pengguna`, `perangkat`, `lokasi`, `presensi`, `koreksi_presensi`, `sesi` muncul.

**B4.** Jika ada pengaturan IP access list, izinkan semua alamat (IP Vercel berubah-ubah).

## C. Taruh kode di GitHub
1. Ekstrak `presensi-ma.zip`.
2. GitHub → **New repository** (Private) → beri nama `presensi-ma` → Create.
3. Klik **uploading an existing file**, lalu seret **ISI** folder `presensi-ma` (bukan foldernya).
   Pastikan di halaman repositori terlihat langsung: `index.html`, `vercel.json`, `api/`, `m/`, `admin/`, `includes/`, `assets/`.
   (Kalau yang terlihat hanya satu folder `presensi-ma`, berarti salah tingkat: ulangi.)
4. **Commit changes**.

## D. Deploy di Vercel
1. vercel.com → **Add New… → Project** → pilih repositori `presensi-ma` → **Import**.
2. **Framework Preset: Other.** Build Command dan Output Directory dibiarkan kosong.
3. Buka **Environment Variables**, tambahkan satu per satu:

| Name | Value |
|---|---|
| `DB_HOST` | host dari TiDB |
| `DB_PORT` | `4000` |
| `DB_NAME` | `test` |
| `DB_USER` | username dari TiDB |
| `DB_PASS` | password dari TiDB |
| `DB_SSL` | `1` |
| `BIOMETRIK_MODE` | `simulasi` (nilai awal; nanti diubah lewat menu Pengaturan) |

4. Klik **Deploy**. Tunggu selesai, lalu klik **Visit**. Alamatnya mirip `https://presensi-ma-xxxx.vercel.app`.

## E. Pemeriksaan pertama
1. Buka `https://alamat-anda.vercel.app/admin/login.php` → NIP `199203142018`, sandi `password123`.
2. **Ganti semua sandi contoh**: menu Pengguna & Perangkat → Ubah.
3. Menu **Lokasi** → klik titik di peta (atau isi koordinat) → atur radius → Simpan.
4. Di HP buka alamat utama `https://alamat-anda.vercel.app/` → login pegawai → coba presensi (izinkan lokasi).

## F. Mengaktifkan sidik jari asli (opsional)
1. Login web panel sebagai Admin → menu **Pengaturan** → **Mode verifikasi: Sidik jari asli perangkat** → Simpan.
   (Hanya bisa jika panel dibuka lewat HTTPS, yang otomatis di Vercel. Tidak perlu Redeploy.)
2. Tiap pegawai: buka situs di Chrome Android → login → tekan **Daftarkan sidik jari** (HP harus sudah punya sidik jari terdaftar).

## G. Memperbarui aplikasi
Ubah file di GitHub (atau unggah ulang) → Vercel otomatis deploy ulang. Mengubah Environment Variables **wajib Redeploy**.

## Jika bermasalah
- **Error 500 / halaman gagal dimuat**: Vercel → Project → **Logs**. Hampir selalu DB_HOST/PORT/USER/PASS salah atau `DB_SSL` belum `1`.
- **404 di semua halaman**: file tidak berada di akar repositori (lihat langkah C.3).
- **Login "Tidak dapat terhubung ke server"**: fungsi PHP gagal; cek Logs.
- **GPS ditolak**: pastikan izin lokasi browser untuk situs diizinkan (alamat sudah HTTPS).

## Catatan
- PHP di Vercel memakai runtime komunitas `vercel-php` (bukan resmi Vercel). Untuk pemakaian rutin madrasah, hosting PHP + MySQL biasa lebih sederhana.
- Paket gratis Vercel mensyaratkan pemakaian non-komersial/pribadi; cek syarat terbaru.

## Memperbarui database yang sudah ada (fitur Evaluasi Bulanan)
Jika database online Anda sudah diisi dengan versi lama, jalankan isi **`migrasi_evaluasi.sql`** sekali di SQL Editor/DBeaver
(tanpa `USE`, pada database yang sama). Data tidak dihapus. Untuk database baru, cukup `database_tidb.sql` versi terbaru.
