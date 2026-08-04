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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_key((string) ($_POST['panel_media_action'] ?? ''));
    if ($action === 'delete') {
        $nonce = (string) ($_POST['_wpnonce'] ?? '');
        if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_panel_media')) {
            casting_set_flash('error', 'نشست منقضی شده. دوباره تلاش کنید.');
            casting_redirect('panel.php');
        }
        if (!casting_user_can_manage_gallery($user_id)) {
            casting_set_flash('error', 'اجازه حذف پست را ندارید.');
            casting_redirect('panel.php');
        }
        $res = casting_user_media_delete_own($user_id, (int) ($_POST['media_id'] ?? 0));
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'پست حذف شد.' : $res['error']);
        casting_redirect('panel.php');
    }
}

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

$posts_count = casting_user_media_public_count($user_id);
$followers_count = casting_followers_count($user_id);
$following_count = casting_following_count($user_id);
$gallery_items = casting_user_media_public($user_id, 60);
$pending_items = casting_user_media_list($user_id, 'pending', 40);
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

      <ul class="ig-profile-stats">
        <li>
          <strong><?= (int) $posts_count ?></strong>
          <span>پست</span>
        </li>
        <li>
          <a href="<?= casting_e(casting_url('following.php?tab=followers')) ?>">
            <strong><?= (int) $followers_count ?></strong>
            <span>دنبال‌کننده</span>
          </a>
        </li>
        <li>
          <a href="<?= casting_e(casting_url('following.php?tab=following')) ?>">
            <strong><?= (int) $following_count ?></strong>
            <span>دنبال‌شده</span>
          </a>
        </li>
      </ul>

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

  <?php if ($pending_items !== []) : ?>
    <section class="ig-profile-pending" aria-labelledby="ig-pending-title">
      <header class="ig-profile-pending-head">
        <h2 id="ig-pending-title">در انتظار تأیید</h2>
        <p class="meta"><?= (int) count($pending_items) ?> پست هنوز منتشر نشده است.</p>
      </header>
      <div class="ig-profile-grid ig-profile-grid--pending">
        <?php foreach ($pending_items as $item) :
            $url = casting_user_media_url($item);
            $thumb = casting_user_media_thumb_url($item);
            if ($url === '' && $thumb === '') {
                continue;
            }
            $is_video = ($item['media_type'] ?? '') === 'video';
            $caption = trim((string) ($item['caption'] ?? ''));
            $media_id = (int) ($item['id'] ?? 0);
            ?>
          <figure class="ig-profile-cell ig-profile-cell--thumb is-pending<?= $is_video ? ' is-video' : '' ?>">
            <a href="<?= casting_e($url !== '' ? $url : $thumb) ?>" data-post-expand aria-label="مشاهده پست در انتظار تأیید">
              <?php if ($is_video) : ?>
                <video src="<?= casting_e($url) ?>" muted preload="metadata" playsinline<?= $thumb !== '' && $thumb !== $url ? ' poster="' . casting_e($thumb) . '"' : '' ?>></video>
                <span class="ig-profile-cell-badge" aria-hidden="true">▶</span>
              <?php else : ?>
                <img src="<?= casting_e($thumb !== '' ? $thumb : $url) ?>" alt="" loading="lazy">
              <?php endif; ?>
            </a>
            <span class="ig-profile-pending-chip">در انتظار تأیید</span>
            <?php if ($caption !== '') : ?>
              <figcaption class="ig-profile-cell-meta">
                <p><?= nl2br(casting_e($caption)) ?></p>
              </figcaption>
            <?php else : ?>
              <figcaption class="ig-profile-cell-meta ig-profile-cell-meta--empty" aria-hidden="true"></figcaption>
            <?php endif; ?>
            <?php if ($can_gallery && $media_id > 0) : ?>
              <form class="ig-profile-cell-delete" method="post" action="panel.php" onsubmit="return confirm('این پست حذف شود؟');">
                <?php wp_nonce_field('casting_panel_media'); ?>
                <input type="hidden" name="panel_media_action" value="delete">
                <input type="hidden" name="media_id" value="<?= $media_id ?>">
                <button class="btn btn-ghost btn-sm" type="submit" title="حذف پست">حذف</button>
              </form>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($gallery_items === []) : ?>
    <div class="ig-profile-empty">
      <p><?= $pending_items !== [] ? 'هنوز پست تأییدشده‌ای ندارید.' : 'هنوز پستی منتشر نشده است.' ?></p>
      <?php if ($can_gallery) : ?>
        <a class="btn btn-primary" href="<?= casting_e(casting_url('my-gallery.php')) ?>">اولین پست را اضافه کنید</a>
        <?php if (!casting_user_can_auto_publish_media($user_id)) : ?>
          <p class="meta">پس از تأیید مدیر، پست در پروفایل دیده می‌شود.</p>
        <?php endif; ?>
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
          $media_id = (int) ($item['id'] ?? 0);
          ?>
        <figure class="ig-profile-cell ig-profile-cell--thumb<?= $is_video ? ' is-video' : '' ?>">
          <a href="<?= casting_e($url) ?>" data-post-expand aria-label="مشاهده پست">
            <?php if ($is_video) : ?>
              <video src="<?= casting_e($url) ?>" muted preload="metadata" playsinline<?= $thumb !== '' && $thumb !== $url ? ' poster="' . casting_e($thumb) . '"' : '' ?>></video>
              <span class="ig-profile-cell-badge" aria-hidden="true">▶</span>
            <?php else : ?>
              <img src="<?= casting_e($thumb !== '' ? $thumb : $url) ?>" alt="" loading="lazy">
            <?php endif; ?>
          </a>
          <?php if ($caption !== '') : ?>
            <figcaption class="ig-profile-cell-meta">
              <p><?= nl2br(casting_e($caption)) ?></p>
            </figcaption>
          <?php else : ?>
            <figcaption class="ig-profile-cell-meta ig-profile-cell-meta--empty" aria-hidden="true"></figcaption>
          <?php endif; ?>
          <?php casting_render_media_engagement($media_id, $user_id, false); ?>
          <?php if ($can_gallery && $media_id > 0) : ?>
            <form class="ig-profile-cell-delete" method="post" action="panel.php" onsubmit="return confirm('این پست حذف شود؟');">
              <?php wp_nonce_field('casting_panel_media'); ?>
              <input type="hidden" name="panel_media_action" value="delete">
              <input type="hidden" name="media_id" value="<?= $media_id ?>">
              <button class="btn btn-ghost btn-sm" type="submit" title="حذف پست">حذف</button>
            </form>
          <?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php casting_render_admin_approved_media_section($user_id); ?>
</section>
<?php
casting_render_panel_end();
?>
