<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Logout Handler
 */

require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/helpers/utils.php';

Auth::logout();
flash_set('info', 'Anda telah berhasil keluar dari sistem.');

if (isset($_GET['from']) && $_GET['from'] === 'apk') {
    redirect('apk/login.php');
} else {
    redirect('login.php');
}
