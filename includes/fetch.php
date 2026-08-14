<?php
require_once __DIR__ . '/db.php';

// Fetches raw bytes from a URL. Prefers curl (handles gzip, redirects,
// TLS SNI more reliably) but falls back to a stream context so this
// still works on hosts without the curl extension.
function ri_http_get($url, $timeout = 15)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // accept gzip/deflate
        curl_setopt($ch, CURLOPT_USERAGENT, 'ReadIt/1.0 (+personal RSS reader)');
        $body = curl_exec($ch);
        $ok = ($body !== false) && (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200);
        @curl_close($ch);
        return $ok ? $body : false;
    }

    $context = stream_context_create(array(
        'http' => array(
            'method'  => 'GET',
            'timeout' => $timeout,
            'header'  => "User-Agent: ReadIt/1.0 (+personal RSS reader)\r\n",
        ),
    ));
    $body = @file_get_contents($url, false, $context);
    return $body === false ? false : $body;
}

// Pulls a thumbnail URL out of an RSS <item>, if the feed bothered to
// include one. Checks (in order) media:content, media:thumbnail, then
// a plain <enclosure>. Returns '' if none look like an image.
function ri_extract_item_thumbnail($item)
{
    $media = $item->children('http://search.yahoo.com/mrss/');

    if (isset($media->content)) {
        foreach ($media->content as $content) {
            $attrs = $content->attributes();
            $type = (string) $attrs['type'];
            $url = (string) $attrs['url'];
            if ($url !== '' && ($type === '' || strpos($type, 'image') === 0)) {
                return $url;
            }
        }
    }

    if (isset($media->thumbnail)) {
        $url = (string) $media->thumbnail->attributes()->url;
        if ($url !== '') {
            return $url;
        }
    }

    if (isset($item->enclosure)) {
        $attrs = $item->enclosure->attributes();
        $type = (string) $attrs['type'];
        $url = (string) $attrs['url'];
        if ($url !== '' && strpos($type, 'image') === 0) {
            return $url;
        }
    }

    return '';
}

// Scrapes the og:image meta tag out of a raw HTML page. Used to
// backfill a thumbnail source for feeds (like ScienceDaily) whose RSS
// doesn't carry one. Only looks at the first chunk of the document,
// since og tags live in <head>, to keep this cheap.
function ri_extract_og_image($html)
{
    if (!$html) {
        return '';
    }
    $head = substr($html, 0, 60000);

    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\']/i', $head, $m)) {
        return $m[1];
    }
    if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]*property=["\']og:image["\']/i', $head, $m)) {
        return $m[1];
    }

    return '';
}

function ri_gd_available()
{
    return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
}

// Downloads a source image and re-saves it locally as two plain
// JPEGs: a center-cropped square for list thumbnails, and a resized
// (uncropped) version for the post detail page. GD decodes whatever
// format the source is (including WebP), so this is also what makes
// ScienceDaily's WebP images visible on browsers - like ~2012 WebKit -
// that can't decode WebP natively. Returns array('thumbnail' => ...,
// 'full_image' => ...) with local /thumbs/... paths, or '' for either
// that couldn't be produced.
function ri_make_local_images($sourceUrl, $itemId)
{
    $result = array('thumbnail' => '', 'full_image' => '');

    if ($sourceUrl === '' || !ri_gd_available()) {
        return $result;
    }

    $bytes = ri_http_get($sourceUrl, 10);
    if (!$bytes) {
        return $result;
    }

    $src = @imagecreatefromstring($bytes);
    if (!$src) {
        return $result;
    }

    if (!is_dir(THUMBNAIL_DIR)) {
        @mkdir(THUMBNAIL_DIR, 0755, true);
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);

    $side = min($srcW, $srcH);
    $cropX = (int) (($srcW - $side) / 2);
    $cropY = (int) (($srcH - $side) / 2);

    $thumb = imagecreatetruecolor(THUMBNAIL_SIZE, THUMBNAIL_SIZE);
    imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
    imagecopyresampled($thumb, $src, 0, 0, $cropX, $cropY, THUMBNAIL_SIZE, THUMBNAIL_SIZE, $side, $side);
    if (@imagejpeg($thumb, THUMBNAIL_DIR . '/' . $itemId . '.jpg', THUMBNAIL_JPEG_QUALITY)) {
        $result['thumbnail'] = THUMBNAIL_URL_PATH . '/' . $itemId . '.jpg';
    }
    @imagedestroy($thumb);

    if ($srcW > FULL_IMAGE_MAX_WIDTH) {
        $fullW = FULL_IMAGE_MAX_WIDTH;
        $fullH = (int) round($srcH * ($fullW / $srcW));
    } else {
        $fullW = $srcW;
        $fullH = $srcH;
    }

    $full = imagecreatetruecolor($fullW, $fullH);
    imagefill($full, 0, 0, imagecolorallocate($full, 255, 255, 255));
    imagecopyresampled($full, $src, 0, 0, 0, 0, $fullW, $fullH, $srcW, $srcH);
    if (@imagejpeg($full, THUMBNAIL_DIR . '/' . $itemId . '-full.jpg', THUMBNAIL_JPEG_QUALITY)) {
        $result['full_image'] = THUMBNAIL_URL_PATH . '/' . $itemId . '-full.jpg';
    }
    @imagedestroy($full);

    @imagedestroy($src);

    return $result;
}

// Removes the local JPEG derivatives for an item, if any were made.
function ri_delete_local_images($itemId)
{
    @unlink(THUMBNAIL_DIR . '/' . $itemId . '.jpg');
    @unlink(THUMBNAIL_DIR . '/' . $itemId . '-full.jpg');
}

// Parses an RSS 2.0 <channel><item> feed body into a plain array of
// items. Returns an empty array on any parse failure rather than
// throwing, so one broken feed never takes the whole page down.
function ri_parse_rss($body)
{
    if (!$body) {
        return array();
    }

    $prevErrSetting = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErrSetting);

    if ($xml === false || !isset($xml->channel->item)) {
        return array();
    }

    $items = array();
    foreach ($xml->channel->item as $item) {
        $link = trim((string) $item->link);
        $guid = trim((string) $item->guid);
        if ($guid === '') {
            $guid = $link;
        }
        if ($link === '' || $guid === '') {
            continue;
        }

        $pubDateRaw = trim((string) $item->pubDate);
        $pubDate = $pubDateRaw !== '' ? strtotime($pubDateRaw) : false;
        if ($pubDate === false) {
            $pubDate = time();
        }

        $items[] = array(
            'guid'             => $guid,
            'title'            => trim((string) $item->title),
            'link'             => $link,
            'description'      => trim((string) $item->description),
            'pub_date'         => $pubDate,
            'thumbnail_source' => ri_extract_item_thumbnail($item),
        );
    }

    return $items;
}

// Fetches every configured feed and upserts new/changed items into
// the database. Safe to call on every page load; ri_refresh_if_stale()
// below is what actually decides whether that's necessary.
function ri_refresh_all($pdo)
{
    $feeds = $GLOBALS['FEEDS'];
    $now = time();

    $insert = $pdo->prepare(
        'INSERT OR IGNORE INTO items (guid, source, title, link, description, pub_date, fetched_at, thumbnail_source)
         VALUES (:guid, :source, :title, :link, :description, :pub_date, :fetched_at, :thumbnail_source)'
    );
    $markDone = $pdo->prepare(
        'UPDATE items SET thumbnail = :thumbnail, full_image = :full_image, thumbnail_checked = 1 WHERE id = :id'
    );
    $immediateCount = 0;

    foreach ($feeds as $sourceKey => $feed) {
        $body = ri_http_get($feed['url']);
        $items = ri_parse_rss($body);

        foreach ($items as $item) {
            $insert->execute(array(
                ':guid'             => $item['guid'],
                ':source'           => $sourceKey,
                ':title'            => $item['title'],
                ':link'             => $item['link'],
                ':description'      => $item['description'],
                ':pub_date'         => $item['pub_date'],
                ':fetched_at'       => $now,
                ':thumbnail_source' => $item['thumbnail_source'] !== '' ? $item['thumbnail_source'] : null,
            ));

            // If the feed already handed us an image URL (e.g. Psychology
            // Today's media:content), generating the local JPEG is just
            // one image fetch - cheap - so do it right away instead of
            // making it wait in line behind ScienceDaily's og:image
            // scrape, which needs a whole extra article-page fetch first.
            // Only for genuinely new rows, and only up to a per-refresh
            // cap so a sudden burst of new items can't stall the page.
            if ($item['thumbnail_source'] !== '' && $insert->rowCount() > 0 && $immediateCount < MAX_IMMEDIATE_THUMBNAILS_PER_REFRESH) {
                $newId = $pdo->lastInsertId();
                $images = ri_make_local_images($item['thumbnail_source'], $newId);
                $markDone->execute(array(
                    ':thumbnail'  => $images['thumbnail'] !== '' ? $images['thumbnail'] : null,
                    ':full_image' => $images['full_image'] !== '' ? $images['full_image'] : null,
                    ':id'         => $newId,
                ));
                $immediateCount++;
            }
        }

        // Keep only the newest MAX_ITEMS_PER_SOURCE rows per source so
        // the database doesn't grow without bound over months/years.
        // Look up which rows that prunes first so their local thumbnail
        // files can be cleaned up too, not just the DB rows.
        $select = $pdo->prepare(
            'SELECT id FROM items WHERE source = :source AND id NOT IN (
                SELECT id FROM items WHERE source = :source2
                ORDER BY pub_date DESC LIMIT :limit
            )'
        );
        $select->bindValue(':source', $sourceKey);
        $select->bindValue(':source2', $sourceKey);
        $select->bindValue(':limit', MAX_ITEMS_PER_SOURCE, PDO::PARAM_INT);
        $select->execute();
        $pruneIds = $select->fetchAll(PDO::FETCH_COLUMN);

        if ($pruneIds) {
            foreach ($pruneIds as $pruneId) {
                ri_delete_local_images($pruneId);
            }
            $placeholders = implode(',', array_fill(0, count($pruneIds), '?'));
            $pdo->prepare('DELETE FROM items WHERE id IN (' . $placeholders . ')')->execute($pruneIds);
        }
    }

    ri_backfill_thumbnails($pdo);

    ri_meta_set($pdo, 'last_refresh', (string) $now);
}

// Produces local thumbnail/full_image JPEGs for a small, bounded
// batch of items that don't have one yet. For feeds whose RSS didn't
// carry an image (thumbnail_source empty - currently ScienceDaily),
// this first scrapes og:image off the linked article. Bounded so a
// single page load never triggers dozens of extra fetches; leftover
// items just get picked up on the next refresh cycle.
//
// The budget is split evenly across sources rather than taken from
// the most-recent-overall items: a purely global "most recent first"
// query would let a fast-publishing feed's new items keep jumping the
// queue ahead of another feed's backlog, starving it indefinitely.
function ri_backfill_thumbnails($pdo)
{
    $sources = array_keys($GLOBALS['FEEDS']);
    $perSource = (int) ceil(MAX_THUMBNAIL_BACKFILL_PER_REFRESH / count($sources));

    $select = $pdo->prepare(
        'SELECT id, link, thumbnail_source FROM items
         WHERE source = :source AND thumbnail_checked = 0
         ORDER BY pub_date DESC LIMIT :limit'
    );

    $rows = array();
    foreach ($sources as $sourceKey) {
        $select->bindValue(':source', $sourceKey);
        $select->bindValue(':limit', $perSource, PDO::PARAM_INT);
        $select->execute();
        $rows = array_merge($rows, $select->fetchAll(PDO::FETCH_ASSOC));
    }

    $update = $pdo->prepare(
        'UPDATE items SET thumbnail = :thumbnail, full_image = :full_image,
            thumbnail_source = :thumbnail_source, thumbnail_checked = 1
         WHERE id = :id'
    );

    foreach ($rows as $row) {
        $sourceUrl = $row['thumbnail_source'];

        if (!$sourceUrl) {
            $html = ri_http_get($row['link'], 8);
            $sourceUrl = $html ? ri_extract_og_image($html) : '';
        }

        $images = ri_make_local_images($sourceUrl, $row['id']);

        $update->execute(array(
            ':thumbnail'        => $images['thumbnail'] !== '' ? $images['thumbnail'] : null,
            ':full_image'       => $images['full_image'] !== '' ? $images['full_image'] : null,
            ':thumbnail_source' => $sourceUrl !== '' ? $sourceUrl : null,
            ':id'               => $row['id'],
        ));
    }
}

// Refreshes feeds only if the cache has gone stale, so a normal page
// view is a fast DB read and the (slow) network fetch only happens
// once every CACHE_TTL seconds regardless of traffic.
function ri_refresh_if_stale($pdo)
{
    $last = (int) ri_meta_get($pdo, 'last_refresh', 0);
    if ((time() - $last) < CACHE_TTL) {
        return;
    }

    // Stamp last_refresh immediately, before the slow network calls,
    // so concurrent requests during a refresh don't all pile on and
    // hammer the source feeds at once.
    ri_meta_set($pdo, 'last_refresh', (string) time());

    ri_refresh_all($pdo);
}
