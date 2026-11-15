# DATABASE DESIGN DOCUMENTATION
## MBB BUSINESS MANAGEMENT SYSTEM (mbb_management)
### CV. MAJU BERKAH BERSAMA

---

### 1. INFORMASI DATABASE
- **Nama Database**: `mbb_management`
- **Database Engine**: `InnoDB` (Dukungan penuh Relasi Foreign Key, Transaksi ACID, dan Row-Level Locking)
- **Character Set**: `utf8mb4`
- **Collation**: `utf8mb4_unicode_ci`
- **Konvensi Kolom**: Snake case (`created_at`, `user_id`, dsb)
- **Primary Key**: Integer Auto-Increment (`id`)
- **Foreign Key**: Naming convention `[table_singular]_id` (contoh: `division_id`, `role_id`)
- **Timestamps**: `created_at` (DEFAULT CURRENT_TIMESTAMP), `updated_at` (DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)

---

### 2. KELOMPOK MODUL & ENTITAS TABEL

```
  ┌─────────────────────────┐     ┌─────────────────────────┐
  │   SISTEM & HAK AKSES    │     │   ORGANISASI & PEGAWAI  │
  ├─────────────────────────┤     ├─────────────────────────┤
  │ - roles                 │     │ - divisions             │
  │ - permissions           │     │ - positions             │
  │ - role_permissions      │     │ - employees             │
  │ - users                 │     │ - position_histories    │
  │ - company_settings      │     └─────────────────────────┘
  │ - audit_logs            │
  └─────────────────────────┘
               │                               │
               ▼                               ▼
  ┌─────────────────────────┐     ┌─────────────────────────┐
  │       PERSEDIAAN        │     │     COFFEE TRADING      │
  ├─────────────────────────┤     ├─────────────────────────┤
  │ - categories            │     │ - farmers               │
  │ - products              │     │ - suppliers             │
  │ - warehouses            │     │ - customers             │
  │ - stock_movements       │     │ - purchases             │
  └─────────────────────────┘     │ - purchase_details      │
               │                  │ - sales                 │
               │                  │ - sale_details          │
               │                  └─────────────────────────┘
               ▼                               │
  ┌─────────────────────────┐                  ▼
  │    COFFEE PRODUCTION    │     ┌─────────────────────────┐
  ├─────────────────────────┤     │     ROASTING SERVICE    │
  │ - production_orders     │     ├─────────────────────────┤
  │ - production_items      │     │ - roasting_orders       │
  └─────────────────────────┘     │ - roasting_order_items  │
               │                  └─────────────────────────┘
               ▼                               │
  ┌─────────────────────────┐                  ▼
  │     AGEN BRILINK        │     ┌─────────────────────────┐
  ├─────────────────────────┤     │    DIVISI LAIN & ASET   │
  │ - brilink_accounts      │     ├─────────────────────────┤
  │ - brilink_transactions  │     │ - division_activities   │
  │ - brilink_daily_reconcile     │ - inventory_assets      │
  └─────────────────────────┘     │ - inventory_mutations   │
               │                  └─────────────────────────┘
               ▼                               │
  ┌────────────────────────────────────────────┴────────────┐
  │                   KEUANGAN TERPADU                      │
  ├─────────────────────────────────────────────────────────┤
  │ - cash_accounts                                         │
  │ - cash_transactions                                     │
  │ - expenses                                              │
  │ - receivables (Piutang Usaha)                           │
  │ - payables (Utang Usaha)                                │
  └─────────────────────────────────────────────────────────┘
               │
               ▼
  ┌─────────────────────────┐
  │ ADMINISTRASI & SURAT    │
  ├─────────────────────────┤
  │ - incoming_letters      │
  │ - outgoing_letters      │
  │ - documents             │
  │ - letter_templates      │
  └─────────────────────────┘
```

---

### 3. DETAIL SPESIFIKASI TABEL

#### A. MODUL SISTEM & PENGGUNA
1. **`roles`**: Daftar hak peran sistem (Super Admin, Komisaris, Direksi, Direktur, Manager, Administrasi, Operasional, Petugas Divisi).
2. **`permissions`**: Daftar izin akses kode (contoh: `dashboard.view`, `organization.manage`, `coffee_trading.create`, dll).
3. **`role_permissions`**: Pivot table relasi many-to-many role dengan permission.
4. **`users`**: Akun pengguna login aplikasi (username, email, password_hash, role_id, employee_id, status_active, must_change_password).
5. **`company_settings`**: Key-value data perusahaan (nama perusahaan, alamat, logo, telepon, rekening, penandatangan surat).
6. **`audit_logs`**: Log jejak digital sistem (user_id, action, module, record_id, ip_address, user_agent, details, created_at).

#### B. MODUL ORGANISASI & SUMBER DAYA MANUSIA
7. **`divisions`**: Daftar divisi perusahaan (Administrasi, Operasional, Dekoration, Sound Sistem, dll) dengan parent hierarchy jika ada.
8. **`positions`**: Master jabatan struktural perusahaan (Komisaris, Direksi, Direktur, Manager, Kepala Divisi, Staf) dengan relasi `parent_position_id` untuk diagram hierarki visual.
9. **`employees`**: Data anggota/karyawan perusahaan (NIP/NIK, nama, telepon, alamat, division_id, position_id, status_kerja).
10. **`position_histories`**: Catatan riwayat mutasi, promosi, atau pergantian posisi anggota perusahaan.

#### C. MODUL PERSEDIAAN & MASTER BARANG
11. **`categories`**: Kategori produk (Green Beans, Roasted Beans, Ground Coffee, Kemasan, Perlengkapan, dll).
12. **`warehouses`**: Gudang penyimpanan (Gudang Utama Air Hitam, Gudang Produksi, Gudang Transit).
13. **`products`**: Master barang (kode_sku, nama_produk, kategori_id, satuan, harga_beli_terakhir, harga_jual, stok_minimum, is_raw_material).
14. **`stock_movements`**: Kartu stok mutasi persediaan masuk/keluar lengkap dengan jenis transaksi (PURCHASE, SALE, PRODUCTION_IN, PRODUCTION_OUT, SHRINKAGE, ADJUSTMENT) dan nomor referensi.

#### D. MODUL COFFEE TRADING
15. **`farmers`**: Rekanan petani kopi lokal Lampung Barat (nama, nomor_kontak, desa, kelompok_tani).
16. **`suppliers`**: Rekanan supplier/pedagang besar kopi atau kemasan.
17. **`customers`**: Data pembeli biji kopi, kopi olahan, atau jasa.
18. **`purchases`**: Header transaksi pembelian kopi (no_faktur, tanggal, supplier_type [FARMER/SUPPLIER], partner_id, total_bruto, total_potongan, total_netto, status_bayar).
19. **`purchase_details`**: Detail timbangan biji kopi (product_id, berat_kg, kadar_air, potongan_kotoran_persen, berat_bersih_kg, harga_per_kg, subtotal).
20. **`sales`**: Header transaksi penjualan kopi (no_invoice, tanggal, customer_id, total_amount, diskon, ppn, grand_total, status_pembayaran).
21. **`sale_details`**: Detail produk yang dijual (product_id, qty_kg, harga_satuan, subtotal).

#### E. MODUL COFFEE PRODUCTION & ROASTING SERVICE
22. **`production_orders`**: Perintah kerja roasting/produksi internal (no_batch, tanggal, penanggung_jawab_id, berat_bahan_baku_kg, berat_hasil_kg, persentase_susut, hpp_total, hpp_per_kg, status).
23. **`production_items`**: Komponen bahan baku yang digunakan dan produk jadi yang dihasilkan.
24. **`roasting_orders`**: Pesanan jasa roasting dari pelanggan luar (no_order, customer_id, tanggal_masuk, estimasi_selesai, profile_roast [LIGHT/MEDIUM/DARK], berat_mentah_kg, berat_matang_kg, tarif_jasa_per_kg, total_biaya, status_pengerjaan, status_bayar).

#### F. MODUL AGEN BRILINK
25. **`brilink_accounts`**: Master mesin EDC / Rekening operasional BRILink (nama_perangkat/akun, nomor_rekening, saldo_awal, saldo_saat_ini).
26. **`brilink_transactions`**: Pencatatan transaksi harian (no_referensi, tanggal, jenis_transaksi [TRANSFER, TARIK_TUNAI, SETOR_TUNAI, BAYAR_TAGIHAN, PULSA_DIGITAL], nominal_transaksi, biaya_admin, fee_perusahaan, fee_bank, total_diterima_kas, status).
27. **`brilink_daily_reconcile`**: Rekonsiliasi harian kas fisik agen vs mutasi saldo mutasi rekening BRI.

#### G. MODUL KEUANGAN TERPADU
28. **`cash_accounts`**: Buku rekening kas perusahaan (Kas Utama Kantor, Kas Operasional Kopi, Kas Kasir BRILink, Bank Mandiri/BRI Operasional).
29. **`cash_transactions`**: Mutasi jurnal kas masuk (DEBET) dan kas keluar (KREDIT) dengan tagging divisi dan lini usaha.
30. **`expenses`**: Pengeluaran operasional terinci (beban listrik, BBM, perawatan mesin roasting, konsumsi, gaji).
31. **`receivables`**: Catatan piutang dari penjualan kopi atau jasa yang belum lunas.
32. **`payables`**: Catatan utang dagang ke petani/supplier.

#### H. MODUL DIVISI OPERASIONAL, DEKORATION, SOUND SISTEM & ASET
33. **`division_activities`**: Pencatatan proyek acara, job dekorasi, job sound system (divisi_id, nama_kegiatan, lokasi, tanggal_mulai, tanggal_selesai, penanggung_jawab, estimasi_biaya, pendapatan, status).
34. **`inventory_assets`**: Master inventaris perusahaan (kode_aset, nama_barang, division_id, tanggal_perolehan, nilai_perolehan, kondisi [BAIK, RUSAK_RINGAN, RUSAK_BERAT], lokasi_penyimpanan).
35. **`inventory_mutations`**: Log peminjaman, pemindahan, atau perawatan aset/perlengkapan.

#### I. MODUL ADMINISTRASI & TATA PERSURATAN
36. **`incoming_letters`**: Arsip surat masuk (no_agenda, no_surat_asal, pengirim, tanggal_surat, perihal, file_attachment).
37. **`outgoing_letters`**: Arsip surat keluar (no_surat_otomatis, tujuan, perihal, tanggal_kirim, penandatangan, status_surat).
38. **`documents`**: Berkas digital legalitas, kontrak, sertifikat, dan SOP perusahaan.
