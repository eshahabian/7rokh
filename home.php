<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/panel-profile.php';
require_once __DIR__ . '/includes/panel-home.php';
require_once __DIR__ . '/includes/visitors.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/director-workspace.php';
require_once __DIR__ . '/includes/follows.php';
require_once __DIR__ . '/includes/feed.php';
require_once __DIR__ . '/includes/ad-posters.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$profile = casting_get_profile($user_id);
$complete = casting_profile_complete($profile);
$premium = casting_user_is_premium($user_id);
$can_search = casting_user_can_member_search($user_id);

$view_stats = casting_profile_view_stats($user_id);
$unread_messages = casting_dm_unread_peer_count($user_id);
$favorites_count = 0;
if (casting_user_is_director_role($user_id)) {
    $favorites_count = count(casting_director_list_highlighted_talents($user_id));
}
$followers_count = casting_followers_count($user_id);
$following_count = casting_following_count($user_id);

$premium_members = casting_home_premium_members(24, $user_id);
$newest_members = casting_newest_members(24, $user_id);

$panel_title = 'خانه';
$primary_activity = casting_user_primary_activity_label($user_id);
if ($primary_activity !== '') {
    $panel_title .= ' · ' . $primary_activity;
}
casting_render_panel_start($panel_title, 'home');
if (isset($_GET['welcome'])) {
    echo '<div class="flash flash-success" role="alert">ثبت‌نام و ورود با موفقیت انجام شد.</div>';
}
casting_render_flash();
$welcome = casting_panel_home_welcome($user_id, (string) $user->display_name, (string) ($profile['gender'] ?? ''));
?>
<section class="panel-home" aria-label="خانه">
  <header class="panel-home-greeting flash flash-success" role="status">
    <p class="panel-home-greeting-eyebrow"><?= casting_brand_html() ?></p>
    <h2 class="panel-home-greeting-title"><?= casting_e($welcome['headline']) ?></h2>
    <p class="panel-home-greeting-sub"><?= casting_brandify($welcome['subline']) ?></p>
  </header>

  <?php
  $promo_slides = [
      ['src' => casting_asset('images/promo-slide-1.png'), 'alt' => 'صحنه فیلم‌برداری و صندلی کارگردان'],
      ['src' => casting_asset('images/promo-slide-2.png'), 'alt' => 'دوربین سینمایی و تجهیزات تولید'],
      ['src' => casting_asset('images/promo-slide-3.png'), 'alt' => 'سالن سینما و پرده نمایش'],
      ['src' => casting_asset('images/promo-slide-4.png'), 'alt' => 'دوربین سینمایی روی سه‌پایه در استودیو'],
      ['src' => casting_asset('images/promo-slide-5.png'), 'alt' => 'میز گریم و آینه پشت صحنه'],
      ['src' => casting_asset('images/promo-slide-6.png'), 'alt' => 'میکروفون بوم و صحنه فیلم‌برداری'],
  ];
  casting_render_promo_banner($promo_slides);
  ?>

  <?php casting_render_panel_home_quick_filters($can_search); ?>

  <?php casting_render_home_opportunities_section($user_id); ?>

  <?php casting_render_home_following_feed_section($user_id); ?>

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
    <a class="panel-stat-card panel-stat-card--link" href="<?= casting_e(casting_url('following.php?tab=followers')) ?>">
      <span class="panel-stat-icon" aria-hidden="true">◉</span>
      <div>
        <strong><?= (int) $followers_count ?></strong>
        <span>دنبال‌کننده‌ها</span>
      </div>
    </a>
    <a class="panel-stat-card panel-stat-card--link" href="<?= casting_e(casting_url('following.php?tab=following')) ?>">
      <span class="panel-stat-icon" aria-hidden="true">☆</span>
      <div>
        <strong><?= (int) $following_count ?></strong>
        <span>دنبال‌شده‌ها</span>
      </div>
    </a>
    <?php if (casting_user_is_director_role($user_id)) : ?>
      <article class="panel-stat-card">
        <span class="panel-stat-icon" aria-hidden="true">♥</span>
        <div>
          <strong><?= (int) $favorites_count ?></strong>
          <span>لیست کاندیدا</span>
        </div>
      </article>
      <article class="panel-stat-card">
        <span class="panel-stat-icon" aria-hidden="true">◎</span>
        <div>
          <strong><?= (int) $view_stats['day'] ?></strong>
          <span>بازدید امروز</span>
        </div>
      </article>
    <?php else : ?>
      <article class="panel-stat-card">
        <span class="panel-stat-icon" aria-hidden="true">◉</span>
        <div>
          <strong><?= (int) $view_stats['day'] ?></strong>
          <span>بازدید امروز</span>
        </div>
      </article>
    <?php endif; ?>
  </section>

  <section class="panel-ads-section" aria-labelledby="panel-premium-title">
    <header class="panel-ads-head">
      <h2 id="panel-premium-title">اعضای ویژه</h2>
    </header>
    <?php if ($premium_members === []) : ?>
      <p class="empty-state">فعلاً عضو ویژه‌ای برای نمایش نیست.</p>
    <?php else : ?>
      <?php casting_render_panel_home_member_row($premium_members, true, 'panel-premium-more', 4, $user_id); ?>
    <?php endif; ?>
  </section>

  <section class="panel-ads-section" aria-labelledby="panel-newest-title">
    <header class="panel-ads-head">
      <h2 id="panel-newest-title">جدیدترین اعضا</h2>
    </header>
    <?php if ($newest_members === []) : ?>
      <p class="empty-state">هنوز عضو جدیدی نیست.</p>
    <?php else : ?>
      <?php
      // «بیشتر» فقط برای کارگردان‌ها؛ ادمین‌های پورتال همیشه مستثنی‌اند
      $newest_allow_more = casting_user_is_director_role($user_id)
          || casting_user_is_super_admin($user_id)
          || casting_user_is_listed_portal_admin($user_id);
      casting_render_panel_home_member_row(
          $newest_members,
          false,
          'panel-newest-more',
          8,
          $user_id,
          $newest_allow_more
      );
      ?>
    <?php endif; ?>
  </section>

  <?php if (casting_user_is_super_admin($user_id)) : ?>
    <section class="panel-ads-section panel-home-member-stats" aria-labelledby="panel-member-stats-title">
      <header class="panel-ads-head">
        <h2 id="panel-member-stats-title">آمار تخصص‌های هنری</h2>
      </header>
      <?php casting_render_member_count_tiles(null, 'home-stats--panel'); ?>
    </section>
  <?php endif; ?>

  <?php
  $completion_percent = function_exists('casting_profile_completion_percent')
      ? casting_profile_completion_percent($profile, $user_id)
      : ($complete ? 100 : 0);
  if ($completion_percent < 100) :
      if (!function_exists('casting_render_panel_completion_card')) {
          require_once __DIR__ . '/includes/panel-profile.php';
      }
      ?>
    <div class="panel-home-completion">
      <?php casting_render_panel_completion_card($profile, $user_id); ?>
    </div>
  <?php elseif ($premium) : ?>
    <div class="panel-home-premium"><?php casting_render_premium_countdown($user_id); ?></div>
  <?php endif; ?>
</section>
<?php
casting_render_panel_end();
?>
