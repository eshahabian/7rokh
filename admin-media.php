<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/user-media.php';

$user = casting_require_casting_user();
$admin_id = (int) $user->ID;
casting_require_admin_permission('approve_media');

$allowed_status = ['pending', 'approved', 'rejected', 'deleted', 'all'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    $return_status = sanitize_key((string) ($_POST['return_status'] ?? 'pending'));
    if (!in_array($return_status, $allowed_status, true)) {
        $return_status = 'pending';
    }
    $redirect = 'admin-media.php?status=' . rawurlencode($return_status);
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_admin_media')) {
        casting_set_flash('error', 'نشست منقضی شده.');
        casting_redirect($redirect);
    }
    $media_id = (int) ($_POST['media_id'] ?? 0);
    $action = sanitize_key((string) ($_POST['media_action'] ?? ''));
    if ($action === 'approve') {
        $res = casting_approve_user_media($media_id, $admin_id);
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'تأیید و منتشر شد.' : $res['error']);
    } elseif ($action === 'reject') {
        $res = casting_reject_user_media($media_id, $admin_id, (string) ($_POST['reject_reason'] ?? ''));
        casting_set_flash(
            $res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'رد شد و پیام برای کاربر ارسال شد.' : $res['error']
        );
    } elseif ($action === 'restore') {
        $res = casting_restore_deleted_user_media($media_id, $admin_id);
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'دوباره برای کاربر منتشر شد.' : $res['error']);
    } elseif ($action === 'archive') {
        $res = casting_archive_deleted_user_media($media_id);
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'از آرشیو ادمین برای همیشه حذف شد.' : $res['error']);
    }
    casting_redirect($redirect);
}

$status = sanitize_key((string) ($_GET['status'] ?? 'pending'));
if (!in_array($status, $allowed_status, true)) {
    $status = 'pending';
}
$items = casting_admin_list_media($status, 100);
$pending_count = casting_admin_pending_media_count();
$deleted_count = casting_admin_deleted_media_count();

casting_render_panel_start('تأیید گالری', 'admin-media');
casting_render_flash();
?>
<section class="dash-card">
  <h1>تأیید گالری</h1>
  <p class="lede">عکس و ویدیوهای ارسال‌شده را بررسی کنید. پست‌هایی که کاربر حذف کرده تا یک ماه در تب «حذف‌شده» می‌مانند؛ می‌توانید دوباره منتشر کنید یا برای همیشه آرشیو کنید. با رد کردن پست، به کاربر پیام می‌رود تا بتواند ویرایش کند.</p>

  <div class="admin-tabs admin-media-tabs" role="tablist" aria-label="وضعیت گالری">
    <a class="admin-tab<?= $status === 'pending' ? ' is-active' : '' ?>" href="admin-media.php?status=pending" title="در انتظار">
      <span class="admin-tab-mark admin-tab-mark--pending" aria-hidden="true"></span>
      <span>در انتظار (<?= (int) $pending_count ?>)</span>
    </a>
    <a class="admin-tab<?= $status === 'approved' ? ' is-active' : '' ?>" href="admin-media.php?status=approved" title="تأییدشده">
      <span class="admin-tab-mark admin-tab-mark--approved" aria-hidden="true"></span>
      <span>تأییدشده</span>
    </a>
    <a class="admin-tab<?= $status === 'rejected' ? ' is-active' : '' ?>" href="admin-media.php?status=rejected" title="ردشده">
      <span class="admin-tab-mark admin-tab-mark--rejected" aria-hidden="true"></span>
      <span>ردشده</span>
    </a>
    <a class="admin-tab<?= $status === 'deleted' ? ' is-active' : '' ?>" href="admin-media.php?status=deleted" title="حذف‌شده توسط کاربر — آرشیو یک‌ماهه">
      <span class="admin-tab-mark admin-tab-mark--deleted" aria-hidden="true"></span>
      <span>حذف‌شده (<?= (int) $deleted_count ?>)</span>
    </a>
    <a class="admin-tab<?= $status === 'all' ? ' is-active' : '' ?>" href="admin-media.php?status=all" title="همه">
      <span class="admin-tab-mark admin-tab-mark--all" aria-hidden="true"></span>
      <span>همه</span>
    </a>
  </div>

  <?php if ($items === []) : ?>
    <p class="empty-state">موردی نیست.</p>
  <?php else : ?>
    <div class="admin-media-list">
      <?php foreach ($items as $item) :
          $owner = get_user_by('id', (int) ($item['user_id'] ?? 0));
          $url = casting_user_media_url($item);
          $thumb = casting_user_media_thumb_url($item);
          $is_video = ($item['media_type'] ?? '') === 'video';
          $item_status = (string) ($item['status'] ?? '');
          ?>
        <article class="admin-media-card admin-media-card--<?= casting_e($item_status) ?>">
          <div class="admin-media-preview">
            <?php if ($is_video && $url !== '') : ?>
              <video src="<?= casting_e($url) ?>" controls preload="metadata" playsinline></video>
            <?php elseif ($thumb !== '' || $url !== '') : ?>
              <img src="<?= casting_e($thumb !== '' ? $thumb : $url) ?>" alt="" loading="lazy">
            <?php endif; ?>
          </div>
          <div class="admin-media-body">
            <h2><?= casting_e($owner ? (string) $owner->display_name : 'کاربر') ?></h2>
            <p class="meta">
              <?= ($is_video ? 'ویدیو' : 'عکس') ?> ·
              <?= casting_e(casting_user_media_status_label($item_status)) ?> ·
              <?= casting_e((string) ($item['created_at'] ?? '')) ?>
              <?php if ($item_status === 'deleted' && trim((string) ($item['deleted_at'] ?? '')) !== '') : ?>
                · حذف: <?= casting_e((string) $item['deleted_at']) ?>
              <?php endif; ?>
              <?php if ($item_status === 'pending' && !empty($item['is_resubmit'])) : ?>
                · <span class="chip">ویرایش مجدد</span>
              <?php endif; ?>
            </p>
            <?php if (trim((string) ($item['caption'] ?? '')) !== '') : ?>
              <p><?= nl2br(casting_e((string) $item['caption'])) ?></p>
            <?php endif; ?>
            <?php if ($item_status === 'rejected' && trim((string) ($item['reject_reason'] ?? '')) !== '') : ?>
              <p class="meta admin-media-reject-reason">دلیل رد: <?= casting_e((string) $item['reject_reason']) ?></p>
            <?php endif; ?>
            <?php if ($item_status === 'pending') : ?>
              <div class="cta-row">
                <form method="post" action="admin-media.php">
                  <?php wp_nonce_field('casting_admin_media'); ?>
                  <input type="hidden" name="return_status" value="<?= casting_e($status) ?>">
                  <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                  <input type="hidden" name="media_action" value="approve">
                  <button class="btn btn-primary" type="submit">تأیید و انتشار</button>
                </form>
                <form method="post" action="admin-media.php" class="admin-media-reject">
                  <?php wp_nonce_field('casting_admin_media'); ?>
                  <input type="hidden" name="return_status" value="<?= casting_e($status) ?>">
                  <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                  <input type="hidden" name="media_action" value="reject">
                  <input type="text" name="reject_reason" placeholder="دلیل رد (اختیاری)">
                  <button class="btn btn-ghost" type="submit">رد</button>
                </form>
              </div>
            <?php elseif ($item_status === 'deleted') : ?>
              <div class="cta-row admin-media-deleted-actions">
                <form method="post" action="admin-media.php">
                  <?php wp_nonce_field('casting_admin_media'); ?>
                  <input type="hidden" name="return_status" value="deleted">
                  <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                  <input type="hidden" name="media_action" value="restore">
                  <button class="btn btn-primary btn-sm" type="submit" title="دوباره در پروفایل کاربر منتشر شود">انتشار مجدد</button>
                </form>
                <form method="post" action="admin-media.php" onsubmit="return confirm('این پست برای همیشه از آرشیو ادمین حذف شود؟');">
                  <?php wp_nonce_field('casting_admin_media'); ?>
                  <input type="hidden" name="return_status" value="deleted">
                  <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                  <input type="hidden" name="media_action" value="archive">
                  <button class="btn btn-ghost btn-sm" type="submit" title="حذف دائمی از آرشیو یک‌ماهه">آرشیو دائم</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
