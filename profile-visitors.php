<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/visitors.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$visitors = casting_profile_visitors($user_id);

casting_render_panel_start('بازدیدکنندگان پروفایل', 'visitors');
casting_render_flash();
?>
<section class="dash-card">
  <h1>بازدیدکنندگان پروفایل من</h1>
  <p class="meta">آخرین بازدیدها از پروفایل شما</p>
  <?php if (!$visitors) : ?>
    <p class="empty-state">هنوز بازدیدی ثبت نشده است.</p>
  <?php else : ?>
    <ul class="panel-list">
      <?php foreach ($visitors as $row) : ?>
        <li class="panel-list-item">
          <div>
            <strong><a href="<?= casting_e(casting_panel_profile_url((int) $row['visitor_id'])) ?>"><?= casting_e($row['name']) ?></a></strong>
            <span class="meta"><?= casting_e(casting_role_label($row['role'])) ?></span>
          </div>
          <time><?= casting_e($row['visited_at']) ?></time>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
