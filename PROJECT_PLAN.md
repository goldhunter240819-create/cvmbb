# PROJECT PLAN: MBB BUSINESS MANAGEMENT SYSTEM
## CV. MAJU BERKAH BERSAMA (MBB)

---

### 1. RINGKASAN EKSEKUTIF

**MBB Business Management System** adalah platform Enterprise Resource Planning (ERP) terintegrasi berbasis web yang dirancang khusus untuk memusatkan, mengotomatisasi, dan menyelaraskan seluruh operasional multi-unit usaha dari **CV. MAJU BERKAH BERSAMA**. 

Perusahaan beroperasi di Jln. Raya Gunung Terang No. 09, Kecamatan Air Hitam, Kabupaten Lampung Barat dengan lini usaha yang bervariasi:
1. **Coffee Trading** (Perdagangan Biji Kopi dari Petani & Supplier)
2. **Coffee Production** (Pengolahan Biji Kopi, Roasting Internal, Grinding & Packaging Kopi Bubuk)
3. **Coffee Roasting Service** (Jasa Maklon Roasting Kopi Pelanggan)
4. **Agen BRILink** (Layanan Perbankan & Transaksi Keuangan Mikro)
5. **Divisi Dekoration & Sound Sistem** (Jasa Pesta, Acara, dan Persewaan Perlengkapan)
6. **Administrasi & Keuangan Terpadu** (Tata Kelola Dokumen, Aset/Inventaris, Kas & Laporan Manajemen)

Sistem dibangun tanpa framework backend yang berat (PHP Native 8.x + MySQL InnoDB + Bootstrap 5 + Vanilla JS) untuk memastikan portabilitas tinggi pada lingkungan server XAMPP lokal maupun hosting, performa kilat, dan kemudahan pemeliharaan jangka panjang tanpa ketergantungan library pihak ketiga yang rentan usang.

---

### 2. TUJUAN & SASARAN SISTEM

1. **Sentralisasi Data Multi-Unit Bisnis**: Menghilangkan silo data antar lini usaha (kopi, BRILink, dekorasi, sound system, kantor).
2. **Integritas Transaksi & Keuangan**: 
   - Memastikan prinsip akuntansi yang sehat: Omzet bukan laba, modal bukan pendapatan, uang titipan nasabah BRILink dipisahkan dari kas operasional perusahaan.
   - Semua pergerakan barang dan kas tercatat dengan sumber referensi yang jelas (audit trail).
3. **Fleksibilitas Struktur Organisasi**:
   - Struktur hierarki komisaris, direksi, direktur, manager, hingga kepala divisi dapat dikonfigurasi dinamis.
   - Pemisahan tegas antara **Jabatan Organisasi Perusahaan** dengan **Akun Akses Sistem (User Roles)**.
4. **Pencegahan Fraud & Transparansi**:
   - Role Based Access Control (RBAC) granular per modul dan aksi.
   - Pencatatan log aktivitas sistem (Audit Log).
5. **Kemudahan Operasional untuk Non-Programmer**:
   - Antarmuka Bahasa Indonesia yang ramah pengguna, modern, elegan, dan adaptif di desktop maupun ponsel.

---

### 3. ARSITEKTUR TEKNOLOGI & INFRASTRUKTUR

| Komponen | Spesifikasi & Pilihan Teknologi | Keterangan |
|---|---|---|
| **Backend Engine** | PHP 8.x Native (Modular Architecture) | Clean procedural/OOP hybrid, DRY pattern |
| **Database** | MySQL / MariaDB (InnoDB Engine, utf8mb4) | ACID compliant, FK constraint, database transaction |
| **Database Connector**| PHP Data Objects (PDO) | Prepared Statements anti-SQL Injection |
| **Frontend Framework**| Bootstrap 5.3 + Custom CSS Theme | Palet MBB (Biru, Navy, Biru Muda, Putih) |
| **Iconography** | Bootstrap Icons & Font Awesome 6 | Visual yang konsisten di semua modul |
| **Data Visualization**| Chart.js 4.x | Grafik interaktif pendapatan, pengeluaran & tren |
| **Server Environment**| Apache HTTP Server on XAMPP (Windows/Linux) | Localhost `http://localhost/cvmbb/` |
| **Client Support** | Responsive Web (Desktop, Tablet, Smartphone) | Mobile-first viewport navigation |

---

### 4. STRUKTUR ORGANISASI PERUSAHAAN (BASE BASELINE)

Sesuai ketetapan dewan pimpinan CV. MAJU BERKAH BERSAMA:

```
                      [ KOMISARIS ]
                   KH. MUHAMMAD THOHA
                           │
                           ▼
                       [ DIREKSI ]
                        NUR ISLAH
               ┌───────────┴───────────┐
               ▼                       ▼
    [ DIVISI ADMINISTRASI ]   [ DIVISI OPERASIONAL ]
          NURIL HUDA               AHMAD SOBIRIN
               │
               ▼
          [ DIREKTUR ]
           M. ROZIKIN
               │
               ▼
           [ MANAGER ]
          M. ILHAM SIFA
        ┌──────┴──────┐
        ▼             ▼
[ DIVISI DEKORATION ] [ DIVISI SOUND SISTEM ]
   FATKHUR ROHMAN         MISBAHUL MUNIR
```

---

### 5. PEMBAGIAN FASE PENGEMBANGAN (PHASING)

- **Tahap 1: Core System & Foundation** (Fokus Pengerjaan Awal)
  - Arsitektur direktori modular & engine inti (Auth, Database, Session, RBAC, Validator, CSRF).
  - Database schema relasional lengkap & database.sql.
  - Skema identitas visual MBB (Logo asli, Favicon, palet biru-navy-putih).
  - Login & Logout dengan proteksi brute-force & first-time password change flag.
  - Dashboard eksekutif berbasis real query database & filter periode.
  - Modul Struktur Organisasi (Hierarki visual dinamis, CRUD jabatan & anggota, mutasi, riwayat jabatan, cetak resmi).
  - Modul Manajemen Profil Perusahaan (konfigurasi dinamis).
  - Modul Pengguna & Hak Akses (RBAC).

- **Tahap 2: Administrasi & Kepegawaian**
  - Data lengkap pegawai & biodata.
  - Pengarsipan surat masuk, surat keluar dengan generator nomor otomatis.
  - Manajemen arsip digital dan pelaporan aktivitas divisi.

- **Tahap 3: Coffee Trading**
  - Pembelian biji kopi (petani & supplier) dengan parameter kadar air, kualitas fisik, potongan timbangan.
  - Penjualan biji kopi, penagihan pelanggan, manajemen stok gudang kopi.

- **Tahap 4: Coffee Production**
  - Formulasi roasting internal, pencatatan shrinkage (penyusutan berat), penggilingan, packaging biji jadi & bubuk.
  - Kalkulasi HPP (Harga Pokok Produksi) otomatis.

- **Tahap 5: Roasting Service (Maklon)**
  - Penerimaan kopi konsumen, status antrean, pilihan roast profile (Light, Medium, Dark), timbang susut, faktur jasa.

- **Tahap 6: Administrasi BRILink**
  - Pencatatan transaksi harian, fee sharing, modal perputaran kas agen, rekonsiliasi kas tunai vs saldo bank.

- **Tahap 7: Keuangan Terpadu & Inventaris**
  - Multi-kas akun, pembukuan kas masuk/keluar, utang/piutang, kartu aset dan depresiasi.
  - Laporan Laba Rugi dan Arus Kas otomatis per lini bisnis dan konsolidasi.

- **Tahap 8: Reporting, Backup, & Hardening**
  - Backup database 1-klik, export PDF/Excel berlogo, audit log viewer, audit performa.

---

### 6. STANDAR KEAMANAN & INTEGRITAS DATA

1. **Authentication**: `password_hash()` menggunakan algoritma BCRYPT / ARGON2ID yang aman.
2. **Session Security**: Session timeout otomatis, `session_regenerate_id()` saat login untuk menangkal session fixation.
3. **Data Access**: Wajib menggunakan PDO Prepared Statements untuk setiap query parametrik.
4. **CSRF Protection**: Token anti-pemalsuan permintaan lintas situs pada setiap form mutasi POST/PUT/DELETE.
5. **XSS Protection**: Sanitasi output wajib menggunakan `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
6. **Audit Trail**: Setiap aktivitas penting (login, buat transaksi, modifikasi data, hapus data) dicatat ke tabel `audit_logs` bersama identitas pengguna, IP address, dan timestamp.
