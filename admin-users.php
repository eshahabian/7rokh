<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
if (!casting_user_has_admin_permission($user_id, 'suspend_users') && !casting_user_has_admin_permission($user_id, 'unblock_users')) {
    casting_require_admin_permission('suspend_users');
}

$error = '';
$search = trim((string) ($_GET['q'] ?? ''));
$target_id = (int) ($_GET['user'] ?? 0);
$results = $search !== '' ? casting_admin_search_casting_users($search) : [];
$can_suspend = casting_user_has_admin_permission($user_id, 'suspend_users');
$can_unblock = casting_user_has_admin_permission($user_id, 'unblock_users');

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
$suspended = $target ? casting_user_is_suspended($target_id) : false;
$suspend_reason = $target ? (string) get_user_meta($target_id, 'casting_suspended_reason', true) : '';

casting_render_panel_start('کاربران و تعلیق', 'admin-users');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card">
  <h1>کاربران و تعلیق</h1>
  <p class="meta">جستجو، تعلیق حساب، یا رفع بلاک بین کاربران.</p>

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
        <li><strong>وضعیت:</strong> <?= $suspended ? 'معلق' : 'فعال' ?></li>
        <?php if ($suspended && $suspend_reason !== '') : ?>
          <li><strong>دلیل تعلیق:</strong> <?= casting_e($suspend_reason) ?></li>
        <?php endif; ?>
      </ul>

      <?php if ($can_suspend && !casting_user_is_super_admin($target_id)) : ?>
        <div class="cta-row">
          <?php if ($suspended) : ?>
            <form method="post" action="admin-users.php?user=<?= $target_id ?>">
              <?php wp_nonce_field('casting_admin_users'); ?>
              <input type="hidden" name="target_id" value="<?= $target_id ?>">
              <button class="btn btn-primary" type="submit" name="action" value="unsuspend">رفع تعلیق</button>
            </form>
          <?php else : ?>
            <form class="form admin-suspend-form" method="post" action="admin-users.php?user=<?= $target_id ?>">
              <?php wp_nonce_field('casting_admin_users'); ?>
              <input type="hidden" name="target_id" value="<?= $target_id ?>">
              <div class="field">
                <label for="reason">دلیل تعلیق (اختیاری)</label>
                <textarea id="reason" name="reason" rows="2" maxlength="500"></textarea>
              </div>
              <button class="btn btn-reject" type="submit" name="action" value="suspend">تعلیق کاربر</button>
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
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
