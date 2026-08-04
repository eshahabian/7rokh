<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/layout.php';

$user = casting_current_user();
if ($user) {
    $role = casting_get_user_role((int) $user->ID);
    if ($role !== '') {
        casting_redirect('home.php');
    }
}

$counts = casting_member_counts();
$home_slides = [
    ['src' => casting_asset('images/home-slide-1.png'), 'alt' => 'صحنه فیلم‌برداری و صندلی کارگردان'],
    ['src' => casting_asset('images/home-slide-2.png'), 'alt' => 'دوربین سینمایی و تجهیزات تولید'],
    ['src' => casting_asset('images/home-slide-3.png'), 'alt' => 'سالن تئاتر و صحنه نمایش'],
    ['src' => casting_asset('images/home-slide-4.png'), 'alt' => 'پشت صحنه و میز گریم'],
    ['src' => casting_asset('images/home-slide-5.png'), 'alt' => 'کلاکت و فیلمنامه'],
    ['src' => casting_asset('images/home-slide-6.png'), 'alt' => 'تجهیزات صدا و فیلم‌برداری'],
];

casting_render_head('خانه', 'page-home');
casting_render_header('home');
casting_render_flash();
?>
<main class="wrap hero">
  <div class="hero-copy">
    <section class="panel-promo-banner hero-promo-banner" aria-label="نمایش ویژه" data-promo-slider>
      <div class="panel-promo-slides">
        <?php foreach ($home_slides as $i => $slide) : ?>
          <figure class="panel-promo-slide<?= $i === 0 ? ' is-active' : '' ?>">
            <img src="<?= casting_e($slide['src']) ?>" alt="<?= casting_e($slide['alt']) ?>" width="1280" height="720" decoding="<?= $i === 0 ? 'sync' : 'async' ?>">
          </figure>
        <?php endforeach; ?>
      </div>
      <div class="panel-promo-banner-copy">
        <h1>محل نمایش تبلیغات اعضای ویژه</h1>
        <p>اینجا بهترین مکان برای دیده شدن استعداد شماست</p>
      </div>
      <div class="panel-promo-dots" data-promo-dots role="tablist" aria-label="اسلایدها">
        <?php foreach ($home_slides as $i => $slide) : ?>
          <button
            type="button"
            class="<?= $i === 0 ? 'is-active' : '' ?>"
            aria-label="اسلاید <?= (int) ($i + 1) ?>"
            aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
            data-promo-dot="<?= (int) $i ?>"
          ></button>
        <?php endforeach; ?>
      </div>
    </section>

    <p class="hero-lead"><?= casting_brand_html() ?> - پرتابل ارتباط هنرمندان سینما و تئاتر با پروژه های هنری</p>
    <div class="cta-row hero-cta">
      <a class="btn btn-primary" href="register.php">عضویت</a>
      <a class="btn btn-primary" href="login.php">ورود</a>
    </div>

    <div class="home-stats" aria-label="آمار اعضا">
      <div class="stat-item">
        <strong><?= (int) $counts['talents'] ?></strong>
        <span>هنرمند</span>
      </div>
      <div class="stat-item">
        <strong><?= (int) $counts['employers'] ?></strong>
        <span>کارفرما</span>
      </div>
      <div class="stat-item">
        <strong><?= (int) $counts['total'] ?></strong>
        <span>کل اعضا</span>
      </div>
    </div>
  </div>
</main>
<?php casting_render_footer(); ?>
