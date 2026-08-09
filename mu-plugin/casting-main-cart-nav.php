<?php
/**
 * Plugin Name: Casting Portal — سبد خرید در هدر سایت
 * Description: آیکون سبد خرید کنار شبکه‌های اجتماعی هدر + شمارنده زنده
 * Version: 1.4
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
  vertical-align:middle !important;
  text-decoration:none !important;
  line-height:1 !important;
  margin:0 0.35em !important;
  padding:0 !important;
  color:#666 !important;
  box-sizing:border-box;
}
.casting-main-cart-social:hover,
.casting-main-cart-social:focus{
  color:#555 !important;
  opacity:1;
}
.casting-main-cart-social svg{
  width:1em;
  height:1em;
  display:block;
  fill:currentColor;
  color:inherit;
  flex:0 0 auto;
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

    wp_register_style('casting-main-cart-nav', false, [], '1.4');
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
      var svg = link.querySelector("svg");
      if (!svg) return;
      var cs = window.getComputedStyle(ref);
      var icon = ref.querySelector("i, svg, img");
      var color = cs.color || "#666";
      var size = parseFloat(cs.fontSize) || 16;
      if (icon) {
        var ics = window.getComputedStyle(icon);
        if (ics.color && ics.color !== "rgba(0, 0, 0, 0)") color = ics.color;
        var iw = parseFloat(ics.width);
        var ih = parseFloat(ics.height);
        var ifs = parseFloat(ics.fontSize);
        if (iw > 0 && iw < 64) size = iw;
        else if (ih > 0 && ih < 64) size = ih;
        else if (ifs > 0) size = ifs;
      }
      size = Math.round(size);
      link.style.color = color;
      link.style.opacity = cs.opacity && cs.opacity !== "1" ? cs.opacity : "1";
      link.style.display = "inline-flex";
      link.style.alignItems = "center";
      link.style.justifyContent = "center";
      link.style.verticalAlign = "middle";
      link.style.lineHeight = cs.lineHeight || "1";
      if (cs.height && cs.height !== "auto" && parseFloat(cs.height) > 0) {
        link.style.height = cs.height;
      }
      if (cs.width && cs.width !== "auto" && parseFloat(cs.width) > 0) {
        link.style.width = cs.width;
        link.style.minWidth = cs.width;
      }
      svg.style.width = size + "px";
      svg.style.height = size + "px";
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
      '<svg viewBox="0 0 576 512" aria-hidden="true"><path d="M0 24C0 10.7 10.7 0 24 0H69.5c22 0 41.5 12.8 50.6 32h411c26.3 0 45.5 25 38.6 50.4l-41 152.3c-8.5 31.4-37 53.3-69.5 53.3H170.7l5.4 28.5c2.2 11.3 12.1 19.5 23.6 19.5H488c13.3 0 24 10.7 24 24s-10.7 24-24 24H199.7c-34.6 0-64.3-24.6-70.7-58.5L77.4 54.5c-.7-3.8-4-6.5-7.9-6.5H24C10.7 48 0 37.3 0 24zM128 464a48 48 0 1 1 96 0 48 48 0 1 1-96 0zm336-48a48 48 0 1 1 0 96 48 48 0 1 1 0-96z"/></svg>' +
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

    wp_register_script('casting-main-cart-nav', false, [], '1.4', true);
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
