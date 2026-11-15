<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Core Permission & RBAC Manager
 */

require_once __DIR__ . '/Database.php';

class Permission {
    private static ?array $userPermissions = null;

    /**
     * Cek apakah user saat ini memiliki izin permission tertentu
     */
    public static function can(string $permissionKey): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // Developer dan Super Admin memiliki semua akses operasional
        if (!empty($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['super_admin', 'developer'], true)) {
            return true;
        }

        $permissions = self::getPermissionsForUser($_SESSION['user_role_id'] ?? 0);
        return in_array($permissionKey, $permissions, true);
    }

    /**
     * Cek apakah user memiliki peran (role) tertentu
     */
    public static function hasRole(string|array $roles): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $currentRole = $_SESSION['user_role'] ?? '';
        if (is_array($roles)) {
            return in_array($currentRole, $roles, true);
        }
        return $currentRole === $roles;
    }

    /**
     * Wajibkan izin permission, jika tidak miliki redirect/tampilkan 403 Forbidden
     */
    public static function require(string $permissionKey): void {
        if (!self::can($permissionKey)) {
            http_response_code(403);
            require_once __DIR__ . '/../helpers/utils.php';
            flash_set('danger', 'Akses Ditolak: Anda tidak memiliki izin untuk mengakses halaman tersebut.');
            redirect('index.php');
            exit;
        }
    }

    /**
     * Cek apakah pengguna saat ini adalah Developer (Maul)
     */
    public static function isDeveloper(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return ($_SESSION['user_role'] ?? '') === 'developer' || ($_SESSION['username'] ?? '') === 'Maul';
    }

    /**
     * Khusus membatasi akses hanya untuk akun Developer
     */
    public static function requireDeveloper(): void {
        if (!self::isDeveloper()) {
            http_response_code(403);
            require_once __DIR__ . '/../helpers/utils.php';
            flash_set('danger', 'Akses Ditolak: Portal Developer khusus untuk Developer / System Architect (Maul).');
            redirect('index.php');
            exit;
        }
    }

    /**
     * Wajibkan salah satu dari role yang ditentukan
     */
    public static function requireRole(string|array $roles): void {
        if (!self::hasRole($roles)) {
            http_response_code(403);
            require_once __DIR__ . '/../helpers/utils.php';
            flash_set('danger', 'Akses Ditolak: Hak akses akun Anda tidak mencukupi.');
            redirect('index.php');
            exit;
        }
    }

    /**
     * Ambil daftar permission berdasarkan role ID
     */
    public static function getPermissionsForUser(int $roleId): array {
        if (self::$userPermissions !== null) {
            return self::$userPermissions;
        }

        if ($roleId <= 0) {
            return [];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT p.permission_key 
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$roleId]);
        self::$userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return self::$userPermissions;
    }
}
