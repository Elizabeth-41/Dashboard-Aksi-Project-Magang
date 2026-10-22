# Diagram sistem Rencana Aksi Smart City

_Dokumentasi struktur fitur, database, relasi data, dan alur utama aplikasi._

---

## 📋 Use case sistem

Diagram berikut menunjukkan kapabilitas berdasarkan aktor Guest, Unit/OPD, dan Admin Pusat.

```mermaid
flowchart LR
    accTitle: Use Case Sistem
    accDescr: Use case aplikasi Rencana Aksi Smart City untuk Guest, Unit OPD, dan Admin Pusat.

    guest([👤 Guest])
    unit([👤 Unit atau OPD])
    admin([👤 Admin Pusat])

    subgraph aplikasi["🌐 Aplikasi Rencana Aksi Smart City"]
        lihat_dashboard(["📊 Melihat dashboard publik"])
        filter_tahun(["🔍 Memfilter berdasarkan tahun"])
        lihat_dinas(["📋 Melihat daftar unit atau dinas"])
        login(["🔐 Login dan 2FA"])
        kelola_kegiatan(["📝 Mengelola kegiatan unit"])
        unggah_bukti(["📥 Mengunggah bukti dukung"])
        lihat_bukti(["🔍 Melihat dan mencari bukti"])
        laporan(["📊 Melihat laporan capaian"])
        ekspor(["📤 Mengekspor PDF atau Excel"])
        verifikasi(["✅ Memverifikasi bukti"])
        akses(["🔒 Mengelola akses pengguna"])
        audit(["📝 Melihat audit trail"])
        notifikasi(["📤 Mengelola notifikasi"])
    end

    guest --> lihat_dashboard
    guest --> filter_tahun
    guest --> lihat_dinas
    guest --> login

    unit --> login
    unit --> kelola_kegiatan
    unit --> unggah_bukti
    unit --> lihat_bukti
    unit --> laporan
    unit --> ekspor
    unit --> notifikasi

    admin --> login
    admin --> kelola_kegiatan
    admin --> unggah_bukti
    admin --> lihat_bukti
    admin --> laporan
    admin --> ekspor
    admin --> verifikasi
    admin --> akses
    admin --> audit
    admin --> notifikasi

    classDef actor fill:#ede9fe,stroke:#7c3aed,stroke-width:2px,color:#3b0764
    classDef usecase fill:#dbeafe,stroke:#2563eb,stroke-width:1px,color:#1e3a5f
    class guest,unit,admin actor
    class lihat_dashboard,filter_tahun,lihat_dinas,login,kelola_kegiatan,unggah_bukti,lihat_bukti,laporan,ekspor,verifikasi,akses,audit,notifikasi usecase
```

> Guest hanya mengakses data publik. Detail kegiatan, pencarian bukti, dan bukti dukung memerlukan autentikasi sesuai kebijakan aplikasi.

## 🗃️ ERD database

ERD ini menggambarkan tabel domain utama dan tabel pendukung autentikasi, notifikasi, serta audit.

```mermaid
erDiagram
    accTitle: ERD Rencana Aksi Smart City
    accDescr: Entitas utama dinas, pengguna, kegiatan, bukti dukung, akses pengguna, notifikasi, dan audit trail beserta kardinalitasnya.

    DINAS ||--o{ USER : "memiliki"
    DINAS ||--o{ RENCANA_AKSI : "menjadi leading sector"
    DINAS ||--o{ ALLOWED_USER : "membatasi akses"
    DINAS ||--o{ DINAS : "memiliki parent"
    USER ||--o{ RENCANA_AKSI : "membuat"
    USER ||--o{ BUKTI_DUKUNG : "mengunggah"
    USER ||--o{ BUKTI_DUKUNG : "memverifikasi"
    RENCANA_AKSI ||--o{ BUKTI_DUKUNG : "memiliki"
    USER ||--o{ NOTIFICATIONS : "menerima"
    USER ||--o{ ACTIVITY_LOG : "menjadi pelaku"

    DINAS {
        bigint id PK
        string nama UK
        string slug UK
        bigint parent_id FK
        timestamp created_at
        timestamp updated_at
    }

    USER {
        bigint id PK
        bigint dinas_id FK
        string name
        string email UK
        string password
        string role
        text two_factor_secret
        text two_factor_recovery_codes
        timestamp two_factor_confirmed_at
        timestamp created_at
        timestamp updated_at
    }

    RENCANA_AKSI {
        bigint id PK
        uuid uuid UK
        bigint dinas_id FK
        bigint created_by FK
        text program
        string judul
        string jenis_keluaran
        string kategori
        string dimensi
        string tahun_label
        json tahun_pelaksanaan
        string status
        timestamp created_at
        timestamp updated_at
    }

    BUKTI_DUKUNG {
        bigint id PK
        uuid uuid UK
        bigint rencana_aksi_id FK
        bigint uploaded_by FK
        bigint verified_by FK
        string nama_file
        string path_file
        string tipe_file
        bigint ukuran_file
        text deskripsi
        string status_verifikasi
        text alasan_penolakan
        timestamp verified_at
        timestamp created_at
        timestamp updated_at
    }

    ALLOWED_USER {
        bigint id PK
        bigint dinas_id FK
        bigint inviter_id FK
        string email UK
        string name
        string role
        string status
        timestamp invited_at
        timestamp created_at
        timestamp updated_at
    }

    NOTIFICATIONS {
        uuid id PK
        string type
        bigint notifiable_id FK
        string notifiable_type
        text data
        timestamp read_at
        timestamp created_at
        timestamp updated_at
    }

    ACTIVITY_LOG {
        bigint id PK
        string log_name
        text description
        string subject_type
        bigint subject_id
        bigint causer_id FK
        text properties
        timestamp created_at
        timestamp updated_at
    }
```

## 🔗 Relasi database

Relasi berikut menekankan arah dependensi data domain utama.

```mermaid
flowchart TB
    accTitle: Relasi Database Utama
    accDescr: Alur relasi dari unit kerja dan pengguna menuju kegiatan, bukti dukung, verifikasi, dan audit.

    dinas["🏢 DINAS<br/>id, nama, slug"]
    user["👤 USERS<br/>id, dinas_id, role"]
    allowed["🔒 ALLOWED_USERS<br/>email, role, status"]
    rencana["📋 RENCANA_AKSI<br/>dinas_id, created_by"]
    bukti["📥 BUKTI_DUKUNG<br/>rencana_aksi_id, uploaded_by"]
    audit["📝 ACTIVITY_LOG<br/>causer_id, subject_id"]
    notif["📤 NOTIFICATIONS<br/>notifiable_id"]

    dinas -->|"1 : N"| user
    dinas -->|"1 : N"| rencana
    dinas -->|"1 : N"| allowed
    user -->|"created_by"| rencana
    rencana -->|"1 : N"| bukti
    user -->|"uploaded_by / verified_by"| bukti
    user -->|"causer_id"| audit
    user -->|"notifiable_id"| notif

    classDef entity fill:#dbeafe,stroke:#2563eb,stroke-width:2px,color:#1e3a5f
    classDef audit_entity fill:#fef9c3,stroke:#ca8a04,stroke-width:2px,color:#713f12
    class dinas,user,allowed,rencana,bukti entity
    class audit,notif audit_entity
```

## 🔄 Flowchart alur utama

Flowchart ini menunjukkan alur pengguna dari membuka aplikasi sampai membaca dashboard, login, mengelola bukti, dan menghasilkan laporan.

```mermaid
flowchart TB
    accTitle: Alur Utama Aplikasi
    accDescr: Alur pengguna dari membuka aplikasi, memilih akses publik atau login, hingga pengelolaan kegiatan, bukti, verifikasi, dan laporan.

    mulai([🚀 Membuka aplikasi]) --> dashboard[📊 Memuat dashboard publik]
    dashboard --> pilih{🔍 Membutuhkan fitur privat?}
    pilih -->|Tidak| filter[📋 Memilih tahun atau unit]
    filter --> publik([✅ Melihat data publik])
    pilih -->|Ya| login[🔐 Login]
    login --> cek_2fa{🔒 2FA aktif?}
    cek_2fa -->|Tidak dan wajib| pasang[🔧 Memasang 2FA]
    pasang --> verifikasi_login[✅ Memverifikasi kode authenticator]
    verifikasi_login --> cek_role
    cek_2fa -->|Ya| cek_role{👤 Role pengguna?}
    cek_role -->|Unit| unit[📝 Mengelola kegiatan dan mengunggah bukti]
    cek_role -->|Admin Pusat| admin[🔒 Mengelola akses dan kegiatan]
    unit --> bukti[📥 Bukti menunggu verifikasi]
    admin --> review[🔍 Meninjau dan memverifikasi bukti]
    bukti --> review
    review --> status{✅ Bukti diterima?}
    status -->|Ya| publikasi[📤 Bukti tampil sesuai cakupan akses]
    status -->|Tidak| tolak[⚠️ Menyimpan alasan penolakan]
    publikasi --> laporan[📊 Membuka laporan capaian]
    tolak --> laporan
    laporan --> ekspor[📤 Ekspor PDF atau Excel]
    ekspor --> selesai([✅ Selesai])
    publikasi --> selesai

    classDef start fill:#ede9fe,stroke:#7c3aed,stroke-width:2px,color:#3b0764
    classDef process fill:#dbeafe,stroke:#2563eb,stroke-width:1px,color:#1e3a5f
    classDef decision fill:#fef9c3,stroke:#ca8a04,stroke-width:2px,color:#713f12
    classDef success fill:#dcfce7,stroke:#16a34a,stroke-width:2px,color:#14532d
    classDef warning fill:#fee2e2,stroke:#dc2626,stroke-width:1px,color:#7f1d1d

    class mulai,selesai start
    class dashboard,filter,login,pasang,verifikasi_login,unit,admin,bukti,review,publikasi,laporan,ekspor process
    class pilih,cek_2fa,cek_role,status decision
    class publik,selesai success
    class tolak warning
```

## 📚 Sumber struktur

Diagram disusun berdasarkan:

- migration pada `database/migrations/`;
- model dan relasi Eloquent pada `app/Models/`;
- route API pada `routes/api.php` dan route web pada `routes/web.php`;
- policy dan middleware autentikasi/otorisasi;
- feature test pada `tests/Feature/`.
