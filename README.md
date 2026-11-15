# MBB BUSINESS MANAGEMENT SYSTEM
## ERP Terpadu CV. MAJU BERKAH BERSAMA (Air Hitam, Lampung Barat)

---

Aplikasi **MBB BUSINESS MANAGEMENT SYSTEM** adalah Enterprise Resource Planning (ERP) berbasis web modular yang mengintegrasikan tata kelola organisasi, administrasi, keuangan, persediaan, perdagangan biji kopi (*coffee trading*), pengolahan internal (*coffee production*), jasa maklon sangrai (*coffee roasting service*), keagenan perbankan (*agen BRILink*), serta aktivitas divisi dekorasi dan tata suara (*sound system*).

---

### 1. PERSYARATAN SISTEM & LINGKUNGAN SERVER

- **Web Server**: Apache 2.4+ (Bawaan XAMPP)
- **Bahasa Pemrograman**: PHP Native 8.0, 8.1, atau 8.2+
- **Ekstensi PHP Wajib**:
  - `pdo_mysql`
  - `openssl`
  - `mbstring`
  - `fileinfo`
  - `gd`
- **Database Engine**: MySQL 5.7+ atau MariaDB 10.4+ (Engine **InnoDB**)
- **Browser Modern**: Google Chrome, Mozilla Firefox, Microsoft Edge, atau Safari (Desktop & Mobile)

---

### 2. KREDENSIAL AKUN AWAL SISTEM

| No | Tipe Akun | Username | Password | Role / Hak Akses | Status |
|:---:|:---|:---|:---|:---|:---:|
| 1 | **Developer Super Admin** | `Maul` | `Basenglah123.` | SUPER ADMIN (Akses Penuh) | Aktif |
| 2 | **Administrator Sistem** | `admin` | `admin` | SUPER ADMIN (Akses Penuh) | Aktif |

> [!NOTE]
> Setelah berhasil masuk pertama kali, sangat disarankan untuk mengganti password default melalui menu **Profil > Ganti Password** di pojok kanan atas.

---

### 3. PETUNJUK INSTALASI XAMPP & MENJALANKAN APLIKASI

#### Langkah 1: Penempatan Berkas Proyek
Pastikan folder proyek berada di direktori web server XAMPP:
```
C:\xampp\htdocs\cvmbb\
```
*(atau alias folder `mbb` sesuai konfigurasi Apache).*

#### Langkah 2: Mengaktifkan Service Apache dan MySQL
1. Buka **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache** dan **MySQL** hingga indikator berwarna hijau.

#### Langkah 3: Membuat & Mengimpor Database
1. Buka browser dan akses **phpMyAdmin** di URL:
   ```
   http://localhost/phpmyadmin/
   ```
2. Buat database baru bernama:
   ```
   mbb_management
   ```
   *(Collation: `utf8mb4_unicode_ci`)*.
3. Klik tab **Import** pada database `mbb_management`, pilih berkas:
   ```
   C:\xampp\htdocs\cvmbb\database\database.sql
   ```
   Lalu klik **Go** / **Impor**.

*Atau melalui terminal command prompt:*
```bash
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\cvmbb\database\database.sql"
```

#### Langkah 4: Konfigurasi Koneksi Database
Jika Anda menggunakan password database khusus selain default kosong XAMPP, sesuaikan pada berkas:
`config/database.php`
```php
return [
    'host'     => '127.0.0.1',
    'port'     => '3306',
    'database' => 'mbb_management',
    'username' => 'root',
    'password' => '', // isi password database jika ada
];
```

#### Langkah 5: Membuka Aplikasi
Buka peramban web dan akses URL:
```
http://localhost/cvmbb/
```
*(Halaman akan langsung mengarahkan Anda ke portal login profesional MBB).*

---

### 4. STRUKTUR ORGANISASI AKTIF PERUSAHAAN

Sistem telah diinisialisasi dengan data struktur kepengurusan resmi CV. MAJU BERKAH BERSAMA:

- **KOMISARIS**: KH. MUHAMMAD THOHA
- **DIREKSI**: NUR ISLAH
  - **Divisi Administrasi** (PJ: NURIL HUDA)
  - **Divisi Operasional** (PJ: AHMAD SOBIRIN)
  - **DIREKTUR**: M. ROZIKIN
    - **MANAGER**: M. ILHAM SIFA
      - **Divisi Dekoration** (PJ: FATKHUR ROHMAN)
      - **Divisi Sound Sistem** (PJ: MISBAHUL MUNIR)

Fitur pada menu **Struktur Organisasi**:
1. **Diagram Hierarki Visual Interaktif**: Bagan resmi struktur perusahaan.
2. **Cetak Struktur Berkop Resmi**: Fitur cetak berstandar surat keputusan A4 dengan logo asli MBB dan lembar pengesahan Direktur serta Komisaris.
3. **Mutasi & Rotasi Jabatan**: Mengubah penugasan jabatan/divisi dengan pencatatan riwayat kronologis dan nomor SK.
4. **Master Jabatan & Divisi**: Tambah, edit, dan hapus posisi hierarki tanpa merusak kode program.

---

### 5. CARA MENGGANTI LOGO DAN PROFIL PERUSAHAAN

1. Masuk ke aplikasi dengan akun `Maul` atau `admin`.
2. Pada menu bilah samping (Sidebar), pilih **Sistem & Pengaturan** > **Profil Perusahaan**.
3. Di formulir ini Anda dapat:
   - Mengunggah berkas logo baru (PNG/JPG/WEBP transparan). Logo akan langsung otomatis diperbarui pada favicon tab peramban, kop dokumen, sidebar, dan portal login.
   - Mengubah Nama Perusahaan, Singkatan, Slogan/Tagline.
   - Mengubah Alamat Kantor, Nomor Kontak/WhatsApp, dan Email Resmi.
   - Mengubah Pejabat Penandatangan Dokumen Standar.
4. Klik tombol **Simpan Profil Perusahaan**.

---

### 6. PENCADANGAN (BACKUP) DATABASE

1. Masuk ke menu **Sistem & Pengaturan** > **Backup Database**.
2. Klik tombol **Buat Backup Database Sekarang**.
3. Sistem akan menghasilkan berkas `.sql` utuh yang tersimpan aman di direktori `storage/backups/`.
4. Anda dapat mengunduh berkas cadangan kapan saja dengan mengklik tombol **Unduh File**.

---

### 7. STRUKTUR ARSITEKTUR DIREKTORI

```
cvmbb/
├── assets/
│   ├── css/
│   │   └── mbb-theme.css          # Tema kustom warna brand MBB (Navy, Blue, White)
│   ├── images/
│   │   └── logo.png               # Logo resmi CV. Maju Berkah Bersama & Favicon
│   └── js/
│       └── mbb-app.js             # Skrip interaktif & konfirmasi modal
├── components/
│   ├── header.php                 # Header HTML, CSS, Favicon tab & Auth guard
│   ├── sidebar.php                # Bilah samping navigasi berbasis RBAC
│   ├── topbar.php                 # Header atas, jam kerja, profil & logout
│   └── footer.php                 # Footer penutup & skrip pustaka JS
├── config/
│   ├── app.php                    # Konfigurasi aplikasi & base URL otomatis
│   └── database.php               # Konfigurasi koneksi PDO MySQL
├── core/
│   ├── Auth.php                   # Autentikasi sesi, login/logout, audit log
│   ├── Database.php               # Singleton koneksi PDO & transaksi
│   ├── Permission.php             # RBAC role & permission checker
│   └── Validator.php              # Validator formulir server-side
├── database/
│   └── database.sql               # Skema 35 tabel InnoDB & master seed data
├── helpers/
│   └── utils.php                  # Helper Rupiah, tanggal Indo, CSRF, flash message
├── modules/
│   ├── dashboard/                 # Controller view dashboard
│   ├── organization/              # Bagan hierarki, posisi, mutasi, cetak
│   ├── employees/                 # CRUD anggota pegawai
│   ├── settings/                  # Profil perusahaan, akun user, backup
│   └── [unit-bisnis]/             # Trading, Roastery, BRILink, Sound, Dekor
├── storage/
│   ├── backups/                   # Tempat penyimpanan arsip backup SQL
│   └── logs/                      # Log sistem
├── uploads/
│   └── company/                   # Unggahan logo dan berkas identitas
├── index.php                      # Dashboard eksekutif utama (real DB query)
├── login.php                      # Portal login otentikasi
├── logout.php                     # Handler keluar sistem
└── README.md                      # Dokumentasi komprehensif sistem
```
