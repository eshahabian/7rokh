<?php
/**
 * Plugin Name: Casting Portal — سبد خرید در منوی سایت
 * Description: لینک سبد خرید پورتال + شمارنده در منوی اصلی وردپرس
 * Version: 1.0
 *
 * نصب: public_html/wp-content/mu-plugins/casting-main-cart-nav.php
 * (خودکار با deploy — .cpanel.yml)
 *
 * سازگار با PHP 7.4 — بدون str_contains.
 */

declare(strict_types=1);

if (defined('CASTING_MAIN_CART_NAV_LOADED')) {
    return;
}
define('CASTING_MAIN_CART_NAV_LOADED', true);

/**
 * آدرس صفحهٔ سبد پورتال
 */
function casting_main_cart_url(): string
{
    if (defined('CASTING_PORTAL_CART_URL') && is_string(CASTING_PORTAL_CART_URL) && CASTING_PORTAL_CART_URL !== '') {
        return CASTING_PORTAL_CART_URL;
    }

    return home_url('/casting-portal/cart.php');
}

function casting_main_cart_count_from_cookie(): int
{
    $name = 'casting_cart_count';
    if (!isset($_COOKIE[$name])) {
        return 0;
    }

    return max(0, (int) $_COOKIE[$name]);
}

function casting_main_cart_is_portal_request(): bool
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    return $uri !== '' && strpos($uri, '/casting-portal/') !== false;
}

/**
 * @return list<string>
 */
function casting_main_cart_preferred_locations(): array
{
    return [
        'primary',
        'main',
        'header',
        'menu-1',
        'primary-menu',
        'top',
        'primary_navigation',
        'main-menu',
        'header-menu',
        'top-menu',
        'navbar',
    ];
}

function casting_main_cart_mark_injected(): void
{
    $GLOBALS['casting_main_cart_nav_injected'] = true;
}

function casting_main_cart_was_injected(): bool
{
    return !empty($GLOBALS['casting_main_cart_nav_injected']);
}

function casting_main_cart_menu_item_html(): string
{
    $url = esc_url(casting_main_cart_url());
    $count = casting_main_cart_count_from_cookie();
    $label = 'سبد خرید';
    $badge = '';
    if ($count > 0) {
        $badge = ' <span class="casting-main-cart-badge" aria-label="'
            . esc_attr($count . ' مورد در سبد')
            . '">' . (int) $count . '</span>';
    }

    return '<li class="menu-item menu-item-type-custom casting-main-cart-nav-item">'
        . '<a class="casting-main-cart-link" href="' . $url . '">'
        . esc_html($label)
        . $badge
        . '</a></li>';
}

/**
 * @param object|array $args
 */
function casting_main_cart_should_inject_into_menu($args): bool
{
    if (casting_main_cart_was_injected()) {
        return false;
    }
    $loc = '';
    if (is_object($args) && isset($args->theme_location)) {
        $loc = (string) $args->theme_location;
    } elseif (is_array($args) && isset($args['theme_location'])) {
        $loc = (string) $args['theme_location'];
    }

    // فقط منوی اصلی شناخته‌شده — به فوتر/ویجت‌های بدون location دست نزن
    // اگر تم location دیگری داشت، لینک شناور wp_footer به‌عنوان پشتیبان می‌آید
    return $loc !== '' && in_array($loc, casting_main_cart_preferred_locations(), true);
}

/**
 * @param string $items
 * @param object|array $args
 */
function casting_main_cart_nav_items($items, $args)
{
    if (is_admin() || casting_main_cart_is_portal_request()) {
        return $items;
    }
    $items = (string) $items;
    if (strpos($items, 'casting-main-cart-nav-item') !== false) {
        casting_main_cart_mark_injected();

        return $items;
    }
    if (!casting_main_cart_should_inject_into_menu($args)) {
        return $items;
    }

    casting_main_cart_mark_injected();

    return $items . casting_main_cart_menu_item_html();
}

function casting_main_cart_enqueue_styles(): void
{
    if (is_admin() || casting_main_cart_is_portal_request()) {
        return;
    }
    $css = '
.casting-main-cart-badge{
  display:inline-block;
  min-width:1.15em;
  margin-inline-start:0.35em;
  padding:0.05em 0.4em;
  border-radius:999px;
  background:#b08d57;
  color:#fff;
  font-size:0.75em;
  font-weight:700;
  line-height:1.4;
  vertical-align:middle;
  text-align:center;
}
.casting-main-cart-fallback{
  position:fixed;
  z-index:9998;
  inset-inline-end:1rem;
  bottom:1rem;
  display:inline-flex;
  align-items:center;
  gap:0.35rem;
  padding:0.55rem 0.9rem;
  border-radius:999px;
  background:#1c1917;
  color:#fff;
  text-decoration:none;
  font-size:0.9rem;
  font-weight:600;
  box-shadow:0 8px 24px rgba(0,0,0,.18);
}
.casting-main-cart-fallback:hover,
.casting-main-cart-fallback:focus{
  color:#fff;
  opacity:.92;
}
';
    wp_register_style('casting-main-cart-nav', false, [], '1.0');
    wp_enqueue_style('casting-main-cart-nav');
    wp_add_inline_style('casting-main-cart-nav', trim($css));
}

/**
 * اگر تم منوی استاندارد نداشت، لینک شناور پشتیبان
 */
function casting_main_cart_footer_fallback(): void
{
    if (is_admin() || casting_main_cart_is_portal_request()) {
        return;
    }
    if (casting_main_cart_was_injected()) {
        return;
    }
    // فقط فرانت عمومی؛ روی پیش‌نمایش سفارشی‌ساز هم مزاحم نشود
    if (function_exists('is_customize_preview') && is_customize_preview()) {
        return;
    }
    $url = esc_url(casting_main_cart_url());
    $count = casting_main_cart_count_from_cookie();
    $badge = '';
    if ($count > 0) {
        $badge = ' <span class="casting-main-cart-badge" aria-label="'
            . esc_attr($count . ' مورد در سبد')
            . '">' . (int) $count . '</span>';
    }
    echo '<a class="casting-main-cart-fallback" href="' . $url . '">سبد خرید' . $badge . '</a>';
    casting_main_cart_mark_injected();
}

add_filter('wp_nav_menu_items', 'casting_main_cart_nav_items', 20, 2);
add_action('wp_enqueue_scripts', 'casting_main_cart_enqueue_styles', 30);
add_action('wp_footer', 'casting_main_cart_footer_fallback', 40);
