<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$blockers = casting_users_who_blocked_me($user_id);

casting_render_panel_start('بلاک‌کنندگان من', 'blockers');
casting_render_flash();
?>
<section class="dash-card">
  <h1>بلاک‌کنندگان من</h1>
  <p class="meta">این کاربران شما را بلاک کرده‌اند و امکان پیام‌رسانی مستقیم وجود ندارد.</p>
  <?php if (!$blockers) : ?>
    <p class="empty-state">کسی شما را بلاک نکرده است.</p>
  <?php else : ?>
    <ul class="panel-list">
      <?php foreach ($blockers as $row) : ?>
        <li class="panel-list-item">
          <div>
            <strong><?= casting_e($row['name']) ?></strong>
            <span class="meta"><?= casting_e(casting_role_label($row['role'])) ?></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
