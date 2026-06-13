<?php
/**
 * sitemap.php
 *
 * Sitemap_Generator — produces a dynamic XML sitemap (Req 21.1).
 *
 * The sitemap lists the static public pages of the site plus one <url> entry
 * per ACTIVE service (as a clean /services/{slug} URL). Every URL is built as
 * an absolute URL from SITE_URL and XML-escaped through htmlspecialchars so the
 * document is always well-formed.
 *
 * Request lifecycle: load the shared engine (config, db, functions), send the
 * XML content type, then stream the urlset. There must be no leading
 * whitespace or output before the XML declaration, so the file opens with the
 * PHP tag immediately and the closing tag is intentionally omitted.
 *
 * Requirements: 21.1.
 */

declare(strict_types=1);

// --- Shared engine ----------------------------------------------------------
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

// --- Response headers -------------------------------------------------------
header('Content-Type: application/xml; charset=utf-8');

// --- Build the absolute URL list --------------------------------------------
$base = rtrim(SITE_URL, '/');

/**
 * Build an absolute URL from a root-relative path.
 *
 * @param string $base Site base URL with no trailing slash.
 * @param string $path Root-relative path beginning with "/".
 * @return string      Absolute URL.
 */
$absolute = static function (string $base, string $path) : string {
    if ($path === '/') {
        return $base . '/';
    }
    return $base . '/' . ltrim($path, '/');
};

// Static public pages (Req 21.1).
$paths = ['/', '/about', '/services', '/doctors', '/gallery', '/appointment', '/contact'];

$urls = [];
foreach ($paths as $path) {
    $urls[] = $absolute($base, $path);
}

// One <url> per ACTIVE service as /services/{slug}, ordered by sort order.
$stmt = db()->prepare(
    'SELECT slug FROM services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
);
$stmt->execute();
foreach ($stmt->fetchAll() as $row) {
    $slug = (string) ($row['slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    $urls[] = $absolute($base, '/services/' . $slug);
}

// --- Emit the sitemap -------------------------------------------------------
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo '  <url><loc>'
        . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</loc></url>' . "\n";
}
echo '</urlset>' . "\n";
