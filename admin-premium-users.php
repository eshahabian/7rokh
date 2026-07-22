<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
casting_require_admin_permission('view_premium_users');

$error = '';
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$target_id = (int) ($_GET['user'] ?? 0);
$can_suspend = casting_user_has_admin_permission($user_id, 'suspend_users');
$can_manage_password = true;
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
  <p class="meta"><?= (int) $total ?> عضو — مدیریت حساب، رمز عبور و اشتراک ویژه</p>

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
        <li><strong>نقش:</strong> <?= casting_e(casting_role_label($target_role)) ?></li>
        <li><strong>وضعیت حساب:</strong> <?= $suspended ? 'غیرفعال (تعلیق)' : 'فعال' ?></li>
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

      <?php if (!$target_is_super) : ?>
        <div class="admin-member-actions">
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
        </div>
      <?php else : ?>
        <p class="meta">حساب مدیر اصلی از این بخش قابل تعلیق یا تغییر رمز نیست.</p>
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
    <div class="admin-table-wrap">
      <table class="admin-table admin-members-table">
        <thead>
          <tr>
            <th>کاربر</th>
            <th>نقش</th>
            <th>وضعیت</th>
            <th>اشتراک ویژه</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $row) : ?>
            <tr<?= $target_id === (int) $row['id'] ? ' class="is-selected"' : '' ?>>
              <td>
                <strong><?= casting_e($row['name']) ?></strong>
                <span class="meta"><?= casting_e($row['login']) ?></span>
              </td>
              <td><?= casting_e(casting_role_label($row['role'])) ?></td>
              <td>
                <?php if ($row['suspended']) : ?>
                  <span class="chip chip-danger">غیرفعال</span>
                <?php else : ?>
                  <span class="chip chip-active">فعال</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($row['premium']) : ?>
                  <?php if (($row['until_ts'] ?? null) !== null) : ?>
                    <span class="nav-premium-countdown admin-table-countdown" data-premium-until-ts="<?= (int) $row['until_ts'] ?>">
                      <span data-premium-countdown><?= casting_e($row['remaining']) ?></span>
                    </span>
                    <span class="meta"><?= casting_e($row['until']) ?></span>
                  <?php else : ?>
                    <span class="chip chip-premium">ویژه</span>
                  <?php endif; ?>
                <?php else : ?>
                  —
                <?php endif; ?>
              </td>
              <td>
                <a class="btn btn-primary btn-sm" href="<?= casting_e($member_query((int) $row['id'])) ?>">مدیریت</a>
                <a class="btn btn-ghost btn-sm" href="member.php?id=<?= (int) $row['id'] ?>">پروفایل</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
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
