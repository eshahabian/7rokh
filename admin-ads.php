<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/ad-posters.php';

$user = casting_require_casting_user();
$admin_id = (int) $user->ID;
if (!casting_user_can_moderate_ad_posters($admin_id)) {
    wp_die('دسترسی به تأیید پوستر تبلیغات برای شما فعال نیست.', 'دسترسی غیرمجاز', ['response' => 403]);
}

$allowed_status = ['pending', 'approved', 'rejected', 'all'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    $return_status = sanitize_key((string) ($_POST['return_status'] ?? 'pending'));
    if (!in_array($return_status, $allowed_status, true)) {
        $return_status = 'pending';
    }
    $redirect = 'admin-ads.php?status=' . rawurlencode($return_status);
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_admin_ads')) {
        casting_set_flash('error', 'نشست منقضی شده.');
        casting_redirect($redirect);
    }
    $poster_id = (int) ($_POST['poster_id'] ?? 0);
    $action = sanitize_key((string) ($_POST['ad_action'] ?? ''));
    if ($action === 'approve') {
        $res = casting_ad_poster_approve($poster_id, $admin_id);
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'تأیید شد و در بنر تبلیغات صفحه اصلی نمایش داده می‌شود.' : $res['error']);
    } elseif ($action === 'reject') {
        $res = casting_ad_poster_reject($poster_id, $admin_id, (string) ($_POST['reject_reason'] ?? ''));
        casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'رد شد. کاربر می‌تواند پوستر را اصلاح و دوباره بفرستد.' : $res['error']);
    }
    casting_redirect($redirect);
}

$status = sanitize_key((string) ($_GET['status'] ?? 'pending'));
if (!in_array($status, $allowed_status, true)) {
    $status = 'pending';
}
$items = casting_admin_ad_posters_list($status, 100);
$pending_count = casting_admin_pending_ad_posters_count();

casting_render_panel_start('تأیید پوستر تبلیغات', 'admin-ads');
casting_render_flash();
?>
<section class="dash-card">
  <h1>تأیید پوستر تبلیغات</h1>
  <p class="lede">پوسترهای پرداخت‌شده را بررسی کنید. پس از تأیید، در بنر تبلیغات صفحه اصلی (خانه اعضا و صفحه عمومی) نمایش داده می‌شوند.</p>

  <div class="admin-tabs admin-media-tabs" role="tablist" aria-label="وضعیت پوسترها">
    <a class="admin-tab<?= $status === 'pending' ? ' is-active' : '' ?>" href="admin-ads.php?status=pending">
      <span class="admin-tab-mark admin-tab-mark--pending" aria-hidden="true"></span>
      <span>در انتظار (<?= (int) $pending_count ?>)</span>
    </a>
    <a class="admin-tab<?= $status === 'approved' ? ' is-active' : '' ?>" href="admin-ads.php?status=approved">
      <span class="admin-tab-mark admin-tab-mark--approved" aria-hidden="true"></span>
      <span>تأییدشده</span>
    </a>
    <a class="admin-tab<?= $status === 'rejected' ? ' is-active' : '' ?>" href="admin-ads.php?status=rejected">
      <span class="admin-tab-mark admin-tab-mark--rejected" aria-hidden="true"></span>
      <span>ردشده</span>
    </a>
    <a class="admin-tab<?= $status === 'all' ? ' is-active' : '' ?>" href="admin-ads.php?status=all">
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
          $url = casting_ad_poster_url($item);
          $item_status = (string) ($item['status'] ?? '');
          $item_title = trim((string) ($item['title'] ?? ''));
          ?>
        <article class="admin-media-card admin-media-card--<?= casting_e($item_status) ?>">
          <div class="admin-media-preview">
            <?php if ($url !== '') : ?>
              <img src="<?= casting_e($url) ?>" alt="" loading="lazy">
            <?php endif; ?>
          </div>
          <div class="admin-media-body">
            <h2><?= casting_e($owner ? (string) $owner->display_name : 'کاربر') ?></h2>
            <p class="meta">
              @<?= casting_e($owner ? (string) $owner->user_login : '') ?>
              · <?= casting_e(casting_ad_type_label((string) ($item['ad_type'] ?? ''))) ?>
              · <?= (int) ($item['width'] ?? 0) ?>×<?= (int) ($item['height'] ?? 0) ?>
              · <?= casting_e(casting_ad_poster_status_label($item_status)) ?>
              · <?= casting_e((string) ($item['created_at'] ?? '')) ?>
            </p>
            <?php if ($item_title !== '') : ?>
              <p><?= casting_e($item_title) ?></p>
            <?php endif; ?>
            <?php if ($item_status === 'rejected' && trim((string) ($item['reject_reason'] ?? '')) !== '') : ?>
              <p class="meta admin-media-reject-reason">دلیل رد: <?= casting_e((string) ($item['reject_reason'])) ?></p>
            <?php endif; ?>
            <?php if ($item_status === 'pending') : ?>
              <div class="cta-row">
                <form method="post" action="admin-ads.php">
                  <?php wp_nonce_field('casting_admin_ads'); ?>
                  <input type="hidden" name="return_status" value="<?= casting_e($status) ?>">
                  <input type="hidden" name="poster_id" value="<?= (int) $item['id'] ?>">
                  <input type="hidden" name="ad_action" value="approve">
                  <button class="btn btn-primary" type="submit">تأیید و انتشار</button>
                </form>
                <form method="post" action="admin-ads.php" class="admin-media-reject">
                  <?php wp_nonce_field('casting_admin_ads'); ?>
                  <input type="hidden" name="return_status" value="<?= casting_e($status) ?>">
                  <input type="hidden" name="poster_id" value="<?= (int) $item['id'] ?>">
                  <input type="hidden" name="ad_action" value="reject">
                  <input type="text" name="reject_reason" placeholder="دلیل رد (اختیاری)">
                  <button class="btn btn-ghost" type="submit">رد</button>
                </form>
              </div>
            <?php elseif ($item_status === 'rejected') : ?>
              <form method="post" action="admin-ads.php">
                <?php wp_nonce_field('casting_admin_ads'); ?>
                <input type="hidden" name="return_status" value="<?= casting_e($status) ?>">
                <input type="hidden" name="poster_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="ad_action" value="approve">
                <button class="btn btn-ghost btn-sm" type="submit">تأیید همین فایل</button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
