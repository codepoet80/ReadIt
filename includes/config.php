<?php
// Site-wide settings. Edit these to taste.

define('SITE_NAME', 'ReadIt');
define('SITE_TAGLINE', 'scroll without the brainrot');

// If this site lives in a subdirectory rather than at your domain's
// root (e.g. https://example.com/ReadIt/ instead of https://example.com/),
// set this to that path - leading slash, no trailing slash. Leave as
// '' if it's hosted at the root. Every internal link, stylesheet, icon,
// and generated thumbnail URL is built from this, via ri_url() in
// includes/render.php - get it right here rather than editing paths
// throughout the templates.
define('BASE_PATH', '/ReadIt');

define('DB_PATH', __DIR__ . '/../data/feeds.sqlite');

// How long a fetched feed is considered fresh, in seconds, before
// index.php will try to re-fetch it on the next page load.
define('CACHE_TTL', 1200); // 20 minutes

// How many items to keep per source. Older rows get pruned after each
// refresh so the database doesn't grow forever.
define('MAX_ITEMS_PER_SOURCE', 300);

define('ITEMS_PER_PAGE', 25);

// Feeds without a built-in thumbnail (currently ScienceDaily) get one
// backfilled by scraping og:image off the linked article, then
// re-encoded locally as a JPEG (see includes/images.php - this is
// what makes ScienceDaily's WebP images visible on browsers, like
// old WebKit, that can't decode WebP). This caps how many of those
// extra fetches happen per refresh, so a page load that triggers a
// refresh never has to wait on dozens of them; leftovers are picked
// up on the next refresh cycle.
define('MAX_THUMBNAIL_BACKFILL_PER_REFRESH', 8);

// New items whose feed already gave us an image URL (Psychology
// Today's media:content) skip that bounded backfill queue entirely -
// generating their local JPEG is just one image fetch, so it happens
// right away in ri_refresh_all, up to this per-refresh cap.
define('MAX_IMMEDIATE_THUMBNAILS_PER_REFRESH', 20);

// Local, pre-cropped image derivatives live here, served directly as
// static files - no PHP involved in serving them.
define('THUMBNAIL_DIR', __DIR__ . '/../thumbs');
define('THUMBNAIL_URL_PATH', BASE_PATH . '/thumbs');
define('THUMBNAIL_SIZE', 140);       // square list thumbnail, px
define('FULL_IMAGE_MAX_WIDTH', 640); // uncropped detail-page image, px
define('THUMBNAIL_JPEG_QUALITY', 82);

// key => array(label shown on the site, feed url)
$GLOBALS['FEEDS'] = array(
    'sciencedaily' => array(
        'label' => 'ScienceDaily',
        'url'   => 'https://www.sciencedaily.com/rss/all.xml',
    ),
    'psychologytoday' => array(
        'label' => 'Psychology Today',
        'url'   => 'https://www.psychologytoday.com/us/news/feed',
    ),
);
