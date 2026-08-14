<?php
require_once __DIR__ . '/config.php';

function ri_get_db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $isNew = !file_exists(DB_PATH);

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = WAL');

    if ($isNew) {
        ri_init_schema($pdo);
    }
    ri_migrate($pdo);

    return $pdo;
}

function ri_init_schema($pdo)
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            guid TEXT NOT NULL UNIQUE,
            source TEXT NOT NULL,
            title TEXT NOT NULL,
            link TEXT NOT NULL,
            description TEXT,
            pub_date INTEGER NOT NULL,
            fetched_at INTEGER NOT NULL,
            thumbnail TEXT,
            thumbnail_source TEXT,
            full_image TEXT,
            thumbnail_checked INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_items_pub_date ON items (pub_date DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_items_source ON items (source)');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS meta (
            key TEXT PRIMARY KEY,
            value TEXT
        )'
    );
}

// Adds columns introduced after a site is already deployed, so
// upgrading is just "upload the new files" with no manual DB step.
function ri_migrate($pdo)
{
    $cols = array();
    foreach ($pdo->query('PRAGMA table_info(items)') as $col) {
        $cols[] = $col['name'];
    }
    if (!in_array('thumbnail', $cols)) {
        $pdo->exec('ALTER TABLE items ADD COLUMN thumbnail TEXT');
    }
    if (!in_array('thumbnail_source', $cols)) {
        $pdo->exec('ALTER TABLE items ADD COLUMN thumbnail_source TEXT');
    }
    if (!in_array('full_image', $cols)) {
        $pdo->exec('ALTER TABLE items ADD COLUMN full_image TEXT');
    }
    if (!in_array('thumbnail_checked', $cols)) {
        $pdo->exec('ALTER TABLE items ADD COLUMN thumbnail_checked INTEGER NOT NULL DEFAULT 0');
    }
    // Upgrading from the pre-local-image version: any thumbnail value
    // present is a raw remote URL (possibly WebP) rather than a local
    // JPEG. Move it to thumbnail_source and force a regeneration pass.
    if (!in_array('thumbnail_source', $cols)) {
        $pdo->exec(
            "UPDATE items SET thumbnail_source = thumbnail, thumbnail = NULL, thumbnail_checked = 0
             WHERE thumbnail IS NOT NULL"
        );
    }
}

function ri_meta_get($pdo, $key, $default = null)
{
    $stmt = $pdo->prepare('SELECT value FROM meta WHERE key = ?');
    $stmt->execute(array($key));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : $default;
}

function ri_meta_set($pdo, $key, $value)
{
    // INSERT OR REPLACE rather than ON CONFLICT DO UPDATE: some shared
    // hosts still ship SQLite older than 3.24 which lacks upsert syntax.
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)');
    $stmt->execute(array($key, $value));
}
