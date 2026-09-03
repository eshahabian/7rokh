<?php
/**
 * Plugin Name: Casting Portal — خرید اشتراک در هدر سایت
 * Description: آیکون خرید اشتراک کنار شبکه‌های اجتماعی هدر + شمارنده زنده
 * Version: 2.2
 *
 * نصب: public_html/wp-content/mu-plugins/casting-main-cart-nav.php
 *
 * سازگار با PHP 7.4 — بدون str_contains.
 */

declare(strict_types=1);

if (defined('CASTING_MAIN_CART_NAV_LOADED')) {
    return;
}
define('CASTING_MAIN_CART_NAV_LOADED', true);

function casting_main_cart_url(): string
{
    if (defined('CASTING_PORTAL_CART_URL') && is_string(CASTING_PORTAL_CART_URL) && CASTING_PORTAL_CART_URL !== '') {
        return CASTING_PORTAL_CART_URL;
    }

    return home_url('/casting-portal/cart.php');
}

function casting_main_cart_count_url(): string
{
    if (defined('CASTING_PORTAL_CART_COUNT_URL') && is_string(CASTING_PORTAL_CART_COUNT_URL) && CASTING_PORTAL_CART_COUNT_URL !== '') {
        return CASTING_PORTAL_CART_COUNT_URL;
    }

    return home_url('/casting-portal/cart-count.php');
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

function casting_main_cart_should_render(): bool
{
    if (is_admin() || casting_main_cart_is_portal_request()) {
        return false;
    }
    if (function_exists('is_customize_preview') && is_customize_preview()) {
        return false;
    }

    return true;
}

function casting_main_cart_link_html(): string
{
    $url = esc_url(casting_main_cart_url());
    $label = esc_attr('خرید اشتراک');
    $count = casting_main_cart_count_from_cookie();
    $badge_class = 'casting-main-cart-badge' . ($count > 0 ? '' : ' is-empty');
    $badge_text = $count > 0 ? (string) (int) $count : '';

    return '<a class="casting-main-cart-social" href="' . $url . '" title="' . $label . '" aria-label="' . $label . '"><i class="fa fa-shopping-cart" aria-hidden="true"></i><span class="' . esc_attr($badge_class) . '">' . $badge_text . '</span></a>';
}

/**
 * آیکون سبد را در HTML هدر می‌گذارد تا برای مهمان (صفحهٔ کش‌شده) هم دیده شود.
 *
 * @param mixed $html
 * @return mixed
 */
function casting_main_cart_inject_html($html)
{
    if (!is_string($html) || $html === '' || !casting_main_cart_should_render()) {
        return $html;
    }

    if (strpos($html, 'casting-main-cart-social') !== false) {
        return $html;
    }

    $link = casting_main_cart_link_html();
    $replaced = preg_replace(
        '/(<a\b[^>]*\bclass="[^"]*\bjeg_twitter\b[^"]*"[^>]*>.*?<\/a>)/is',
        '$1' . $link,
        $html,
        1
    );

    return is_string($replaced) ? $replaced : $html;
}

function casting_main_cart_maybe_purge_cache(): void
{
    if (!function_exists('get_option') || !function_exists('update_option')) {
        return;
    }
    $ver = '2.2';
    if ((string) get_option('casting_main_cart_nav_ver', '') === $ver) {
        return;
    }
    update_option('casting_main_cart_nav_ver', $ver, false);
    do_action('litespeed_purge_all');
    do_action('litespeed_purge_url', home_url('/'));
}

function casting_main_cart_maybe_buffer(): void
{
    if (!casting_main_cart_should_render()) {
        return;
    }
    if (defined('LSCWP_V') || defined('LSCWP_DIR') || class_exists('LiteSpeed\\Core', false)) {
        return;
    }
    ob_start('casting_main_cart_inject_html');
}

function casting_main_cart_litespeed_js_excludes($excludes)
{
    if (!is_array($excludes)) {
        $excludes = [];
    }
    $excludes[] = 'CASTING_MAIN_CART';
    $excludes[] = 'casting-main-cart-nav';
    $excludes[] = 'casting-main-cart-social';

    return $excludes;
}

function casting_main_cart_script_tag($tag, $handle, $src)
{
    if ($handle !== 'casting-main-cart-nav' || !is_string($tag)) {
        return $tag;
    }
    if (strpos($tag, 'data-no-defer') === false) {
        $tag = str_replace('<script ', '<script data-no-optimize="1" data-no-defer="1" ', $tag);
    }

    return $tag;
}

function casting_main_cart_enqueue_assets(): void
{
    if (!casting_main_cart_should_render()) {
        return;
    }

    $css = '
.casting-main-cart-social{
  position:relative !important;
}
.jeg_header .socials_widget,
.jeg_navbar .socials_widget,
.jeg_topbar .socials_widget{
  overflow:visible !important;
  width:auto !important;
  max-width:none !important;
}
.jeg_social_icon_block .casting-main-cart-social,
.socials_widget .casting-main-cart-social{
  display:inline-block !important;
  vertical-align:middle;
}
.socials_widget .casting-main-cart-social i{
  font-size:inherit;
}
.casting-main-cart-social .casting-main-cart-badge{
  position:absolute;
  top:-0.35em;
  inset-inline-end:-0.45em;
  min-width:1.05em;
  padding:0.05em 0.28em;
  border-radius:999px;
  background:#e85d04;
  color:#fff !important;
  font-size:0.58em;
  font-weight:700;
  line-height:1.35;
  text-align:center;
  box-shadow:0 1px 2px rgba(0,0,0,.2);
  pointer-events:none;
}
.casting-main-cart-badge.is-empty{
  display:none !important;
}
.casting-main-cart-fallback{
  position:fixed;
  z-index:9998;
  inset-inline-end:1rem;
  bottom:1rem;
  display:none;
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
.casting-main-cart-fallback.is-visible{
  display:inline-flex;
}
.casting-main-cart-fallback:hover,
.casting-main-cart-fallback:focus{
  color:#fff;
  opacity:.92;
}
.casting-main-cart-fallback .casting-main-cart-badge{
  position:static;
  inset:auto;
  margin-inline-start:0.25em;
  font-size:0.75em;
}
';

    wp_register_style('casting-main-cart-nav', false, [], '2.2');
    wp_enqueue_style('casting-main-cart-nav');
    wp_add_inline_style('casting-main-cart-nav', trim($css));

    $data = [
        'url'      => casting_main_cart_url(),
        'countUrl' => casting_main_cart_count_url(),
        'count'    => casting_main_cart_count_from_cookie(),
        'label'    => 'خرید اشتراک',
    ];

    $js = <<<'JS'
(function () {
  var CFG = window.CASTING_MAIN_CART || {};
  function qsAll(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }
  function isSocialHref(h) {
    h = (h || "").toLowerCase();
    return (
      h.indexOf("facebook.com") !== -1 ||
      h.indexOf("fb.com") !== -1 ||
      h.indexOf("twitter.com") !== -1 ||
      h.indexOf("instagram.com") !== -1 ||
      h.indexOf("t.me") !== -1 ||
      h.indexOf("telegram") !== -1 ||
      h.indexOf("linkedin.com") !== -1 ||
      h.indexOf("youtube.com") !== -1 ||
      h.indexOf("aparat.com") !== -1
    );
  }
  function isHeaderSocialLink(el) {
    if (!el || el.classList.contains("casting-main-cart-social")) return false;
    var cls = " " + (el.className || "") + " ";
    if (
      cls.indexOf(" jeg_facebook ") !== -1 ||
      cls.indexOf(" jeg_twitter ") !== -1 ||
      cls.indexOf(" jeg_instagram ") !== -1 ||
      cls.indexOf(" jeg_linkedin ") !== -1 ||
      cls.indexOf(" jeg_youtube ") !== -1
    ) {
      return true;
    }
    return isSocialHref(el.getAttribute("href"));
  }
  function widgetLinks(widget) {
    return qsAll("a[href]", widget).filter(isHeaderSocialLink);
  }
  function headerWidgets() {
    var sel = [
      ".jeg_header .socials_widget",
      ".jeg_navbar .socials_widget",
      ".jeg_header_sticky .socials_widget",
      ".jeg_sticky_nav .socials_widget",
      ".jeg_stickybar .socials_widget",
      "header .socials_widget",
      ".jeg_topbar .socials_widget",
      ".jeg_header .jeg_social_icon_block",
    ].join(",");
    var found = qsAll(sel);
    if (found.length) return found;
    return qsAll(".socials_widget, .jeg_social_icon_block").filter(function (el) {
      return widgetLinks(el).length > 0;
    });
  }
  function cleanBrandClasses(className) {
    return (className || "")
      .split(/\s+/)
      .filter(function (c) {
        if (!c) return false;
        return !/facebook|twitter|instagram|linkedin|youtube|telegram|aparat|fa-facebook|fa-twitter|fa-x-twitter|fa-instagram|fa-linkedin|fa-youtube|fa-telegram/i.test(
          c
        );
      });
  }
  function badgeHtml() {
    return (
      '<span class="casting-main-cart-badge' +
      (CFG.count > 0 ? "" : " is-empty") +
      '">' +
      (CFG.count > 0 ? String(CFG.count) : "") +
      "</span>"
    );
  }
  function buildCartLink(template) {
    var a = template.cloneNode(false);
    var classes = cleanBrandClasses(template.className);
    if (classes.indexOf("casting-main-cart-social") === -1) {
      classes.push("casting-main-cart-social");
    }
    a.className = classes.join(" ");
    a.href = CFG.url || "#";
    a.title = CFG.label || "خرید اشتراک";
    a.setAttribute("aria-label", CFG.label || "خرید اشتراک");
    a.removeAttribute("target");
    a.removeAttribute("rel");

    var icon = template.querySelector("i");
    if (icon) {
      var iEl = icon.cloneNode(false);
      var ic = cleanBrandClasses(icon.className);
      if (ic.indexOf("fa") === -1 && ic.indexOf("fas") === -1 && ic.indexOf("fab") === -1) {
        ic.push("fa");
      }
      ic = ic.filter(function (c) {
        return !/^fa-(facebook|twitter|x-twitter|instagram|linkedin|youtube|telegram)/i.test(c);
      });
      if (ic.indexOf("fa-shopping-cart") === -1 && ic.indexOf("fa-cart-shopping") === -1) {
        ic.push("fa-shopping-cart");
      }
      iEl.className = ic.join(" ");
      iEl.setAttribute("aria-hidden", "true");
      a.appendChild(iEl);
    } else {
      a.innerHTML = '<i class="fa fa-shopping-cart" aria-hidden="true"></i>';
    }
    a.insertAdjacentHTML("beforeend", badgeHtml());
    return a;
  }
  function setCount(n) {
    CFG.count = Math.max(0, parseInt(n, 10) || 0);
    qsAll(".casting-main-cart-badge").forEach(function (badge) {
      if (CFG.count > 0) {
        badge.textContent = String(CFG.count);
        badge.classList.remove("is-empty");
      } else {
        badge.textContent = "";
        badge.classList.add("is-empty");
      }
    });
  }
  function injectWidget(widget) {
    if (!widget || widget.querySelector(".casting-main-cart-social")) return true;
    var socials = widgetLinks(widget);
    if (!socials.length) return false;
    var last = socials[socials.length - 1];
    var parent = last.parentElement;
    if (!parent) return false;
    var link = buildCartLink(last);
    if (last.nextSibling) {
      parent.insertBefore(link, last.nextSibling);
    } else {
      parent.appendChild(link);
    }
    return true;
  }
  function place() {
    var widgets = headerWidgets();
    var i;
    var placed = false;
    for (i = 0; i < widgets.length; i++) {
      if (injectWidget(widgets[i])) placed = true;
    }
    if (placed) {
      var fb = document.querySelector(".casting-main-cart-fallback");
      if (fb) fb.classList.remove("is-visible");
    }
    return placed;
  }
  function showFallback() {
    var fb = document.querySelector(".casting-main-cart-fallback");
    if (fb) fb.classList.add("is-visible");
  }
  function refreshCount() {
    if (!CFG.countUrl) return;
    fetch(CFG.countUrl, {
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json" },
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.ok) {
          setCount(0);
          return;
        }
        setCount(data.count || 0);
      })
      .catch(function () {});
  }
  function boot() {
    try {
      var placed = place();
      if (!placed) {
        var n = 0;
        var t = setInterval(function () {
          n++;
          try {
            if (place() || n > 24) {
              clearInterval(t);
              if (!document.querySelector(".casting-main-cart-social")) showFallback();
              refreshCount();
            }
          } catch (e) {
            clearInterval(t);
          }
        }, 250);
      } else {
        refreshCount();
      }
    } catch (e) {}
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
JS;

    wp_register_script('casting-main-cart-nav', false, [], '2.2', true);
    wp_enqueue_script('casting-main-cart-nav');
    wp_add_inline_script(
        'casting-main-cart-nav',
        'window.CASTING_MAIN_CART = ' . wp_json_encode($data) . ';' . "\n" . $js
    );
}

function casting_main_cart_footer_markup(): void
{
    if (!casting_main_cart_should_render()) {
        return;
    }
    $url = esc_url(casting_main_cart_url());
    $count = casting_main_cart_count_from_cookie();
    $badge_class = 'casting-main-cart-badge' . ($count > 0 ? '' : ' is-empty');
    $badge = '<span class="' . esc_attr($badge_class) . '">' . ($count > 0 ? (string) (int) $count : '') . '</span>';
    echo '<a class="casting-main-cart-fallback" href="' . $url . '" aria-label="خرید اشتراک">خرید اشتراک' . $badge . '</a>';
}

add_action('init', 'casting_main_cart_maybe_purge_cache', 20);
add_action('wp_enqueue_scripts', 'casting_main_cart_enqueue_assets', 30);
add_action('wp_footer', 'casting_main_cart_footer_markup', 40);
add_action('template_redirect', 'casting_main_cart_maybe_buffer', 0);
add_filter('litespeed_buffer_before', 'casting_main_cart_inject_html', 5);
add_filter('litespeed_buffer_after', 'casting_main_cart_inject_html', 5);
add_filter('script_loader_tag', 'casting_main_cart_script_tag', 10, 3);
add_filter('litespeed_optimize_js_excludes', 'casting_main_cart_litespeed_js_excludes');
add_filter('litespeed_optm_js_defer_exc', 'casting_main_cart_litespeed_js_excludes');
add_filter('litespeed_optm_gm_js_exc', 'casting_main_cart_litespeed_js_excludes');
