<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/panel-profile.php';
require_once __DIR__ . '/includes/follows.php';
require_once __DIR__ . '/includes/user-media.php';
require_once __DIR__ . '/includes/media-engagement.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$profile = casting_get_profile($user_id);
$premium = casting_user_is_premium($user_id);
$activity = casting_user_primary_activity_label($user_id);
$photo = (string) ($profile['photo_url'] ?? '');
if ($photo === '') {
    $profile_shot = casting_load_portrait($user_id, 'profile');
    $photo = (string) ($profile_shot['url'] ?? '');
}
if ($photo === '') {
    $closeup = casting_load_portrait($user_id, 'closeup');
    $photo = (string) ($closeup['url'] ?? '');
}

$gallery_items = casting_user_media_public($user_id, 60);
$can_gallery = casting_user_can_manage_gallery($user_id);
$can_photos = casting_user_can_upload_portraits($user_id);
$city = trim((string) ($profile['city'] ?? ''));
$bio = trim((string) ($profile['bio'] ?? ''));
if (!function_exists('casting_dm_unread_peer_count')) {
    require_once __DIR__ . '/includes/chat.php';
}
$unread_messages = casting_dm_unread_peer_count($user_id);

$panel_title = 'پنل کاربری';
if ($activity !== '') {
    $panel_title .= ' · ' . $activity;
}
casting_render_panel_start($panel_title, 'panel');
if (isset($_GET['welcome'])) {
    echo '<div class="flash flash-success" role="alert">ثبت‌نام و ورود با موفقیت انجام شد.</div>';
}
casting_render_flash();
?>
<section class="ig-profile" aria-label="پروفایل من">
  <header class="ig-profile-header">
    <div class="ig-profile-avatar<?= $photo !== '' ? ' has-photo' : '' ?>">
      <?php if ($photo !== '') : ?>
        <img src="<?= casting_e($photo) ?>" alt="<?= casting_e((string) $user->display_name) ?>">
      <?php else : ?>
        <span class="ig-profile-avatar-fallback" aria-hidden="true"><?= casting_e(function_exists('mb_substr') ? mb_substr((string) $user->display_name, 0, 1, 'UTF-8') : substr((string) $user->display_name, 0, 1)) ?></span>
      <?php endif; ?>
      <?php casting_render_presence_dot($user_id, 'lg'); ?>
    </div>

    <div class="ig-profile-main">
      <div class="ig-profile-name-row">
        <h1 class="ig-profile-name"><?= casting_e((string) $user->display_name) ?></h1>
        <?php if ($premium) : ?>
          <span class="chip chip-premium">ویژه</span>
        <?php endif; ?>
        <?php casting_render_official_page_badge($user_id); ?>
      </div>

      <div class="ig-profile-meta">
        <?php if ($activity !== '') : ?>
          <p class="ig-profile-role"><?= casting_e($activity) ?></p>
        <?php endif; ?>
        <?php if ($city !== '') : ?>
          <p class="ig-profile-place"><?= casting_e($city) ?></p>
        <?php endif; ?>
        <?php if ($bio !== '') : ?>
          <p class="ig-profile-bio"><?= nl2br(casting_e($bio)) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="ig-profile-actions">
      <a class="btn btn-primary" href="<?= casting_e(casting_url('edit-profile.php')) ?>">ویرایش پروفایل</a>
      <a class="btn btn-ghost ig-profile-action-badge" href="<?= casting_e(casting_url('chat.php')) ?>">
        پیام کاربران
        <?php if ($unread_messages > 0) : ?>
          <span class="nav-badge" aria-label="<?= casting_e((string) $unread_messages) ?> پیام جدید"><?= (int) $unread_messages ?></span>
        <?php endif; ?>
      </a>
      <a class="btn btn-ghost" href="<?= casting_e(casting_url('change-phone.php')) ?>">تغییر شماره تلفن</a>
      <?php if ($can_photos) : ?>
        <a class="btn btn-ghost" href="<?= casting_e(casting_url('profile-photo.php')) ?>">تغییر عکس</a>
      <?php endif; ?>
      <?php if ($can_gallery) : ?>
        <a class="btn btn-ghost" href="<?= casting_e(casting_url('my-gallery.php')) ?>">افزودن پست</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= casting_e(casting_url('settings.php')) ?>">تنظیمات</a>
    </div>
  </header>

  <div class="ig-profile-tabs" role="tablist">
    <span class="ig-profile-tab is-active" role="tab" aria-selected="true">پست‌ها</span>
  </div>

  <?php if ($gallery_items === []) : ?>
    <div class="ig-profile-empty">
      <p>هنوز پستی منتشر نشده است.</p>
      <?php if ($can_gallery) : ?>
        <a class="btn btn-primary" href="<?= casting_e(casting_url('my-gallery.php')) ?>">اولین پست را اضافه کنید</a>
        <p class="meta">پس از تأیید مدیر، پست در پروفایل دیده می‌شود.</p>
      <?php endif; ?>
    </div>
  <?php else : ?>
    <div class="ig-profile-grid">
      <?php foreach ($gallery_items as $item) :
          $url = casting_user_media_url($item);
          $thumb = casting_user_media_thumb_url($item);
          if ($url === '') {
              continue;
          }
          $is_video = ($item['media_type'] ?? '') === 'video';
          $caption = trim((string) ($item['caption'] ?? ''));
          ?>
        <figure class="ig-profile-cell<?= $is_video ? ' is-video' : '' ?>">
          <a href="<?= casting_e($url) ?>" target="_blank" rel="noopener">
            <?php if ($is_video) : ?>
              <video src="<?= casting_e($url) ?>" muted preload="metadata" playsinline<?= $thumb !== '' && $thumb !== $url ? ' poster="' . casting_e($thumb) . '"' : '' ?>></video>
              <span class="ig-profile-cell-badge" aria-hidden="true">▶</span>
            <?php else : ?>
              <img src="<?= casting_e($thumb !== '' ? $thumb : $url) ?>" alt="" loading="lazy">
            <?php endif; ?>
          </a>
          <?php if ($caption !== '') : ?>
            <figcaption class="ig-profile-cell-meta<?= (function_exists('mb_strlen') ? mb_strlen($caption, 'UTF-8') : strlen($caption)) > 70 ? ' is-clamped' : '' ?>">
              <p><?= nl2br(casting_e($caption)) ?></p>
              <?php if ((function_exists('mb_strlen') ? mb_strlen($caption, 'UTF-8') : strlen($caption)) > 70) : ?>
                <button type="button" class="link-button media-engage-more" data-post-expand>بیشتر…</button>
              <?php endif; ?>
            </figcaption>
          <?php else : ?>
            <figcaption class="ig-profile-cell-meta ig-profile-cell-meta--empty" aria-hidden="true"></figcaption>
          <?php endif; ?>
          <?php casting_render_media_engagement((int) ($item['id'] ?? 0), $user_id, false); ?>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php casting_render_admin_approved_media_section($user_id); ?>
</section>
<?php
casting_render_panel_end();
?>
