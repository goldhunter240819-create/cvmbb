@echo off
color 0B
title Pull dari GitHub - CV. MAJU BERKAH BERSAMA (MBB)
echo ==================================================
echo   TOMBOL AJAIB - PULL KODE CV. MBB DARI GITHUB
echo ==================================================
echo.

echo [1/2] Menarik pembaruan kode terbaru dari GitHub...
git pull origin main

echo.
echo [2/2] Apakah Anda ingin meng-import / update database MySQL sekarang?
set /p syncDb="Ketik Y lalu Enter jika ingin update database, atau tekan Enter untuk lewati (Y/N): "
if /i "%syncDb%"=="Y" (
    echo.
    echo Sedang memperbarui database...
    if exist "C:\xampp\mysql\bin\mysql.exe" (
        "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS mbb_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        "C:\xampp\mysql\bin\mysql.exe" -u root mbb_management < database\mbb_management.sql
        echo Database mbb_management berhasil disinkronkan!
    ) else (
        echo File mysql.exe tidak ditemukan di C:\xampp\mysql\bin\.
    )
)

echo.
echo ==================================================
echo   SELESAI! KODE & SISTEM CV MBB SUDAH TERUPDATE.
echo ==================================================
echo.
pause
