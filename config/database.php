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
        $dbPath = APP_ROOT . '/database/crimegraph.sqlite';
        $needsInit = !file_exists($dbPath) || filesize($dbPath) === 0;

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

        try { self::$instance->exec('ALTER TABLE cases ADD COLUMN agency_reference TEXT DEFAULT NULL;'); } catch (Throwable $e) {}
        try { self::$instance->exec('ALTER TABLE cases ADD COLUMN is_unsolved INTEGER NOT NULL DEFAULT 1;'); } catch (Throwable $e) {}
        try { self::$instance->exec('ALTER TABLE cases ADD COLUMN osint_keywords TEXT DEFAULT NULL;'); } catch (Throwable $e) {}
        try {
            self::$instance->exec('CREATE TABLE IF NOT EXISTS osint_feeds (id INTEGER PRIMARY KEY AUTOINCREMENT, case_id INTEGER DEFAULT NULL, source_name TEXT NOT NULL, feed_url TEXT DEFAULT NULL, title TEXT NOT NULL, content TEXT NOT NULL, url TEXT DEFAULT NULL, published_at DATETIME DEFAULT CURRENT_TIMESTAMP, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE);');
        } catch (Throwable $e) {}
        try {
            self::$instance->exec('CREATE TABLE IF NOT EXISTS cross_case_matches (id INTEGER PRIMARY KEY AUTOINCREMENT, source_case_id INTEGER NOT NULL, target_case_id INTEGER NOT NULL, entity_name TEXT NOT NULL, entity_type TEXT NOT NULL, confidence_score INTEGER NOT NULL DEFAULT 85, match_details TEXT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (source_case_id) REFERENCES cases(id) ON DELETE CASCADE, FOREIGN KEY (target_case_id) REFERENCES cases(id) ON DELETE CASCADE);');
        } catch (Throwable $e) {}

        return self::$instance;
    }
}
