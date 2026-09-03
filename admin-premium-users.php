<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/referral.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
casting_require_admin_permission('view_premium_users');
casting_referral_maybe_backfill();

$repair = casting_premium_repair_orphan_once($user_id);
if ($repair['ran'] && $repair['revoked'] !== []) {
    $names = array_values($repair['revoked']);
    casting_set_flash(
        'success',
        'ویژهٔ بدون مدرک پرداخت لغو شد: ' . implode('، ', $names)
    );
}

$error = '';
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$target_id = (int) ($_GET['user'] ?? 0);
$can_suspend = casting_user_has_admin_permission($user_id, 'suspend_users')
    || casting_user_is_portal_owner($user_id);
$can_manage_password = casting_user_is_portal_owner($user_id)
    || casting_user_has_admin_permission($user_id, 'view_premium_users');
$can_delete_user = casting_user_has_admin_permission($user_id, 'view_premium_users');
$can_view_blocks = casting_user_has_admin_permission($user_id, 'view_user_blocks')
    || casting_user_has_admin_permission($user_id, 'unblock_users');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_admin_members')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $target_id = (int) ($_POST['target_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        $redirect = 'admin-premium-users.php?user=' . $target_id;
        if ($search !== '') {
            $redirect .= '&q=' . rawurlencode($search);
        }
        if ($page > 1) {
            $redirect .= '&page=' . $page;
        }
        $redirect .= '#member-admin';

        if ($action === 'suspend' && $can_suspend) {
            $result = casting_admin_suspend_user($target_id, $user_id, (string) ($_POST['reason'] ?? ''));
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'حساب کاربر غیرفعال شد.' : $result['error']);
        } elseif ($action === 'unsuspend' && $can_suspend) {
            $result = casting_admin_unsuspend_user($target_id, $user_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'حساب کاربر فعال شد.' : $result['error']);
        } elseif ($action === 'set_password' && $can_manage_password) {
            $result = casting_admin_set_password(
                $target_id,
                $user_id,
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['confirm_password'] ?? '')
            );
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'رمز عبور کاربر تغییر کرد.' : $result['error']);
        } elseif ($action === 'grant_premium_intent') {
            $mode = casting_user_is_premium($target_id) ? 'renew' : 'grant';
            $result = casting_admin_grant_premium_begin($user_id, $target_id, $mode);
            casting_set_flash(
                $result['ok'] ? 'success' : 'error',
                $result['ok']
                    ? 'مرحلهٔ تأیید شروع شد. تیک اطمینان را بزنید و پس از شمارش معکوس ۱۰ ثانیه، تأیید نهایی را بزنید.'
                    : $result['error']
            );
        } elseif ($action === 'grant_premium_cancel') {
            casting_admin_grant_premium_clear();
            casting_set_flash('success', 'ویژه کردن دستی لغو شد.');
        } elseif ($action === 'grant_premium') {
            $confirmed = !empty($_POST['grant_confirm']);
            $check = casting_admin_grant_premium_can_finalize($user_id, $target_id, $confirmed);
            if (!$check['ok']) {
                casting_set_flash('error', $check['error']);
            } else {
                $was_premium = casting_user_is_premium($target_id);
                $result = casting_admin_grant_premium($target_id, $user_id);
                casting_admin_grant_premium_clear();
                if ($result['ok']) {
                    casting_set_flash(
                        'success',
                        $was_premium
                            ? 'اشتراک ویژه کاربر ۳ ماه تمدید شد.'
                            : 'کاربر به عضو ویژه تبدیل شد (۳ ماه).'
                    );
                } else {
                    casting_set_flash('error', $result['error']);
                }
            }
        } elseif ($action === 'revoke_premium') {
            $result = casting_admin_revoke_premium($target_id, $user_id, (string) ($_POST['revoke_note'] ?? ''));
            casting_set_flash(
                $result['ok'] ? 'success' : 'error',
                $result['ok'] ? 'عضویت ویژه لغو شد.' : $result['error']
            );
        } elseif ($action === 'delete_user' && $can_delete_user) {
            $confirm_login = trim((string) ($_POST['confirm_login'] ?? ''));
            $target_user = get_user_by('id', $target_id);
            $expected = $target_user ? (string) $target_user->user_login : '';
            if ($expected === '' || $confirm_login === '' || strcasecmp($confirm_login, $expected) !== 0) {
                casting_set_flash('error', 'برای حذف، نام کاربری را دقیقاً وارد کنید.');
            } else {
                $result = casting_admin_delete_user($target_id, $user_id);
                if ($result['ok']) {
                    casting_set_flash('success', 'کاربر حذف شد.');
                    $redirect = 'admin-premium-users.php';
                    if ($search !== '') {
                        $redirect .= '?q=' . rawurlencode($search);
                        if ($page > 1) {
                            $redirect .= '&page=' . $page;
                        }
                    } elseif ($page > 1) {
                        $redirect .= '?page=' . $page;
                    }
                } else {
                    casting_set_flash('error', $result['error']);
                }
            }
        }
        casting_redirect($redirect);
    }
}

$list = casting_list_casting_members($page, 50, $search);
$members = $list['rows'];
$total = $list['total'];
$total_pages = max(1, (int) ceil($total / $list['per_page']));

$target = $target_id > 0 ? get_user_by('id', $target_id) : false;
$target_role = $target ? casting_get_user_role($target_id) : '';
$suspended = $target ? casting_user_is_suspended($target_id) : false;
$suspend_reason = $target ? (string) get_user_meta($target_id, 'casting_suspended_reason', true) : '';
$target_premium = $target ? casting_user_is_premium($target_id) : false;
$target_until_ts = $target_premium ? casting_premium_expire_timestamp($target_id) : null;
$target_is_super = $target ? casting_user_is_super_admin($target_id) : false;

$list_url = 'admin-premium-users.php';
if ($search !== '') {
    $list_url .= '?q=' . rawurlencode($search);
    if ($page > 1) {
        $list_url .= '&page=' . $page;
    }
} elseif ($page > 1) {
    $list_url .= '?page=' . $page;
}

$member_query = static function (int $member_id) use ($search, $page): string {
    $url = 'admin-premium-users.php?user=' . $member_id;
    if ($search !== '') {
        $url .= '&q=' . rawurlencode($search);
    }
    if ($page > 1) {
        $url .= '&page=' . $page;
    }
    return $url . '#member-admin';
};

casting_render_panel_start('مشترکین', 'admin-premium');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card">
  <h1>مشترکین</h1>
  <p class="meta"><?= (int) $total ?> عضو — مدیریت حساب و رمز. پرداخت آنلاین خودش ویژه می‌کند؛ ویژه کردن دستی تأیید دومرحله‌ای دارد.</p>

  <form class="form admin-search-form" method="get" action="admin-premium-users.php">
    <div class="field">
      <label for="q">جستجو</label>
      <input id="q" name="q" type="search" value="<?= casting_e($search) ?>" placeholder="نام، ایمیل یا نام کاربری">
    </div>
    <button class="btn btn-primary" type="submit">جستجو</button>
    <?php if ($search !== '') : ?>
      <a class="btn btn-ghost" href="admin-premium-users.php">پاک کردن</a>
    <?php endif; ?>
  </form>

  <?php if ($target && $target_role !== '') : ?>
    <div class="admin-member-panel" id="member-admin" data-admin-member-panel>
      <div class="admin-member-panel-head">
        <h2 class="panel-section-title">مدیریت: <?= casting_e($target->display_name) ?></h2>
        <a class="btn btn-ghost btn-sm" href="<?= casting_e($list_url) ?>">بستن</a>
      </div>

      <ul class="info-list">
        <li><strong>نام کاربری:</strong> <?= casting_e($target->user_login) ?></li>
        <li><strong>ایمیل:</strong> <?= casting_e($target->user_email) ?></li>
        <?php if (casting_user_can_view_contact_numbers($user_id)) : ?>
          <?php casting_render_contact_number_items(casting_get_profile($target_id)); ?>
        <?php endif; ?>
        <li><strong>نقش:</strong> <?= casting_e(casting_user_profile_chip_label($target_id, $user_id)) ?></li>
        <li><strong>وضعیت حساب:</strong> <?= $suspended ? 'غیرفعال (تعلیق)' : 'فعال' ?></li>
        <li><strong>مدت فعال بودن:</strong> <?= casting_e(casting_user_active_duration_label($target_id)) ?></li>
        <li><strong>آخرین فعالیت:</strong> <?= casting_e(casting_user_last_active_label($target_id)) ?></li>
        <?php
        $target_referral_code = casting_get_referral_code($target_id);
        $target_referred_by = casting_user_referred_by($target_id);
        $target_referral_count = casting_referred_users_count($target_id);
        ?>
        <?php if ($target_referral_code !== '') : ?>
          <li><strong>کد معرفی:</strong> <span class="membership-number referral-code" dir="ltr"><?= casting_e($target_referral_code) ?></span></li>
        <?php endif; ?>
        <?php if ($target_referred_by > 0) : ?>
          <?php $ref_by_user = get_user_by('id', $target_referred_by); ?>
          <li>
            <strong>معرف:</strong>
            <?php if ($ref_by_user) : ?>
              <a href="<?= casting_e($member_query($target_referred_by)) ?>"><?= casting_e((string) $ref_by_user->display_name) ?></a>
              <span class="meta">(<?= casting_e((string) $ref_by_user->user_login) ?>)</span>
            <?php else : ?>
              #<?= (int) $target_referred_by ?>
            <?php endif; ?>
          </li>
        <?php endif; ?>
        <li><strong>ثبت‌نام با کد این کاربر:</strong> <?= (int) $target_referral_count ?> نفر</li>
        <?php if ($suspended && $suspend_reason !== '') : ?>
          <li><strong>دلیل تعلیق:</strong> <?= casting_e($suspend_reason) ?></li>
        <?php endif; ?>
        <li>
          <strong>اشتراک ویژه:</strong>
          <?php if ($target_premium) : ?>
            <?php if ($target_until_ts !== null) : ?>
              <span class="nav-premium-countdown admin-table-countdown" data-premium-until-ts="<?= (int) $target_until_ts ?>">
                <span data-premium-countdown><?= casting_e(casting_premium_countdown_nav_label($target_id)) ?></span>
              </span>
              — پایان: <?= casting_e(casting_premium_until_label($target_id)) ?>
            <?php else : ?>
              فعال
            <?php endif; ?>
          <?php else : ?>
            ندارد
          <?php endif; ?>
        </li>
      </ul>

      <?php
      $admin_referrals = casting_list_referred_users($target_id);
      if ($admin_referrals !== []) :
          ?>
        <div class="admin-member-action-box">
          <h3 class="panel-section-title">افراد معرفی‌شده</h3>
          <ul class="panel-list referral-list">
            <?php foreach ($admin_referrals as $ref_row) : ?>
              <li class="panel-list-item referral-list-item">
                <div class="referral-list-main">
                  <a href="<?= casting_e($member_query((int) $ref_row['id'])) ?>"><strong><?= casting_e($ref_row['name']) ?></strong></a>
                  <span class="meta"><?= casting_e(casting_role_label($ref_row['role'])) ?> · <?= casting_e($ref_row['login']) ?></span>
                </div>
                <div class="referral-list-meta meta">
                  <span>فعال: <?= casting_e($ref_row['active_duration']) ?></span>
                  <span>آخرین فعالیت: <?= casting_e($ref_row['last_active']) ?></span>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!$target_is_super) : ?>
        <div class="admin-member-actions">
          <div class="admin-member-action-box">
            <h3 class="panel-section-title">اشتراک ویژه</h3>
            <p class="meta">پرداخت آنلاین کاربر خودش ویژه را فعال می‌کند. ویژه کردن دستی فقط برای موارد استثناست و تأیید دومرحله‌ای دارد.</p>
            <?php
            $grant_pending = casting_admin_grant_premium_pending($target_id);
            $grant_wait = casting_admin_grant_premium_wait_seconds();
            $grant_remaining = 0;
            if ($grant_pending !== null) {
                $grant_remaining = max(0, $grant_wait - (time() - (int) $grant_pending['started_at']));
            }
            ?>
            <?php if ($grant_pending !== null) : ?>
              <div
                class="admin-grant-confirm"
                data-grant-premium-confirm
                data-grant-wait="<?= (int) $grant_wait ?>"
                data-grant-remaining="<?= (int) $grant_remaining ?>"
              >
                <p class="admin-grant-confirm-q"><strong>از انجام این کار مطمئن هستید؟</strong></p>
                <p class="meta">
                  <?= ($grant_pending['mode'] ?? '') === 'renew'
                    ? 'اشتراک ویژه این کاربر ۳ ماه تمدید می‌شود.'
                    : 'این کاربر ۳ ماه عضو ویژه می‌شود.' ?>
                </p>
                <label class="admin-grant-confirm-check">
                  <input type="checkbox" name="grant_confirm_ui" value="1" data-grant-confirm-check>
                  از انجام این کار مطمئن هستم
                </label>
                <p class="admin-grant-confirm-timer meta" data-grant-confirm-timer aria-live="polite">
                  <?php if ($grant_remaining > 0) : ?>
                    لطفاً <strong data-grant-seconds><?= (int) $grant_remaining ?></strong> ثانیه صبر کنید…
                  <?php else : ?>
                    می‌توانید تأیید نهایی را بزنید.
                  <?php endif; ?>
                </p>
                <div class="cta-row">
                  <form method="post" action="<?= casting_e($member_query($target_id)) ?>" data-grant-finalize-form>
                    <?php wp_nonce_field('casting_admin_members'); ?>
                    <input type="hidden" name="target_id" value="<?= $target_id ?>">
                    <input type="hidden" name="grant_confirm" value="0" data-grant-confirm-hidden>
                    <button class="btn btn-primary" type="submit" name="action" value="grant_premium" data-grant-finalize-btn disabled>
                      تأیید نهایی و ویژه کردن
                    </button>
                  </form>
                  <form method="post" action="<?= casting_e($member_query($target_id)) ?>">
                    <?php wp_nonce_field('casting_admin_members'); ?>
                    <input type="hidden" name="target_id" value="<?= $target_id ?>">
                    <button class="btn btn-ghost" type="submit" name="action" value="grant_premium_cancel">انصراف</button>
                  </form>
                </div>
              </div>
            <?php else : ?>
              <div class="cta-row">
                <form method="post" action="<?= casting_e($member_query($target_id)) ?>">
                  <?php wp_nonce_field('casting_admin_members'); ?>
                  <input type="hidden" name="target_id" value="<?= $target_id ?>">
                  <button class="btn btn-primary" type="submit" name="action" value="grant_premium_intent">
                    <?= $target_premium ? 'شروع تمدید ویژه (+۳ ماه)' : 'شروع تبدیل به عضو ویژه' ?>
                  </button>
                </form>
                <?php if ($target_premium) : ?>
                  <form method="post" action="<?= casting_e($member_query($target_id)) ?>" onsubmit="return confirm('عضویت ویژه این کاربر کاملاً لغو شود؟');">
                    <?php wp_nonce_field('casting_admin_members'); ?>
                    <input type="hidden" name="target_id" value="<?= $target_id ?>">
                    <input type="hidden" name="revoke_note" value="لغو دستی از پنل مشترکین">
                    <button class="btn btn-reject" type="submit" name="action" value="revoke_premium">لغو ویژه</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($can_suspend) : ?>
            <div class="admin-member-action-box">
              <h3 class="panel-section-title">غیرفعال / فعال کردن حساب</h3>
              <?php if ($suspended) : ?>
                <form method="post" action="<?= casting_e($member_query($target_id)) ?>">
                  <?php wp_nonce_field('casting_admin_members'); ?>
                  <input type="hidden" name="target_id" value="<?= $target_id ?>">
                  <button class="btn btn-primary" type="submit" name="action" value="unsuspend">فعال کردن حساب</button>
                </form>
              <?php else : ?>
                <form class="form admin-suspend-form" method="post" action="<?= casting_e($member_query($target_id)) ?>">
                  <?php wp_nonce_field('casting_admin_members'); ?>
                  <input type="hidden" name="target_id" value="<?= $target_id ?>">
                  <div class="field">
                    <label for="reason">دلیل غیرفعال کردن (اختیاری)</label>
                    <textarea id="reason" name="reason" rows="2" maxlength="500"></textarea>
                  </div>
                  <button class="btn btn-reject" type="submit" name="action" value="suspend">غیرفعال کردن حساب</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="admin-member-action-box admin-member-action-box--password">
            <h3 class="panel-section-title">تغییر رمز عبور</h3>
            <p class="meta">رمز جدید را وارد کنید — کاربر با رمز جدید وارد می‌شود.</p>
            <form class="form admin-password-form" method="post" action="<?= casting_e($member_query($target_id)) ?>" data-loading>
              <?php wp_nonce_field('casting_admin_members'); ?>
              <input type="hidden" name="target_id" value="<?= $target_id ?>">
              <div class="field">
                <label for="new_password">رمز جدید</label>
                <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
              </div>
              <div class="field">
                <label for="confirm_password">تکرار رمز جدید</label>
                <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
              </div>
              <button class="btn btn-primary" type="submit" name="action" value="set_password">ذخیره رمز جدید</button>
            </form>
          </div>

          <?php if ($can_delete_user) : ?>
            <div class="admin-member-action-box admin-member-action-box--danger">
              <h3 class="panel-section-title">حذف کاربر</h3>
              <p class="meta">حذف دائمی است و قابل بازگشت نیست. برای تأیید، نام کاربری را وارد کنید: <strong><?= casting_e((string) $target->user_login) ?></strong></p>
              <form
                class="form admin-delete-user-form"
                method="post"
                action="<?= casting_e($member_query($target_id)) ?>"
                onsubmit="return confirm('این کاربر برای همیشه حذف شود؟ این عمل قابل بازگشت نیست.');"
              >
                <?php wp_nonce_field('casting_admin_members'); ?>
                <input type="hidden" name="target_id" value="<?= $target_id ?>">
                <div class="field">
                  <label for="confirm_login">نام کاربری برای تأیید حذف</label>
                  <input id="confirm_login" name="confirm_login" type="text" autocomplete="off" required placeholder="<?= casting_e((string) $target->user_login) ?>">
                </div>
                <button class="btn btn-reject" type="submit" name="action" value="delete_user">حذف دائمی کاربر</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php else : ?>
        <p class="meta">حساب مدیر اصلی از این بخش قابل تعلیق، تغییر رمز یا حذف نیست.</p>
      <?php endif; ?>

      <?php if (!$can_suspend && !$target_is_super) : ?>
        <p class="meta">برای غیرفعال کردن حساب، دسترسی «تعلیق / رفع تعلیق کاربر» لازم است.</p>
      <?php endif; ?>

      <div class="cta-row">
        <a class="btn btn-ghost btn-sm" href="member.php?id=<?= $target_id ?>">مشاهده پروفایل</a>
        <?php if ($can_view_blocks) : ?>
          <a class="btn btn-ghost btn-sm" href="admin-users.php?user=<?= $target_id ?>">بلاک‌ها و تاریخچه</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$members) : ?>
    <p class="empty-state">کاربری پیدا نشد.</p>
  <?php else : ?>
    <div class="admin-members-list" role="list">
      <?php foreach ($members as $row) :
        $row_ref = casting_get_referral_code((int) $row['id']);
        $row_role = casting_user_profile_chip_label((int) $row['id'], $user_id);
        $row_active = casting_user_active_duration_label((int) $row['id']);
        $until_parts = $row['premium'] && (($row['until_ts'] ?? null) !== null)
          ? casting_premium_until_parts((string) ($row['until'] ?? ''))
          : ['date' => '', 'time' => ''];
        ?>
        <article class="admin-member-row<?= $target_id === (int) $row['id'] ? ' is-selected' : '' ?>" role="listitem">
          <div class="admin-member-row-body">
            <div class="admin-member-row-head">
              <strong class="admin-member-row-name"><?= casting_e($row['name']) ?></strong>
              <?php if ($row_role !== '') : ?>
                <span class="admin-member-row-role"><?= casting_e($row_role) ?></span>
              <?php endif; ?>
              <?php if ($row['suspended']) : ?>
                <span class="chip chip-danger">غیرفعال</span>
              <?php else : ?>
                <span class="chip chip-active">فعال</span>
              <?php endif; ?>
              <?php if ($row['premium']) : ?>
                <span class="chip chip-premium">ویژه</span>
              <?php endif; ?>
            </div>

            <div class="admin-member-row-codes">
              <span class="admin-member-code"><?= casting_e($row['login']) ?></span>
              <?php if (($row['membership_number'] ?? '') !== '') : ?>
                <span class="admin-member-code membership-number"><?= casting_e((string) $row['membership_number']) ?></span>
              <?php endif; ?>
              <?php if ($row_ref !== '') : ?>
                <span class="admin-member-code">معرف <span class="membership-number referral-code" dir="ltr"><?= casting_e($row_ref) ?></span></span>
              <?php endif; ?>
            </div>

            <div class="admin-member-row-stats">
              <span class="admin-member-stat">
                <span class="admin-member-stat-label">فعال</span>
                <span class="admin-member-stat-value"><?= casting_e($row_active) ?></span>
              </span>
              <span class="admin-member-stat">
                <span class="admin-member-stat-label">اشتراک</span>
                <span class="admin-member-stat-value">
                  <?php if ($row['premium']) : ?>
                    <?php if (($row['until_ts'] ?? null) !== null) : ?>
                      <span class="nav-premium-countdown admin-table-countdown" data-premium-until-ts="<?= (int) $row['until_ts'] ?>">
                        <span data-premium-countdown><?= casting_e($row['remaining']) ?></span>
                      </span>
                      <?php if ($until_parts['date'] !== '') : ?>
                        <span class="admin-member-stat-sub">
                          <?= casting_e($until_parts['date']) ?>
                          <?php if ($until_parts['time'] !== '') : ?>
                            · <?= casting_e($until_parts['time']) ?>
                          <?php endif; ?>
                        </span>
                      <?php endif; ?>
                    <?php else : ?>
                      فعال
                    <?php endif; ?>
                  <?php else : ?>
                    ندارد
                  <?php endif; ?>
                </span>
              </span>
            </div>
          </div>

          <div class="admin-member-row-actions">
            <a class="btn btn-primary btn-sm" href="<?= casting_e($member_query((int) $row['id'])) ?>">مدیریت</a>
            <a class="btn btn-ghost btn-sm" href="member.php?id=<?= (int) $row['id'] ?>">پروفایل</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1) : ?>
      <nav class="admin-pagination" aria-label="صفحه‌بندی">
        <?php for ($p = 1; $p <= $total_pages; $p++) : ?>
          <?php
            $page_url = 'admin-premium-users.php?page=' . $p;
            if ($search !== '') {
                $page_url .= '&q=' . rawurlencode($search);
            }
            if ($target_id > 0) {
                $page_url .= '&user=' . $target_id;
            }
            $page_url .= $target_id > 0 ? '#member-admin' : '';
            ?>
          <a class="btn btn-ghost btn-sm<?= $p === $page ? ' is-active' : '' ?>" href="<?= casting_e($page_url) ?>"><?= $p ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
