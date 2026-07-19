<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
casting_require_admin_permission('view_user_blocks');

$can_unblock = casting_user_has_admin_permission($user_id, 'unblock_users');
$blocks = casting_list_all_user_blocks(500);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_unblock) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_admin_blocks')) {
        casting_set_flash('error', 'درخواست نامعتبر است.');
    } else {
        $blocker_id = (int) ($_POST['blocker_id'] ?? 0);
        $blocked_id = (int) ($_POST['blocked_id'] ?? 0);
        $result = casting_admin_force_unblock($blocker_id, $blocked_id, $user_id);
        casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'بلاک برداشته شد.' : $result['error']);
    }
    casting_redirect('admin-blocks.php');
}

casting_render_panel_start('بلاک‌های کاربران', 'admin-blocks');
casting_render_flash();
?>
<section class="dash-card">
  <h1>بلاک‌های کاربران</h1>
  <p class="meta">فهرست اینکه چه کاربری چه کسی را بلاک کرده و علت آن.</p>

  <?php if (!$blocks) : ?>
    <p class="empty-state">بلاک فعالی ثبت نشده است.</p>
  <?php else : ?>
    <div class="admin-table-wrap">
      <table class="admin-table admin-blocks-table">
        <thead>
          <tr>
            <th>بلاک‌کننده</th>
            <th>بلاک‌شده</th>
            <th>علت</th>
            <th>تاریخ</th>
            <?php if ($can_unblock) : ?><th></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($blocks as $row) : ?>
            <tr>
              <td>
                <strong><?= casting_e($row['blocker_name']) ?></strong>
                <span class="meta">@<?= casting_e($row['blocker_login']) ?></span>
              </td>
              <td>
                <strong><?= casting_e($row['target_name']) ?></strong>
                <span class="meta">@<?= casting_e($row['target_login']) ?></span>
              </td>
              <td><?= $row['reason'] !== '' ? nl2br(casting_e($row['reason'])) : '—' ?></td>
              <td><?= casting_e($row['blocked_at'] !== '' ? $row['blocked_at'] : '—') ?></td>
              <?php if ($can_unblock) : ?>
                <td>
                  <form method="post" action="admin-blocks.php">
                    <?php wp_nonce_field('casting_admin_blocks'); ?>
                    <input type="hidden" name="blocker_id" value="<?= (int) $row['blocker_id'] ?>">
                    <input type="hidden" name="blocked_id" value="<?= (int) $row['target_id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">رفع بلاک</button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
