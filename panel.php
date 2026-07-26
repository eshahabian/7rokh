<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/visitors.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/director-workspace.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$profile = casting_get_profile($user_id);
$complete = casting_profile_complete($profile);
$premium = casting_user_is_premium($user_id);

$view_stats = casting_profile_view_stats($user_id);
$unread_messages = casting_dm_unread_peer_count($user_id);
$favorites_count = 0;
if (casting_user_is_director_role($user_id)) {
    $favorites_count = count(casting_director_list_highlighted_talents($user_id));
}

$premium_members = casting_home_premium_members(8, $user_id);
$newest_members = casting_newest_members(8, $user_id);

casting_render_panel_start('پنل کاربری', 'panel');
if (isset($_GET['welcome'])) {
    echo '<div class="flash flash-success" role="alert">ثبت‌نام و ورود با موفقیت انجام شد.</div>';
}
casting_render_flash();
?>
<section class="panel-home" aria-label="داشبورد پنل">
  <section class="panel-promo-banner" aria-label="محل نمایش تبلیغات اعضای ویژه">
    <div class="panel-promo-banner-copy">
      <h1>محل نمایش تبلیغات اعضای ویژه</h1>
      <p>اینجا بهترین مکان برای دیده شدن استعداد شماست</p>
      <a class="btn btn-ghost panel-promo-banner-cta" href="<?= casting_e(casting_url('premium.php')) ?>">جزئیات بیشتر</a>
    </div>
    <div class="panel-promo-banner-art" aria-hidden="true"></div>
    <div class="panel-promo-dots" aria-hidden="true">
      <span class="is-active"></span><span></span><span></span>
    </div>
  </section>

  <section class="panel-ads-section" aria-labelledby="panel-premium-title">
    <header class="panel-ads-head">
      <h2 id="panel-premium-title">اعضای ویژه</h2>
    </header>
    <?php if ($premium_members === []) : ?>
      <p class="empty-state">فعلاً عضو ویژه‌ای برای نمایش نیست.</p>
    <?php else : ?>
      <div class="panel-ads-grid">
        <?php foreach ($premium_members as $member) : ?>
          <?php casting_render_panel_home_member_tile($member, true); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel-ads-section" aria-labelledby="panel-newest-title">
    <header class="panel-ads-head">
      <h2 id="panel-newest-title">جدیدترین اعضا</h2>
    </header>
    <?php if ($newest_members === []) : ?>
      <p class="empty-state">هنوز عضو جدیدی نیست.</p>
    <?php else : ?>
      <div class="panel-ads-grid">
        <?php foreach ($newest_members as $member) : ?>
          <?php casting_render_panel_home_member_tile($member, false); ?>
        <?php endforeach; ?>
      </div>
      <div class="panel-ads-foot">
        <a class="btn btn-ghost" href="<?= casting_e(casting_url('newest-users.php')) ?>">مشاهده همه اعضای جدید</a>
      </div>
    <?php endif; ?>
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
casting_render_panel_end();
?>
