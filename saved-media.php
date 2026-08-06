<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/director-workspace.php';
require_once __DIR__ . '/includes/user-media.php';
require_once __DIR__ . '/includes/media-engagement.php';
require_once __DIR__ . '/includes/media-protect.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_is_director_role($user_id)) {
    casting_set_flash('error', 'این بخش فقط برای کارگردان‌هاست.');
    casting_redirect('home.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_key((string) ($_POST['save_action'] ?? ''));
    $media_id = (int) ($_POST['media_id'] ?? 0);
    if ($action === 'unsave' && $media_id > 0
        && isset($_POST['_wpnonce']) && wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_saved_media')
    ) {
        casting_media_toggle_save($user_id, $media_id);
        casting_set_flash('success', 'از ذخیره‌شده‌ها حذف شد.');
        casting_redirect('saved-media.php');
    }
}

$items = casting_media_list_saved($user_id, 80);
$watermark = casting_media_protect_viewer_label($user);

casting_render_panel_start('ذخیره‌شده‌ها', 'saved');
casting_render_flash();
?>
<section class="dash-card">
  <h1>ذخیره‌شده‌ها</h1>
  <p class="meta">عکس و ویدیوهایی که مثل اینستاگرام در پروفایل خودتان نگه داشته‌اید. دانلود مستقیم غیرفعال است.</p>
  <?php if ($items === []) : ?>
    <p class="empty-state">هنوز چیزی ذخیره نکرده‌اید. روی پست‌ها دکمه «ذخیره» را بزنید.</p>
  <?php else : ?>
    <div class="profile-media-grid saved-media-grid">
      <?php foreach ($items as $item) :
          $url = casting_user_media_url($item);
          $thumb = casting_user_media_thumb_url($item);
          if ($url === '') {
              continue;
          }
          $is_video = ($item['media_type'] ?? '') === 'video';
          $media_id = (int) ($item['id'] ?? 0);
          $owner_id = (int) ($item['user_id'] ?? 0);
          $owner = $owner_id > 0 ? get_user_by('id', $owner_id) : null;
          $caption = trim((string) ($item['caption'] ?? ''));
          ?>
        <figure class="profile-media-item<?= $is_video ? ' is-video' : '' ?>">
          <?php if ($is_video) :
              casting_render_protected_video($url, $watermark, [
                  'class'  => 'media-protect--gallery',
                  'poster' => ($thumb !== '' && $thumb !== $url) ? $thumb : '',
              ]);
          else :
              casting_render_protected_image($thumb !== '' ? $thumb : $url, $watermark, [
                  'class' => 'media-protect--gallery',
              ]);
          endif; ?>
          <figcaption class="profile-media-caption">
            <?php if ($owner) : ?>
              <p class="meta">
                <a href="<?= casting_e(casting_url('member.php?id=' . $owner_id)) ?>"><?= casting_e((string) $owner->display_name) ?></a>
              </p>
            <?php endif; ?>
            <?php if ($caption !== '') : ?>
              <p><?= nl2br(casting_e($caption)) ?></p>
            <?php endif; ?>
            <form method="post" action="<?= casting_e(casting_url('saved-media.php')) ?>" onsubmit="return confirm('از ذخیره‌شده‌ها حذف شود؟');">
              <?php wp_nonce_field('casting_saved_media'); ?>
              <input type="hidden" name="save_action" value="unsave">
              <input type="hidden" name="media_id" value="<?= $media_id ?>">
              <button type="submit" class="btn btn-ghost btn-sm">حذف ذخیره</button>
            </form>
          </figcaption>
          <?php casting_render_media_engagement($media_id, $user_id, true); ?>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
