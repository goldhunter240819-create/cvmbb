# ENTITY RELATIONSHIP DIAGRAM (ERD)
## MBB BUSINESS MANAGEMENT SYSTEM (mbb_management)
### CV. MAJU BERKAH BERSAMA

---

### 1. DIAGRAM MERMAID ENTITAS & RELASI

```mermaid
erDiagram
    DIVISIONS ||--o{ POSITIONS : "memiliki jabatan"
    POSITIONS ||--o{ POSITIONS : "hirarki atasan-bawahan"
    POSITIONS ||--o{ EMPLOYEES : "dijabat oleh"
    DIVISIONS ||--o{ EMPLOYEES : "menampung anggota"
    EMPLOYEES ||--o| USERS : "diasosiasikan dengan akun"
    ROLES ||--o{ USERS : "memberi peran"
    ROLES ||--o{ ROLE_PERMISSIONS : "memiliki relasi"
    PERMISSIONS ||--o{ ROLE_PERMISSIONS : "diberikan ke peran"
    USERS ||--o{ AUDIT_LOGS : "mencatat aktivitas"
    
    EMPLOYEES ||--o{ POSITION_HISTORIES : "memiliki riwayat"
    POSITIONS ||--o{ POSITION_HISTORIES : "posisi riwayat"
    DIVISIONS ||--o{ POSITION_HISTORIES : "divisi riwayat"

    DIVISIONS ||--o{ INVENTORY_ASSETS : "memiliki aset"
    DIVISIONS ||--o{ DIVISION_ACTIVITIES : "menjalankan kegiatan"

    CATEGORIES ||--o{ PRODUCTS : "mengelompokkan"
    PRODUCTS ||--o{ STOCK_MOVEMENTS : "pergerakan stok"
    WAREHOUSES ||--o{ STOCK_MOVEMENTS : "disimpan di"

    FARMERS ||--o{ PURCHASES : "menjual kopi ke MBB"
    SUPPLIERS ||--o{ PURCHASES : "menyuplai ke MBB"
    PURCHASES ||--o{ PURCHASE_DETAILS : "memiliki baris timbangan"
    PRODUCTS ||--o{ PURCHASE_DETAILS : "barang dibeli"

    CUSTOMERS ||--o{ SALES : "membeli dari MBB"
    SALES ||--o{ SALE_DETAILS : "memiliki item penjualan"
    PRODUCTS ||--o{ SALE_DETAILS : "barang dijual"

    CUSTOMERS ||--o{ ROASTING_ORDERS : "memesan jasa roasting"
    USERS ||--o{ PRODUCTION_ORDERS : "operator produksi"

    CASH_ACCOUNTS ||--o{ CASH_TRANSACTIONS : "mutasi saldo kas"
    USERS ||--o{ CASH_TRANSACTIONS : "kasir / pencatat"
    CASH_ACCOUNTS ||--o{ EXPENSES : "sumber dana beban"

    BRILINK_ACCOUNTS ||--o{ BRILINK_TRANSACTIONS : "menampung transaksi"
    USERS ||--o{ BRILINK_TRANSACTIONS : "operator brilink"
```

---

### 2. KARDINALITAS & LOGIKA RELASI PENTING

1. **User vs Employee (1 : 0..1)**:
   - Satu pegawai *bisa* memiliki akun sistem, tetapi *tidak wajib*.
   - Satu akun sistem terikat pada satu pegawai yang diverifikasi.
   - Hak akses login sistem (`role_id`) tidak sama dengan jabatan struktural (`position_id`).

2. **Position Parent-Child (Hierarki Organisasi)**:
   - `positions.parent_position_id` merujuk kembali ke `positions.id`.
   - Pola ini memungkinkan pembentukan pohon hierarki dinamis (Komisaris -> Direksi -> Direktur -> Manager -> Divisi).

3. **Stock & Accounting Integrity**:
   - Pembelian (`purchases`) dan Penjualan (`sales`) menggerakkan tabel `stock_movements` dan `cash_transactions` secara atomik di dalam satu `DB::beginTransaction()`.
   - Modul BRILink mencatat biaya admin dan fee jasa ke `cash_transactions` dengan referensi `source_type = 'BRILINK'`.

4. **Multi-Divisi Activity Tracking**:
   - Proyek dekorasi atau penyewaan sound sistem dapat mencatat biaya pengeluaran dan pemasukan langsung terhubung ke kas perusahaan.
