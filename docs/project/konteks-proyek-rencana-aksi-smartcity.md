# Konteks Proyek: Dashboard Rencana Aksi Smart City Banjarmasin

## Latar Belakang
Mengganti dashboard lama berbasis Google Data Studio di
`smartcity.banjarmasinkota.go.id/p/rencana-aksi.html` dengan website interaktif
untuk memonitor capaian Rencana Aksi Smart City tiap unit (Dinas/OPD) di Kota
Banjarmasin, dengan tambahan fitur **upload bukti dukung** per kegiatan.

> **Catatan skala proyek**: semua fitur di dokumen ini (termasuk verifikasi
> bukti, audit trail, notifikasi, export laporan, dan keamanan tambahan)
> **wajib ada di v1** — tidak ada yang ditunda ke fase berikutnya. Yang
> tetap bertahap hanyalah **urutan teknis pembangunannya** (lihat bagian
> "Urutan Pembangunan v1" di akhir dokumen), bukan cakupan fiturnya.

## Sumber Data Asli (sudah dianalisis)
File: `Google_Data_Studio_-_Rencana_Aksi_Kota_Cerdas_2019-2026.xlsx`
Berisi **156 baris kegiatan/rencana aksi** dari **39 unit berbeda** (2019-2026).
Sudah dibersihkan & dinormalisasi jadi dua file CSV siap-seed:
- `database/seed-data/rencana-aksi-seed-data.csv` — 156 baris kegiatan, kolom sudah dinormalisasi
- `database/seed-data/dinas-unit-seed-data.csv` — 39 unit unik (nama + slug), untuk seed tabel dinas

### Mapping kolom asli -> field aplikasi
| Kolom Excel asli | Field aplikasi | Catatan |
|---|---|---|
| `DIMENSI` | `dimensi` | 6 nilai: Smart Governance, Branding, Economy, Living, Society, Environment (typo asli "Environtment" dikoreksi jadi "Environment") |
| `PROGRAM` | `program` | Nullable — kegiatan lama (2019-2021) sering tidak punya program induk |
| `KEGIATAN/RENCANA AKSI` | `judul` | Judul kegiatan yang ditampilkan di dashboard |
| `RENCANA AKSI` | `jenis_keluaran` | ⚠️ Nama kolom asli membingungkan — isinya JENIS KELUARAN: aplikasi/literasi/infrastruktur/sdm/kebijakan |
| `KATEGORI` | `kategori` | tik / non_tik (78 & 78 baris) |
| `LEADING SECTOR` | relasi ke tabel `dinas` (39 unit) | Termasuk Kecamatan (4), Bagian Setda (7), BUMD, Sekretariat DPRD, dll |
| `TAHUN` | `tahun_label` | String tampilan asli, misal "2022 - 2026" atau "2023 & 2025" (ada yang tidak kontinu) |
| `PERIODE` | `tahun_pelaksanaan` (JSON array tahun) | Sumber lebih akurat untuk parsing tahun karena beberapa periode tidak kontinu |
| `Tautan Realisasi` | — (diabaikan) | Kosong di 156/156 baris — digantikan sepenuhnya oleh fitur upload bukti |

## Role & Akses (4 tingkat)
1. **Guest (tanpa login)** — akses dashboard & pencarian bukti read-only.
2. **Unit/OPD** (39 akun, satu per `LEADING SECTOR`) — kelola rencana aksi &
   bukti milik unitnya sendiri, termasuk ubah status kegiatan secara manual.
3. **Admin Kecamatan** — cakupan terbatas: melihat/upload/hapus bukti dari
   unit-unit di bawah kecamatannya (via `dinas.parent_id`, diisi manual oleh
   Admin Pusat karena data Excel tidak menyediakan relasi ini).
4. **Admin Pusat** (Diskominfotik) — akses penuh ke semua unit, plus kelola
   Manajemen Akses (whitelist email + role + unit) dan menambah kegiatan baru.

## Alur Proses (Swimlane)
1. User login → gagal: "Login gagal". Berhasil: Dashboard + "Login Berhasil".
   Login hanya berhasil jika email ada di `allowed_users` dan statusnya aktif.
2. User mencari "Rencana Kegiatan/Aksi" → tidak ditemukan: pesan "Tidak ada
   Rencana Kegiatan Aksi yang ditemukan".
3. Ditemukan → tampilkan daftar "Rencana Kegiatan Aksi".
4. User memilih salah satu kegiatan dari daftar.
5. Sistem menampilkan halaman upload bukti (boleh lebih dari satu file per
   kegiatan, tidak dipisah per tahun) — **wajib menyertakan keterangan/
   deskripsi teks singkat**, tidak cukup nama file saja.
6. User upload dokumen bukti → tersimpan dengan status_verifikasi = "Menunggu".
7. Admin (Kecamatan/Pusat, sesuai cakupan) meninjau bukti → menandai
   "Diterima" atau "Ditolak" (dengan alasan penolakan). Kalau ditolak, unit
   pengunggah menerima notifikasi untuk upload ulang.
8. Unit atau Admin bisa mengubah status kegiatan (Belum Mulai/Berjalan/
   Selesai) kapan saja secara manual — tidak dihitung otomatis.

### Alur khusus Guest (tanpa login)
- Langsung ke Dashboard publik (read-only) dan halaman Pencarian Bukti.
- Bukti yang ditampilkan ke Guest **hanya yang berstatus verifikasi
  "Diterima"** — bukti "Menunggu"/"Ditolak" hanya terlihat oleh Unit
  pemilik & Admin, supaya publik tidak melihat data yang belum tervalidasi.
- Mengakses aksi tulis → diarahkan ke halaman Login.

### Alur khusus Admin: Manajemen Izin Akses
1. Admin Pusat membuka halaman "Manajemen Akses".
2. Tambah email baru + role (Unit/Admin Kecamatan/Admin Pusat) + unit terkait.
3. Lihat daftar email + status + role + unit + terakhir login.
4. Cabut akses kapan saja.

## Struktur Halaman Website (lengkap)
1. **Dashboard** (Guest/Unit/Admin) — stat cards, gauge target, pie Dimensi,
   daftar unit. **Ada filter/switch tahun** (data historis 2019-2026, bukan
   cuma snapshot "sekarang").
2. **Halaman Pencarian Bukti** (Guest/Unit/Admin) — filter: unit, dimensi,
   jenis keluaran, kategori, status kegiatan, status verifikasi (khusus
   Unit/Admin), tahun, tipe file.
3. **Halaman Detail Rencana Aksi + Upload Bukti** (Unit login, Admin)
4. **Halaman Login** (+ **Reset Password**)
5. **Halaman Manajemen Akses** (khusus Admin Pusat)
6. **Halaman Tambah/Kelola Kegiatan** (khusus Admin Pusat) — untuk menambah
   rencana aksi baru di luar 156 data awal (mis. hasil RPJMD/evaluasi baru)
7. **Halaman Verifikasi Bukti** (Unit terkait cakupan Admin) — antrean bukti
   berstatus "Menunggu" untuk ditinjau
8. **Halaman Laporan/Export** — rekap capaian per dimensi/unit/tahun,
   export ke PDF & Excel (wajib ada di v1, bukan opsional)
9. **Halaman "Tentang"** (publik) — penjelasan umum program Smart City
   Banjarmasin untuk pengunjung Guest

## Prototype Frontend (sudah dibuat, perlu disesuaikan)
File: `docs/prototypes/dashboard-rencana-aksi-banjarmasin.jsx`
Stack: React + Tailwind CSS + recharts + lucide-react.
Field masih pakai data contoh (`title`, 7 dinas rekaan) — perlu diselaraskan
ke skema asli (`judul`, 39 unit, status manual, status_verifikasi, dst) dan
ditambah halaman-halaman baru di atas yang belum ada di prototype.

## Rencana Backend: Laravel

### Tabel yang dibutuhkan (lengkap, termasuk fitur baru)
- `users` (role: unit/admin_kecamatan/admin_pusat, `dinas_id` nullable,
  kolom untuk 2FA bawaan Fortify)
- `allowed_users` (email, role, dinas_id, status, invited_by, invited_at,
  last_login_at)
- `dinas` (nama, slug, **parent_id** nullable self-referencing — diisi
  manual oleh Admin Pusat setelah seed)
- `rencana_aksi` (dinas_id, program, judul, jenis_keluaran, kategori,
  dimensi, tahun_label, tahun_pelaksanaan JSON, status manual, created_by)
- `bukti_dukung` (rencana_aksi_id, nama_file, path_file, tipe_file,
  ukuran_file, **deskripsi** teks wajib, uploaded_by,
  **status_verifikasi** enum menunggu/diterima/ditolak,
  **alasan_penolakan** nullable, **verified_by** nullable,
  **verified_at** nullable, created_at)
- `activity_log` (tabel generik/polymorphic — pakai package
  `spatie/laravel-activitylog`, jangan bikin tabel log terpisah tiap fitur):
  mencatat perubahan status kegiatan, verifikasi/penolakan bukti,
  penghapusan bukti, perubahan di Manajemen Akses — siapa, aksi apa, kapan,
  data sebelum/sesudah
- `notifications` (tabel bawaan Laravel Notifications) — dipakai untuk:
  bukti ditolak, reminder kegiatan "Belum Mulai" di tahun berjalan,
  notifikasi Admin saat ada upload baru menunggu verifikasi

### Endpoint API (lengkap)
```
# Publik (guest, tanpa auth, read-only)
GET    /api/dinas?tahun=                        -> daftar unit + progress (filter tahun)
GET    /api/dinas/{id}/rencana-aksi?tahun=       -> kegiatan milik 1 unit
GET    /api/rencana-aksi/{id}/bukti              -> bukti yang status_verifikasi=diterima
GET    /api/bukti/search?dinas=&dimensi=&kategori=&jenis_keluaran=&status=&tahun=&tipe_file=
GET    /api/tentang                              -> konten halaman Tentang

# Perlu login (Unit / Admin Kecamatan / Admin Pusat)
POST   /api/login
POST   /api/logout
POST   /api/forgot-password                      -> reset password
POST   /api/rencana-aksi/{id}/bukti              -> upload bukti (+ deskripsi wajib)
PATCH  /api/rencana-aksi/{id}                    -> ubah status kegiatan (manual)
GET    /api/verifikasi/antrean                   -> daftar bukti "Menunggu" sesuai cakupan
PATCH  /api/bukti/{id}/verifikasi                -> set diterima/ditolak + alasan
DELETE /api/bukti/{id}                           -> hapus bukti (tercatat di activity_log)
GET    /api/laporan/export?format=pdf|xlsx&...   -> export laporan

# Khusus Admin Pusat
GET    /api/admin/allowed-users
POST   /api/admin/allowed-users
PATCH  /api/admin/allowed-users/{id}
DELETE /api/admin/allowed-users/{id}
PATCH  /api/admin/dinas/{id}                     -> set parent_id (hierarki kecamatan)
POST   /api/admin/rencana-aksi                   -> tambah kegiatan baru
GET    /api/admin/activity-log                   -> lihat audit trail
```

### Catatan penting untuk backend
- Validasi tipe file: cek **MIME type asli**, bukan cuma ekstensi (cegah
  file berbahaya berkedok `.pdf`), plus batas ukuran maksimal
- Rate limiting di endpoint `/api/login` (cegah brute force) — pakai
  throttle bawaan Laravel
- **2FA wajib untuk Admin Pusat** (opsional untuk role lain) — pakai
  `laravel/fortify`
- Role-based access sesuai cakupan masing-masing (Unit: milik sendiri;
  Admin Kecamatan: unit dengan `parent_id` mengarah ke dirinya; Admin
  Pusat: semua)
- Bukti yang ditampilkan ke Guest **wajib difilter `status_verifikasi =
  diterima`** di level query, jangan hanya disembunyikan di frontend
- Status kegiatan & status verifikasi bukti selalu dari input manual,
  tidak pernah dihitung otomatis
- Hierarki `parent_id` pada `dinas` diisi manual oleh Admin Pusat setelah
  seed awal

## Urutan Pembangunan v1 (semua fitur wajib, dibangun berurutan)
Semua ada di scope v1 — urutan ini murni supaya pembangunannya solid, bukan
berarti sebagian fitur ditunda:

1. **Skema database lengkap** — buat semua tabel di atas sekaligus
   (termasuk kolom verifikasi & audit), meski logic-nya belum dipakai semua
2. **Auth 4-role + whitelist email + login/logout/reset password** — fondasi
   semua fitur lain
3. **Seeder data awal** — baca `database/seed-data/rencana-aksi-seed-data.csv` &
   `database/seed-data/dinas-unit-seed-data.csv`, isi 156 kegiatan + 39 unit
4. **CRUD inti**: kegiatan, upload bukti (dengan deskripsi), ubah status
   manual — pastikan jalan benar sebelum lanjut
5. **Verifikasi bukti + audit trail** (`activity_log`) — menempel ke aksi
   upload/ubah status yang sudah solid dari langkah 4
6. **Notifikasi** (bukti ditolak, reminder, upload baru) — independen,
   ditambahkan begitu event-event di atas sudah terpicu dengan benar
7. **Halaman Laporan/Export** (PDF/Excel) — independen, tinggal query dari
   data yang sudah lengkap
8. **Keamanan tambahan**: validasi MIME type, rate limiting login, 2FA
   Admin Pusat (`laravel/fortify`) — biasanya pasang package siap pakai,
   paling aman dikerjakan terakhir agar tidak mengganggu alur testing fitur
   inti selama development
9. **Frontend**: update prototype React — selaraskan field data asli,
   tambah halaman Pencarian Bukti, Verifikasi Bukti, Manajemen Akses,
   Tambah Kegiatan, Laporan, dan Tentang yang belum ada
