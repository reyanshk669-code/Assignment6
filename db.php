<?php
/**
 * OmniMart Multi-Vendor E-Commerce Platform
 * Database Connection & Query Helper (Supports MySQL & Zero-Config SQLite Fallback)
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;
    private static bool $connectionAttempted = false;
    private static ?string $connectionError = null;
    private static string $activeDriver = 'mysql';

    /**
     * Get singleton PDO instance
     */
    public static function getConnection(): ?PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (self::$connectionAttempted && self::$instance === null) {
            return null;
        }

        self::$connectionAttempted = true;

        $sqliteDbPath = __DIR__ . '/omnimart.sqlite';

        // 1. If SQLite database file is active or requested
        if (file_exists($sqliteDbPath) && (!defined('FORCE_MYSQL') || !FORCE_MYSQL)) {
            try {
                self::$instance = new PDO("sqlite:" . $sqliteDbPath, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                self::$instance->exec("PRAGMA foreign_keys = ON;");

                // Compatibility helper functions for SQLite
                if (method_exists(self::$instance, 'sqliteCreateFunction')) {
                    self::$instance->sqliteCreateFunction('CURDATE', function() { return date('Y-m-d'); });
                    self::$instance->sqliteCreateFunction('NOW', function() { return date('Y-m-d H:i:s'); });
                    self::$instance->sqliteCreateFunction('GREATEST', function($a, $b) { return max((float)$a, (float)$b); });
                }

                self::$activeDriver = 'sqlite';
                return self::$instance;
            } catch (Exception $e) {
                // fallback to mysql
            }
        }

        // 2. MySQL Connection
        $host = DB_HOST;
        $port = DB_PORT;
        $dbname = DB_NAME;
        $username = DB_USER;
        $password = DB_PASS;
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];

        try {
            self::$instance = new PDO($dsn, $username, $password, $options);
            self::$activeDriver = 'mysql';
            return self::$instance;
        } catch (PDOException $e) {
            self::$connectionError = $e->getMessage();

            // If not currently on setup.php, redirect to setup.php
            $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
            if ($currentScript !== 'setup.php' && php_sapi_name() !== 'cli') {
                header("Location: " . baseUrl('setup.php'));
                exit;
            }

            return null;
        }
    }

    public static function getDriver(): string {
        return self::$activeDriver;
    }

    public static function getError(): ?string {
        return self::$connectionError;
    }
}

/**
 * Convenient Global Database Wrapper Functions
 */
function getDB(): ?PDO {
    return Database::getConnection();
}

function dbFetchAll(string $sql, array $params = []): array {
    $db = getDB();
    if (!$db) return [];
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database Error [fetchAll]: " . $e->getMessage() . " | SQL: $sql");
        return [];
    }
}

function dbFetchOne(string $sql, array $params = []): ?array {
    $db = getDB();
    if (!$db) return null;
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();
        return $res !== false ? $res : null;
    } catch (PDOException $e) {
        error_log("Database Error [fetchOne]: " . $e->getMessage() . " | SQL: $sql");
        return null;
    }
}

function dbExecute(string $sql, array $params = []): bool {
    $db = getDB();
    if (!$db) return false;
    try {
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Database Error [execute]: " . $e->getMessage() . " | SQL: $sql");
        return false;
    }
}

function dbLastInsertId(): ?string {
    $db = getDB();
    return $db ? $db->lastInsertId() : null;
}
