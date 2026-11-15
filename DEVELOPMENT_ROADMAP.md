# DEVELOPMENT ROADMAP: MBB BUSINESS MANAGEMENT SYSTEM
## CV. MAJU BERKAH BERSAMA (MBB)

---

### TAHAPAN PENGEMBANGAN SISTEMATIS (STATUS: LENGKAP 100%)

```
[TAHAP 1: CORE & ORG] (DONE) ──► [TAHAP 2: ADM & PERSURATAN] (DONE) ──► [TAHAP 3: COFFEE TRADING] (DONE)
         │                                                                         │
         ▼                                                                         ▼
[TAHAP 4: PRODUCTION] (DONE) ──► [TAHAP 5: ROAST SERVICE] (DONE)    ──► [TAHAP 6: BRILINK MOD] (DONE)
         │                                                                         │
         ▼                                                                         ▼
[TAHAP 7: DEKORASI & SOUND] (DONE) ──► [TAHAP 8: KEUANGAN, INVENTARIS & LAPORAN] (DONE)
```

---

### DETAIL ROADMAP & CAPAIAN IMPLEMENTASI

#### TAHAP 1 — CORE SYSTEM & ORGANIZATIONAL FOUNDATION (SELESAI 100%)
- [x] Dokumen Analisis Arsitektur (`PROJECT_PLAN.md`, `DATABASE_DESIGN.md`, `ERD.md`, `DEVELOPMENT_ROADMAP.md`).
- [x] Struktur Direktori Modular & Pengaturan Aset (Logo Asli MBB, Favicon di semua halaman).
- [x] Database Schema Lengkap 35 tabel relasional (`database/database.sql`) & Seed Data Struktur Awal Perusahaan.
- [x] Engine Inti: `core/Database.php` (PDO Wrapper), `core/Auth.php`, `core/Permission.php`, `core/Validator.php`, `helpers/utils.php`.
- [x] Sistem Autentikasi: Login, Logout, Session Expiration, CSRF Token, Brute Force Throttling, Password Hashing.
- [x] Akun Developer (`Maul` / `Basenglah123.`) dan Administrator (`admin` / `admin`).
- [x] Layout UI/UX Responsif (Bootstrap 5, Palet Navy-Blue MBB, Page Hero Banner di setiap halaman, Sidebar Dinamis, Topbar, Breadcrumbs).
- [x] Dashboard Eksekutif: Real DB Metric Cards, Filter Periode Waktu, Chart.js Analitik, Log Aktivitas Terbaru.
- [x] Modul Struktur Organisasi:
  - Visual Tree Hierarchy Organisasi CV. MAJU BERKAH BERSAMA.
  - CRUD Master Divisi & Master Jabatan.
  - CRUD Data Anggota Pejabat Struktural.
  - Fitur Mutasi / Pindah Divisi & Pencatatan Riwayat Jabatan.
  - Fitur Cetak Struktur Organisasi Resmi Berkop Surat Perusahaan.
- [x] Modul Profil Perusahaan (Pengaturan Logo, Alamat, Kontak, Penandatangan).
- [x] Modul Manajemen Pengguna & RBAC.
- [x] Modul Backup Database 1-klik (.sql dump & download).

#### TAHAP 2 — ADMINISTRASI & TATA PERSURATAN (SELESAI 100%)
- [x] Master Data Pegawai & Anggota (CRUD, NIP, Jabatan, Divisi, Kontak).
- [x] Buku Register Surat Masuk (Nomor Agenda otomatis `AGD/2026/xxx`, Scan Berkas, Disposisi).
- [x] Buku Register Surat Keluar (Nomor Surat otomatis `xxx/MBB-DIR/XI/2026`, Penandatangan Resmi, Status).
- [x] Fitur Cetak Surat Resmi Berkop Perusahaan CV. MAJU BERKAH BERSAMA (Logo asli, format A4, stempel & tanda tangan Direktur).
- [x] Manajemen Arsip Dokumen Digital (Kategori Legalitas, Sertifikasi Uji Kopi, Kontrak Kerja, SOP Operasional).
- [x] Buku Agenda Kegiatan Divisi (PJ Nuril Huda, Ahmad Sobirin, Fatkhur Rohman, Misbahul Munir) terhubung ke Dashboard utama.

#### TAHAP 3 — COFFEE TRADING & MIGRASI DATA EXCEL (SELESAI 100%)
- [x] Analisis & Migrasi data riil [CV MBB 2026.xlsx](file:///c:/xampp/htdocs/cvmbb/CV%20MBB%202026.xlsx):
  - 31 riwayat pengiriman penjualan kopi (Mei - November 2026) senilai Rp 4,58 Miliar (82,3 Ton).
  - Data pembelian harian kopi dari petani binaan (Bocil, Huda, Budi Santoso, Agus, Zainal, Sudar).
- [x] Kalkulasi Khusus MBB:
  - Biaya Langsir (Rp 100/Kg).
  - Biaya Upah Pekerja (Rp 150/Kg).
  - Biaya Operasional Armada & INVS.
  - MBB Fee (Rp 75 - Rp 100/Kg).
  - Distribusi Upah Pekerja (Ahmad Sobirin, Nuril Huda, M. Ilham Sifa, Tim Lapangan).
- [x] Cetak Surat Jalan & Faktur Pengiriman Kopi Resmi Berkop Surat (`modules/coffee-trading/print_delivery.php`).

#### TAHAP 4 — COFFEE PRODUCTION (ROASTING & PACKAGING) (SELESAI 100%)
- [x] Pencatatan Batch Roasting Kopi Internal (`production_orders`).
- [x] Pemotongan otomatis stok bahan baku (Green Beans Robusta).
- [x] Penambahan stok barang jadi (Roasted Beans Medium/Dark, Kopi Bubuk Kemasan 250gr, 500gr, 1kg).
- [x] Perhitungan Susut Berat Otomatis (Shrinkage Kg & Persentase).
- [x] Perhitungan HPP Otomatis (Biaya Bahan Baku + Biaya Gas/Kemasan / Output).
- [x] Mutasi Stok Transaksional aman dengan Database Transaction.

#### TAHAP 5 — COFFEE ROASTING SERVICE / JASA MAKLON PELANGGAN (SELESAI 100%)
- [x] Formulir Penerimaan Kopi Milik Pelanggan & Rekam Data Pelanggan.
- [x] Pilihan Profil Roasting: Light Roast, Medium Roast, Dark Roast.
- [x] Tracking Alur Status Pengerjaan: ANTREAN -> ROASTING -> COOLING & RESTING -> PACKAGING -> SIAP DIAMBIL -> SELESAI.
- [x] Perhitungan Tarif Jasa per Kg & Status Pembayaran (LUNAS / BELUM BAYAR).
- [x] Cetak Bukti Serah Terima & Nota Jasa Roasting Berkop MBB (`modules/roasting-service/print_order.php`).

#### TAHAP 6 — MODUL ADMINISTRASI AGEN BRILINK (SELESAI 100%)
- [x] Pencatatan Transaksi Layanan BRILink: Transfer, Tarik Tunai, Setor Tunai, Tagihan, Pulsa/Top-up.
- [x] Kalkulasi Otomatis Fee Jasa MBB: `admin_fee_charged` - `bank_fee` = `company_income`.
- [x] Pemisahan Uang Titipan Nasabah vs Saldo EDC/Rekening Operasional & Laci Kas Tunai.
- [x] Cetak Struk Transaksi Mini / Thermal Berkop Agen BRILink MBB (`modules/brilink/print_receipt.php`).

#### TAHAP 7 — DIVISI DEKORATION & SOUND SISTEM (SELESAI 100%)
- [x] Modul Divisi Dekoration (Penanggung Jawab: **FATKHUR ROHMAN**):
  - Pencatatan job dekorasi pesta & pernikahan, jadwal, lokasi, nilai kontrak vs biaya kru & bahan.
- [x] Modul Divisi Sound Sistem (Penanggung Jawab: **MISBAHUL MUNIR**):
  - Pencatatan sewa audio tata suara lapangan & gedung, daya watt, nilai sewa vs biaya operator, BBM genset.
- [x] Kalkulasi laba bersih masing-masing divisi dan integrasi ke Dashboard utama.

#### TAHAP 8 — KEUANGAN, INVENTARIS & LAPORAN KONSOLIDASI (SELESAI 100%)
- [x] Modul Inventaris & Aset Perusahaan:
  - Master aset per divisi (Mesin roasting, Sound system, Rangka pelaminan dekorasi, EDC, Laptop).
  - Status kondisi aset (BAIK, RUSAK RINGAN, RUSAK BERAT), lokasi, dan penanggung jawab.
  - Cetak Daftar Inventaris Aset Resmi (`modules/inventory/print_assets.php`).
- [x] Modul Kas & Keuangan Terpadu:
  - Buku Kas Besar (Kas Tunai Utama, Rekening Operasional BRI, Kas Coffee Trading).
  - Transaksi Kas Masuk / Keluar dengan klasifikasi lini usaha.
  - Fitur Mutasi Pindah Dana Antar-Kas tanpa double-counting omzet.
- [x] Modul Laporan Manajemen & Laba Rugi Konsolidasi:
  - Rincian laba rugi per lini bisnis (Coffee Trading, Roasting Service, BRILink, Dekoration, Sound Sistem).
  - Pemotongan beban umum operasional kantor pusat.
  - Cetak Laporan Keuangan Konsolidasi Resmi Berkop Surat A4 (`modules/reports/print_financial.php`).
