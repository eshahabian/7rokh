<?php
declare(strict_types=1);

/**
 * Sitemap عمومی پورتال برای Google Search Console و سایر موتورهای جستجو.
 * فقط صفحات ایندکس‌شدنی (بدون لاگین اجباری).
 */

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: public, max-age=3600');

$origin = 'https://7rokh.ir';
if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    if (defined('CASTING_MAIN_SITE_URL') && trim((string) CASTING_MAIN_SITE_URL) !== '') {
        $origin = rtrim((string) CASTING_MAIN_SITE_URL, '/');
    }
}

$base = $origin . '/casting-portal';
require_once __DIR__ . '/includes/seo.php';
$urls = [];
foreach (casting_seo_pages() as $page => $metadata) {
    $urls[] = ['loc' => $base . '/' . ($page === 'index.php' ? '' : $page), 'changefreq' => 'monthly', 'priority' => $page === 'index.php' ? '1.0' : '0.6'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $item) : ?>
  <url>
    <loc><?= htmlspecialchars($item['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></loc>
<?php if (!empty($item['lastmod'])) : ?>
    <lastmod><?= htmlspecialchars($item['lastmod'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></lastmod>
<?php endif; ?>
    <changefreq><?= htmlspecialchars($item['changefreq'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></changefreq>
    <priority><?= htmlspecialchars($item['priority'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
