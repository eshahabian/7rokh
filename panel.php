<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/panel-profile.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$role = casting_get_user_role($user_id);
$profile = casting_get_profile($user_id);
$complete = casting_profile_complete($profile);
$premium = casting_user_is_premium($user_id);
$profile_error = '';
$profile_success = '';
$request_count = casting_user_new_request_count($user_id);

$profile_post = casting_process_profile_post($user_id);
if ($profile_post['error'] !== '') {
    $profile_error = $profile_post['error'];
}
if ($profile_post['success'] !== '') {
    $profile_success = $profile_post['success'];
}
if ($profile_post['profile'] !== null) {
    $profile = $profile_post['profile'];
    $complete = casting_profile_complete($profile);
}

$hide_talent_profile = casting_profile_hides_talent_fields($profile['activities'] ?? [], $user_id);

casting_render_panel_start('پنل کاربری', 'panel');
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
?>
<section class="dash-card panel-welcome">
  <span class="chip"><?= casting_e(casting_user_profile_chip_label($user_id)) ?><?php if ($premium) : ?> · ویژه<?php endif; ?></span>
  <h1>سلام، <?= casting_e($user->display_name) ?></h1>
  <?php if (($profile['membership_number'] ?? '') !== '') : ?>
    <p class="membership-number-line">شماره عضویت: <span class="membership-number"><?= casting_e((string) $profile['membership_number']) ?></span></p>
  <?php endif; ?>
  <?php if (!$complete) : ?>
    <p class="meta">پروفایلتان کامل نیست. برای دیده‌شدن بهتر، اطلاعات و عکس را تکمیل کنید.</p>
  <?php elseif (!$hide_talent_profile) : ?>
    <p class="meta">پروفایل آماده است<?= !empty($profile['visible']) ? ' و قابل مشاهده است' : '؛ فعلاً مخفی است' ?>.</p>
  <?php endif; ?>
  <?php if ($premium) : ?>
    <?php casting_render_premium_countdown($user_id); ?>
  <?php endif; ?>
  <div class="cta-row">
    <a class="btn btn-ghost" href="#completion">تکمیل پروفایل</a>
    <a class="btn btn-primary" href="#edit-profile">ویرایش اطلاعات</a>
    <a class="btn btn-ghost" href="chat.php">پیام‌ها</a>
    <a class="btn btn-ghost" href="my-requests.php">درخواست‌ها<?php if ($request_count > 0) : ?> (<?= (int) $request_count ?>)<?php endif; ?></a>
  </div>
</section>

<?php
casting_panel_render_section($user_id, static function () use ($profile): void {
    casting_render_panel_completion_card($profile);
}, 'تکمیل پروفایل');
?>

<div class="panel-profile-stack" id="profile">
  <h2 class="panel-section-title panel-stack-heading">پروفایل من</h2>
  <?php
  casting_panel_render_section($user_id, static function () use ($user_id): void {
      casting_render_member_profile_view($user_id, $user_id, true);
  }, 'نمایش پروفایل');
  ?>
</div>

<?php
casting_panel_render_section($user_id, static function () use ($user_id, $profile, $profile_error, $profile_success, $complete): void {
    casting_render_profile_edit_form(
        $user_id,
        $profile,
        $profile_error !== '' || $profile_success !== '' || !$complete || isset($_GET['edit'])
    );
}, 'ویرایش پروفایل');
?>

<?php casting_render_panel_end(); ?>
