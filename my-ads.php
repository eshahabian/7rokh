<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/ad-posters.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$is_admin = casting_user_can_moderate_ad_posters($user_id);

$allowed_inbox = ['pending', 'approved', 'archived', 'rejected', 'all'];
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
    $is_mod_action = in_array($action, ['approve', 'reject', 'republish'], true);
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

    if ($action === 'approve' || $action === 'reject' || $action === 'republish') {
        if (!$is_admin) {
            casting_set_flash('error', 'اجازه تأیید ندارید.');
            casting_redirect($mine_url);
        }
        $poster_id = (int) ($_POST['poster_id'] ?? 0);
        if ($action === 'reject') {
            $res = casting_ad_poster_reject($poster_id, $user_id, (string) ($_POST['reject_reason'] ?? ''));
            casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'رد شد. کاربر می‌تواند پوستر را اصلاح و دوباره بفرستد.' : $res['error']);
        } else {
            $range = casting_ad_poster_parse_display_range($_POST);
            if (!$range['ok']) {
                casting_set_flash('error', $range['error']);
                casting_redirect($return);
            }
            if ($action === 'approve') {
                $res = casting_ad_poster_approve($poster_id, $user_id, $range['from'], $range['until']);
                casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'منتشر شد و تا تاریخ انتخاب‌شده در بنر صفحه اصلی نمایش داده می‌شود.' : $res['error']);
            } else {
                $res = casting_ad_poster_republish($poster_id, $user_id, $range['from'], $range['until']);
                casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'دوباره منتشر شد و در بازه جدید در تبلیغات نمایش داده می‌شود.' : $res['error']);
            }
        }
        casting_redirect($return);
    }

    $title = (string) ($_POST['title'] ?? '');
    if ($action === 'undo_delete') {
        $res = casting_ad_poster_undo_delete($user_id, (int) ($_POST['poster_id'] ?? 0));
        casting_set_flash(
            $res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'پوستر حذف شد و به صف ادمین نرفت. می‌توانید دوباره ارسال کنید.' : $res['error']
        );
    } elseif ($action === 'resubmit') {
        $res = casting_ad_poster_resubmit($user_id, (int) ($_POST['poster_id'] ?? 0), 'poster_file', $title);
        casting_set_flash(
            $res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'پوستر دوباره ارسال شد. تا ۵ دقیقه می‌توانید حذف یا ویرایش کنید.' : $res['error']
        );
    } else {
        $credit_id = (int) ($_POST['credit_id'] ?? 0);
        $owner_type = sanitize_key((string) ($_POST['ad_type'] ?? ''));
        $res = casting_ad_poster_submit($user_id, $credit_id, 'poster_file', $title, $owner_type);
        casting_set_flash(
            $res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'پوستر ارسال شد. تا ۵ دقیقه می‌توانید حذف یا ویرایش کنید؛ بعد از آن برای تأیید ادمین می‌رود.' : $res['error']
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
$can_upload = casting_user_can_submit_ad_poster($user_id);
$has_pending = false;
$has_approved = false;
$has_undo = false;
foreach ($items as $row) {
    $st = (string) ($row['status'] ?? '');
    if ($st === 'pending') {
        $has_pending = true;
        if (casting_ad_poster_can_undo($row)) {
            $has_undo = true;
        }
    }
    if ($st === 'approved') {
        $has_approved = true;
    }
}
$upload_hint = '';
if (!$can_upload) {
    if ($has_undo) {
        $upload_hint = 'تا ۵ دقیقه می‌توانید همین پوستر را حذف یا ویرایش کنید. بعد از آن برای تأیید ادمین می‌رود و دیگر قابل تغییر نیست.';
    } elseif ($has_pending) {
        $upload_hint = 'پوستر شما در انتظار تأیید است. پس از تأیید ادمین، برای پوستر بعدی باید دوباره هزینه تبلیغات را پرداخت کنید.';
    } elseif ($has_approved) {
        $upload_hint = 'پوستر تأیید و منتشر شد. برای ارسال پوستر جدید از خرید اشتراک اقدام کنید.';
    } else {
        $upload_hint = 'پس از پرداخت هزینهٔ تبلیغات، انتخاب فایل و ارسال برای تأیید فعال می‌شود.';
    }
}
$pending_count = $is_admin ? casting_admin_pending_ad_posters_count() : 0;
$archived_count = $is_admin ? casting_admin_archived_ad_posters_count() : 0;
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
    <p class="lede">پوسترها بعد از ۵ دقیقه مهلت اصلاح کاربر اینجا می‌آیند. برای انتشار، مدت نمایش را از تقویم انتخاب کنید. بعد از پایان آن زمان به آرشیو می‌روند تا در صورت نیاز دوباره منتشر شوند.</p>
    <div class="admin-tabs admin-media-tabs" role="tablist" aria-label="وضعیت پوسترها">
      <a class="admin-tab<?= $inbox_status === 'pending' ? ' is-active' : '' ?>" href="<?= casting_e($inbox_url('pending')) ?>">
        <span class="admin-tab-mark admin-tab-mark--pending" aria-hidden="true"></span>
        <span>در انتظار (<?= (int) $pending_count ?>)</span>
      </a>
      <a class="admin-tab<?= $inbox_status === 'approved' ? ' is-active' : '' ?>" href="<?= casting_e($inbox_url('approved')) ?>">
        <span class="admin-tab-mark admin-tab-mark--approved" aria-hidden="true"></span>
        <span>در حال نمایش</span>
      </a>
      <a class="admin-tab<?= $inbox_status === 'archived' ? ' is-active' : '' ?>" href="<?= casting_e($inbox_url('archived')) ?>">
        <span class="admin-tab-mark admin-tab-mark--archived" aria-hidden="true"></span>
        <span>آرشیو<?= $archived_count > 0 ? ' (' . (int) $archived_count . ')' : '' ?></span>
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
                <?php casting_render_ad_poster_zoom($url, $item_title !== '' ? $item_title : 'پوستر'); ?>
              <?php endif; ?>
            </div>
            <div class="admin-media-body">
              <h2><?= casting_e($owner ? (string) $owner->display_name : 'کاربر') ?></h2>
              <p class="meta">
                @<?= casting_e($owner ? (string) $owner->user_login : '') ?>
                · <?= casting_e(casting_ad_type_label((string) ($item['ad_type'] ?? ''))) ?>
                · <?= (int) ($item['width'] ?? 0) ?>×<?= (int) ($item['height'] ?? 0) ?>
                · <?= casting_e(casting_ad_poster_status_label($item_status, $item)) ?>
                · <?= casting_e((string) ($item['created_at'] ?? '')) ?>
              </p>
              <?php if ($item_title !== '') : ?>
                <p><?= casting_e($item_title) ?></p>
              <?php endif; ?>
              <?php if ($item_status === 'rejected' && trim((string) ($item['reject_reason'] ?? '')) !== '') : ?>
                <p class="meta admin-media-reject-reason">دلیل رد: <?= casting_e((string) ($item['reject_reason'])) ?></p>
              <?php endif; ?>
              <?php if ($item_status === 'pending') : ?>
                <div class="ad-admin-review">
                  <form method="post" action="my-ads.php" class="ad-publish-form">
                    <?php wp_nonce_field('casting_ad_poster'); ?>
                    <input type="hidden" name="return_status" value="<?= casting_e($inbox_status) ?>">
                    <input type="hidden" name="poster_id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="ad_action" value="approve">
                    <?php casting_render_ad_publish_calendar((int) $item['id']); ?>
                    <div class="cta-row">
                      <button class="btn btn-primary" type="submit">تأیید و انتشار</button>
                    </div>
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
                <form method="post" action="my-ads.php" class="ad-publish-form">
                  <?php wp_nonce_field('casting_ad_poster'); ?>
                  <input type="hidden" name="return_status" value="<?= casting_e($inbox_status) ?>">
                  <input type="hidden" name="poster_id" value="<?= (int) $item['id'] ?>">
                  <input type="hidden" name="ad_action" value="approve">
                  <?php casting_render_ad_publish_calendar((int) $item['id']); ?>
                  <button class="btn btn-ghost btn-sm" type="submit">تأیید همین فایل</button>
                </form>
              <?php elseif ($item_status === 'archived' || $item_status === 'approved') : ?>
                <form method="post" action="my-ads.php" class="ad-publish-form">
                  <?php wp_nonce_field('casting_ad_poster'); ?>
                  <input type="hidden" name="return_status" value="<?= casting_e($inbox_status) ?>">
                  <input type="hidden" name="poster_id" value="<?= (int) $item['id'] ?>">
                  <input type="hidden" name="ad_action" value="republish">
                  <?php
                  $from_ymd = casting_ad_poster_ymd((string) ($item['display_from'] ?? ''));
                  $until_ymd = casting_ad_poster_ymd((string) ($item['display_until'] ?? ''));
                  if ($item_status === 'archived') {
                      $from_ymd = casting_ad_poster_default_display_from();
                      $until_ymd = casting_ad_poster_default_display_until();
                  }
                  casting_render_ad_publish_calendar((int) $item['id'], $from_ymd, $until_ymd);
                  ?>
                  <button class="btn <?= $item_status === 'archived' ? 'btn-primary' : 'btn-ghost' ?> btn-sm" type="submit">
                    <?= $item_status === 'archived' ? 'باز نشر با تاریخ جدید' : 'تغییر مدت نمایش' ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php else : ?>
    <p class="lede"><?= $is_admin ? 'پوستر خودتان را از اینجا بفرستید.' : 'بعد از ارسال تا ۵ دقیقه مثل undo ایمیل می‌توانید پوستر را حذف یا عوض کنید. بعد از آن برای تأیید ادمین می‌رود. انتخاب فایل فقط بعد از پرداخت هزینهٔ تبلیغات فعال است.' ?></p>

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

    <?php if ($upload_hint !== '') : ?>
      <p class="meta ad-upload-hint"><?= casting_e($upload_hint) ?>
        <?php if (!$has_pending) : ?>
          <a href="<?= casting_e(casting_url('cart.php#shop-ads')) ?>">خرید اشتراک</a>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <form class="form ad-upload-form<?= $can_upload ? '' : ' is-disabled' ?>" method="post" enctype="multipart/form-data" action="my-ads.php"<?= $can_upload ? '' : ' onsubmit="return false;"' ?>>
      <?php wp_nonce_field('casting_ad_poster'); ?>
      <input type="hidden" name="ad_action" value="upload">
      <?php if ($paid_credits !== []) : ?>
        <div class="field">
          <label for="credit_id">سهمیه پرداخت‌شده</label>
          <select id="credit_id" name="credit_id" required<?= $can_upload ? '' : ' disabled' ?>>
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
          <select id="ad_type" name="ad_type" required<?= $can_upload ? '' : ' disabled' ?>>
            <?php foreach (casting_ad_type_labels() as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="field">
        <label for="title">عنوان (اختیاری)</label>
        <input id="title" name="title" type="text" maxlength="120" placeholder="مثلاً نام نمایش یا فیلم"<?= $can_upload ? '' : ' disabled' ?>>
      </div>
      <div class="field">
        <label for="poster_file">فایل پوستر</label>
        <?php casting_render_file_pick([
            'id'        => 'poster_file',
            'name'      => 'poster_file',
            'disabled'  => !$can_upload,
            'required'  => true,
            'max_bytes' => (int) $spec['max_bytes'],
        ]); ?>
        <p class="field-hint">JPG / PNG / WebP — حداقل <?= (int) $spec['min_width'] ?>×<?= (int) $spec['min_height'] ?> — حداکثر <?= casting_e(casting_upload_max_label_fa('image')) ?></p>
      </div>
      <button class="btn btn-primary" type="submit"<?= $can_upload ? '' : ' disabled' ?>>ارسال برای تأیید</button>
    </form>

    <h2 class="panel-section-title">پوسترهای شما</h2>
    <?php if ($items === []) : ?>
      <p class="empty-state">هنوز پوستری ارسال نکرده‌اید.</p>
    <?php else : ?>
      <div class="ad-poster-grid">
        <?php foreach ($items as $item) :
            $url = casting_ad_poster_url($item);
            $status = (string) ($item['status'] ?? 'pending');
            $is_rejected = $status === 'rejected';
            $can_undo = casting_ad_poster_can_undo($item);
            $can_edit_now = $is_rejected || $can_undo;
            $is_editing = $edit_id === (int) $item['id'] && $can_edit_now;
            $item_title = trim((string) ($item['title'] ?? ''));
            $undo_left = $can_undo ? casting_ad_poster_undo_remaining($item) : 0;
            $undo_until = $can_undo ? (casting_ad_poster_created_ts($item) + casting_ad_poster_undo_seconds()) : 0;
            ?>
          <article class="ad-poster-card ad-poster-card--<?= casting_e($status) ?>"<?= $is_editing ? ' id="ad-edit-focus"' : '' ?>>
            <?php if ($url !== '') : ?>
              <?php casting_render_ad_poster_zoom($url, $item_title !== '' ? $item_title : 'پوستر'); ?>
            <?php endif; ?>
            <div class="ad-poster-card-body">
              <span class="chip<?= $is_rejected ? ' chip-danger' : ($status === 'archived' ? ' chip-muted' : '') ?>"><?= casting_e(casting_ad_poster_status_label($status, $item)) ?></span>
              <p class="meta">
                <?= casting_e(casting_ad_type_label((string) ($item['ad_type'] ?? ''))) ?>
                · <?= (int) ($item['width'] ?? 0) ?>×<?= (int) ($item['height'] ?? 0) ?>
                · <?= casting_e((string) ($item['created_at'] ?? '')) ?>
              </p>
              <?php if ($item_title !== '') : ?>
                <p><?= casting_e($item_title) ?></p>
              <?php endif; ?>
              <?php if ($can_undo) : ?>
                <p class="meta ad-undo-hint" data-undo-until="<?= (int) $undo_until ?>">
                  تا <strong data-undo-remain><?= (int) floor($undo_left / 60) ?>:<?= sprintf('%02d', $undo_left % 60) ?></strong> می‌توانید این پوستر را حذف یا ویرایش کنید. بعد از آن برای تأیید ادمین می‌رود.
                </p>
              <?php endif; ?>
              <?php if ($is_rejected && trim((string) ($item['reject_reason'] ?? '')) !== '') : ?>
                <p class="meta gallery-reject-reason">دلیل رد: <?= casting_e((string) ($item['reject_reason'])) ?></p>
              <?php endif; ?>
              <?php if ($can_edit_now) : ?>
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
                      <?php casting_render_file_pick([
                          'id'        => 'edit_file_' . (int) $item['id'],
                          'name'      => 'poster_file',
                          'required'  => true,
                          'max_bytes' => (int) $spec['max_bytes'],
                      ]); ?>
                    </div>
                    <div class="cta-row">
                      <button class="btn btn-primary btn-sm" type="submit">ارسال مجدد</button>
                      <a class="btn btn-ghost btn-sm" href="<?= casting_e($mine_url) ?>">انصراف</a>
                    </div>
                  </form>
                <?php else : ?>
                  <div class="cta-row" data-undo-actions>
                    <a class="btn btn-primary btn-sm" href="my-ads.php?tab=mine&edit=<?= (int) $item['id'] ?>"><?= $can_undo ? 'ویرایش و ارسال مجدد' : 'اصلاح و ارسال مجدد' ?></a>
                    <?php if ($can_undo) : ?>
                      <form method="post" action="my-ads.php" onsubmit="return confirm('پوستر از صف تأیید ادمین حذف می‌شود و سهمیه برای ارسال دوباره آزاد می‌گردد. ادامه می‌دهید؟');">
                        <?php wp_nonce_field('casting_ad_poster'); ?>
                        <input type="hidden" name="ad_action" value="undo_delete">
                        <input type="hidden" name="poster_id" value="<?= (int) $item['id'] ?>">
                        <button class="btn btn-ghost btn-sm" type="submit">حذف</button>
                      </form>
                    <?php endif; ?>
                  </div>
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
