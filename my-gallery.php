<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/user-media.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_can_manage_gallery($user_id)) {
    casting_set_flash('error', 'برای افزودن پست باید عضو پورتال باشید.');
    casting_redirect('home.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_gallery')) {
        casting_set_flash('error', 'نشست منقضی شده. دوباره تلاش کنید.');
        casting_redirect('my-gallery.php');
    }
    $action = sanitize_key((string) ($_POST['gallery_action'] ?? 'upload'));
    $caption = (string) ($_POST['caption'] ?? '');

    if ($action === 'delete') {
        $res = casting_user_media_delete_own($user_id, (int) ($_POST['media_id'] ?? 0));
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'پست حذف شد.' : $res['error']);
    } elseif ($action === 'edit') {
        $media_id = (int) ($_POST['media_id'] ?? 0);
        $file_field = '';
        if (!empty($_FILES['edit_photo']['name'])) {
            $file_field = 'edit_photo';
        } elseif (!empty($_FILES['edit_video']['name'])) {
            $file_field = 'edit_video';
        }
        $res = casting_user_media_edit_own($user_id, $media_id, $caption, $file_field);
        if ($res['ok']) {
            casting_clear_user_gallery_reject_notice($user_id);
        }
        casting_set_flash(
            $res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'ویرایش ذخیره شد و دوباره برای تأیید مدیر ارسال شد.' : $res['error']
        );
    } else {
        if (!empty($_FILES['gallery_photo']['name'])) {
            $res = casting_user_media_submit_upload($user_id, 'gallery_photo', 'photo', $caption);
        } elseif (!empty($_FILES['gallery_video']['name'])) {
            $res = casting_user_media_submit_upload($user_id, 'gallery_video', 'video', $caption);
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
$edit_id = (int) ($_GET['edit'] ?? 0);
$reject_notice = casting_user_gallery_reject_notice($user_id);
$rejected_count = casting_user_rejected_media_count($user_id);

if ($edit_id > 0 || ($reject_notice && (int) $reject_notice['media_id'] === $edit_id)) {
    casting_clear_user_gallery_reject_notice($user_id);
    $reject_notice = null;
}

casting_render_panel_start('گالری من', 'gallery');
casting_render_flash();
?>
<section class="dash-card">
  <?php casting_render_panel_heading('گالری من'); ?>
  <p class="lede">عکس و ویدیو را با کپشن بفرستید. تا تأیید مدیر برای دیگران دیده نمی‌شود. ویرایش هم دوباره نیاز به تأیید دارد.</p>

  <?php if ($rejected_count > 0) : ?>
    <div class="flash flash-error gallery-reject-banner" role="status">
      <?= (int) $rejected_count ?> پست رد شده دارید.
      آن را در لیست پایین ببینید، اصلاح کنید و دوباره برای تأیید بفرستید.
      <?php if ($reject_notice && (int) $reject_notice['media_id'] > 0 && $edit_id <= 0) : ?>
        <a class="btn btn-ghost btn-sm" href="my-gallery.php?edit=<?= (int) $reject_notice['media_id'] ?>">مشاهده و ویرایش</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

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
    <div class="field">
      <label for="caption">کپشن</label>
      <textarea id="caption" name="caption" rows="3" maxlength="500" placeholder="متن کوتاه برای این پست…"></textarea>
    </div>
    <p class="field-hint">در هر ارسال یکی از دو فایل را پر کنید.</p>
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
          $caption = trim((string) ($item['caption'] ?? ''));
          $is_editing = $edit_id === (int) $item['id'];
          $is_rejected = $status === 'rejected';
          ?>
        <figure class="profile-media-item is-manage<?= $is_rejected ? ' is-rejected' : '' ?><?= $is_editing ? ' is-editing' : '' ?>"<?= $is_editing ? ' id="gallery-edit-focus"' : '' ?>>
          <?php if ($is_video && $url !== '') : ?>
            <video src="<?= casting_e($url) ?>" controls preload="metadata" playsinline></video>
          <?php elseif ($thumb !== '' || $url !== '') : ?>
            <img src="<?= casting_e($thumb !== '' ? $thumb : $url) ?>" alt="" loading="lazy">
          <?php endif; ?>
          <figcaption>
            <span class="chip<?= $is_rejected ? ' chip-danger' : '' ?>"><?= casting_e(casting_user_media_status_label($status)) ?></span>
            <?php if ($caption !== '') : ?>
              <p class="profile-media-caption-text"><?= nl2br(casting_e($caption)) ?></p>
            <?php endif; ?>
            <?php if ($is_rejected && trim((string) ($item['reject_reason'] ?? '')) !== '') : ?>
              <span class="meta gallery-reject-reason">دلیل رد: <?= casting_e((string) $item['reject_reason']) ?></span>
            <?php elseif ($is_rejected) : ?>
              <span class="meta gallery-reject-reason">این پست رد شده؛ می‌توانید ویرایش و دوباره ارسال کنید.</span>
            <?php endif; ?>

            <?php if ($is_editing) : ?>
              <form class="form gallery-edit-form" method="post" enctype="multipart/form-data" action="my-gallery.php">
                <?php wp_nonce_field('casting_gallery'); ?>
                <input type="hidden" name="gallery_action" value="edit">
                <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                <div class="field">
                  <label for="edit_caption_<?= (int) $item['id'] ?>">کپشن</label>
                  <textarea id="edit_caption_<?= (int) $item['id'] ?>" name="caption" rows="3" maxlength="500"><?= casting_e($caption) ?></textarea>
                </div>
                <div class="field">
                  <label for="edit_file_<?= (int) $item['id'] ?>">جایگزینی فایل (اختیاری)</label>
                  <?php if ($is_video) : ?>
                    <input id="edit_file_<?= (int) $item['id'] ?>" name="edit_video" type="file" accept="video/mp4,video/webm,video/quicktime">
                  <?php else : ?>
                    <input id="edit_file_<?= (int) $item['id'] ?>" name="edit_photo" type="file" accept="image/jpeg,image/png,image/webp">
                  <?php endif; ?>
                </div>
                <p class="field-hint">بعد از ذخیره، پست دوباره در صف تأیید مدیر قرار می‌گیرد.</p>
                <div class="cta-row">
                  <button class="btn btn-primary btn-sm" type="submit">ذخیره و ارسال تأیید</button>
                  <a class="btn btn-ghost btn-sm" href="my-gallery.php">انصراف</a>
                </div>
              </form>
            <?php else : ?>
              <div class="cta-row">
                <a class="btn <?= $is_rejected ? 'btn-primary' : 'btn-ghost' ?> btn-sm" href="my-gallery.php?edit=<?= (int) $item['id'] ?>">
                  <?= $is_rejected ? 'اصلاح و ارسال مجدد' : 'ویرایش' ?>
                </a>
                <form method="post" action="my-gallery.php" onsubmit="return confirm('این پست حذف شود؟');">
                  <?php wp_nonce_field('casting_gallery'); ?>
                  <input type="hidden" name="gallery_action" value="delete">
                  <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">حذف</button>
                </form>
              </div>
            <?php endif; ?>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php if ($edit_id > 0) : ?>
<script>
  (function () {
    var el = document.getElementById('gallery-edit-focus');
    if (el && typeof el.scrollIntoView === 'function') {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  })();
</script>
<?php endif; ?>
<?php casting_render_panel_end(); ?>
