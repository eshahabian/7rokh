<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_id'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_unblock')) {
        casting_set_flash('error', 'درخواست نامعتبر است.');
    } else {
        casting_unblock_user($user_id, (int) $_POST['unblock_id']);
        casting_set_flash('success', 'بلاک برداشته شد.');
    }
    casting_redirect('blocked-by-me.php');
}

$blocked = casting_blocked_by_me($user_id);

casting_render_panel_start('بلاک‌شده‌های من', 'blocked');
casting_render_flash();
?>
<section class="dash-card">
  <h1>بلاک‌شده‌های من</h1>
  <p class="meta">کاربرانی که بلاک کرده‌اید نمی‌توانند به شما پیام بدهند.</p>
  <?php if (!$blocked) : ?>
    <p class="empty-state">کسی را بلاک نکرده‌اید.</p>
  <?php else : ?>
    <ul class="panel-list">
      <?php foreach ($blocked as $row) : ?>
        <li class="panel-list-item panel-list-item-block">
          <div>
            <strong><?= casting_e($row['name']) ?></strong>
            <span class="meta"><?= casting_e(casting_role_label($row['role'])) ?></span>
            <?php if (($row['reason'] ?? '') !== '') : ?>
              <p class="meta block-reason-user">علت: <?= casting_e($row['reason']) ?></p>
            <?php endif; ?>
          </div>
          <form method="post" action="blocked-by-me.php">
            <?php wp_nonce_field('casting_unblock'); ?>
            <input type="hidden" name="unblock_id" value="<?= (int) $row['id'] ?>">
            <button class="btn btn-ghost btn-sm" type="submit">رفع بلاک</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
