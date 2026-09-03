<?php
/**
 * Plugin Name: Casting Portal — Force Domain
 * Description: فقط وقتی درخواست واقعاً روی 7rokh.com است، home/siteurl را قفل می‌کند.
 *
 * نصب:
 * /home/.../public_html/wp-content/mu-plugins/casting-force-domain.php
 *
 * هشدار: روی هاست زندهٔ 7rokh.ir این فایل را نگذار مگر دامنه واقعاً به همان هاست منتقل شده باشد.
 */
declare(strict_types=1);

if (defined('CASTING_FORCE_DOMAIN_LOADED')) {
    return;
}
define('CASTING_FORCE_DOMAIN_LOADED', true);

/**
 * فقط وقتی کاربر با دامنهٔ هدف آمده، URLها را قفل کن.
 * اگر روی 7rokh.ir باشد و این فایل دامنه را به .com ببرد، سایت/ورود می‌شکند.
 */
function casting_force_domain_is_target_host(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    return $host === '7rokh.com' || $host === 'www.7rokh.com';
}

if (!casting_force_domain_is_target_host()) {
    return;
}

add_filter('pre_option_home', static function () {
    return 'https://7rokh.com';
}, 1);

add_filter('pre_option_siteurl', static function () {
    return 'https://7rokh.com';
}, 1);

add_filter('allowed_redirect_hosts', static function (array $hosts): array {
    $hosts[] = '7rokh.com';
    $hosts[] = 'www.7rokh.com';
    $hosts[] = '7rokh.ir';
    $hosts[] = 'www.7rokh.ir';

    return array_values(array_unique($hosts));
}, 1);
