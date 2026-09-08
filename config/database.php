<?php
/**
 * CYVANTA - Database connection (PDO singleton)
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = env('DB_DRIVER', 'sqlite');

        if ($driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_DATABASE
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    DB_USERNAME,
                    DB_PASSWORD,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
                return self::$instance;
            } catch (PDOException $e) {
                error_log('[CYVANTA] MySQL connection failed, falling back to SQLite: ' . $e->getMessage());
            }
        }

        // SQLite mode / fallback
        $dbDir = APP_ROOT . '/database';
        if (!is_dir($dbDir)) {
            @mkdir($dbDir, 0777, true);
        }
        $dbPath = $dbDir . '/crimegraph.sqlite';
        $needsInit = !file_exists($dbPath) || filesize($dbPath) === 0;

        try {
            self::$instance = new PDO(
                'sqlite:' . $dbPath,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            self::$instance->exec('PRAGMA foreign_keys = ON;');
            self::$instance->sqliteCreateFunction('NOW', function() {
                return date('Y-m-d H:i:s');
            });

            if ($needsInit) {
                $sqlFile = APP_ROOT . '/database/sqlite_schema.sql';
                if (file_exists($sqlFile)) {
                    self::$instance->exec(file_get_contents($sqlFile));
                }
            }

            return self::$instance;
        } catch (PDOException $e) {
            error_log('[CYVANTA] SQLite connection error: ' . $e->getMessage());
            throw new Exception('Database connection failed. On Hostinger, ensure the `database/` folder is writable (chmod 777 database). Error: ' . $e->getMessage());
        }
    }
}
