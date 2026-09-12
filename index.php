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
casting_safe_require_once(__DIR__ . '/includes/works-catalog.php');
casting_safe_require_once(__DIR__ . '/includes/landing-showcase.php');
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

$casting_href = static function (string $path): string {
    if (function_exists('casting_url')) {
        return casting_url($path);
    }
    return $path;
};
$eurl = static function (string $path) use ($casting_href): string {
    $url = $casting_href($path);
    return function_exists('casting_e') ? casting_e($url) : htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
};
$casting_asset_src = static function (string $rel): string {
    if (function_exists('casting_asset')) {
        return casting_asset($rel);
    }
    return 'assets/' . ltrim($rel, '/');
};

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
<main class="wrap landing">
  <section class="landing-hero" aria-label="معرفی پورتال">
    <?php
    $home_slides = [
        ['src' => $casting_asset_src('images/home-slide-1.png'), 'alt' => 'صحنه فیلم‌برداری و صندلی کارگردان'],
        ['src' => $casting_asset_src('images/home-slide-2.png'), 'alt' => 'دوربین سینمایی و تجهیزات تولید'],
        ['src' => $casting_asset_src('images/home-slide-3.png'), 'alt' => 'سالن تئاتر و صحنه نمایش'],
        ['src' => $casting_asset_src('images/home-slide-4.png'), 'alt' => 'پشت صحنه و میز گریم'],
        ['src' => $casting_asset_src('images/home-slide-5.png'), 'alt' => 'کلاکت و فیلمنامه'],
        ['src' => $casting_asset_src('images/home-slide-6.png'), 'alt' => 'تجهیزات صدا و فیلم‌برداری'],
    ];
    $banner_shown = false;
    try {
        if (function_exists('casting_render_promo_banner')) {
            casting_render_promo_banner($home_slides, 'hero-promo-banner landing-hero-banner', 'پورتال استعداد سینما و تئاتر');
            $banner_shown = true;
        }
    } catch (Throwable $e) {
        $banner_shown = false;
    }
    if (!$banner_shown) {
        $first = $home_slides[0]['src'] ?? '';
        $alt = $home_slides[0]['alt'] ?? '';
        echo '<section class="panel-promo-banner hero-promo-banner landing-hero-banner" aria-label="پورتال استعداد سینما و تئاتر" data-promo-slider><div class="panel-promo-slides">';
        foreach ($home_slides as $i => $slide) {
            $active = $i === 0 ? ' is-active' : '';
            echo '<figure class="panel-promo-slide' . $active . '"><img src="' . htmlspecialchars((string) ($slide['src'] ?? $first), ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string) ($slide['alt'] ?? $alt), ENT_QUOTES, 'UTF-8') . '"></figure>';
        }
        echo '</div></section>';
    }
    ?>
  </section>

  <section class="landing-intro">
    <p class="landing-kicker"><?= function_exists('casting_brand_html') ? casting_brand_html() : '۷ رخ' ?></p>
    <h1 class="landing-title">پورتال ارتباط هنرمندان با پروژه‌های سینما و تئاتر</h1>
    <p class="landing-lead">پروفایل بسازید، نقش خود را انتخاب کنید و با کارگردان‌ها و تهیه‌کننده‌ها در یک فضای حرفه‌ای مرتبط شوید.</p>
  </section>

  <section class="landing-roles" aria-label="نقش‌های پورتال">
    <header class="landing-section-head">
      <h2>از کجا شروع می‌کنید؟</h2>
      <p>سه نقش اصلی پورتال؛ بعد از ورود، پروفایل را کامل کنید.</p>
    </header>
    <div class="landing-role-grid">
      <article class="landing-role">
        <span class="landing-role-tag">بازیگران</span>
        <h3>بازیگر و عوامل هنری</h3>
        <p>رزومه، عکس و تخصص را ثبت کنید تا برای نقش‌های مناسب دیده شوید.</p>
      </article>
      <article class="landing-role">
        <span class="landing-role-tag">کارگردان</span>
        <h3>جست‌وجو و انتخاب بازیگر</h3>
        <p>از مسیر کارفرما وارد شوید و استعدادها را برای پروژه پیدا کنید.</p>
      </article>
      <article class="landing-role">
        <span class="landing-role-tag">تهیه‌کننده</span>
        <h3>تولید و همکاری حرفه‌ای</h3>
        <p>حساب کارفرما بسازید؛ تخصص تهیه را بعداً در پروفایل مشخص می‌کنید.</p>
      </article>
    </div>
  </section>

  <?php
  if (function_exists('casting_render_landing_showcases')) {
      try {
          casting_render_landing_showcases();
      } catch (Throwable $e) {
          // صفحه اول بدون اسلایدر هم باید باز شود
      }
  }
  ?>

  <section class="landing-features" aria-label="امکانات پورتال">
    <header class="landing-section-head">
      <h2>آنچه در پورتال دارید</h2>
    </header>
    <div class="landing-feature-grid">
      <article class="landing-feature">
        <h3>پروفایل حرفه‌ای</h3>
        <p>مشخصات هنری، نمونه کار و فعالیت‌ها در یک صفحه قابل ارائه.</p>
      </article>
      <article class="landing-feature">
        <h3>ارتباط پروژه‌ای</h3>
        <p>کارگردان و تهیه‌کننده می‌توانند بازیگر مناسب را پیدا کنند و پیام بگذارند.</p>
      </article>
      <article class="landing-feature">
        <h3>اپلیکیشن موبایل</h3>
        <p>همان پورتال روی گوشی؛ ورود سریع‌تر و دسترسی ساده‌تر.</p>
      </article>
      <article class="landing-feature">
        <h3>پشتیبانی و قوانین</h3>
        <p>تماس با مدیران، پرسش‌های متداول و قوانین عضویت در دسترس است.</p>
      </article>
    </div>
    <div class="cta-row landing-cta landing-cta--secondary">
      <a class="btn btn-ghost" href="<?= $eurl('contact.php') ?>">تماس با ما</a>
      <a class="btn btn-ghost" href="<?= $eurl('faq.php') ?>">سوالات متداول</a>
      <a class="btn btn-ghost" href="<?= $eurl('rules.php') ?>">قوانین</a>
    </div>
  </section>

  <?php if ($stats_on && function_exists('casting_render_member_count_tiles')) : ?>
    <section class="landing-stats" aria-label="آمار اعضا">
      <?php casting_render_member_count_tiles($counts); ?>
    </section>
  <?php endif; ?>

  <section class="landing-trust" aria-label="نماد اعتماد الکترونیکی">
    <p class="landing-trust-note">ورود امن با حساب کاربری؛ نماد اعتماد در همین صفحه باقی می‌ماند.</p>
    <div class="home-enamad">
      <?php
      if (function_exists('casting_render_enamad_seal')) {
          casting_render_enamad_seal('enamad-seal--home');
      } else {
          echo '<a referrerpolicy="origin" target="_blank" href="https://trustseal.enamad.ir/?id=768314&Code=s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS"><img referrerpolicy="origin" src="https://trustseal.enamad.ir/logo.aspx?id=768314&Code=s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS" alt="" style="cursor:pointer" code="s5XHl5CaYUtaNbfKIaHLRyYFbuIoYbAS"></a>';
      }
      ?>
    </div>
    <p class="hero-login-hint">قبلاً عضو شده‌اید؟ <a href="<?= $eurl('login.php') ?>">ورود به پورتال</a></p>
  </section>
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
