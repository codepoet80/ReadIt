<?php
// Triggers a feed refresh + thumbnail backfill, meant to be run from
// cron rather than a browser. This is what makes normal page loads
// fast and lets thumbnails (especially ScienceDaily's, which need an
// extra page fetch per item - see includes/fetch.php) catch up on a
// schedule instead of only trickling in when someone happens to load
// a stale page.
//
// CLI-only on purpose: refreshing is not free (it hits the source
// feeds and, for new items, their article pages), so this must not
// be a public URL anyone can hit repeatedly to hammer them.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: this script is meant to be run from cron, not a browser.\n");
}

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/fetch.php';

$pdo = ri_get_db();

try {
    ri_refresh_all($pdo);
    echo '[' . date('Y-m-d H:i:s') . "] refresh OK\n";
} catch (Exception $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] refresh FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
