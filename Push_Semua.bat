@echo off
color 0A
title Push ke GitHub - CV. MAJU BERKAH BERSAMA (MBB)
echo ==================================================
echo   TOMBOL AJAIB - PUSH KODE CV. MBB KE GITHUB
echo ==================================================
echo.

set /p pesan="Masukkan pesan commit (atau tekan Enter untuk otomatis): "
if "%pesan%"=="" set pesan=Update kode sistem CV MBB - %date% %time%

echo.
echo [1/3] Menambahkan semua file ke Git (git add .)...
git add .

echo.
echo [2/3] Menyimpan perubahan lokal (git commit)...
git commit -m "%pesan%"

echo.
echo [3/3] Sinkronisasi & Mengirim ke GitHub (git push)...
git pull origin main --rebase
git push origin main

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ==================================================
    echo   BERHASIL! KODE CV MBB SUDAH TERSIMPAN DI GITHUB.
    echo   (Database live aman dan tidak terganggu)
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
