<?php
declare(strict_types=1);
define('CASTING_MAIN_SITE_URL', 'https://7rokh.ir');
require __DIR__ . '/../includes/seo.php';
function check($condition) { if (!$condition) { throw new RuntimeException('SEO check failed'); } }
foreach (array_keys(casting_seo_pages()) as $page) {
    $_SERVER['SCRIPT_NAME'] = '/casting-portal/' . $page;
    check(casting_seo_indexable());
    ob_start(); casting_render_seo_head(); $html = ob_get_clean();
    check(strpos($html, 'name="description"') !== false);
    check(strpos($html, 'rel="canonical"') !== false);
    check(strpos($html, 'noindex') === false);
}
foreach (['login.php', 'register.php', 'reporters.php', 'home.php', 'admin-users.php', 'cart.php', 'forgot-password.php'] as $page) {
    $_SERVER['SCRIPT_NAME'] = '/casting-portal/' . $page;
    check(!casting_seo_indexable());
    ob_start(); casting_render_seo_head(); $html = ob_get_clean();
    check(strpos($html, 'noindex') !== false);
}
echo "SEO metadata and private routes checks passed.\n";
$_SERVER['SCRIPT_NAME'] = '/casting-portal/index.php';
$_SERVER['REQUEST_URI'] = '/casting-portal/index.php?utm_source=test';
check(casting_seo_canonical() === 'https://7rokh.ir/casting-portal/');
ob_start(); casting_render_seo_head(); $html = ob_get_clean();
check(strpos($html, '<link rel="canonical" href="https://7rokh.ir/casting-portal/">') !== false);
echo "Homepage canonical check passed.\n";
