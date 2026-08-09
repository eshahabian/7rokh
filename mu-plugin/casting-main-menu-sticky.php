<?php
/**
 * Plugin Name: Casting Portal — منوی هدر یک‌ردیفه (sticky)
 * Description: جلوگیری از دو ردیف شدن منوی اصلی سایت بعد از اسکرول (قالب JNews)
 * Version: 1.0
 *
 * نصب: public_html/wp-content/mu-plugins/casting-main-menu-sticky.php
 * (خودکار با deploy — .cpanel.yml)
 *
 * سازگار با PHP 7.4 — بدون str_contains.
 */

declare(strict_types=1);

if (defined('CASTING_MAIN_MENU_STICKY_LOADED')) {
    return;
}
define('CASTING_MAIN_MENU_STICKY_LOADED', true);

function casting_main_menu_sticky_is_portal_request(): bool
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    return $uri !== '' && strpos($uri, '/casting-portal/') !== false;
}

function casting_main_menu_sticky_should_render(): bool
{
    if (is_admin() || casting_main_menu_sticky_is_portal_request()) {
        return false;
    }
    if (function_exists('is_customize_preview') && is_customize_preview()) {
        return false;
    }

    return true;
}

/**
 * در حالت sticky هدر JNews کمی تنگ‌تر می‌شود و آخرین آیتم
 * (مثل «پورتال هفت رخ») به ردیف دوم می‌افتد.
 */
function casting_main_menu_sticky_enqueue_assets(): void
{
    if (!casting_main_menu_sticky_should_render()) {
        return;
    }

    $css = <<<'CSS'
@media (min-width: 1025px) {
  /* منوی اصلی — همیشه یک ردیف */
  .jeg_header .jeg_main_menu,
  .jeg_navbar .jeg_main_menu,
  .jeg_mainbar .jeg_main_menu,
  .jeg_sticky_nav .jeg_main_menu,
  .jeg_header_sticky .jeg_main_menu,
  .jeg_menu.jeg_main_menu {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    white-space: nowrap !important;
    float: none !important;
  }

  .jeg_header .jeg_main_menu > li,
  .jeg_navbar .jeg_main_menu > li,
  .jeg_mainbar .jeg_main_menu > li,
  .jeg_sticky_nav .jeg_main_menu > li,
  .jeg_header_sticky .jeg_main_menu > li,
  .jeg_menu.jeg_main_menu > li {
    float: none !important;
    display: inline-flex !important;
    flex: 0 0 auto !important;
    white-space: nowrap !important;
  }

  .jeg_header .jeg_main_menu > li > a,
  .jeg_navbar .jeg_main_menu > li > a,
  .jeg_mainbar .jeg_main_menu > li > a,
  .jeg_sticky_nav .jeg_main_menu > li > a,
  .jeg_header_sticky .jeg_main_menu > li > a {
    white-space: nowrap !important;
  }

  /* ردیف‌های هدر/استیکی جمع نشوند */
  .jeg_sticky_nav,
  .jeg_sticky_nav .container,
  .jeg_sticky_nav .jeg_nav_row,
  .jeg_sticky_nav .jeg_nav_col,
  .jeg_navbar.shadow,
  .jeg_navbar.shadow .container,
  .jeg_mainbar.jeg_sticky,
  .jeg_header .sticky-wrapper.is-sticky .jeg_nav_row {
    flex-wrap: nowrap !important;
  }

  /* وقتی می‌چسبد کمی فشرده‌تر تا همه جا شوند */
  .jeg_sticky_nav .jeg_main_menu > li > a,
  .jeg_header_sticky .jeg_main_menu > li > a,
  .jeg_navbar.shadow .jeg_main_menu > li > a,
  .jeg_mainbar.jeg_sticky .jeg_main_menu > li > a,
  .jeg_header .sticky-wrapper.is-sticky .jeg_main_menu > li > a,
  body.jeg_sticky_enable .jeg_sticky_nav .jeg_main_menu > li > a {
    padding-left: 0.42em !important;
    padding-right: 0.42em !important;
    font-size: 0.9em !important;
    letter-spacing: 0 !important;
  }

  /* ظرف منو فضای لازم را بگیرد؛ جستجو جمع نشود */
  .jeg_sticky_nav .jeg_nav_item,
  .jeg_navbar.shadow .jeg_nav_item {
    flex-wrap: nowrap !important;
    min-width: 0 !important;
  }

  .jeg_sticky_nav .jeg_main_menu_wrapper,
  .jeg_navbar.shadow .jeg_main_menu_wrapper,
  .jeg_sticky_nav .nav_wrap,
  .jeg_navbar.shadow .nav_wrap {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    overflow: visible !important;
  }
}
CSS;

    wp_register_style('casting-main-menu-sticky', false, [], '1.0');
    wp_enqueue_style('casting-main-menu-sticky');
    wp_add_inline_style('casting-main-menu-sticky', trim($css));
}

add_action('wp_enqueue_scripts', 'casting_main_menu_sticky_enqueue_assets', 100);
