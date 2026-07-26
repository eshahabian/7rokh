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
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $css ?>?v=89">
  <script>
    (function () {
      try {
        if (localStorage.getItem('casting_theme') === 'day') {
          document.documentElement.setAttribute('data-theme', 'day');
        }
      } catch (e) {}
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
        <button type="button" class="theme-toggle-btn is-active" data-theme-pick="night">شب</button>
        <button type="button" class="theme-toggle-btn" data-theme-pick="day">روز</button>
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
    $brand = casting_e(casting_brand());
    $user = casting_current_user();
    $role = $user ? casting_get_user_role((int) $user->ID) : '';
    ?>
  <header class="site-header<?= $panel_menu ? ' site-header--panel' : '' ?>">
    <div class="site-header-bar">
      <a class="brand" href="index.php"><?= $brand ?></a>
      <?php if ($panel_menu) : ?>
        <?php casting_render_panel_menu_toggle($panel_menu_badge); ?>
      <?php endif; ?>
    </div>
    <nav class="nav" aria-label="منوی اصلی">
      <a href="<?= casting_e(casting_main_site_url()) ?>" class="nav-external" target="_blank" rel="noopener">سایت هفت رخ</a>
      <?php if ($role !== '') : ?>
        <a href="index.php" class="<?= $active === 'home' ? 'is-active' : '' ?>">صفحه اصلی</a>
        <a href="panel.php" class="<?= $active === 'panel' ? 'is-active' : '' ?>">پنل کاربری</a>
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
  <div class="flash flash-<?= casting_e($type) ?>" role="alert"><?= casting_e($flash['message']) ?></div>
<?php
}

function casting_render_footer(): void
{
    ?>
  <footer class="site-footer">
    <p><?= casting_e(casting_brand()) ?> — پورتال استعداد و بازیگری</p>
  </footer>
  <button type="button" class="scroll-top" data-scroll-top aria-label="بازگشت به بالای صفحه">
    <span aria-hidden="true">↑</span>
  </button>
  <?php casting_render_pwa_bootstrap(); ?>
  <script src="<?= casting_e(casting_asset('js/main.js')) ?>?v=64" defer></script>
</body>
</html>
<?php
}
