# ReadIt

A Reddit-style front page for two RSS feeds — [ScienceDaily](https://www.sciencedaily.com/rss/all.xml)
and [Psychology Today](https://www.psychologytoday.com/us/front/feed) — with none of the
usual brain rot: no accounts, no voting, no comments, no algorithm, no JavaScript at all.
Just a reverse-chronological list of articles, styled like old.reddit.com.

It's built to run on essentially anything, including a ~2012-era WebKit browser (this was
built to work on a webOS TouchPad) as well as any modern browser or phone.

## What it is

- **Read-only aggregator.** `index.php` lists articles from both feeds, newest first, with
  pagination. `post.php` shows a single article's full description before linking out to
  the original.
- **Old-reddit-style layout.** Numbered list, thumbnails, domain tags, "N minutes ago" —
  no cards, no infinite scroll, no algorithmic ranking.
- **ES5 / old-WebKit-safe front end.** No JavaScript at all, and CSS is deliberately
  old-school: floats and inline-block only, no flexbox, no grid, no `calc()`, no CSS
  variables.
- **PWA-pinnable.** `manifest.json` plus `apple-mobile-web-app-*` meta tags let it be added
  to a home screen and launch full-screen. No service worker — old WebKit can't use one
  anyway, and there's nothing here worth caching offline.
- **Simple, low-dependency PHP backend.** No framework, no Composer. Just PDO + SQLite for
  storage and GD for image handling.

## Requirements

- PHP 7+ (developed against 8.5, but written to avoid 8-only syntax)
- `pdo_sqlite` extension
- `gd` extension, ideally built with WebP read support (see [How thumbnails work](#how-thumbnails-work))
- `curl` extension recommended (falls back to `file_get_contents` + `allow_url_fopen` if it's
  not available)
- Any web server that can run PHP — on nginx, see [Deploying on nginx](#deploying-on-nginx)
  for the config you'll need instead of the included (Apache-only) `.htaccess` files

## File layout

```
index.php            front page (paginated, newest first)
post.php              single-article detail view
cron.php              CLI-only script to trigger a refresh from cron (see below)
manifest.json          PWA manifest
style.css              all styling
favicon.ico, icons/     favicon + home-screen icons (icons/source-monster.png is the master)
includes/
  config.php            site name, tagline, feed URLs, cache/thumbnail settings
  db.php                SQLite connection + schema/migrations
  fetch.php             RSS fetching, parsing, thumbnail generation
  render.php            small view helpers (time-ago, HTML escaping, etc.)
  header.php, footer.php  shared page chrome
data/                 SQLite database lives here (created on first request)
thumbs/               generated JPEG thumbnails live here (created on first request)
```

`data/` and `includes/` each have a `.htaccess` blocking direct web access (the SQLite file
and PHP includes aren't meant to be requested directly). `thumbs/` has one too, just to turn
off directory listing — the JPEGs in there are meant to be served directly.

## Deploying

1. Upload the whole folder to your web server.
2. Make sure `data/` and `thumbs/` are writable by PHP (the exact chmod/ownership depends on
   your host — ask them if `755` isn't enough).
3. Load the site once in a browser. The first request creates `data/feeds.sqlite` and fetches
   both feeds — it'll take a couple of seconds. After that, it's a fast local read on every
   page load until the cache goes stale.
4. (Optional but recommended) set up cron — see below — so refreshes happen on a schedule
   instead of only when a page load happens to hit a stale cache.

Redeploying later (new files over old) is safe — `includes/db.php` auto-adds any new columns
the schema needs, so there's no manual database step.

## Configuration

Everything lives in `includes/config.php`:

| Setting | What it does |
|---|---|
| `SITE_NAME`, `SITE_TAGLINE` | Shown in the header, page titles, and PWA manifest name |
| `CACHE_TTL` | How stale the cache can get (seconds) before a page load triggers a refresh |
| `MAX_ITEMS_PER_SOURCE` | Oldest items get pruned once a source has more than this many |
| `ITEMS_PER_PAGE` | Front page pagination size |
| `MAX_THUMBNAIL_BACKFILL_PER_REFRESH`, `MAX_IMMEDIATE_THUMBNAILS_PER_REFRESH` | Thumbnail generation throttling — see below |
| `THUMBNAIL_SIZE`, `FULL_IMAGE_MAX_WIDTH`, `THUMBNAIL_JPEG_QUALITY` | Generated image sizing/quality |
| `$FEEDS` | The source feeds themselves — add/remove/change RSS feeds here |

## Scheduling refreshes with cron

By default, a feed refresh only happens when someone loads a page and the cache has gone
stale (older than `CACHE_TTL`). That keeps normal page loads fast, but it means refreshes —
and thumbnail backfilling — only happen when there's traffic to trigger them.

`cron.php` runs the same refresh logic directly from the command line, so you can put it on
a schedule instead. It refuses to run over HTTP (it 403s if you try to load it in a browser)
since refreshing isn't free — it hits the source feeds, and for new items, their article
pages too — so it can't be a public URL anyone could hit repeatedly.

Over SSH:

```bash
crontab -e
```

Add a line like:

```
*/10 * * * * /usr/bin/php /full/path/to/ReadIt/cron.php >> /full/path/to/ReadIt/data/cron.log 2>&1
```

- **PHP path**: run `which php` on the server to confirm — some hosts need a
  version-specific binary like `/usr/local/bin/php8.2`.
- **Project path**: wherever you uploaded the site, e.g. `/home/youruser/public_html/ReadIt`.
- **Schedule**: `*/10` (every 10 minutes) is a reasonable default — frequent enough that
  ScienceDaily's thumbnail backfill (throttled to a handful of items per run) catches up
  steadily without hammering the source sites. Go tighter (`*/5`) to catch up faster, or
  looser (`*/30`) to be gentler on their servers.
- **Log destination**: `data/cron.log` is safe — that directory is already blocked from web
  access. Redirect to `/dev/null` instead if you don't want a log at all.

Once cron is running, page loads will basically never trigger their own refresh (cron keeps
things fresh already), so `CACHE_TTL` just becomes a fallback in case cron ever stops.

To verify it's working: run `php cron.php` by hand once first (should print `refresh OK`),
then `tail data/cron.log` after the first scheduled run goes by.

## How thumbnails work

- **Psychology Today** includes an image URL directly in its RSS (`media:content`), so its
  thumbnail is generated immediately when a new article is fetched — no extra network
  request needed to find it.
- **ScienceDaily**'s RSS has no image data at all, and its article images are served as
  WebP — a format old WebKit (and plenty of other old browsers) can't decode. So for these,
  the linked article page is fetched once to scrape its `og:image` meta tag, then that image
  is downloaded and re-saved locally.
- Either way, every image is downloaded server-side, decoded with GD, center-cropped to a
  square for the list view, and re-saved as a plain JPEG (`thumbs/<id>.jpg` and
  `thumbs/<id>-full.jpg` for the larger detail-page version) — universally viewable
  regardless of the source's original format or aspect ratio.
- This is throttled: only a bounded number of new thumbnails are generated per refresh cycle
  (split evenly across sources), so a page load — or a cron run — never has to wait on a
  long burst of slow fetches. Any backlog just catches up over the next few cycles.

## Deploying on nginx

The `.htaccess` files are Apache-only — nginx never reads them, so they're just inert on an
nginx host (harmless to leave in place, in case you ever move to Apache). Use rules like
these in your `server {}` block instead:

```nginx
location ^~ /includes/ {
    deny all;
    return 404;
}

location ^~ /data/ {
    deny all;
    return 404;
}

location ^~ /thumbs/ {
    autoindex off; # off by default anyway, but explicit here
}
```

Use `^~` here rather than a plain regex `location ~ ...` block. Your config almost certainly
also has a regex location for handing `.php` requests to PHP-FPM, e.g.:

```nginx
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php-fpm.sock;
    ...
}
```

Nginx picks between competing *regex* locations by whichever appears first in the config
file — so a plain `location ~ ^/(data|includes)/` deny rule would only actually protect
`includes/*.php` if it happened to be declared before the PHP block. `^~` sidesteps that:
prefix matches using `^~` always win over any regex location, regardless of where either one
is declared, so `/includes/config.php` is guaranteed to be denied before PHP-FPM ever sees
it — the whole point, since everything in `includes/` is PHP meant to be `require`'d, not
requested directly.

## Icon Credit

Monster icon created by Magnific - Flaticon