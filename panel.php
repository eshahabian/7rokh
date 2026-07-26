<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/panel-profile.php';
require_once __DIR__ . '/includes/visitors.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/director-workspace.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$profile = casting_get_profile($user_id);
$complete = casting_profile_complete($profile);
$premium = casting_user_is_premium($user_id);
$profile_error = '';
$profile_success = '';
$is_edit_mode = isset($_GET['edit']);

$profile_post = casting_process_profile_post($user_id);
if ($profile_post['error'] !== '') {
    $profile_error = $profile_post['error'];
    $is_edit_mode = true;
}
if ($profile_post['success'] !== '') {
    $profile_success = $profile_post['success'];
    $is_edit_mode = true;
}
if ($profile_post['profile'] !== null) {
    $profile = $profile_post['profile'];
    $complete = casting_profile_complete($profile);
}

$view_stats = casting_profile_view_stats($user_id);
$unread_messages = casting_dm_unread_peer_count($user_id);
$favorites_count = 0;
if (casting_user_is_director_role($user_id)) {
    $favorites_count = count(casting_director_list_highlighted_talents($user_id));
}

/** @var list<array{title:string,desc:string,place:string}> $promo_ads */
$promo_ads = [
    [
        'title' => 'خانه هنرمندان جوان',
        'desc'  => 'فضای تمرین، کارگاه و معرفی استعدادهای نو',
        'place' => 'تهران، میرداماد',
    ],
    [
        'title' => 'دوبلاژ صداهای ماندگار',
        'desc'  => 'استودیو صدا، گویندگی و دوبله حرفه‌ای',
        'place' => 'تهران، جردن',
    ],
    [
        'title' => 'آتلیه تصویر سینمایی',
        'desc'  => 'عکاسی پرتره، کلوزآپ و بسته‌های ویژه بازیگری',
        'place' => 'تهران، ونک',
    ],
    [
        'title' => 'کارگاه بازیگری صحنه',
        'desc'  => 'کلاس‌های فشرده بازی و آمادگی تست بازیگری',
        'place' => 'اصفهان، چهارباغ',
    ],
];

casting_render_panel_start('پنل کاربری', $is_edit_mode ? 'edit-profile' : 'panel');
if (isset($_GET['welcome'])) {
    echo '<div class="flash flash-success" role="alert">ثبت‌نام و ورود با موفقیت انجام شد.</div>';
}
if ($profile_error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($profile_error) . '</div>';
}
if ($profile_success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($profile_success) . '</div>';
}
casting_render_flash();

if ($is_edit_mode) :
    casting_panel_render_section($user_id, static function () use ($user_id, $profile): void {
        casting_render_profile_edit_form($user_id, $profile, true);
    }, 'ویرایش پروفایل');
else :
?>
<section class="panel-home" aria-label="داشبورد پنل">
  <section class="panel-promo-banner" aria-label="محل نمایش تبلیغات اعضای ویژه">
    <div class="panel-promo-banner-copy">
      <h1>محل نمایش تبلیغات اعضای ویژه</h1>
      <p>اینجا بهترین مکان برای دیده شدن استعداد شماست</p>
      <a class="btn btn-ghost panel-promo-banner-cta" href="<?= casting_e(casting_url('premium.php')) ?>" target="_blank" rel="noopener">جزئیات بیشتر</a>
    </div>
    <div class="panel-promo-banner-art" aria-hidden="true"></div>
    <div class="panel-promo-dots" aria-hidden="true">
      <span class="is-active"></span><span></span><span></span>
    </div>
  </section>

  <section class="panel-ads-section" aria-labelledby="panel-ads-title">
    <header class="panel-ads-head">
      <h2 id="panel-ads-title">تبلیغات اعضای ویژه</h2>
    </header>
    <div class="panel-ads-grid">
      <?php foreach ($promo_ads as $ad) : ?>
        <article class="panel-ad-card">
          <div class="panel-ad-card-media">
            <span class="panel-ad-badge">عضو ویژه</span>
          </div>
          <div class="panel-ad-card-body">
            <h3><?= casting_e($ad['title']) ?></h3>
            <p><?= casting_e($ad['desc']) ?></p>
            <p class="panel-ad-place"><?= casting_e($ad['place']) ?></p>
            <a class="btn btn-ghost btn-sm" href="<?= casting_e(casting_url('newest-users.php')) ?>" target="_blank" rel="noopener">مشاهده پروفایل</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="panel-ads-foot">
      <a class="btn btn-ghost" href="<?= casting_e(casting_url('newest-users.php')) ?>" target="_blank" rel="noopener">مشاهده همه تبلیغات</a>
    </div>
  </section>

  <section class="panel-stat-grid" aria-label="خلاصه وضعیت">
    <article class="panel-stat-card">
      <span class="panel-stat-icon" aria-hidden="true">◎</span>
      <div>
        <strong><?= (int) $view_stats['total'] ?></strong>
        <span>بازدید کل پروفایل</span>
      </div>
    </article>
    <article class="panel-stat-card">
      <span class="panel-stat-icon" aria-hidden="true">✉</span>
      <div>
        <strong><?= (int) $unread_messages ?></strong>
        <span>پیام های خوانده نشده</span>
      </div>
    </article>
    <article class="panel-stat-card">
      <span class="panel-stat-icon" aria-hidden="true">◉</span>
      <div>
        <strong><?= (int) $view_stats['day'] ?></strong>
        <span>بازدید امروز</span>
      </div>
    </article>
    <article class="panel-stat-card">
      <span class="panel-stat-icon" aria-hidden="true">♥</span>
      <div>
        <strong><?= (int) $favorites_count ?></strong>
        <span>علاقمندی های من</span>
      </div>
    </article>
  </section>

  <?php if (!$complete) : ?>
    <p class="meta panel-home-hint">پروفایلتان کامل نیست. از منوی «ویرایش پروفایل من» اطلاعات را تکمیل کنید.</p>
  <?php elseif ($premium) : ?>
    <div class="panel-home-premium"><?php casting_render_premium_countdown($user_id); ?></div>
  <?php endif; ?>
</section>
<?php
endif;

casting_render_panel_end();
?>
