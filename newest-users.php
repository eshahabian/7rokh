<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$members = casting_newest_members(30, $user_id);

casting_render_panel_start('جدیدترین کاربران', 'newest');
casting_render_flash();
?>
<section class="dash-card">
  <h1>جدیدترین کاربران</h1>
  <p class="meta"><?= count($members) ?> عضو اخیر</p>
  <?php if (!$members) : ?>
    <p class="empty-state">هنوز کاربری ثبت نشده است.</p>
  <?php else : ?>
    <div class="member-grid">
      <?php foreach ($members as $member) : ?>
        <?php casting_render_member_card($member, $user_id); ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
