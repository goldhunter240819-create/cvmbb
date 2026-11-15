<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Core Authentication Manager
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../helpers/utils.php';

class Auth {
    private static ?array $cachedUser = null;

    /**
     * Memproses autentikasi login pengguna
     */
    public static function attempt(string $username, string $password): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.*, r.role_name, r.role_title, e.full_name, e.employee_code, p.position_name, d.division_name
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN divisions d ON e.division_id = d.id
            WHERE (u.username = ? OR u.email = ?)
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            log_audit('LOGIN_FAILED', 'auth', null, "Percobaan login gagal untuk username/email: {$username}");
            return ['status' => false, 'message' => 'Username atau Password salah.'];
        }

        if (!$user['is_active']) {
            log_audit('LOGIN_BLOCKED', 'auth', $user['id'], "Login akun dinonaktifkan: {$username}");
            return ['status' => false, 'message' => 'Akun Anda berstatus nonaktif. Silakan hubungi Administrator.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            log_audit('LOGIN_FAILED', 'auth', $user['id'], "Password salah untuk user: {$username}");
            return ['status' => false, 'message' => 'Username atau Password salah.'];
        }

        // Regenerate session id untuk mencegah Session Fixation
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role_name'];
        $_SESSION['user_role_title'] = $user['role_title'];
        $_SESSION['user_role_id'] = (int)$user['role_id'];
        $_SESSION['user_name'] = $user['full_name'] ?: $user['username'];
        $_SESSION['user_position'] = $user['position_name'] ?: $user['role_title'];
        $_SESSION['user_division'] = $user['division_name'] ?: 'Perusahaan';
        $_SESSION['must_change_password'] = (int)$user['must_change_password'];
        $_SESSION['last_activity'] = time();

        // Update database: last login
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $updateStmt = $db->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?");
        $updateStmt->execute([$ip, $user['id']]);

        log_audit('LOGIN_SUCCESS', 'auth', $user['id'], "Pengguna {$user['username']} berhasil masuk ke sistem.");

        return ['status' => true, 'must_change_password' => (bool)$user['must_change_password']];
    }

    /**
     * Cek apakah ada user yang aktif login
     */
    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // Cek timeout session (default 24 jam = 86400 detik)
        $timeout = 86400;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Wajibkan login untuk mengakses halaman
     */
    public static function requireLogin(?string $redirectPath = null): void {
        if (!self::check()) {
            flash_set('warning', 'Sesi Anda telah berakhir atau belum masuk. Silakan login terlebih dahulu.');
            redirect($redirectPath ?? 'login.php');
            exit;
        }
    }

    /**
     * Ambil data pengguna saat ini
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }

        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.*, r.role_name, r.role_title, e.full_name, e.employee_code, p.position_name, d.division_name
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN divisions d ON e.division_id = d.id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        self::$cachedUser = $stmt->fetch() ?: null;
        return self::$cachedUser;
    }

    /**
     * Ambil user ID saat ini
     */
    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Logout pengguna
     */
    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['user_id'])) {
            log_audit('LOGOUT', 'auth', $_SESSION['user_id'], "Pengguna {$_SESSION['username']} keluar dari sistem.");
        }

        $_SESSION = [];
        if (!headers_sent() && ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
