@echo off
color 0A
title Push ke GitHub - CV. MAJU BERKAH BERSAMA (MBB)
echo ==================================================
echo   TOMBOL AJAIB - PUSH KODE CV. MBB KE GITHUB
echo ==================================================
echo.

set /p pesan="Masukkan pesan commit (atau tekan Enter untuk otomatis): "
if "%pesan%"=="" set pesan=Update sistem CV MBB - %date% %time%

echo.
echo [1/4] Meng-export database terbaru (mbb_management.sql)...
if exist "C:\xampp\mysql\bin\mysqldump.exe" (
    "C:\xampp\mysql\bin\mysqldump.exe" -u root mbb_management > database\mbb_management.sql
    echo     Database berhasil di-backup ke database\mbb_management.sql
) else (
    echo     mysqldump tidak ditemukan, melewati backup db...
)

echo.
echo [2/4] Menambahkan semua file ke Git (git add .)...
git add .

echo.
echo [3/4] Menyimpan perubahan lokal (git commit)...
git commit -m "%pesan%"

echo.
echo [4/4] Sinkronisasi & Mengirim ke GitHub (git push)...
git pull origin main --rebase
git push origin main

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ==================================================
    echo   BERHASIL! KODE CV MBB SUDAH TERSIMPAN DI GITHUB.
    echo ==================================================
) else (
    echo.
    echo ==================================================
    echo   PERHATIAN: Push mengalami kendala.
    echo   Mencoba push standar...
    echo ==================================================
    git push origin main
)

echo.
pause
