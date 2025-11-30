# WordPress CBT Theme – Ujian Berbasis Komputer

Tema WordPress untuk menyelenggarakan **Ujian Berbasis Komputer (CBT)**:

- Soal disimpan di **post WordPress** (bisa dari Word, lengkap gambar).
- Konfigurasi ujian, peserta, dan kunci jawaban dikirim via **Excel + VBA**.
- Halaman depan WordPress otomatis menjadi **portal login CBT**.
- Ruang ujian modern, responsif, dengan **dark / light mode**.
- Cocok untuk lab CBT sekolah (SMK/SMA/MA, dll).

---

## Fitur Utama

- 🔐 **Portal Login CBT**

  - Username + password siswa.
  - Token 6 digit (otomatis berganti berdasarkan durasi).
  - Pilih mapel / kode ujian dari dropdown **(hanya test aktif & sesuai tanggal)**.

- 📄 **Soal dari Post WordPress**

  - 1 **post** = 1 paket soal (satu mapel / test).
  - Konten bisa hasil publish dari Microsoft Word:
    - Nomor soal, teks soal.
    - Pilihan A–E.
    - Gambar (otomatis jadi `<img>` di media library WordPress).

- 📊 **Integrasi Excel**

  - Sheet **CONFIG** → info ujian (kode test, nama, tanggal, durasi, dsb.).
  - Sheet **PESERTA** → daftar siswa.
  - Sheet **KUNCI** → kunci jawaban & skor.
  - Sheet **HASIL** → tarik nilai dari WordPress (via REST API).

- 🧠 **Logika CBT**

  - Metode waktu:
    - **Dynamic** (model UNBK, waktu jalan per siswa).
    - **Classic** (waktu tetap sesuai jadwal).
  - Shuffle soal & shuffle opsi.
  - Minimal sisa waktu (menit) sebelum boleh mengumpulkan.
  - Jawaban disimpan **realtime** (tiap klik opsi langsung ke server via AJAX).
  - Navigasi soal:
    - Biru: soal aktif.
    - Hijau: sudah dijawab.
    - Kuning: ditandai **Ragu-ragu**.

- 🎨 **UI Modern + Dark / Light**

  - TailwindCSS (via CDN).
  - Tombol toggle di pojok kanan atas.
  - Header mirip layout ruang ujian/UNBK:
    - Judul ujian, kode test, nama peserta, timer.
  - Body: area soal + opsi.
  - Sidebar: daftar nomor soal + tombol navigasi + tombol selesai.

- 🧷 **Admin Panel CBT**

  - Menu khusus di wp-admin (misal: **CBT Ujian**):
    - Panel **Token Aktif**:
      - Lihat token.
      - Status aktif/kadaluarsa.
      - Tombol “Generate Token Baru”.
    - Panel **Pengaturan Umum**:
      - Masa aktif token.
      - Excel Key.
      - Timezone (WIB/WITA/WIT).
      - Sembunyikan / tampilkan semua mapel.
      - Tampilkan nilai di akhir.
      - Wajib reset saat keluar tanpa logout.
      - Siswa boleh logout sebelum selesai.
      - Peserta boleh daftar sendiri.
      - Token otomatis.
      - Wajib jawab semua soal.
      - Minimal sisa waktu (menit).
      - Metode waktu (Dynamic/Classic).
      - Metode penyimpanan (Realtime/Classic).
      - Pesan error siswa diblokir / sesi terkunci.

- 🧾 **Reset Peserta per Test**

  - Admin bisa reset sesi **login** peserta untuk satu test.
  - Jawaban di database **tidak dihapus**, tetap bisa direkap.

- 🔐 **Keamanan Tambahan**
  - URL `?session=slug` saja **tidak cukup** untuk akses:
    - Login mengeluarkan `session_auth` yang disimpan di DB dan `localStorage`.
    - Endpoint `/exam` cek kecocokan `session_auth`.
  - Kunci jawaban **tidak** disimpan di meta post, tapi di tabel terpisah (`cbt_keys`) yang diisi dari Excel.

---

## Kebutuhan Sistem

- PHP 7.4+ (disarankan 8.x).
- MySQL/MariaDB.
- WordPress 6.x.
- Apache / Nginx / Nginx Proxy Manager.
- Browser modern untuk siswa (Chrome/Edge/Firefox terbaru).

---

## Instalasi Theme

1. **Copy Theme ke WordPress**

   Clone / download theme ini, lalu letakkan di:

   ```text
   wp-content/themes/cbt-theme/
   ```
