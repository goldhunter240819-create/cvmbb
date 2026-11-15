<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Core Database Handler (PDO Singleton)
 */

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/database.php';
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            
            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            } catch (PDOException $e) {
                // Di mode dev tampilkan pesan jelas, di mode produksi sembunyikan detail sensitif
                die("Koneksi Database Gagal: " . htmlspecialchars($e->getMessage()));
            }
        }
        return self::$instance;
    }

    public static function beginTransaction(): bool {
        return self::getInstance()->beginTransaction();
    }

    public static function commit(): bool {
        return self::getInstance()->commit();
    }

    public static function rollBack(): bool {
        return self::getInstance()->rollBack();
    }

    public static function lastInsertId(): string {
        return self::getInstance()->lastInsertId();
    }
}
