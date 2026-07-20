<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contact-messages.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$channels = casting_contact_channel_labels();
$recipient_channels = casting_contact_channels_for_recipient($user_id);
$error = '';
$active_channel = sanitize_key((string) ($_GET['to'] ?? 'site_admin'));
if (!array_key_exists($active_channel, $channels)) {
    $active_channel = 'site_admin';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_contact')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
    } else {
        $action = (string) ($_POST['action'] ?? 'send');

        if ($action === 'mark_read') {
            $message_id = (string) ($_POST['message_id'] ?? '');
            if (casting_contact_mark_read_for_recipient($message_id, $user_id)) {
                casting_set_flash('success', 'پیام خوانده شد.');
            } else {
                casting_set_flash('error', 'پیام پیدا نشد.');
            }
            casting_redirect('contact.php');
        }

        $channel = sanitize_key((string) ($_POST['channel'] ?? ''));
        $subject = sanitize_text_field((string) ($_POST['subject'] ?? ''));
        $message = sanitize_textarea_field((string) ($_POST['message'] ?? ''));
        $result = casting_contact_send_panel_message($user_id, $channel, $subject, $message);

        if (!$result['ok']) {
            $error = $result['error'];
            $active_channel = array_key_exists($channel, $channels) ? $channel : $active_channel;
        } else {
            casting_set_flash('success', 'پیام شما ثبت شد. به‌زودی پاسخ می‌دهیم.');
            casting_redirect('contact.php?to=' . rawurlencode($channel));
        }
    }
}

$inboxes = [];
foreach ($recipient_channels as $channel) {
    $inboxes[$channel] = casting_contact_list_for_recipient($user_id, $channel, 200);
}

casting_render_panel_start('تماس با ما', 'contact');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>تماس با ما</h1>
  <p class="lede">برای پشتیبانی، پیشنهاد یا سوال درباره پورتال <?= casting_e(casting_brand()) ?> پیام بگذارید. پیام‌ها داخل پنل کاربری ذخیره می‌شوند.</p>

  <?php if ($recipient_channels !== []) : ?>
    <?php foreach ($recipient_channels as $channel) :
        $rows = $inboxes[$channel] ?? [];
        $unread = count(array_filter($rows, static fn(array $row): bool => !$row['read']));
        ?>
      <div class="contact-inbox-block">
        <h2 class="panel-section-title">
          پیام‌های دریافتی — <?= casting_e($channels[$channel]) ?>
          <?php if ($unread > 0) : ?><span class="chip chip-active"><?= (int) $unread ?> جدید</span><?php endif; ?>
        </h2>
        <?php if ($rows === []) : ?>
          <p class="empty-state">هنوز پیامی دریافت نشده است.</p>
        <?php else : ?>
          <ul class="panel-list admin-contact-list">
            <?php foreach ($rows as $row) : ?>
              <li class="panel-list-item admin-contact-item <?= $row['read'] ? '' : 'is-unread' ?>">
                <div class="admin-contact-body">
                  <div class="admin-contact-head">
                    <strong><?= casting_e($row['name']) ?></strong>
                    <?php if ($row['sender_login'] !== '') : ?>
                      <span class="meta">@<?= casting_e($row['sender_login']) ?></span>
                    <?php endif; ?>
                    <?php if (!$row['read']) : ?><span class="chip chip-active">جدید</span><?php endif; ?>
                  </div>
                  <p class="admin-contact-subject"><strong><?= casting_e($row['subject']) ?></strong></p>
                  <p class="admin-contact-message"><?= nl2br(casting_e($row['message'])) ?></p>
                  <span class="meta"><?= casting_e($row['at']) ?></span>
                </div>
                <?php if (!$row['read']) : ?>
                  <form method="post" action="contact.php">
                    <?php wp_nonce_field('casting_contact'); ?>
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="message_id" value="<?= casting_e($row['id']) ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">علامت خوانده</button>
                  </form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="contact-send-grid">
    <?php foreach ($channels as $channel => $label) :
        if (in_array($channel, $recipient_channels, true)) {
            continue;
        }
        $recipient_login = casting_contact_recipient_login($channel);
        $is_active = $active_channel === $channel;
        ?>
      <section class="contact-send-card<?= $is_active ? ' is-active' : '' ?>">
        <h2 class="panel-section-title"><?= casting_e($label) ?></h2>
        <p class="meta">پیام شما برای <?= casting_e($label) ?> (<?= casting_e($recipient_login !== '' ? '@' . $recipient_login : '—') ?>) در پنل کاربری ثبت می‌شود.</p>
        <form class="form" method="post" action="contact.php">
          <?php wp_nonce_field('casting_contact'); ?>
          <input type="hidden" name="action" value="send">
          <input type="hidden" name="channel" value="<?= casting_e($channel) ?>">
          <div class="field">
            <label for="subject-<?= casting_e($channel) ?>">موضوع</label>
            <input id="subject-<?= casting_e($channel) ?>" name="subject" type="text" required value="<?= $is_active ? casting_e((string) ($_POST['subject'] ?? '')) : '' ?>">
          </div>
          <div class="field">
            <label for="message-<?= casting_e($channel) ?>">پیام</label>
            <textarea id="message-<?= casting_e($channel) ?>" name="message" rows="5" required maxlength="3000"><?= $is_active ? casting_e((string) ($_POST['message'] ?? '')) : '' ?></textarea>
          </div>
          <button class="btn btn-primary" type="submit">ارسال پیام</button>
        </form>
      </section>
    <?php endforeach; ?>
  </div>
</section>
<?php casting_render_panel_end(); ?>
