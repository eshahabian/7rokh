<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
if (!casting_user_has_admin_permission($user_id, 'suspend_users')
    && !casting_user_has_admin_permission($user_id, 'unblock_users')
    && !casting_user_has_admin_permission($user_id, 'view_user_blocks')) {
    casting_require_admin_permission('suspend_users');
}

$error = '';
$search = trim((string) ($_GET['q'] ?? ''));
$target_id = (int) ($_GET['user'] ?? 0);
$results = $search !== '' ? casting_admin_search_casting_users($search) : [];
$can_suspend = casting_user_has_admin_permission($user_id, 'suspend_users');
$can_unblock = casting_user_has_admin_permission($user_id, 'unblock_users');
$can_view_blocks = casting_user_has_admin_permission($user_id, 'view_user_blocks');
$can_manage_members = casting_user_has_admin_permission($user_id, 'view_premium_users');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_admin_users')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $target_id = (int) ($_POST['target_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'suspend' && $can_suspend) {
            $result = casting_admin_suspend_user($target_id, $user_id, (string) ($_POST['reason'] ?? ''));
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'کاربر معلق شد.' : $result['error']);
        } elseif ($action === 'unsuspend' && $can_suspend) {
            $result = casting_admin_unsuspend_user($target_id, $user_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'تعلیق برداشته شد.' : $result['error']);
        } elseif ($action === 'unblock' && $can_unblock) {
            $blocker_id = (int) ($_POST['blocker_id'] ?? 0);
            $blocked_id = (int) ($_POST['blocked_id'] ?? 0);
            $result = casting_admin_force_unblock($blocker_id, $blocked_id, $user_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'بلاک برداشته شد.' : $result['error']);
        }
        casting_redirect('admin-users.php?user=' . $target_id);
    }
}

$target = $target_id > 0 ? get_user_by('id', $target_id) : false;
$blocks = ($target && $can_unblock) ? casting_admin_user_blocks($target_id) : [];
$block_history = ($target && $can_view_blocks) ? casting_admin_user_block_history($target_id, 100) : [];
$suspended = $target ? casting_user_is_suspended($target_id) : false;
$suspend_reason = $target ? (string) get_user_meta($target_id, 'casting_suspended_reason', true) : '';

casting_render_panel_start('بلاک‌های کاربر', 'admin-users');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card">
  <h1>بلاک‌های کاربر</h1>
  <p class="meta">مدیریت بلاک و تاریخچه. برای لیست اعضا و تعلیق حساب به <a href="admin-premium-users.php">مشترکین</a> بروید.</p>

  <form class="form admin-search-form" method="get" action="admin-users.php">
    <div class="field">
      <label for="q">جستجوی کاربر</label>
      <input id="q" name="q" type="search" value="<?= casting_e($search) ?>" placeholder="نام، ایمیل یا نام کاربری">
    </div>
    <button class="btn btn-primary" type="submit">جستجو</button>
  </form>

  <?php if ($search !== '' && !$results) : ?>
    <p class="empty-state">کاربری پیدا نشد.</p>
  <?php elseif ($results) : ?>
    <ul class="admin-user-pick-list">
      <?php foreach ($results as $row) : ?>
        <li>
          <a href="admin-users.php?user=<?= (int) $row['id'] ?>">
            <strong><?= casting_e($row['name']) ?></strong>
            <span class="meta"><?= casting_e($row['login']) ?></span>
            <?php if ($row['suspended']) : ?><span class="chip chip-danger">معلق</span><?php endif; ?>
            <?php if ($row['premium']) : ?><span class="chip">ویژه</span><?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($target && casting_get_user_role($target_id) !== '') : ?>
    <div class="admin-user-detail">
      <h2 class="panel-section-title"><?= casting_e($target->display_name) ?></h2>
      <ul class="info-list">
        <li><strong>نام کاربری:</strong> <?= casting_e($target->user_login) ?></li>
        <li><strong>ایمیل:</strong> <?= casting_e($target->user_email) ?></li>
        <li><strong>نقش:</strong> <?= casting_e(casting_role_label(casting_get_user_role($target_id))) ?></li>
        <li><strong>وضعیت:</strong> <?= $suspended ? 'غیرفعال' : 'فعال' ?></li>
        <?php if ($suspended && $suspend_reason !== '') : ?>
          <li><strong>دلیل تعلیق:</strong> <?= casting_e($suspend_reason) ?></li>
        <?php endif; ?>
        <?php if (casting_user_is_premium($target_id)) : ?>
          <?php $until_ts = casting_premium_expire_timestamp($target_id); ?>
          <li>
            <strong>اشتراک ویژه:</strong>
            <?php if ($until_ts !== null) : ?>
              <span class="nav-premium-countdown admin-table-countdown" data-premium-until-ts="<?= (int) $until_ts ?>">
                <span data-premium-countdown><?= casting_e(casting_premium_countdown_nav_label($target_id)) ?></span>
              </span>
              — پایان: <?= casting_e(casting_premium_until_label($target_id)) ?>
            <?php else : ?>
              فعال
            <?php endif; ?>
          </li>
        <?php endif; ?>
      </ul>

      <?php if ($can_manage_members) : ?>
        <p class="meta"><a href="admin-premium-users.php?user=<?= $target_id ?>">مدیریت حساب و رمز در بخش مشترکین</a></p>
      <?php endif; ?>

      <?php if ($can_suspend && !casting_user_is_super_admin($target_id)) : ?>
        <div class="cta-row">
          <?php if ($suspended) : ?>
            <form method="post" action="admin-users.php?user=<?= $target_id ?>">
              <?php wp_nonce_field('casting_admin_users'); ?>
              <input type="hidden" name="target_id" value="<?= $target_id ?>">
              <button class="btn btn-primary" type="submit" name="action" value="unsuspend">فعال کردن حساب</button>
            </form>
          <?php else : ?>
            <form class="form admin-suspend-form" method="post" action="admin-users.php?user=<?= $target_id ?>">
              <?php wp_nonce_field('casting_admin_users'); ?>
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

      <?php if ($can_unblock && $blocks) : ?>
        <h3 class="panel-section-title">بلاک‌های مرتبط</h3>
        <ul class="panel-list">
          <?php foreach ($blocks as $block) : ?>
            <li class="panel-list-item panel-list-item-block">
              <div>
                <strong><?= casting_e($block['blocker_name']) ?> → <?= casting_e($block['target_name']) ?></strong>
                <?php if (($block['blocked_at'] ?? '') !== '') : ?>
                  <span class="meta"><?= casting_e($block['blocked_at']) ?></span>
                <?php endif; ?>
                <?php if (($block['reason'] ?? '') !== '') : ?>
                  <p class="meta block-reason-admin">علت: <?= casting_e($block['reason']) ?></p>
                <?php endif; ?>
              </div>
              <form method="post" action="admin-users.php?user=<?= $target_id ?>">
                <?php wp_nonce_field('casting_admin_users'); ?>
                <input type="hidden" name="target_id" value="<?= $target_id ?>">
                <input type="hidden" name="blocker_id" value="<?= (int) $block['blocker_id'] ?>">
                <input type="hidden" name="blocked_id" value="<?= (int) $block['target_id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit" name="action" value="unblock">رفع بلاک</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php elseif ($can_unblock) : ?>
        <p class="meta">بلاک فعالی برای این کاربر نیست.</p>
      <?php endif; ?>

      <?php if ($can_view_blocks && $block_history) : ?>
        <h3 class="panel-section-title">تاریخچه بلاک</h3>
        <p class="meta">بلاک‌هایی که این کاربر انجام داده یا دریافت کرده — شامل موارد رفع‌شده.</p>
        <div class="admin-table-wrap">
          <table class="admin-table admin-blocks-table admin-block-history-table">
            <thead>
              <tr>
                <th>نوع</th>
                <th>طرف مقابل</th>
                <th>عمل</th>
                <th>علت</th>
                <th>تاریخ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($block_history as $entry) : ?>
                <tr>
                  <td>
                    <?php if ($entry['relation'] === 'blocked_other') : ?>
                      <span class="chip chip-danger">بلاک کرد</span>
                    <?php else : ?>
                      <span class="chip">بلاک شد</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($entry['relation'] === 'blocked_other') : ?>
                      <strong><?= casting_e($entry['target_name']) ?></strong>
                    <?php else : ?>
                      <strong><?= casting_e($entry['blocker_name']) ?></strong>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($entry['action'] === 'block') : ?>
                      بلاک
                      <?php if ($entry['active']) : ?><span class="chip chip-active">فعال</span><?php endif; ?>
                    <?php else : ?>
                      رفع بلاک
                      <?php if ($entry['admin_id'] > 0) : ?>
                        <span class="meta">توسط مدیر</span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                  <td><?= $entry['reason'] !== '' ? casting_e($entry['reason']) : '—' ?></td>
                  <td><?= casting_e($entry['at'] !== '' ? $entry['at'] : '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="meta"><a href="admin-blocks.php?user=<?= $target_id ?>">مشاهده همه تاریخچه بلاک‌ها</a></p>
      <?php elseif ($can_view_blocks) : ?>
        <p class="meta">تاریخچه بلاکی برای این کاربر ثبت نشده است.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
