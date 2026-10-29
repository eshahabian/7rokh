<?php
declare(strict_types=1);

/**
 * Sitemap عمومی پورتال برای Google Search Console و سایر موتورهای جستجو.
 * فقط صفحات ایندکس‌شدنی (بدون لاگین اجباری).
 */

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: public, max-age=3600');

$origin = 'https://7rokh.com';
if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    if (defined('CASTING_MAIN_SITE_URL') && trim((string) CASTING_MAIN_SITE_URL) !== '') {
        $origin = rtrim((string) CASTING_MAIN_SITE_URL, '/');
    }
}

$base = $origin . '/casting-portal';
$today = gmdate('Y-m-d');

/** @var list<array{loc:string,changefreq:string,priority:string,lastmod?:string}> $urls */
$urls = [
    ['loc' => $base . '/', 'changefreq' => 'daily', 'priority' => '1.0', 'lastmod' => $today],
    ['loc' => $base . '/register.php', 'changefreq' => 'monthly', 'priority' => '0.9', 'lastmod' => $today],
    ['loc' => $base . '/register.php?path=talent', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => $base . '/register.php?path=hire', 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => $base . '/login.php', 'changefreq' => 'monthly', 'priority' => '0.7', 'lastmod' => $today],
    ['loc' => $base . '/cart.php', 'changefreq' => 'weekly', 'priority' => '0.8', 'lastmod' => $today],
    ['loc' => $base . '/faq.php', 'changefreq' => 'monthly', 'priority' => '0.7', 'lastmod' => $today],
    ['loc' => $base . '/rules.php', 'changefreq' => 'monthly', 'priority' => '0.6', 'lastmod' => $today],
    ['loc' => $base . '/contact.php', 'changefreq' => 'yearly', 'priority' => '0.5', 'lastmod' => $today],
    ['loc' => $base . '/app.php', 'changefreq' => 'monthly', 'priority' => '0.6', 'lastmod' => $today],
    ['loc' => $base . '/forgot-password.php', 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $today],
];

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
