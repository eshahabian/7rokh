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

$counts = CASTING_PUBLIC_HOME_STATS ? casting_member_counts() : ['tiles' => []];
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
    <section class="panel-promo-banner hero-promo-banner" aria-label="مکانی برای دیده شدن" data-promo-slider>
      <div class="panel-promo-slides">
        <?php foreach ($home_slides as $i => $slide) : ?>
          <figure class="panel-promo-slide<?= $i === 0 ? ' is-active' : '' ?>">
            <img src="<?= casting_e($slide['src']) ?>" alt="<?= casting_e($slide['alt']) ?>" width="1280" height="720" decoding="<?= $i === 0 ? 'sync' : 'async' ?>">
          </figure>
        <?php endforeach; ?>
      </div>
      <div class="panel-promo-banner-copy">
        <h1>مکانی برای دیده شدن</h1>
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

    <?php if (CASTING_PUBLIC_HOME_STATS) : ?>
      <?php casting_render_member_count_tiles($counts); ?>
    <?php endif; ?>
  </div>
</main>
<?php casting_render_footer(); ?>
