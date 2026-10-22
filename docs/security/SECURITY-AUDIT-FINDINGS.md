# Temuan Audit Keamanan Dashboard Aksi

## Instruksi untuk coding agent

Implementasikan perbaikan keamanan berikut secara bertahap dan surgical. Jangan mengubah perilaku bisnis yang tidak terkait. Setelah perubahan:

1. Tambahkan atau perbarui feature test untuk setiap temuan.
2. Jalankan test keamanan yang relevan.
3. Jalankan `composer audit` dan `npm audit --omit=dev`.
4. Pastikan tidak ada secret yang ditambahkan ke repository.
5. Laporkan file yang diubah, test yang dijalankan, dan risiko yang masih tersisa.

> Jangan menyalin nilai rahasia dari `.env` ke source code, commit, log, issue, atau output chat.

## Prioritas

| ID | Severity | Temuan | Prioritas |
|---|---|---|---|
| SEC-01 | CRITICAL | Secret dan kredensial lemah terdapat pada `.env` lokal | P0 |
| SEC-02 | MEDIUM | Email uploader terekspos melalui endpoint bukti publik | P1 |
| SEC-03 | MEDIUM-HIGH | PDF upload disajikan inline tanpa isolasi yang kuat | P1 |
| SEC-04 | MEDIUM | CSP default nonaktif di luar production | P1 |
| SEC-05 | LOW-MEDIUM | `dangerouslySetInnerHTML` digunakan untuk QR 2FA | P2 |
| SEC-06 | LOW-MEDIUM | Pemilihan guard pada authorization tidak konsisten | P2 |
| SEC-07 | MEDIUM | Database, log, dan file bukti lokal berpotensi ikut terdistribusi | P1 |
| SEC-08 | UNASSESSED | Dependency/CVE scan belum diverifikasi | P1 |

---

## SEC-01 — Secret dan kredensial lemah pada `.env`

**Severity:** Critical  
**Prioritas:** P0 — wajib sebelum deployment/pembukaan akses publik

### Lokasi

- `.env`
- `.env.example`
- `config/app.php`

### Bukti

`.env` lokal berisi `APP_KEY`, password mail, password awal Admin Pusat, dan `APP_DEBUG=true`. Nilai aktual sengaja tidak dicantumkan dalam laporan ini.

### Dampak

Jika file tersebut tersalin, ter-commit, masuk backup publik, image container, atau artifact CI/CD, attacker dapat memperoleh:

- kredensial SMTP;
- akses awal ke akun Admin Pusat;
- informasi konfigurasi internal;
- stack trace, path server, dan detail exception ketika debug aktif;
- risiko terhadap data terenkripsi jika APP key ikut terekspos.

### Perbaikan yang diminta

1. Rotasi seluruh secret yang pernah tersimpan pada `.env`.
2. Ganti password Admin Pusat default dengan password unik dan kuat.
3. Set `APP_DEBUG=false` pada semua environment yang dapat diakses selain development lokal.
4. Jika `APP_KEY` pernah terekspos, lakukan rotasi terencana dengan mempertimbangkan dampak terhadap sesi dan data terenkripsi.
5. Pastikan `.env`, log, database lokal, dan storage privat tidak masuk artifact deployment.
6. Gunakan secret manager untuk secret production.
7. Jangan menambahkan secret ke `.env.example`; gunakan placeholder kosong atau dokumentasi nama variable saja.

### Acceptance criteria

- Tidak ada secret aktual di source control atau artifact build.
- Production/staging menggunakan `APP_DEBUG=false`.
- Password Admin Pusat default tidak dapat digunakan.
- Kredensial SMTP lama sudah dicabut/dirotasi.
- Ada dokumentasi internal tentang sumber secret production tanpa membocorkan nilainya.

---

## SEC-02 — Email uploader terekspos pada endpoint publik

**Severity:** Medium  
**Prioritas:** P1

### Lokasi

- `routes/api.php`
- `app/Http/Controllers/Api/BuktiSearchController.php`
- `app/Http/Resources/BuktiDukungResource.php`
- `app/Http/Resources/UserResource.php`

### Bukti

Endpoint publik `/api/bukti/search` memuat relasi `uploader`. `BuktiDukungResource` menggunakan `UserResource`, sedangkan `UserResource` mengembalikan field `email`.

### Dampak

Guest dapat memperoleh alamat email uploader dari bukti yang dapat dicari secara publik. Ini meningkatkan risiko:

- phishing terhadap pegawai;
- enumerasi personel dan aktivitas;
- kebocoran data pribadi yang tidak diperlukan untuk tampilan publik.

### Perbaikan yang diminta

1. Buat resource publik terpisah untuk bukti, misalnya `PublicBuktiDukungResource`.
2. Jangan gunakan `UserResource` administratif pada response publik.
3. Pada endpoint publik, hilangkan `email`, `id internal`, role, unit internal, dan metadata administrasi.
4. Jika nama uploader memang dibutuhkan oleh UX publik, kembalikan hanya nama tampilan yang diperlukan.
5. Pertahankan resource lengkap hanya untuk endpoint yang telah diautentikasi dan diotorisasi.

### Acceptance criteria

- Request guest ke `/api/bukti/search` tidak mengandung `uploader.email`.
- Request guest ke daftar bukti kegiatan tidak mengandung email uploader.
- Endpoint admin/unit tetap mengembalikan data yang memang diperlukan oleh UI.
- Test mencakup guest, unit pemilik, unit lain, dan Admin Pusat.

---

## SEC-03 — PDF upload disajikan inline tanpa isolasi yang kuat

**Severity:** Medium-High  
**Prioritas:** P1

### Lokasi

- `app/Http/Controllers/Api/BuktiDukungController.php`
- `app/Models/BuktiDukung.php`
- `tests/Feature/Api/LihatBuktiTest.php`

### Bukti

`BuktiDukungController::lihat()` menampilkan PDF secara inline. CSP `sandbox` hanya ditambahkan untuk gambar; PDF sengaja tidak diberi sandbox karena keterbatasan penampil PDF browser.

### Dampak

File PDF berasal dari uploader dan dapat membawa konten aktif, action eksternal, link phishing, tracking, atau payload yang mengeksploitasi PDF viewer/browser.

### Perbaikan yang diminta

Pilih pendekatan yang paling aman dan konsisten dengan UX:

1. **Preferensi utama:** sajikan PDF sebagai `attachment`, bukan `inline`.
2. Jika preview wajib:
   - lakukan sanitasi/re-encoding PDF sebelum dipublikasi;
   - sajikan dari origin/domain terisolasi tanpa cookie sesi;
   - pertahankan `Content-Type` terdeteksi dan `X-Content-Type-Options: nosniff`;
   - gunakan cache privat/no-store untuk dokumen sensitif.
3. Pertimbangkan antivirus/malware scanning untuk upload.
4. Jangan memperlakukan ekstensi file sebagai dasar keamanan.

### Acceptance criteria

- PDF berbahaya tidak dapat mengeksekusi script dalam origin aplikasi.
- Test memverifikasi `Content-Disposition` sesuai keputusan desain.
- Akses terhadap PDF tetap mengikuti policy yang sama dengan download.
- Gambar dan Office document mempertahankan perilaku yang sudah benar.
- Nama file tidak dapat memengaruhi path file atau memilih file lain.

---

## SEC-04 — CSP nonaktif secara default di luar production

**Severity:** Medium  
**Prioritas:** P1

### Lokasi

- `config/keamanan.php`
- `app/Http/Middleware/TambahHeaderKeamanan.php`
- `tests/Feature/Keamanan/HeaderKeamananTest.php`

### Bukti

Konfigurasi saat ini menggunakan pola:

```php
env('CSP_MODE', env('APP_ENV') === 'production' ? 'enforce' : 'off')
```

Dengan demikian, staging/pre-production dapat berjalan tanpa CSP jika `CSP_MODE` tidak ditetapkan.

### Perbaikan yang diminta

1. Jadikan CSP `enforce` untuk production dan staging.
2. Batasi `off` hanya untuk development lokal yang benar-benar membutuhkan Vite dev server.
3. Gunakan `report` terlebih dahulu bila staging belum siap enforce.
4. Tambahkan pelaporan CSP (`report-to` atau mekanisme yang sesuai) jika infrastruktur mendukung.
5. Jangan melonggarkan `script-src` dengan `'unsafe-inline'` sebagai solusi cepat.

### Acceptance criteria

- Staging mengirim `Content-Security-Policy` atau minimal `Content-Security-Policy-Report-Only`.
- Production mengirim CSP enforce.
- `script-src` tidak mengandung `'unsafe-inline'`.
- Test memverifikasi mode lokal, staging, dan production.

---

## SEC-05 — `dangerouslySetInnerHTML` untuk QR 2FA

**Severity:** Low-Medium  
**Prioritas:** P2

### Lokasi

- `resources/js/pages/DuaFaktor.jsx`

### Bukti

QR SVG dari endpoint 2FA dimasukkan dengan:

```jsx
dangerouslySetInnerHTML={{ __html: qr }}
```

Saat ini sumbernya berasal dari endpoint trusted, sehingga risiko langsung rendah, tetapi keamanan bergantung pada format SVG yang dikembalikan server/library.

### Perbaikan yang diminta

1. Preferensikan `<img>` atau data URL gambar yang tidak diparse sebagai HTML.
2. Jika SVG inline harus dipertahankan, sanitasi dengan allowlist.
3. Tolak `script`, event handler, `foreignObject`, URL eksternal, dan skema `javascript:`.
4. Tambahkan test untuk memastikan payload SVG berbahaya tidak dirender.

### Acceptance criteria

- Tidak ada executable markup dari response QR.
- QR tetap dapat dipindai oleh authenticator.
- Tidak ada regresi pada proses setup/confirm 2FA.

---

## SEC-06 — Pemilihan guard authorization tidak konsisten

**Severity:** Low-Medium  
**Prioritas:** P2

### Lokasi

- `app/Http/Controllers/Api/BuktiDukungController.php`

### Bukti

Endpoint view/download memakai `Gate::forUser($request->user('sanctum'))`, tetapi endpoint delete memakai `Gate::authorize()` tanpa guard eksplisit.

### Perbaikan yang diminta

Gunakan guard yang sama secara konsisten pada operasi API, misalnya:

```php
Gate::forUser($request->user('sanctum'))
    ->authorize('delete', $buktiDukung);
```

Jangan mengandalkan default guard untuk endpoint API.

### Acceptance criteria

- Guest ditolak.
- Unit pemilik dapat menghapus sesuai aturan bisnis.
- Unit lain tidak dapat menghapus.
- Admin Pusat dapat menghapus sesuai policy.
- Behavior konsisten untuk semua mekanisme autentikasi yang didukung.

---

## SEC-07 — Artefak lokal sensitif berpotensi ikut terdistribusi

**Severity:** Medium  
**Prioritas:** P1

### Lokasi

- `database/database.sqlite`
- `storage/logs/`
- `storage/app/private/`
- konfigurasi deployment/build pipeline

### Risiko

Artefak tersebut dapat memuat:

- data pengguna;
- email;
- activity log;
- stack trace;
- dokumen bukti;
- metadata internal.

### Perbaikan yang diminta

1. Pastikan document root web hanya `public/`.
2. Pastikan `storage/`, database lokal, dan log tidak dipaketkan ke deployment publik.
3. Gunakan data sintetis untuk development.
4. Redaksi log sebelum dibagikan.
5. Verifikasi disk object storage production tidak public.
6. Tambahkan pemeriksaan CI untuk mencegah `.env`, database, dan file privat masuk artifact.

### Acceptance criteria

- Request HTTP langsung ke database, log, dan storage privat menghasilkan 404/403.
- Build artifact tidak berisi `.env`, database lokal, atau file upload.
- Bucket/object storage production tidak dapat dibaca tanpa authorization.

---

## SEC-08 — Dependency/CVE scan belum diverifikasi

**Severity:** Belum terukur  
**Prioritas:** P1

### Dependency penting

- Laravel Framework
- Laravel Sanctum
- Laravel Fortify
- DomPDF
- Laravel Excel
- Spatie Activity Log
- Axios/Vite dan dependency frontend

### Perintah verifikasi

Jalankan dari root project:

```powershell
composer audit
npm audit --omit=dev
php artisan test --testsuite=Feature
```

### Acceptance criteria

- Tidak ada vulnerability High/Critical yang belum memiliki mitigasi.
- Vulnerability Medium memiliki issue/remediation plan.
- Lockfile tetap konsisten.
- Hasil scan dilampirkan pada laporan implementasi tanpa membocorkan secret.

---

# Urutan implementasi yang disarankan

1. **P0:** rotasi secret, ganti password default, matikan debug production.
2. **P1:** resource publik tanpa email, hardening PDF, aktifkan CSP staging, lindungi artifact deployment.
3. **P1:** jalankan dependency/CVE scan dan remediasi hasilnya.
4. **P2:** hardening QR SVG dan konsistensi guard.
5. Tambahkan regression test untuk semua acceptance criteria.

# Validasi akhir

```powershell
cd D:\Coba\Dashboard-Aksi\Dashboard-Aksi

php artisan test --filter="Keamanan|LihatBukti|RevokedAccess|TwoFactor"
composer audit
npm audit --omit=dev
```

Jika test suite memakai konfigurasi environment khusus, gunakan command project yang sudah ditetapkan dan jangan menjalankan test terhadap database production.
