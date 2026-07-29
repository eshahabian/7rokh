<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/director-workspace.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_is_director_role($user_id)) {
    casting_set_flash('error', 'این بخش فقط برای کارگردان‌هاست.');
    casting_redirect('panel.php');
}

$favorites = casting_director_list_highlighted_talents($user_id);

casting_render_panel_start('لیست کاندیدا', 'favorites');
casting_render_flash();
?>
<section class="dash-card">
  <h1>لیست کاندیدا</h1>
  <p class="meta">کاربرانی که از جستجو یا پیش‌نمایش پروفایل به لیست کاندیدا اضافه کرده‌اید.</p>
  <?php if ($favorites === []) : ?>
    <p class="empty-state">هنوز کسی را به لیست کاندیدا اضافه نکرده‌اید. از <a href="search-users.php">جستجوی کاربران</a> پروفایل را باز کنید و «لیست کاندیدا» را بزنید.</p>
  <?php else : ?>
    <div class="member-grid">
      <?php foreach ($favorites as $fav) :
          $member = get_user_by('id', (int) $fav['talent_id']);
          if (!$member) {
              continue;
          }
          casting_render_member_card($member, $user_id, ['viewed' => true, 'is_highlight' => true]);
      endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
