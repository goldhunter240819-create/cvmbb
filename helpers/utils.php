<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Global Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Dapatkan Base URL aplikasi
 */
function base_url($path = '') {
    static $base = null;
    if ($base === null) {
        $config = require __DIR__ . '/../config/app.php';
        $base = $config['base_url'];
    }
    return $base . ltrim($path, '/');
}

/**
 * Dapatkan URL Aset publik
 */
function asset_url($path = '') {
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Sanitasi output HTML (XSS prevention)
 */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format angka ke format mata uang Rupiah (IDR)
 */
function format_rupiah($amount, $withPrefix = true) {
    $num = floatval($amount);
    $formatted = number_format($num, 0, ',', '.');
    return $withPrefix ? 'Rp ' . $formatted : $formatted;
}

/**
 * Format tanggal ke Bahasa Indonesia (Contoh: 15 November 2026)
 */
function format_tanggal($dateString, $withTime = false) {
    if (empty($dateString) || $dateString === '0000-00-00' || $dateString === '0000-00-00 00:00:00') {
        return '-';
    }
    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($dateString);
    $d = date('j', $timestamp);
    $m = $bulanIndo[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp);
    
    $result = "$d $m $y";
    if ($withTime) {
        $result .= ' ' . date('H:i', $timestamp) . ' WIB';
    }
    return $result;
}

/**
 * CSRF Protection Token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_validate($redirectUrl = null) {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Sesi keamanan berakhir atau token tidak valid. Silakan coba lagi.');
        if ($redirectUrl) {
            redirect($redirectUrl);
        } else {
            redirect($_SERVER['REQUEST_URI'] ?? 'index.php');
        }
        exit;
    }
    return true;
}

/**
 * Flash Notification System
 */
function flash_set($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type'    => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function flash_has() {
    return !empty($_SESSION['flash_messages']);
}

function flash_get() {
    if (empty($_SESSION['flash_messages'])) {
        return null;
    }
    return array_shift($_SESSION['flash_messages']);
}

function flash_display() {
    if (empty($_SESSION['flash_messages'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash_messages'] as $flash) {
        $type = e($flash['type']);
        $icon = match($type) {
            'success' => 'bi-check-circle-fill',
            'danger'  => 'bi-exclamation-octagon-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            default   => 'bi-info-circle-fill'
        };
        $msg = e($flash['message']);
        $html .= "
        <div class=\"alert alert-{$type} alert-dismissible fade show d-flex align-items-center shadow-sm\" role=\"alert\">
            <i class=\"bi {$icon} fs-5 me-2\"></i>
            <div class=\"flex-grow-1\">{$msg}</div>
            <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
        </div>";
    }
    unset($_SESSION['flash_messages']);
    return $html;
}

/**
 * Redirect Helper
 */
function redirect($path) {
    if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
        $path = base_url($path);
    }
    header("Location: " . $path);
    exit;
}

/**
 * Dapatkan Pengaturan Perusahaan dari Database
 */
function get_company_setting($key, $default = '') {
    static $settings = null;
    if ($settings === null) {
        try {
            require_once __DIR__ . '/../core/Database.php';
            $db = Database::getInstance();
            $stmt = $db->query("SELECT setting_key, setting_value FROM company_settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Log Audit Aktivitas
 */
function log_audit($action, $module, $recordId, $description) {
    try {
        require_once __DIR__ . '/../core/Database.php';
        require_once __DIR__ . '/../core/Auth.php';
        $db = Database::getInstance();
        $user = Auth::user();
        $userId = $user['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, module, record_id, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $module, (string)$recordId, $description, $ip, $ua]);
    } catch (Exception $e) {
        // Fallback log to file
        error_log("Audit log failed: " . $e->getMessage());
    }
}
