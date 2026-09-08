<?php
/**
 * CYVANTA - Database Migration Script for NLP & Relationship Evidence Overhaul
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::connect();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "Running NLP database migration for driver `$driver`...\n";

    // 1. Create rejected_entities table
    if ($driver === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS rejected_entities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                case_id INTEGER DEFAULT NULL,
                document_id INTEGER DEFAULT NULL,
                candidate TEXT NOT NULL,
                predicted_type TEXT NOT NULL,
                reason TEXT NOT NULL,
                source_text TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS rejected_entities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                case_id INT DEFAULT NULL,
                document_id INT DEFAULT NULL,
                candidate VARCHAR(255) NOT NULL,
                predicted_type VARCHAR(80) NOT NULL,
                reason VARCHAR(255) NOT NULL,
                source_text TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
                FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
            ) ENGINE=InnoDB;
        ");
    }
    echo "✓ `rejected_entities` table ready.\n";

    // 2. Add columns to `relationships` table if not existing
    $cols = [];
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA table_info(relationships)");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[] = $r['name'];
        }
    } else {
        $stmt = $pdo->query("SHOW COLUMNS FROM relationships");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[] = $r['Field'];
        }
    }

    if (!in_array('confidence', $cols, true)) {
        $pdo->exec("ALTER TABLE relationships ADD COLUMN confidence INTEGER NOT NULL DEFAULT 80");
        echo "✓ Added `confidence` to `relationships`.\n";
    }
    if (!in_array('evidence_text', $cols, true)) {
        $pdo->exec("ALTER TABLE relationships ADD COLUMN evidence_text TEXT DEFAULT NULL");
        echo "✓ Added `evidence_text` to `relationships`.\n";
    }
    if (!in_array('source_page', $cols, true)) {
        $pdo->exec("ALTER TABLE relationships ADD COLUMN source_page INTEGER DEFAULT 1");
        echo "✓ Added `source_page` to `relationships`.\n";
    }
    if (!in_array('extraction_timestamp', $cols, true)) {
        $pdo->exec("ALTER TABLE relationships ADD COLUMN extraction_timestamp DATETIME DEFAULT NULL");
        echo "✓ Added `extraction_timestamp` to `relationships`.\n";
    }

    // 3. Add `possible_aliases` to `entities` table if not existing
    $entityCols = [];
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA table_info(entities)");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $entityCols[] = $r['name'];
        }
    } else {
        $stmt = $pdo->query("SHOW COLUMNS FROM entities");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $entityCols[] = $r['Field'];
        }
    }

    if (!in_array('possible_aliases', $entityCols, true)) {
        $pdo->exec("ALTER TABLE entities ADD COLUMN possible_aliases TEXT DEFAULT NULL");
        echo "✓ Added `possible_aliases` to `entities`.\n";
    }

    // 4. Run seeder to populate all new Entity & Relationship types
    require __DIR__ . '/seed.php';

    echo "NLP Schema migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
