<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/ad-posters.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$is_admin = casting_user_can_moderate_ad_posters($user_id);

if (!casting_user_can_open_ad_posters($user_id)) {
    casting_set_flash('error', 'برای ارسال پوستر ابتدا هزینهٔ تبلیغات را از خرید اشتراک پرداخت کنید. پس از پرداخت، این بخش باز می‌شود.');
    casting_redirect('cart.php#shop-ads');
}

$allowed_inbox = ['pending', 'approved', 'rejected', 'all'];
$tab = sanitize_key((string) ($_GET['tab'] ?? ''));
if ($tab !== 'inbox' && $tab !== 'mine') {
    $tab = $is_admin ? 'inbox' : 'mine';
}
if (!$is_admin) {
    $tab = 'mine';
}
$inbox_status = sanitize_key((string) ($_GET['status'] ?? 'pending'));
if (!in_array($inbox_status, $allowed_inbox, true)) {
    $inbox_status = 'pending';
}

$mine_url = 'my-ads.php?tab=mine';
$inbox_url = static function (string $status = 'pending'): string {
    return 'my-ads.php?tab=inbox&status=' . rawurlencode($status);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_key((string) ($_POST['ad_action'] ?? 'upload'));
    $is_mod_action = in_array($action, ['approve', 'reject'], true);
    $return = $is_mod_action
        ? $inbox_url(sanitize_key((string) ($_POST['return_status'] ?? 'pending')))
        : $mine_url;

    if (!$is_mod_action && casting_upload_post_too_large()) {
        casting_set_flash('error', casting_upload_post_too_large_message());
        casting_redirect($return);
    }
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_ad_poster')) {
        casting_set_flash('error', 'نشست منقضی شده. دوباره تلاش کنید.');
        casting_redirect($return);
    }

    if ($action === 'approve' || $action === 'reject') {
        if (!$is_admin) {
            casting_set_flash('error', 'اجازه تأیید ندارید.');
            casting_redirect($mine_url);
        }
        $poster_id = (int) ($_POST['poster_id'] ?? 0);
        if ($action === 'approve') {
            $res = casting_ad_poster_approve($poster_id, $user_id);
            casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'تأیید شد و در بنر تبلیغات صفحه اصلی نمایش داده می‌شود.' : $res['error']);
        } else {
            $res = casting_ad_poster_reject($poster_id, $user_id, (string) ($_POST['reject_reason'] ?? ''));
            casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'رد شد. کاربر می‌تواند پوستر را اصلاح و دوباره بفرستد.' : $res['error']);
        }
        casting_redirect($return);
    }

    $title = (string) ($_POST['title'] ?? '');
    if ($action === 'resubmit') {
        $res = casting_ad_poster_resubmit($user_id, (int) ($_POST['poster_id'] ?? 0), 'poster_file', $title);
        casting_set_flash(
            $res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'پوستر دوباره برای تأیید ارسال شد.' : $res['error']
        );
    } else {
        $credit_id = (int) ($_POST['credit_id'] ?? 0);
        $owner_type = sanitize_key((string) ($_POST['ad_type'] ?? ''));
        $res = casting_ad_poster_submit($user_id, $credit_id, 'poster_file', $title, $owner_type);
        casting_set_flash(
            $res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'پوستر ارسال شد و پس از تأیید در قسمت تبلیغات نمایش داده می‌شود.' : $res['error']
        );
    }
    casting_redirect($mine_url);
}

casting_ad_credits_sync_from_orders($user_id);
$open_credits = casting_user_ad_open_credits($user_id);
$spec = casting_ad_poster_spec();
$is_owner = function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id);
$items = casting_user_ad_posters_list($user_id);
$edit_id = (int) ($_GET['edit'] ?? 0);
if ($edit_id > 0) {
    $tab = 'mine';
}
$paid_credits = [];
foreach ($open_credits as $c) {
    if (empty($c['virtual'])) {
        $paid_credits[] = $c;
    }
}
$can_upload = $paid_credits !== [] || ($is_owner && $open_credits !== []);
$pending_count = $is_admin ? casting_admin_pending_ad_posters_count() : 0;
$inbox_items = $is_admin && $tab === 'inbox' ? casting_admin_ad_posters_list($inbox_status, 100) : [];
$page_title = $is_admin ? 'پوستر' : 'ارسال پوستر';

casting_render_panel_start($page_title, 'my-ads');
casting_render_flash();
?>
<section class="dash-card">
  <?php casting_render_panel_heading($page_title); ?>

  <?php if ($is_admin) : ?>
    <div class="admin-tabs admin-media-tabs" role="tablist" aria-label="بخش پوستر">
      <a class="admin-tab<?= $tab === 'inbox' ? ' is-active' : '' ?>" href="<?= casting_e($inbox_url('pending')) ?>">
        <span>پوسترهای دریافتی<?= $pending_count > 0 ? ' (' . (int) $pending_count . ')' : '' ?></span>
      </a>
      <a class="admin-tab<?= $tab === 'mine' ? ' is-active' : '' ?>" href="<?= casting_e($mine_url) ?>">
        <span>ارسال پوستر</span>
      </a>
    </div>
  <?php endif; ?>

  <?php if ($is_admin && $tab === 'inbox') : ?>
    <p class="lede">پوسترهای ارسال‌شده را بررسی کنید. پس از تأیید، در بنر تبلیغات صفحه اصلی نمایش داده می‌شوند.</p>
    <div class="admin-tabs admin-media-tabs" role="tablist" aria-label="وضعیت پوسترها">
      <a class="admin-tab<?= $inbox_status === 'pending' ? ' is-active' : '' ?>" href="<?= casting_e($inbox_url('pending')) ?>">
        <span class="admin-tab-mark admin-tab-mark--pending" aria-hidden="true"></span>
        <span>در انتظار (<?= (int) $pending_count ?>)</span>
      </a>
      <a class="admin-tab<?= $inbox_status === 'approved' ? ' is-active' : '' ?>" href="<?= casting_e($inbox_url('approved')) ?>">
        <span class="admin-tab-mark admin-tab-mark--approved" aria-hidden="true"></span>
        <span>تأییدشده</span>
      </a>
      <a class="admin-tab<?= $inbox_status === 'rejected' ? ' is-active' : '' ?>" href="<?= casting_e($inbox_url('rejected')) ?>">
        <span class="admin-tab-mark admin-tab-mark--rejected" aria-hidden="true"></span>
        <span>ردشده</span>
      </a>
      <a class="admin-tab<?= $inbox_status === 'all' ? ' is-active' : '' ?>" href="<?= casting_e($inbox_url('all')) ?>">
        <span class="admin-tab-mark admin-tab-mark--all" aria-hidden="true"></span>
        <span>همه</span>
      </a>
    </div>

    <?php if ($inbox_items === []) : ?>
      <p class="empty-state">موردی نیست.</p>
    <?php else : ?>
      <div class="admin-media-list">
        <?php foreach ($inbox_items as $item) :
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
                  <form method="post" action="my-ads.php">
                    <?php wp_nonce_field('casting_ad_poster'); ?>
                    <input type="hidden" name="return_status" value="<?= casting_e($inbox_status) ?>">
                    <input type="hidden" name="poster_id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="ad_action" value="approve">
                    <button class="btn btn-primary" type="submit">تأیید و انتشار</button>
                  </form>
                  <form method="post" action="my-ads.php" class="admin-media-reject">
                    <?php wp_nonce_field('casting_ad_poster'); ?>
                    <input type="hidden" name="return_status" value="<?= casting_e($inbox_status) ?>">
                    <input type="hidden" name="poster_id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="ad_action" value="reject">
                    <input type="text" name="reject_reason" placeholder="دلیل رد (اختیاری)">
                    <button class="btn btn-ghost" type="submit">رد</button>
                  </form>
                </div>
              <?php elseif ($item_status === 'rejected') : ?>
                <form method="post" action="my-ads.php">
                  <?php wp_nonce_field('casting_ad_poster'); ?>
                  <input type="hidden" name="return_status" value="<?= casting_e($inbox_status) ?>">
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

  <?php else : ?>
    <p class="lede"><?= $is_admin ? 'پوستر خودتان را از اینجا بفرستید.' : 'پس از پرداخت، پوستر را بفرستید. تا تأیید مدیر در بنر تبلیغات صفحه اصلی دیده نمی‌شود. فقط پوسترهای خودتان را می‌بینید.' ?></p>

    <div class="ad-spec-box" role="note">
      <h2>فرمت و اندازه لازم</h2>
      <ul class="info-list">
        <li><strong>فرمت:</strong> JPG، PNG یا WebP</li>
        <li><strong>سایز پیشنهادی:</strong> <?= (int) $spec['recommended_width'] ?> × <?= (int) $spec['recommended_height'] ?> پیکسل (نسبت ۱۶ به ۶.۷۵ مطابق بنر صفحه اصلی)</li>
        <li><strong>حداقل:</strong> <?= (int) $spec['min_width'] ?> × <?= (int) $spec['min_height'] ?> پیکسل — تصویر باید افقی باشد</li>
        <li><strong>حجم:</strong> حداکثر <?= casting_e(casting_upload_max_label_fa('image')) ?></li>
        <li>اگر نسبت ۱۶:۹ بفرستید هم قبول است؛ بالا و پایین کمی برش می‌خورد چون بنر پهن‌تر است.</li>
      </ul>
    </div>

    <?php if ($can_upload) : ?>
      <form class="form" method="post" enctype="multipart/form-data" action="my-ads.php">
        <?php wp_nonce_field('casting_ad_poster'); ?>
        <input type="hidden" name="ad_action" value="upload">
        <?php if ($paid_credits !== []) : ?>
          <div class="field">
            <label for="credit_id">سهمیه پرداخت‌شده</label>
            <select id="credit_id" name="credit_id" required>
              <?php foreach ($paid_credits as $credit) : ?>
                <option value="<?= (int) ($credit['id'] ?? 0) ?>">
                  <?= casting_e(casting_ad_type_label((string) ($credit['ad_type'] ?? ''))) ?>
                  · سفارش <?= casting_e((string) ($credit['order_code'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php elseif ($is_owner) : ?>
          <input type="hidden" name="credit_id" value="0">
          <div class="field">
            <label for="ad_type">نوع بنر</label>
            <select id="ad_type" name="ad_type" required>
              <?php foreach (casting_ad_type_labels() as $key => $label) : ?>
                <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="field">
          <label for="title">عنوان (اختیاری)</label>
          <input id="title" name="title" type="text" maxlength="120" placeholder="مثلاً نام نمایش یا فیلم">
        </div>
        <div class="field">
          <label for="poster_file">فایل پوستر</label>
          <input id="poster_file" name="poster_file" type="file" accept="image/jpeg,image/png,image/webp" required data-upload-kind="image" data-max-bytes="<?= (int) $spec['max_bytes'] ?>">
          <p class="field-hint">JPG / PNG / WebP — حداقل <?= (int) $spec['min_width'] ?>×<?= (int) $spec['min_height'] ?> — حداکثر <?= casting_e(casting_upload_max_label_fa('image')) ?></p>
        </div>
        <button class="btn btn-primary" type="submit">ارسال برای تأیید</button>
      </form>
    <?php else : ?>
      <p class="empty-state">سهمیهٔ باز ندارید. اگر پوستر در انتظار تأیید است صبر کنید؛ اگر رد شده از لیست پایین دوباره بفرستید. برای سهمیه جدید از <a href="<?= casting_e(casting_url('cart.php#shop-ads')) ?>">خرید اشتراک</a> اقدام کنید.</p>
    <?php endif; ?>

    <h2 class="panel-section-title">پوسترهای شما</h2>
    <?php if ($items === []) : ?>
      <p class="empty-state">هنوز پوستری ارسال نکرده‌اید.</p>
    <?php else : ?>
      <div class="ad-poster-grid">
        <?php foreach ($items as $item) :
            $url = casting_ad_poster_url($item);
            $status = (string) ($item['status'] ?? 'pending');
            $is_rejected = $status === 'rejected';
            $is_editing = $edit_id === (int) $item['id'];
            $item_title = trim((string) ($item['title'] ?? ''));
            ?>
          <article class="ad-poster-card ad-poster-card--<?= casting_e($status) ?>"<?= $is_editing ? ' id="ad-edit-focus"' : '' ?>>
            <?php if ($url !== '') : ?>
              <img src="<?= casting_e($url) ?>" alt="" loading="lazy">
            <?php endif; ?>
            <div class="ad-poster-card-body">
              <span class="chip<?= $is_rejected ? ' chip-danger' : '' ?>"><?= casting_e(casting_ad_poster_status_label($status)) ?></span>
              <p class="meta">
                <?= casting_e(casting_ad_type_label((string) ($item['ad_type'] ?? ''))) ?>
                · <?= (int) ($item['width'] ?? 0) ?>×<?= (int) ($item['height'] ?? 0) ?>
                · <?= casting_e((string) ($item['created_at'] ?? '')) ?>
              </p>
              <?php if ($item_title !== '') : ?>
                <p><?= casting_e($item_title) ?></p>
              <?php endif; ?>
              <?php if ($is_rejected && trim((string) ($item['reject_reason'] ?? '')) !== '') : ?>
                <p class="meta gallery-reject-reason">دلیل رد: <?= casting_e((string) ($item['reject_reason'])) ?></p>
              <?php endif; ?>
              <?php if ($is_rejected) : ?>
                <?php if ($is_editing) : ?>
                  <form class="form" method="post" enctype="multipart/form-data" action="my-ads.php">
                    <?php wp_nonce_field('casting_ad_poster'); ?>
                    <input type="hidden" name="ad_action" value="resubmit">
                    <input type="hidden" name="poster_id" value="<?= (int) $item['id'] ?>">
                    <div class="field">
                      <label for="edit_title_<?= (int) $item['id'] ?>">عنوان</label>
                      <input id="edit_title_<?= (int) $item['id'] ?>" name="title" type="text" maxlength="120" value="<?= casting_e($item_title) ?>">
                    </div>
                    <div class="field">
                      <label for="edit_file_<?= (int) $item['id'] ?>">پوستر جدید</label>
                      <input id="edit_file_<?= (int) $item['id'] ?>" name="poster_file" type="file" accept="image/jpeg,image/png,image/webp" required data-upload-kind="image" data-max-bytes="<?= (int) $spec['max_bytes'] ?>">
                    </div>
                    <div class="cta-row">
                      <button class="btn btn-primary btn-sm" type="submit">ارسال مجدد</button>
                      <a class="btn btn-ghost btn-sm" href="<?= casting_e($mine_url) ?>">انصراف</a>
                    </div>
                  </form>
                <?php else : ?>
                  <a class="btn btn-primary btn-sm" href="my-ads.php?tab=mine&edit=<?= (int) $item['id'] ?>">اصلاح و ارسال مجدد</a>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php if ($edit_id > 0) : ?>
<script>
  (function () {
    var el = document.getElementById('ad-edit-focus');
    if (el && typeof el.scrollIntoView === 'function') {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  })();
</script>
<?php endif; ?>
<?php casting_render_panel_end(); ?>
