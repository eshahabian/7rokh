<?php
/**
 * Plugin Name: Casting Portal — اینماد در فوتر سایت
 * Description: کد رسمی اینماد در ویجت فوتر JNews + جلوگیری از فشرده شدن تصویر
 * Version: 1.1
 *
 * نصب: public_html/wp-content/mu-plugins/casting-main-enamad.php
 * (خودکار با deploy — .cpanel.yml)
 *
 * سازگار با PHP 7.4 — بدون str_contains.
 */

declare(strict_types=1);

if (defined('CASTING_MAIN_ENAMAD_LOADED')) {
    return;
}
define('CASTING_MAIN_ENAMAD_LOADED', true);

function casting_main_enamad_is_portal_request(): bool
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    return $uri !== '' && strpos($uri, '/casting-portal/') !== false;
}

function casting_main_enamad_should_render(): bool
{
    if (is_admin() || casting_main_enamad_is_portal_request()) {
        return false;
    }
    if (function_exists('is_customize_preview') && is_customize_preview()) {
        return false;
    }

    return true;
}

function casting_main_enamad_markup(): string
{
    return '<a referrerpolicy=\'origin\' target=\'_blank\' href=\'https://trustseal.enamad.ir/?id=768314&Code=s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS\'><img referrerpolicy=\'origin\' src=\'https://trustseal.enamad.ir/logo.aspx?id=768314&Code=s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS\' alt=\'\' style=\'cursor:pointer\' code=\'s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS\'></a>';
}

function casting_main_enamad_enqueue_assets(): void
{
    if (!casting_main_enamad_should_render()) {
        return;
    }

    $css = <<<'CSS'
.jeg_footer a[href*="enamad.ir"] img,
.jeg_footer img[src*="trustseal.enamad"] {
  width: auto !important;
  height: auto !important;
  max-width: none !important;
  min-width: 0 !important;
  transform: none !important;
  display: inline-block !important;
}

.jeg_footer a[href*="enamad.ir"] {
  display: inline-block !important;
  line-height: 0 !important;
}
CSS;

    wp_register_style('casting-main-enamad', false, [], '1.1');
    wp_enqueue_style('casting-main-enamad');
    wp_add_inline_style('casting-main-enamad', trim($css));
}

function casting_main_enamad_footer_script(): void
{
    if (!casting_main_enamad_should_render()) {
        return;
    }

    $markup = casting_main_enamad_markup();
    ?>
<script>
(function () {
  var MARKUP = <?= wp_json_encode($markup) ?>;

  function inject() {
    var titles = document.querySelectorAll('.jeg_footer_title');
    for (var i = 0; i < titles.length; i++) {
      var title = titles[i];
      if (!/enamad/i.test(title.textContent || '')) {
        continue;
      }
      var box = title.parentElement;
      if (!box) {
        continue;
      }
      if (box.querySelector('a[href*="enamad.ir"]')) {
        return;
      }
      box.insertAdjacentHTML('beforeend', MARKUP);
      return;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
</script>
    <?php
}

add_action('wp_enqueue_scripts', 'casting_main_enamad_enqueue_assets', 100);
add_action('wp_footer', 'casting_main_enamad_footer_script', 45);
