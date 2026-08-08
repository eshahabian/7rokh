<?php
declare(strict_types=1);

require_once __DIR__ . '/pwa.php';

function casting_main_site_url(): string
{
    return defined('CASTING_MAIN_SITE_URL') ? (string) CASTING_MAIN_SITE_URL : 'https://7rokh.ir';
}

function casting_render_head(string $title, string $body_class = ''): void
{
    $brand = casting_e(casting_brand());
    $full_title = casting_e($title) . ' | ' . $brand;
    $css = casting_e(casting_asset('css/style.css'));
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $full_title ?></title>
  <?php casting_render_pwa_head(); ?>
  <link rel="preload" href="<?= casting_e(casting_asset('fonts/Vazirmatn-Regular.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lalezar&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $css ?>?v=139">
  <script>
    (function () {
      try {
        var theme = localStorage.getItem('casting_theme');
        if (theme === 'night') {
          document.documentElement.removeAttribute('data-theme');
        } else {
          document.documentElement.setAttribute('data-theme', 'day');
        }
      } catch (e) {
        document.documentElement.setAttribute('data-theme', 'day');
      }
    })();
  </script>
</head>
<body class="<?= casting_e($body_class) ?>">
  <div class="bg-atmosphere" aria-hidden="true"></div>
<?php
}

function casting_render_theme_toggle(): void
{
    ?>
      <div class="nav-theme theme-toggle" role="group" aria-label="انتخاب روز یا شب">
        <button type="button" class="theme-toggle-btn" data-theme-pick="night">شب</button>
        <button type="button" class="theme-toggle-btn is-active" data-theme-pick="day">روز</button>
      </div>
    <?php
}

function casting_render_panel_menu_toggle(int $badge = 0): void
{
    ?>
    <button
      type="button"
      class="panel-menu-toggle"
      id="panel-menu-toggle"
      aria-controls="panel-drawer"
      aria-expanded="false"
      aria-label="باز کردن منوی پنل"
      data-panel-menu-toggle
    >
      <span class="panel-menu-toggle-icon" aria-hidden="true">
        <span></span><span></span><span></span>
      </span>
      <span class="panel-menu-toggle-text">منو</span>
      <?php if ($badge > 0) : ?>
        <span class="nav-badge panel-menu-toggle-badge"><?= (int) $badge ?></span>
      <?php endif; ?>
    </button>
    <?php
}

function casting_render_header(?string $active = null, bool $panel_menu = false, int $panel_menu_badge = 0): void
{
    $user = casting_current_user();
    $role = $user ? casting_get_user_role((int) $user->ID) : '';
    $new_followers = 0;
    if ($user && $role !== '') {
        if (!function_exists('casting_new_followers_count')) {
            require_once __DIR__ . '/follows.php';
        }
        $new_followers = casting_new_followers_count((int) $user->ID);
    }
    ?>
  <header class="site-header<?= $panel_menu ? ' site-header--panel' : '' ?>">
    <div class="site-header-bar">
      <?php if ($panel_menu) : ?>
        <?php casting_render_panel_menu_toggle($panel_menu_badge); ?>
      <?php endif; ?>
      <a class="brand" href="index.php"><?= casting_brand_html() ?></a>
    </div>
    <nav class="nav" aria-label="منوی اصلی">
      <a href="<?= casting_e(casting_main_site_url()) ?>" class="nav-external" target="_blank" rel="noopener">سایت <?= casting_brand_html() ?></a>
      <?php if ($role !== '') : ?>
        <a href="home.php" class="<?= $active === 'home' ? 'is-active' : '' ?>">صفحه اصلی</a>
        <?php
        $cart_count = 0;
        try {
            if (!function_exists('casting_cart_count')) {
                $cartLib = __DIR__ . '/cart.php';
                if (is_file($cartLib)) {
                    require_once $cartLib;
                }
            }
            if (function_exists('casting_cart_count')) {
                $cart_count = (int) casting_cart_count();
            }
        } catch (Throwable $e) {
            $cart_count = 0;
        }
        ?>
        <a href="<?= casting_e(casting_url('cart.php')) ?>" class="<?= $active === 'cart' ? 'is-active' : '' ?><?= $cart_count > 0 ? ' has-notify' : '' ?>">
          سبد خرید
          <?php if ($cart_count > 0) : ?>
            <span class="nav-badge" aria-label="<?= (int) $cart_count ?> مورد در سبد"><?= (int) $cart_count ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= casting_e(casting_url($new_followers > 0 ? 'following.php?tab=followers' : 'panel.php')) ?>" class="<?= $active === 'panel' || $active === 'following' ? 'is-active' : '' ?><?= $new_followers > 0 ? ' has-notify' : '' ?>">
          پنل کاربری
          <?php if ($new_followers > 0) : ?>
            <span class="nav-badge" aria-label="<?= (int) $new_followers ?> دنبال‌کننده جدید"><?= (int) $new_followers ?></span>
          <?php endif; ?>
        </a>
        <a href="logout.php">خروج</a>
      <?php else : ?>
        <a href="index.php" class="<?= $active === 'home' ? 'is-active' : '' ?>">صفحه اصلی</a>
        <a href="register.php" class="<?= $active === 'register' ? 'is-active' : '' ?>">عضویت</a>
        <a href="login.php" class="<?= $active === 'login' ? 'is-active' : '' ?>">ورود</a>
        <a href="contact.php" class="<?= $active === 'contact' ? 'is-active' : '' ?>">تماس با ما</a>
        <a href="faq.php" class="<?= $active === 'faq' ? 'is-active' : '' ?>">سوالات متداول</a>
        <a href="rules.php" class="<?= $active === 'rules' ? 'is-active' : '' ?>">قوانین</a>
      <?php endif; ?>
      <?php casting_render_theme_toggle(); ?>
    </nav>
  </header>
<?php
}

function casting_render_flash(): void
{
    $flash = casting_get_flash();
    if (!$flash) {
        return;
    }
    $type = $flash['type'] === 'success' ? 'success' : 'error';
    ?>
  <div class="flash flash-<?= casting_e($type) ?>" role="alert"><?= casting_brandify($flash['message']) ?></div>
<?php
}

/**
 * نشان اعتماد اینماد (فقط پورتال کستینگ)
 */
function casting_render_enamad_seal(string $extra_class = ''): void
{
    $class = trim('enamad-seal ' . $extra_class);
    ?>
  <a
    class="<?= casting_e($class) ?>"
    referrerpolicy="origin"
    target="_blank"
    rel="noopener"
    href="https://trustseal.enamad.ir/?id=4302477&Code=s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS"
    title="نماد اعتماد الکترونیکی"
  >
    <img
      referrerpolicy="origin"
      src="https://trustseal.enamad.ir/logo.aspx?id=4302477&Code=s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS"
      alt="نماد اعتماد الکترونیکی"
      width="125"
      height="136"
      loading="lazy"
      style="cursor:pointer"
      code="s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS"
    >
  </a>
    <?php
}

function casting_render_footer(): void
{
    ?>
  <footer class="site-footer">
    <div class="site-footer-inner">
      <p><?= casting_brand_html() ?> — پورتال استعداد و بازیگری</p>
      <?php casting_render_enamad_seal(); ?>
    </div>
  </footer>
  <button type="button" class="scroll-top" data-scroll-top aria-label="بازگشت به بالای صفحه">
    <span aria-hidden="true">↑</span>
  </button>
  <?php casting_render_pwa_bootstrap(); ?>
  <script>
    window.CASTING_FOLLOW = {
      url: <?= wp_json_encode(casting_url('follow-toggle.php')) ?>,
      nonce: <?= wp_json_encode(wp_create_nonce('casting_follow')) ?>
    };
    window.CASTING_MEDIA_ENGAGE = {
      url: <?= wp_json_encode(casting_url('media-engage.php')) ?>,
      nonce: <?= wp_json_encode(wp_create_nonce('casting_media_engage')) ?>
    };
    <?php
    if (!function_exists('casting_media_protect_viewer_label')) {
        require_once __DIR__ . '/media-protect.php';
    }
    ?>
    window.CASTING_MEDIA_PROTECT = {
      watermark: <?= wp_json_encode(casting_media_protect_viewer_label()) ?>,
      isMobile: <?= wp_json_encode(wp_is_mobile()) ?>
    };
    window.CASTING_SESSION = {
      active: <?= casting_current_user() ? 'true' : 'false' ?>,
      idleSeconds: <?= (int) (function_exists('casting_session_idle_seconds') ? casting_session_idle_seconds() : 300) ?>,
      pingUrl: <?= wp_json_encode(casting_url('session-ping.php')) ?>,
      logoutUrl: <?= wp_json_encode(casting_url('logout.php?reason=idle')) ?>
    };
  </script>
  <script src="<?= casting_e(casting_asset('js/main.js')) ?>?v=95" defer></script>
</body>
</html>
<?php
}
