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
$filter_user = (int) ($_GET['user'] ?? 0);
$filter_user_obj = $filter_user > 0 ? get_user_by('id', $filter_user) : false;
$blocks = casting_list_all_user_blocks(500);
if ($filter_user > 0) {
    $blocks = array_values(array_filter($blocks, static function (array $row) use ($filter_user): bool {
        return $row['blocker_id'] === $filter_user || $row['target_id'] === $filter_user;
    }));
}
$history = casting_list_block_history(500, $filter_user);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_unblock) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_admin_blocks')) {
        casting_set_flash('error', 'درخواست نامعتبر است.');
    } else {
        $blocker_id = (int) ($_POST['blocker_id'] ?? 0);
        $blocked_id = (int) ($_POST['blocked_id'] ?? 0);
        $result = casting_admin_force_unblock($blocker_id, $blocked_id, $user_id);
        casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'بلاک برداشته شد.' : $result['error']);
    }
    $redirect = 'admin-blocks.php';
    if ($filter_user > 0) {
        $redirect .= '?user=' . $filter_user;
    }
    casting_redirect($redirect);
}

casting_render_panel_start('بلاک‌های کاربران', 'admin-blocks');
casting_render_flash();
?>
<section class="dash-card">
  <h1>بلاک‌های فعال</h1>
  <?php if ($filter_user_obj) : ?>
    <p class="meta">فیلتر: <?= casting_e($filter_user_obj->display_name) ?> — <a href="admin-blocks.php">نمایش همه</a></p>
  <?php else : ?>
    <p class="meta">کاربرانی که الان بلاک هستند.</p>
  <?php endif; ?>

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
            <th>تاریخ بلاک</th>
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
                  <form method="post" action="admin-blocks.php<?= $filter_user > 0 ? '?user=' . $filter_user : '' ?>">
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

<section class="dash-card admin-block-history">
  <h2 class="panel-section-title">تاریخچه بلاک‌ها</h2>
  <p class="meta">سابقه بلاک کردن و بلاک شدن کاربران — شامل مواردی که رفع بلاک شده‌اند.</p>

  <?php if (!$history) : ?>
    <p class="empty-state">هنوز رویداد بلاکی در تاریخچه ثبت نشده است. از این به بعد هر بلاک و رفع بلاک اینجا ذخیره می‌شود.</p>
  <?php else : ?>
    <div class="admin-table-wrap">
      <table class="admin-table admin-blocks-table admin-block-history-table">
        <thead>
          <tr>
            <th>عمل</th>
            <th>بلاک‌کننده</th>
            <th>بلاک‌شده</th>
            <th>علت بلاک</th>
            <th>تاریخ بلاک</th>
            <th>تاریخ رویداد</th>
            <th>انجام‌دهنده</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $row) : ?>
            <tr>
              <td>
                <?php if ($row['action'] === 'block') : ?>
                  <span class="chip chip-danger">بلاک</span>
                  <?php if ($row['active']) : ?>
                    <span class="chip chip-active">فعال</span>
                  <?php endif; ?>
                <?php else : ?>
                  <span class="chip">رفع بلاک</span>
                <?php endif; ?>
              </td>
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
              <td><?= casting_e($row['at'] !== '' ? $row['at'] : '—') ?></td>
              <td>
                <?php if ($row['action'] === 'unblock' && $row['admin_id'] > 0) : ?>
                  مدیر: <?= casting_e($row['admin_name'] !== '' ? $row['admin_name'] : '—') ?>
                <?php elseif ($row['action'] === 'unblock') : ?>
                  خود کاربر
                <?php else : ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
