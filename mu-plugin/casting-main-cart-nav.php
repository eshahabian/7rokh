<?php
/**
 * Plugin Name: Casting Portal — سبد خرید در هدر سایت
 * Description: آیکون سبد خرید کنار شبکه‌های اجتماعی هدر + شمارنده زنده
 * Version: 1.2
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

function casting_main_cart_enqueue_assets(): void
{
    if (!casting_main_cart_should_render()) {
        return;
    }

    $css = '
.casting-main-cart-social{
  display:inline-flex !important;
  align-items:center;
  justify-content:center;
  position:relative;
  vertical-align:middle;
  text-decoration:none !important;
  line-height:1;
  margin-inline:0.2em;
  color:inherit;
}
.casting-main-cart-social svg{
  width:1em;
  height:1em;
  display:block;
  fill:currentColor;
  color:inherit;
}
.casting-main-cart-badge{
  position:absolute;
  top:-0.45em;
  inset-inline-end:-0.55em;
  min-width:1.1em;
  padding:0.05em 0.28em;
  border-radius:999px;
  background:#e85d04;
  color:#fff !important;
  fill:none;
  font-size:0.62em;
  font-weight:700;
  line-height:1.35;
  text-align:center;
  box-shadow:0 1px 2px rgba(0,0,0,.2);
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

    wp_register_style('casting-main-cart-nav', false, [], '1.2');
    wp_enqueue_style('casting-main-cart-nav');
    wp_add_inline_style('casting-main-cart-nav', trim($css));

    $data = [
        'url'      => casting_main_cart_url(),
        'countUrl' => casting_main_cart_count_url(),
        'count'    => casting_main_cart_count_from_cookie(),
        'label'    => 'سبد خرید',
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
      h.indexOf("x.com") !== -1 ||
      h.indexOf("instagram.com") !== -1 ||
      h.indexOf("t.me") !== -1 ||
      h.indexOf("telegram") !== -1 ||
      h.indexOf("linkedin.com") !== -1 ||
      h.indexOf("youtube.com") !== -1 ||
      h.indexOf("aparat.com") !== -1
    );
  }
  function findAnchor() {
    var scopes = [
      "header",
      ".jeg_header",
      ".td-header-wrap",
      ".tdb-header-template",
      ".site-header",
      ".main-header",
      "#header",
      ".top-bar",
      ".topbar",
      ".header-top",
    ];
    var i, j, scope, links;
    for (i = 0; i < scopes.length; i++) {
      scope = document.querySelector(scopes[i]);
      if (!scope) continue;
      links = qsAll("a[href]", scope);
      for (j = 0; j < links.length; j++) {
        if (isSocialHref(links[j].getAttribute("href"))) return links[j];
      }
    }
    links = qsAll("a[href]");
    for (j = 0; j < links.length; j++) {
      if (isSocialHref(links[j].getAttribute("href"))) return links[j];
    }
    return null;
  }
  function findInsertPoint(anchor) {
    var node = anchor, k, parent, socials, last;
    for (k = 0; k < 6 && node; k++) {
      parent = node.parentElement;
      if (!parent) break;
      socials = qsAll("a[href]", parent).filter(function (el) {
        return isSocialHref(el.getAttribute("href"));
      });
      if (socials.length >= 1) {
        last = socials[socials.length - 1];
        return { parent: parent, after: last };
      }
      node = parent;
    }
    return { parent: anchor.parentElement, after: anchor };
  }
  function matchSocialLook(link, ref) {
    if (!link || !ref) return;
    try {
      var cs = window.getComputedStyle(ref);
      if (cs.color) link.style.color = cs.color;
      if (cs.opacity) link.style.opacity = cs.opacity;
      var icon = ref.querySelector("i, svg, img, span");
      var svg = link.querySelector("svg");
      if (icon && svg) {
        var ics = window.getComputedStyle(icon);
        var size = ics.width && ics.width !== "auto" && ics.width !== "0px" ? ics.width : ics.fontSize;
        if (size) {
          svg.style.width = size;
          svg.style.height = size;
        }
        if (ics.color) link.style.color = ics.color;
      }
    } catch (e) {}
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
  function buildLink(cls) {
    var a = document.createElement("a");
    a.className = cls;
    a.href = CFG.url || "#";
    a.title = CFG.label || "سبد خرید";
    a.setAttribute("aria-label", CFG.label || "سبد خرید");
    a.innerHTML =
      '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2S15.9 22 17 22s2-.9 2-2-.9-2-2-2zM7.16 14h9.69c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0 0 21.33 5H6.21l-.94-2H1v2h2l3.6 7.59-1.35 2.44C4.52 16.37 5.48 18 7 18h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12L7.16 14z"/></svg>' +
      '<span class="casting-main-cart-badge' +
      (CFG.count > 0 ? "" : " is-empty") +
      '">' +
      (CFG.count > 0 ? String(CFG.count) : "") +
      "</span>";
    return a;
  }
  function place() {
    if (document.querySelector(".casting-main-cart-social")) return true;
    var anchor = findAnchor();
    if (!anchor) return false;
    var spot = findInsertPoint(anchor);
    if (!spot || !spot.parent || !spot.after) return false;
    var link = buildLink("casting-main-cart-social");
    matchSocialLook(link, spot.after);
    if (spot.after.nextSibling) {
      spot.parent.insertBefore(link, spot.after.nextSibling);
    } else {
      spot.parent.appendChild(link);
    }
    var fb = document.querySelector(".casting-main-cart-fallback");
    if (fb) fb.classList.remove("is-visible");
    return true;
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
        if (!data.logged_in) {
          setCount(0);
          return;
        }
        setCount(data.count || 0);
      })
      .catch(function () {});
  }
  function boot() {
    if (!place()) {
      var n = 0;
      var t = setInterval(function () {
        n++;
        if (place() || n > 20) {
          clearInterval(t);
          if (!document.querySelector(".casting-main-cart-social")) showFallback();
          refreshCount();
        }
      }, 250);
      return;
    }
    refreshCount();
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
JS;

    wp_register_script('casting-main-cart-nav', false, [], '1.2', true);
    wp_enqueue_script('casting-main-cart-nav');
    wp_add_inline_script(
        'casting-main-cart-nav',
        'window.CASTING_MAIN_CART = ' . wp_json_encode($data) . ';' . "\n" . $js
    );
}

/**
 * لینک شناور پشتیبان — فقط اگر کنار سوشال پیدا نشد (با JS نمایش داده می‌شود)
 */
function casting_main_cart_footer_markup(): void
{
    if (!casting_main_cart_should_render()) {
        return;
    }
    $url = esc_url(casting_main_cart_url());
    $count = casting_main_cart_count_from_cookie();
    $badge_class = 'casting-main-cart-badge' . ($count > 0 ? '' : ' is-empty');
    $badge = '<span class="' . esc_attr($badge_class) . '">' . ($count > 0 ? (string) (int) $count : '') . '</span>';
    echo '<a class="casting-main-cart-fallback" href="' . $url . '" aria-label="سبد خرید">سبد خرید' . $badge . '</a>';
}

add_action('wp_enqueue_scripts', 'casting_main_cart_enqueue_assets', 30);
add_action('wp_footer', 'casting_main_cart_footer_markup', 40);
