<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/user-media.php';

$user = casting_require_casting_user();
$admin_id = (int) $user->ID;
casting_require_admin_permission('approve_media');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_admin_media')) {
        casting_set_flash('error', 'نشست منقضی شده.');
        casting_redirect('admin-media.php');
    }
    $media_id = (int) ($_POST['media_id'] ?? 0);
    $action = sanitize_key((string) ($_POST['media_action'] ?? ''));
    if ($action === 'approve') {
        $res = casting_approve_user_media($media_id, $admin_id);
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'تأیید و منتشر شد.' : $res['error']);
    } elseif ($action === 'reject') {
        $res = casting_reject_user_media($media_id, $admin_id, (string) ($_POST['reject_reason'] ?? ''));
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'رد شد.' : $res['error']);
    }
    casting_redirect('admin-media.php');
}

$status = sanitize_key((string) ($_GET['status'] ?? 'pending'));
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}
$items = casting_admin_list_media($status, 100);
$pending_count = casting_admin_pending_media_count();

casting_render_panel_start('تأیید گالری', 'admin-media');
casting_render_flash();
?>
<section class="dash-card">
  <h1>تأیید گالری</h1>
  <p class="lede">عکس و ویدیوهای ارسال‌شده توسط بازیگران را بررسی کنید. فقط موارد تأییدشده در پروفایل عمومی دیده می‌شوند.</p>

  <div class="admin-tabs" role="tablist">
    <a class="admin-tab<?= $status === 'pending' ? ' is-active' : '' ?>" href="admin-media.php?status=pending">در انتظار (<?= (int) $pending_count ?>)</a>
    <a class="admin-tab<?= $status === 'approved' ? ' is-active' : '' ?>" href="admin-media.php?status=approved">تأییدشده</a>
    <a class="admin-tab<?= $status === 'rejected' ? ' is-active' : '' ?>" href="admin-media.php?status=rejected">ردشده</a>
    <a class="admin-tab<?= $status === 'all' ? ' is-active' : '' ?>" href="admin-media.php?status=all">همه</a>
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
          ?>
        <article class="admin-media-card">
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
              <?= casting_e(casting_user_media_status_label((string) ($item['status'] ?? ''))) ?> ·
              <?= casting_e((string) ($item['created_at'] ?? '')) ?>
              <?php if (($item['status'] ?? '') === 'pending' && !empty($item['is_resubmit'])) : ?>
                · <span class="chip">ویرایش مجدد</span>
              <?php endif; ?>
            </p>
            <?php if (trim((string) ($item['caption'] ?? '')) !== '') : ?>
              <p><?= nl2br(casting_e((string) $item['caption'])) ?></p>
            <?php endif; ?>
            <?php if (($item['status'] ?? '') === 'pending') : ?>
              <div class="cta-row">
                <form method="post" action="admin-media.php">
                  <?php wp_nonce_field('casting_admin_media'); ?>
                  <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                  <input type="hidden" name="media_action" value="approve">
                  <button class="btn btn-primary" type="submit">تأیید و انتشار</button>
                </form>
                <form method="post" action="admin-media.php" class="admin-media-reject">
                  <?php wp_nonce_field('casting_admin_media'); ?>
                  <input type="hidden" name="media_id" value="<?= (int) $item['id'] ?>">
                  <input type="hidden" name="media_action" value="reject">
                  <input type="text" name="reject_reason" placeholder="دلیل رد (اختیاری)">
                  <button class="btn btn-ghost" type="submit">رد</button>
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
