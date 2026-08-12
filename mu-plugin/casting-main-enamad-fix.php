<?php
/**
 * Plugin Name: Casting Portal — رفع لود اول اینماد
 * Description: روی سایت اصلی وردپرس، لوگوی اینماد را بعد از load دوباره می‌کشد تا بار اول خالی نماند.
 * Version: 1.0
 *
 * نصب: public_html/wp-content/mu-plugins/casting-main-enamad-fix.php
 * (خودکار با deploy — .cpanel.yml)
 *
 * سازگار با PHP 7.4
 */

declare(strict_types=1);

if (defined('CASTING_MAIN_ENAMAD_FIX_LOADED')) {
    return;
}
define('CASTING_MAIN_ENAMAD_FIX_LOADED', true);

/**
 * در صفحات پورتال کستینگ اجرا نشود (آنجا layout خودش لودر دارد).
 */
function casting_main_enamad_should_run(): bool
{
    if (is_admin()) {
        return false;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ($uri !== '' && strpos($uri, '/casting-portal') !== false) {
        return false;
    }

    return true;
}

function casting_main_enamad_fix_script(): void
{
    if (!casting_main_enamad_should_run()) {
        return;
    }

    echo "<script>\n";
    echo <<<'JS'
(function () {
  if (window.__castingEnamadFix) return;
  window.__castingEnamadFix = true;

  function baseSrc(img) {
    var raw = img.getAttribute("data-enamad-src") || img.getAttribute("src") || "";
    if (!raw) return "";
    try {
      var u = new URL(raw, window.location.href);
      u.searchParams.delete("_");
      return u.toString();
    } catch (e) {
      return raw.replace(/([?&])_=[^&]*/g, "$1").replace(/[?&]$/, "").replace(/\?&/, "?").replace(/&&+/g, "&");
    }
  }

  function isEnamadImg(img) {
    var src = (img.getAttribute("src") || "") + " " + (img.getAttribute("data-enamad-src") || "");
    return /trustseal\.enamad\.ir/i.test(src) || img.hasAttribute("data-enamad-seal");
  }

  function isBad(img) {
    return !img.complete || !img.naturalWidth || img.naturalWidth < 20;
  }

  function revive(img) {
    if (!img || img.getAttribute("data-enamad-fixing") === "1") return;
    img.setAttribute("data-enamad-fixing", "1");
    img.setAttribute("referrerpolicy", "origin");

    var parent = img.closest("a");
    if (parent) parent.setAttribute("referrerpolicy", "origin");

    var base = baseSrc(img);
    if (!base || !/trustseal\.enamad\.ir/i.test(base)) return;

    var tries = 0;
    var maxTries = 6;
    var timer = null;

    function apply(bust) {
      img.setAttribute("referrerpolicy", "origin");
      var url = base;
      if (bust) {
        url += (base.indexOf("?") >= 0 ? "&" : "?") + "_=" + Date.now() + "-" + tries;
      }
      img.src = url;
    }

    function schedule() {
      if (tries >= maxTries) return;
      tries += 1;
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        apply(true);
      }, 220 * tries);
    }

    img.addEventListener("error", schedule);
    img.addEventListener("load", function () {
      if (isBad(img)) schedule();
    });

    schedule();
  }

  function scan() {
    var nodes = document.querySelectorAll(
      'img[src*="trustseal.enamad.ir"], img[data-enamad-src*="trustseal.enamad.ir"], img[data-enamad-seal]'
    );
    for (var i = 0; i < nodes.length; i++) {
      if (isEnamadImg(nodes[i])) revive(nodes[i]);
    }
  }

  function boot() {
    window.setTimeout(scan, 150);
  }

  if (document.readyState === "complete") boot();
  else window.addEventListener("load", boot);
})();
JS;
    echo "\n</script>\n";
}

add_action('wp_footer', 'casting_main_enamad_fix_script', 99);
