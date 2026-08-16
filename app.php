<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mobile-app.php';

$user = casting_current_user();
$logged_in = $user && casting_get_user_role((int) $user->ID) !== '';
if ($logged_in) {
    require_once __DIR__ . '/includes/panel.php';
} else {
    require_once __DIR__ . '/includes/layout.php';
}

$apk_ready = casting_android_apk_ready();

if ($logged_in) {
    casting_render_panel_start('اپلیکیشن موبایل', 'app');
} else {
    casting_render_head('اپلیکیشن موبایل', 'page-app');
    casting_render_header('app');
}
casting_render_flash();
?>
<?php if (!$logged_in) : ?><main class="wrap panel-page"><?php endif; ?>
  <section class="<?= $logged_in ? 'dash-card panel-wide app-download-page' : 'panel panel-wide app-download-page' ?>">
    <h1>اپلیکیشن موبایل ۷ رخ</h1>
    <p class="lede">پورتال ۷ رخ را روی گوشی نصب کنید تا سریع‌تر وارد شوید و اعلان‌ها را از دست ندهید.</p>

    <div class="app-download-web">
      <article class="app-download-card">
        <h2>اندروید</h2>
        <p class="meta">فایل نصب مستقیم (APK) برای گوشی‌های اندروید.</p>
        <?php if ($apk_ready) : ?>
          <a class="btn btn-primary" href="<?= casting_e(casting_android_apk_download_url()) ?>">دانلود اپلیکیشن اندروید</a>
        <?php else : ?>
          <p class="app-download-soon">فایل نصب اندروید به‌زودی اینجا قرار می‌گیرد.</p>
        <?php endif; ?>
      </article>
      <article class="app-download-card">
        <h2>آیفون</h2>
        <p class="meta">نسخه iOS به‌زودی از طریق App Store در دسترس خواهد بود.</p>
        <p class="app-download-soon">به‌زودی</p>
      </article>
    </div>

    <p class="app-download-inapp meta">شما الان داخل اپلیکیشن هستید. نیازی به دانلود دوباره نیست.</p>
  </section>
<?php if ($logged_in) : ?>
<?php casting_render_panel_end(); ?>
<?php else : ?>
</main>
<?php casting_render_footer(); ?>
<?php endif; ?>
