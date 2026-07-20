<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contact-messages.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
casting_require_admin_permission('view_contact_messages');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_admin_contact')) {
        casting_set_flash('error', 'درخواست نامعتبر است.');
    } elseif ((string) ($_POST['action'] ?? '') === 'mark_read') {
        $msg_id = (string) ($_POST['message_id'] ?? '');
        casting_contact_mark_read($msg_id);
        casting_set_flash('success', 'پیام خوانده شد.');
    }
    casting_redirect('admin-contact.php');
}

$messages = casting_contact_list_messages(200);

casting_render_panel_start('پیام‌های تماس', 'admin-contact');
casting_render_flash();
?>
<section class="dash-card">
  <h1>پیام‌های تماس با ما</h1>
  <p class="meta">همه پیام‌های فرم تماس اینجا ذخیره می‌شوند — حتی اگر ایمیل ارسال نشود.</p>

  <?php if (!$messages) : ?>
    <p class="empty-state">هنوز پیامی ثبت نشده است.</p>
  <?php else : ?>
    <ul class="panel-list admin-contact-list">
      <?php foreach ($messages as $row) : ?>
        <li class="panel-list-item admin-contact-item <?= $row['read'] ? '' : 'is-unread' ?>">
          <div class="admin-contact-body">
            <div class="admin-contact-head">
              <strong><?= casting_e($row['name']) ?></strong>
              <a class="meta" href="mailto:<?= casting_e($row['email']) ?>"><?= casting_e($row['email']) ?></a>
              <?php if (!$row['read']) : ?><span class="chip chip-active">جدید</span><?php endif; ?>
              <?php if ($row['mail_sent']) : ?><span class="chip">ایمیل شد</span><?php endif; ?>
            </div>
            <p class="admin-contact-subject"><strong><?= casting_e($row['subject']) ?></strong></p>
            <p class="admin-contact-message"><?= nl2br(casting_e($row['message'])) ?></p>
            <span class="meta"><?= casting_e($row['at']) ?></span>
          </div>
          <?php if (!$row['read']) : ?>
            <form method="post" action="admin-contact.php">
              <?php wp_nonce_field('casting_admin_contact'); ?>
              <input type="hidden" name="message_id" value="<?= casting_e($row['id']) ?>">
              <button class="btn btn-ghost btn-sm" type="submit" name="action" value="mark_read">علامت خوانده</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
