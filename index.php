<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/safe-load.php';

if (!casting_safe_require_once(__DIR__ . '/includes/bootstrap.php')) {
    casting_safe_fail_page(
        'صفحه اصلی پورتال بارگذاری نشد',
        'فایل includes/bootstrap.php ناقص یا خراب است. ' . casting_safe_load_error()
    );
}

if (!casting_safe_require_once(__DIR__ . '/includes/layout.php')) {
    casting_safe_fail_page(
        'صفحه اصلی پورتال بارگذاری نشد',
        'فایل includes/layout.php ناقص یا خراب است. ' . casting_safe_load_error()
    );
}

$stats_on = defined('CASTING_PUBLIC_HOME_STATS') && CASTING_PUBLIC_HOME_STATS;
if ($stats_on) {
    casting_safe_require_once(__DIR__ . '/includes/profile.php');
}
casting_safe_require_once(__DIR__ . '/includes/ad-posters.php');

try {
    $user = function_exists('casting_current_user') ? casting_current_user() : null;
    if ($user && function_exists('casting_get_user_role')) {
        $role = casting_get_user_role((int) $user->ID);
        if ($role !== '' && function_exists('casting_redirect')) {
            casting_redirect('home.php');
        }
    }
} catch (Throwable $e) {
    $user = null;
}

$counts = ['tiles' => []];
if ($stats_on && function_exists('casting_member_counts')) {
    try {
        $counts = casting_member_counts();
    } catch (Throwable $e) {
        $counts = ['tiles' => []];
    }
}

if (function_exists('casting_render_head')) {
    casting_render_head('خانه', 'page-home');
}
if (function_exists('casting_render_header')) {
    casting_render_header('home');
}
if (function_exists('casting_render_flash')) {
    casting_render_flash();
}
?>
<main class="wrap hero">
  <div class="hero-copy">
    <?php
    $home_slides = [
        ['src' => function_exists('casting_asset') ? casting_asset('images/home-slide-1.png') : 'assets/images/home-slide-1.png', 'alt' => 'صحنه فیلم‌برداری و صندلی کارگردان'],
        ['src' => function_exists('casting_asset') ? casting_asset('images/home-slide-2.png') : 'assets/images/home-slide-2.png', 'alt' => 'دوربین سینمایی و تجهیزات تولید'],
        ['src' => function_exists('casting_asset') ? casting_asset('images/home-slide-3.png') : 'assets/images/home-slide-3.png', 'alt' => 'سالن تئاتر و صحنه نمایش'],
        ['src' => function_exists('casting_asset') ? casting_asset('images/home-slide-4.png') : 'assets/images/home-slide-4.png', 'alt' => 'پشت صحنه و میز گریم'],
        ['src' => function_exists('casting_asset') ? casting_asset('images/home-slide-5.png') : 'assets/images/home-slide-5.png', 'alt' => 'کلاکت و فیلمنامه'],
        ['src' => function_exists('casting_asset') ? casting_asset('images/home-slide-6.png') : 'assets/images/home-slide-6.png', 'alt' => 'تجهیزات صدا و فیلم‌برداری'],
    ];
    try {
        if (function_exists('casting_render_promo_banner')) {
            casting_render_promo_banner($home_slides, 'hero-promo-banner');
        }
    } catch (Throwable $e) {
        echo '<section class="panel-promo-banner hero-promo-banner" aria-label="اینجا برای تبلیغات شماست"><div class="panel-promo-slides">';
        echo '<figure class="panel-promo-slide is-active"><img src="' . htmlspecialchars($home_slides[0]['src'], ENT_QUOTES, 'UTF-8') . '" alt=""></figure>';
        echo '</div></section>';
    }
    ?>

    <p class="hero-lead"><?= function_exists('casting_brand_html') ? casting_brand_html() : '۷ رخ' ?> - پورتال ارتباط هنرمندان سینما و تئاتر با پروژه های هنری</p>

    <div class="home-enamad" aria-label="نماد اعتماد الکترونیکی">
<a referrerpolicy='origin' target='_blank' href='https://trustseal.enamad.ir/?id=768314&Code=s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS'><img referrerpolicy='origin' src='https://trustseal.enamad.ir/logo.aspx?id=768314&Code=s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS' alt='' style='cursor:pointer' code='s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS'></a>
    </div>

    <?php if ($stats_on && function_exists('casting_render_member_count_tiles')) : ?>
      <?php casting_render_member_count_tiles($counts); ?>
    <?php endif; ?>
  </div>
</main>
<?php
try {
    if (function_exists('casting_render_footer')) {
        casting_render_footer(true);
    }
} catch (Throwable $e) {
    if (function_exists('casting_render_footer')) {
        try {
            casting_render_footer();
        } catch (Throwable $e2) {
            echo '</body></html>';
        }
    }
}
