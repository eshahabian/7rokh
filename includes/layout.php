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
  <link rel="stylesheet" href="<?= $css ?>?v=159">
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
      <div class="header-theme theme-toggle" role="group" aria-label="انتخاب روز یا شب">
        <button type="button" class="theme-toggle-btn" data-theme-pick="night">شب</button>
        <button type="button" class="theme-toggle-btn is-active" data-theme-pick="day">روز</button>
      </div>
    <?php
}

/**
 * سفارش‌ها کنار دکمه روز/شب در منوی اصلی پورتال
 */
function casting_render_nav_cart(?string $active = null): void
{
    $user = casting_current_user();
    $role = $user ? casting_get_user_role((int) $user->ID) : '';
    $logged_in = $role !== '';

    $cart_count = 0;
    try {
        if (!function_exists('casting_cart_count')) {
            $cart_lib = __DIR__ . '/cart.php';
            if (is_file($cart_lib)) {
                require_once $cart_lib;
            }
        }
        if (function_exists('casting_cart_count')) {
            $cart_count = (int) casting_cart_count();
        }
    } catch (Throwable $e) {
        $cart_count = 0;
    }
    $href = casting_url('cart.php');
    $title = $logged_in ? 'سفارش‌ها' : 'مشاهده خدمات و سفارش‌ها';
    ?>
      <a
        href="<?= casting_e($href) ?>"
        class="nav-cart<?= $active === 'cart' ? ' is-active' : '' ?><?= $cart_count > 0 ? ' has-notify' : '' ?>"
        title="<?= casting_e($title) ?>"
      >
        <span class="nav-cart-icon" aria-hidden="true">
          <svg viewBox="0 0 576 512" width="16" height="16" focusable="false"><path fill="currentColor" d="M0 24C0 10.7 10.7 0 24 0H69.5c22 0 41.5 12.8 50.6 32h411c26.3 0 45.5 25 38.6 50.4l-41 152.3c-8.5 31.4-37 53.3-69.5 53.3H170.7l5.4 28.5c2.2 11.3 12.1 19.5 23.6 19.5H488c13.3 0 24 10.7 24 24s-10.7 24-24 24H199.7c-34.6 0-64.3-24.6-70.7-58.5L77.4 54.5c-.7-3.8-4-6.5-7.9-6.5H24C10.7 48 0 37.3 0 24zM128 464a48 48 0 1 1 96 0 48 48 0 1 1-96 0zm336-48a48 48 0 1 1 0 96 48 48 0 1 1 0-96z"/></svg>
        </span>
        <span class="nav-cart-label">سفارش‌ها</span>
        <?php if ($cart_count > 0) : ?>
          <span class="nav-badge" aria-label="<?= (int) $cart_count ?> مورد در سفارش‌ها"><?= (int) $cart_count ?></span>
        <?php endif; ?>
      </a>
    <?php
}

function casting_render_menu_toggle_button(
    string $controls_id,
    string $aria_label,
    string $extra_attrs = '',
    int $badge = 0,
    string $extra_class = ''
): void {
    $class = trim('panel-menu-toggle ' . $extra_class);
    ?>
    <button
      type="button"
      class="<?= casting_e($class) ?>"
      aria-controls="<?= casting_e($controls_id) ?>"
      aria-expanded="false"
      aria-label="<?= casting_e($aria_label) ?>"
      <?= $extra_attrs ?>
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

function casting_render_panel_menu_toggle(int $badge = 0): void
{
    casting_render_menu_toggle_button(
        'panel-drawer',
        'باز کردن منوی پنل',
        'id="panel-menu-toggle" data-panel-menu-toggle',
        $badge
    );
}

function casting_render_site_nav_toggle(): void
{
    casting_render_menu_toggle_button(
        'site-main-nav',
        'باز کردن منو',
        'data-site-nav-toggle',
        0,
        'site-nav-toggle'
    );
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
      <?php else : ?>
        <?php casting_render_site_nav_toggle(); ?>
      <?php endif; ?>
      <?php if (!$panel_menu && $role === '') : ?>
        <nav class="site-header-quick" aria-label="دسترسی سریع">
          <a href="<?= casting_e(casting_main_site_url()) ?>" class="site-header-quick-link"><?= casting_brand_html() ?></a>
          <a href="register.php" class="site-header-quick-link<?= $active === 'register' ? ' is-active' : '' ?>">ثبت نام</a>
          <a href="login.php" class="site-header-quick-link<?= $active === 'login' ? ' is-active' : '' ?>">ورود به پنل کاربری</a>
        </nav>
      <?php endif; ?>
      <div class="site-header-brand-cluster">
        <?php casting_render_theme_toggle(); ?>
        <a class="brand" href="index.php"><?= casting_brand_html() ?></a>
      </div>
    </div>
    <nav class="nav" id="site-main-nav" aria-label="منوی اصلی" data-site-nav>
      <a href="<?= casting_e(casting_main_site_url()) ?>" class="nav-external">سایت <?= casting_brand_html() ?></a>
      <?php if ($role !== '') : ?>
        <a href="home.php" class="<?= $active === 'home' ? 'is-active' : '' ?>">صفحه اصلی</a>
        <a href="<?= casting_e(casting_url($new_followers > 0 ? 'following.php?tab=followers' : 'panel.php')) ?>" class="<?= $active === 'panel' || $active === 'following' ? 'is-active' : '' ?><?= $new_followers > 0 ? ' has-notify' : '' ?>">
          پنل کاربری
          <?php if ($new_followers > 0) : ?>
            <span class="nav-badge" aria-label="<?= (int) $new_followers ?> دنبال‌کننده جدید"><?= (int) $new_followers ?></span>
          <?php endif; ?>
        </a>
        <a href="logout.php">خروج</a>
      <?php else : ?>
        <a href="index.php" class="<?= $active === 'home' ? 'is-active' : '' ?>">صفحه اصلی</a>
        <a href="register.php" class="<?= $active === 'register' ? 'is-active' : '' ?>">ثبت نام</a>
        <a href="login.php" class="<?= $active === 'login' ? 'is-active' : '' ?>">ورود به پنل کاربری</a>
        <a href="contact.php" class="<?= $active === 'contact' ? 'is-active' : '' ?>">تماس با ما</a>
        <a href="faq.php" class="<?= $active === 'faq' ? 'is-active' : '' ?>">سوالات متداول</a>
        <a href="rules.php" class="<?= $active === 'rules' ? 'is-active' : '' ?>">قوانین</a>
      <?php endif; ?>
      <?php casting_render_nav_cart($active); ?>
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
 *
 * نکته: loading=lazy و دستکاری URL باعث می‌شود بار اول لوگو خالی بماند
 * (سرور اینماد به Referer/زمان درخواست حساس است). کد نزدیک به نمونه رسمی اینماد است.
 */
function casting_render_enamad_seal(string $extra_class = ''): void
{
    $class = trim('enamad-seal ' . $extra_class);
    $enamad_id = '4302477';
    $enamad_code = 's5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS';
    $enamad_href = 'https://trustseal.enamad.ir/?id=' . $enamad_id . '&Code=' . $enamad_code;
    $enamad_src = 'https://trustseal.enamad.ir/logo.aspx?id=' . $enamad_id . '&Code=' . $enamad_code;
    ?>
  <a
    class="<?= casting_e($class) ?>"
    referrerpolicy="origin"
    target="_blank"
    href="<?= casting_e($enamad_href) ?>"
    title="نماد اعتماد الکترونیکی"
  >
    <img
      referrerpolicy="origin"
      src="<?= casting_e($enamad_src) ?>"
      alt=""
      width="125"
      height="136"
      loading="eager"
      decoding="sync"
      fetchpriority="low"
      style="cursor:pointer"
      code="<?= casting_e($enamad_code) ?>"
      data-enamad-src="<?= casting_e($enamad_src) ?>"
      data-enamad-seal
    >
  </a>
  <script>
    (function () {
      var img = document.querySelector("[data-enamad-seal]");
      if (!img) return;
      var src = img.getAttribute("data-enamad-src") || img.getAttribute("src") || "";
      var tries = 0;
      var retry = function () {
        if (!src || tries >= 3) return;
        tries += 1;
        img.removeAttribute("src");
        window.setTimeout(function () {
          img.setAttribute("referrerpolicy", "origin");
          img.src = src;
        }, 350 * tries);
      };
      img.addEventListener("error", retry);
      img.addEventListener("load", function () {
        if (img.naturalWidth < 2 || img.naturalHeight < 2) retry();
      });
      window.addEventListener("load", function () {
        window.setTimeout(function () {
          if (!img.complete || img.naturalWidth < 2) retry();
        }, 600);
      });
    })();
  </script>
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
  <script src="<?= casting_e(casting_asset('js/main.js')) ?>?v=102" defer></script>
</body>
</html>
<?php
}
