<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/contact-messages.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
casting_require_admin_permission('view_contact_messages');

$mail_status = casting_mail_status();
$test_result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_admin_contact')) {
        casting_set_flash('error', 'درخواست نامعتبر است.');
        casting_redirect('admin-contact.php');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'mark_read') {
        casting_contact_mark_read((string) ($_POST['message_id'] ?? ''));
        casting_set_flash('success', 'پیام خوانده شد.');
        casting_redirect('admin-contact.php');
    }

    if ($action === 'test_mail') {
        $to = sanitize_email((string) ($_POST['test_email'] ?? $user->user_email));
        if (!is_email($to)) {
            casting_set_flash('error', 'ایمیل تست معتبر نیست.');
        } else {
            $result = casting_send_mail(
                $to,
                '[هفت رخ] تست SMTP',
                "این یک ایمیل تست از پورتال است.\n\nزمان: " . current_time('mysql')
            );
            if ($result['ok']) {
                casting_set_flash('success', 'ایمیل تست به ' . $to . ' ارسال شد. inbox و اسپم را چک کنید.');
            } else {
                casting_set_flash('error', 'ارسال ناموفق: ' . $result['error']);
            }
        }
        casting_redirect('admin-contact.php');
    }
}

$messages = casting_contact_list_messages(200);

casting_render_panel_start('پیام‌های تماس', 'admin-contact');
casting_render_flash();
?>
<section class="dash-card">
  <h1>پیام‌های تماس با ما</h1>
  <p class="meta">همه پیام‌های فرم تماس اینجا ذخیره می‌شوند — حتی اگر ایمیل ارسال نشود.</p>

  <div class="admin-mail-status">
    <h2 class="panel-section-title">وضعیت ایمیل (SMTP)</h2>
    <ul class="info-list">
      <li><strong>config.local.php:</strong> <?= $mail_status['local_config'] ? 'موجود' : 'پیدا نشد — روی هاست بسازید' ?></li>
      <li><strong>SMTP آماده:</strong> <?= $mail_status['smtp_ready'] ? 'بله' : 'خیر (رمز خالی است)' ?></li>
      <li><strong>سرور:</strong> <?= casting_e($mail_status['host']) ?>:<?= (int) $mail_status['port'] ?> (<?= casting_e($mail_status['secure']) ?>)</li>
      <li><strong>فرستنده:</strong> <?= casting_e($mail_status['from']) ?></li>
    </ul>
    <form class="form admin-mail-test-form" method="post" action="admin-contact.php">
      <?php wp_nonce_field('casting_admin_contact'); ?>
      <div class="field">
        <label for="test_email">تست ارسال ایمیل</label>
        <input id="test_email" name="test_email" type="email" value="<?= casting_e((string) $user->user_email) ?>" placeholder="ایمیل دریافت تست">
      </div>
      <button class="btn btn-ghost btn-sm" type="submit" name="action" value="test_mail">ارسال ایمیل تست</button>
    </form>
  </div>

  <?php if (!$messages) : ?>
    <p class="empty-state">هنوز پیامی ثبت نشده است.</p>
  <?php else : ?>
    <h2 class="panel-section-title">پیام‌ها</h2>
    <ul class="panel-list admin-contact-list">
      <?php foreach ($messages as $row) : ?>
        <li class="panel-list-item admin-contact-item <?= $row['read'] ? '' : 'is-unread' ?>">
          <div class="admin-contact-body">
            <div class="admin-contact-head">
              <strong><?= casting_e($row['name']) ?></strong>
              <a class="meta" href="mailto:<?= casting_e($row['email']) ?>"><?= casting_e($row['email']) ?></a>
              <?php if (!$row['read']) : ?><span class="chip chip-active">جدید</span><?php endif; ?>
              <?php if ($row['mail_sent']) : ?><span class="chip">ایمیل شد</span><?php else : ?><span class="chip chip-danger">ایمیل نشد</span><?php endif; ?>
            </div>
            <?php if (!$row['mail_sent'] && ($row['mail_error'] ?? '') !== '') : ?>
              <p class="meta admin-mail-error">خطای ایمیل: <?= casting_e((string) $row['mail_error']) ?></p>
            <?php endif; ?>
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
