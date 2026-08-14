<?php
/**
 * Plugin Name: Casting Portal — خرید اشتراک در هدر سایت
 * Description: آیکون خرید اشتراک کنار شبکه‌های اجتماعی هدر + شمارنده زنده
 * Version: 1.11
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

    // فقط badge — اندازه/فاصله/رنگ را از خود تم و Font Awesome می‌گیرد
    $css = '
.casting-main-cart-social{
  position:relative !important;
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

    wp_register_style('casting-main-cart-nav', false, [], '1.9');
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
      h.indexOf("x.com") !== -1 ||
      h.indexOf("instagram.com") !== -1 ||
      h.indexOf("t.me") !== -1 ||
      h.indexOf("telegram") !== -1 ||
      h.indexOf("linkedin.com") !== -1 ||
      h.indexOf("youtube.com") !== -1 ||
      h.indexOf("aparat.com") !== -1
    );
  }
  function isTwitter(h) {
    h = (h || "").toLowerCase();
    return h.indexOf("twitter.com") !== -1 || h.indexOf("x.com") !== -1;
  }
  function getSocials(parent) {
    return qsAll("a[href]", parent || document).filter(function (el) {
      return (
        isSocialHref(el.getAttribute("href")) &&
        !el.classList.contains("casting-main-cart-social")
      );
    });
  }
  function findSocialCluster() {
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
    var i, scope, socials;
    for (i = 0; i < scopes.length; i++) {
      scope = document.querySelector(scopes[i]);
      if (!scope) continue;
      socials = getSocials(scope);
      if (socials.length) {
        return { root: scope, socials: socials };
      }
    }
    socials = getSocials(document);
    if (!socials.length) return null;
    return { root: socials[0].parentElement, socials: socials };
  }
  function pickTemplate(socials) {
    var i, href;
    for (i = 0; i < socials.length; i++) {
      href = socials[i].getAttribute("href") || "";
      if (isTwitter(href)) return socials[i];
    }
    return socials[socials.length - 1];
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
      iEl.removeAttribute("aria-hidden");
      iEl.setAttribute("aria-hidden", "true");
      a.appendChild(iEl);
    } else {
      a.innerHTML =
        '<i class="fa fa-shopping-cart" aria-hidden="true"></i>';
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
  function place() {
    if (document.querySelector(".casting-main-cart-social")) return true;
    var cluster = findSocialCluster();
    if (!cluster || !cluster.socials.length) return false;
    var socials = cluster.socials;
    var template = pickTemplate(socials);
    var parent = template.parentElement;
    if (!parent) return false;
    var link = buildCartLink(template);
    var last = socials[socials.length - 1];
    if (last.nextSibling) {
      parent.insertBefore(link, last.nextSibling);
    } else {
      parent.appendChild(link);
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
        // مهمان هم می‌تواند سبد داشته باشد
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

    wp_register_script('casting-main-cart-nav', false, [], '1.9', true);
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

add_action('wp_enqueue_scripts', 'casting_main_cart_enqueue_assets', 30);
add_action('wp_footer', 'casting_main_cart_footer_markup', 40);
