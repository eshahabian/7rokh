<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/user-media.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_can_manage_gallery($user_id)) {
    casting_set_flash('error', 'گالری فقط برای بازیگران فعال است.');
    casting_redirect('home.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_gallery')) {
        casting_set_flash('error', 'نشست منقضی شده. دوباره تلاش کنید.');
        casting_redirect('my-gallery.php');
    }
    $action = sanitize_key((string) ($_POST['gallery_action'] ?? 'upload'));
    if ($action === 'delete') {
        $res = casting_user_media_delete_own($user_id, (int) ($_POST['media_id'] ?? 0));
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'حذف شد.' : $res['error']);
    } else {
        if (!empty($_FILES['gallery_photo']['name'])) {
            $res = casting_user_media_submit_upload($user_id, 'gallery_photo', 'photo');
        } elseif (!empty($_FILES['gallery_video']['name'])) {
            $res = casting_user_media_submit_upload($user_id, 'gallery_video', 'video');
        } else {
            $res = ['ok' => false, 'error' => 'یک عکس یا ویدیو انتخاب کنید.'];
        }
        casting_set_flash(
            $res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'ارسال شد و پس از تأیید مدیر در پروفایل نمایش داده می‌شود.' : $res['error']
        );
    }
    casting_redirect('my-gallery.php');
}

$items = casting_user_media_list($user_id, '', 80);
casting_render_panel_start('گالری من', 'gallery');
casting_render_flash();
?>
<section class="dash-card">
  <h1>گالری من</h1>
  <p class="lede">عکس و ویدیوهای اضافه را اینجا بارگذاری کنید. تا تأیید مدیران سایت، برای دیگران نمایش داده نمی‌شود.</p>

  <form class="form" method="post" enctype="multipart/form-data" action="my-gallery.php">
    <?php wp_nonce_field('casting_gallery'); ?>
    <input type="hidden" name="gallery_action" value="upload">
    <div class="form-grid">
      <div class="field">
        <label for="gallery_photo">عکس جدید</label>
        <input id="gallery_photo" name="gallery_photo" type="file" accept="image/jpeg,image/png,image/webp">
        <p class="field-hint">JPG / PNG / WebP — حداکثر ۵ مگابایت</p>
      </div>
      <div class="field">
        <label for="gallery_video">ویدیو جدید</label>
        <input id="gallery_video" name="gallery_video" type="file" accept="video/mp4,video/webm,video/quicktime">
        <p class="field-hint">MP4 / WebM / MOV — حداکثر ۴۰ مگابایت</p>
      </div>
    </div>
    <p class="field-hint">در هر ارسال یکی از دو فیلد را پر کنید.</p>
    <button class="btn btn-primary" type="submit">ارسال برای تأیید</button>
  </form>

  <h2 class="panel-section-title">فایل‌های شما</h2>
  <?php if ($items === []) : ?>
    <p class="empty-state">هنوز چیزی ارسال نکرده‌اید.</p>
  <?php else : ?>
    <div class="profile-media-grid profile-media-grid--manage">
      <?php foreach ($items as $item) :
          $url = casting_user_media_url($item);
          $thumb = casting_user_media_thumb_url($item);
          $is_video = ($item['media_type'] ?? '') === 'video';
          $status = (string) ($item['status'] ?? 'pending');
          ?>
        <figure class="profile-media-item is-manage">
          <?php if ($is_video && $url !== '') : ?>
            <video src="<?= casting_e($url) ?>" controls preload="metadata" playsinline></video>
          <?php elseif ($thumb !== '' || $url !== '') : ?>
            <img src="<?= casting_e($thumb !== '' ? $thumb : $url) ?>" alt="" loading="lazy">
          <?php endif; ?>
          <figcaption>
            <span class="chip"><?= casting_e(casting_user_media_status_label($status)) ?></span>
            <?php if ($status === 'rejected' && trim((string) ($item['reject_reason'] ?? '')) !== '') : ?>
              <span class="meta"><?= casting_e((string) $item['reject_reason']) ?></span>
            <?php endif; ?>
            <form method="post" action="my-gallery.php" onsubmit="return confirm('حذف شود؟');">
              <?php wp_nonce_field('casting_gallery'); ?>
              <input type="hidden" name="gallery_action" value="delete">
              <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit">حذف</button>
            </form>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
