<?php

function ri_h($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function ri_domain($url)
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return $url;
    }
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }
    return $host;
}

// "3 hours ago" / "2 days ago" style relative time, reddit-style.
function ri_time_ago($timestamp)
{
    $diff = time() - (int) $timestamp;
    if ($diff < 60) {
        return 'just now';
    }

    $units = array(
        31536000 => 'year',
        2592000  => 'month',
        86400    => 'day',
        3600     => 'hour',
        60       => 'minute',
    );

    foreach ($units as $seconds => $label) {
        $count = floor($diff / $seconds);
        if ($count >= 1) {
            return $count . ' ' . $label . ($count == 1 ? '' : 's') . ' ago';
        }
    }

    return 'just now';
}

// Reduces an RSS description down to safe, plain-ish HTML: strips
// scripts/styles/images/iframes entirely and only allows a small set
// of harmless inline tags. Everything else is stripped to text.
function ri_clean_description($html)
{
    if ($html === null || $html === '') {
        return '';
    }
    $allowed = '<p><br><b><strong><i><em><a><ul><ol><li><blockquote>';
    $clean = strip_tags($html, $allowed);

    // strip_tags leaves href/target attributes alone, but also leaves
    // any bare "javascript:" hrefs untouched; scrub those out.
    $clean = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $clean);

    return trim($clean);
}

// Short plain-text teaser for the front page list (no HTML at all).
function ri_teaser($html, $maxLen = 200)
{
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
    if (function_exists('mb_strlen')) {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $maxLen)) . '…';
    }
    if (strlen($text) <= $maxLen) {
        return $text;
    }
    return rtrim(substr($text, 0, $maxLen)) . '...';
}
