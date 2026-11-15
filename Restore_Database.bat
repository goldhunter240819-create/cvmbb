@echo off
color 0E
title Setup Database - CV. MAJU BERKAH BERSAMA (MBB)
echo ==================================================
echo   SETUP / IMPORT DATABASE CV. MBB KE XAMPP LOCAL
echo ==================================================
echo.

echo [1/2] Membuat database mbb_management jika belum ada...
if exist "C:\xampp\mysql\bin\mysql.exe" (
    "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS mbb_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    
    echo [2/2] Meng-import struktur tabel dan data riil dari database\mbb_management.sql...
    "C:\xampp\mysql\bin\mysql.exe" -u root mbb_management < database\mbb_management.sql
    
    if %ERRORLEVEL% EQU 0 (
        echo.
        echo ==================================================
        echo   BERHASIL 100%! DATABASE SIAP DIGUNAKAN.
        echo   Buka browser: http://localhost/cvmbb/
        echo   Versi Mobile: http://localhost/cvmbb/apk/
        echo ==================================================
    ) else (
        echo.
        echo Gagal meng-import data. Pastikan MySQL di XAMPP sudah berjalan (Start).
    )
) else (
    echo File mysql.exe tidak ditemukan di C:\xampp\mysql\bin\.
    echo Silakan import manual file database\mbb_management.sql lewat phpMyAdmin.
)

echo.
pause
