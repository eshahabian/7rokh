<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
casting_require_admin_permission('view_premium_users');

$members = casting_list_premium_members();

casting_render_panel_start('مشترکین ویژه', 'admin-premium');
casting_render_flash();
?>
<section class="dash-card">
  <h1>مشترکین ویژه</h1>
  <p class="meta"><?= count($members) ?> کاربر با اشتراک فعال</p>

  <?php if (!$members) : ?>
    <p class="empty-state">فعلاً کاربر ویژه‌ای فعال نیست.</p>
  <?php else : ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>کاربر</th>
            <th>نقش</th>
            <th>باقی‌مانده</th>
            <th>پایان</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $row) : ?>
            <tr>
              <td>
                <strong><?= casting_e($row['name']) ?></strong>
                <span class="meta"><?= casting_e($row['login']) ?></span>
              </td>
              <td><?= casting_e(casting_role_label($row['role'])) ?></td>
              <td>
                <?php if (($row['until_ts'] ?? null) !== null) : ?>
                  <span class="nav-premium-countdown admin-table-countdown" data-premium-until-ts="<?= (int) $row['until_ts'] ?>">
                    <span data-premium-countdown><?= casting_e($row['remaining']) ?></span>
                  </span>
                <?php else : ?>
                  —
                <?php endif; ?>
              </td>
              <td><?= casting_e($row['until']) ?></td>
              <td><a class="btn btn-ghost btn-sm" href="member.php?id=<?= (int) $row['id'] ?>">پروفایل</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
