<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/follows.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$tab = ((string) ($_GET['tab'] ?? 'following')) === 'followers' ? 'followers' : 'following';

$ids = $tab === 'followers'
    ? casting_list_follower_ids($user_id, 100)
    : casting_list_following_ids($user_id, 100);

casting_render_panel_start($tab === 'followers' ? 'دنبال‌کننده‌ها' : 'دنبال‌شده‌ها', 'following');
casting_render_flash();
?>
<section class="dash-card">
  <?php casting_render_panel_heading($tab === 'followers' ? 'دنبال‌کننده‌ها' : 'دنبال‌شده‌ها'); ?>
  <div class="admin-tabs" role="tablist">
    <a class="admin-tab<?= $tab === 'following' ? ' is-active' : '' ?>" href="following.php?tab=following">دنبال‌شده‌ها (<?= (int) casting_following_count($user_id) ?>)</a>
    <a class="admin-tab<?= $tab === 'followers' ? ' is-active' : '' ?>" href="following.php?tab=followers">دنبال‌کننده‌ها (<?= (int) casting_followers_count($user_id) ?>)</a>
  </div>

  <?php if ($ids === []) : ?>
    <p class="empty-state"><?= $tab === 'followers' ? 'هنوز کسی شما را دنبال نکرده است.' : 'هنوز کسی را دنبال نکرده‌اید.' ?></p>
  <?php else : ?>
    <div class="member-grid">
      <?php foreach ($ids as $id) :
          $member = get_user_by('id', $id);
          if (!$member) {
              continue;
          }
          casting_render_member_card($member, $user_id);
          ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
