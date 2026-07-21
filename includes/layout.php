<?php
declare(strict_types=1);

require_once __DIR__ . '/pwa.php';

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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $css ?>?v=58">
</head>
<body class="<?= casting_e($body_class) ?>">
  <div class="bg-atmosphere" aria-hidden="true"></div>
<?php
}

function casting_render_header(?string $active = null): void
{
    $brand = casting_e(casting_brand());
    $user = casting_current_user();
    $role = $user ? casting_get_user_role((int) $user->ID) : '';
    ?>
  <header class="site-header">
    <a class="brand" href="index.php"><?= $brand ?></a>
    <nav class="nav" aria-label="منوی اصلی">
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
  <?php casting_render_pwa_bootstrap(); ?>
  <script src="<?= casting_e(casting_asset('js/main.js')) ?>?v=48" defer></script>
</body>
</html>
<?php
}
