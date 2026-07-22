<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contact-messages.php';

casting_nocache();

$user = casting_current_user();
$logged_in = $user && casting_get_user_role((int) $user->ID) !== '';
$user_id = $logged_in ? (int) $user->ID : 0;

if ($logged_in) {
    require_once __DIR__ . '/includes/panel.php';
} else {
    require_once __DIR__ . '/includes/layout.php';
}

$channels = casting_contact_channel_labels();
$recipient_channels = $logged_in ? casting_contact_channels_for_recipient($user_id) : [];
$error = '';
$name = '';
$email = '';
$subject = '';
$message = '';
$channel = sanitize_key((string) ($_GET['to'] ?? $_POST['channel'] ?? 'site_admin'));

if ($logged_in && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $name = (string) $user->display_name;
    $email = (string) $user->user_email;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_contact')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
    } else {
        $action = (string) ($_POST['action'] ?? 'send');

        if ($action === 'mark_read' && $logged_in) {
            $message_id = (string) ($_POST['message_id'] ?? '');
            if (casting_contact_mark_read_for_recipient($message_id, $user_id)) {
                casting_set_flash('success', 'پیام خوانده شد.');
            } else {
                casting_set_flash('error', 'پیام پیدا نشد.');
            }
            casting_redirect('contact.php');
        }

        if ($action === 'send') {
            $channel = sanitize_key((string) ($_POST['channel'] ?? ''));
            $subject = sanitize_text_field((string) ($_POST['subject'] ?? ''));
            $message = sanitize_textarea_field((string) ($_POST['message'] ?? ''));
            $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
            $email = sanitize_email((string) ($_POST['email'] ?? ''));

            $result = casting_contact_send_message($channel, $subject, $message, $user_id, $name, $email);

            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', 'پیام شما ثبت شد. به‌زودی پاسخ می‌دهیم.');
                casting_redirect('contact.php');
            }
        }
    }
}

$inboxes = [];
foreach ($recipient_channels as $recipient_channel) {
    $inboxes[$recipient_channel] = casting_contact_list_for_recipient($user_id, $recipient_channel, 200);
}

$form_state = [
    'name'      => $name,
    'email'     => $email,
    'subject'   => $subject,
    'message'   => $message,
    'channel'   => $channel,
    'logged_in' => $logged_in,
    'user_id'   => $user_id,
    'action'    => 'contact.php',
    'form_id'   => 'contact-form',
];

if ($logged_in) {
    casting_render_panel_start('تماس با ما', 'contact');
} else {
    casting_render_head('تماس با ما', 'page-contact');
    casting_render_header('contact');
}
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<?php if (!$logged_in) : ?><main class="wrap panel-page"><?php endif; ?>
  <section class="<?= $logged_in ? 'dash-card panel-wide' : 'panel panel-wide' ?>">
    <h1>تماس با ما</h1>
    <p class="lede">برای پشتیبانی، پیشنهاد یا سوال درباره پورتال <?= casting_e(casting_brand()) ?> پیام بگذارید. نیازی به ورود نیست.</p>

    <?php if ($recipient_channels !== []) : ?>
      <?php foreach ($recipient_channels as $recipient_channel) :
          $rows = $inboxes[$recipient_channel] ?? [];
          $unread = count(array_filter($rows, static fn(array $row): bool => !$row['read']));
          ?>
        <div class="contact-inbox-block">
          <h2 class="panel-section-title">
            پیام‌های دریافتی — <?= casting_e($channels[$recipient_channel]) ?>
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
                      <?php elseif ($row['email'] !== '') : ?>
                        <a class="meta" href="mailto:<?= casting_e($row['email']) ?>"><?= casting_e($row['email']) ?></a>
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

    <?php casting_render_contact_send_form($form_state); ?>
  </section>
<?php if ($logged_in) : ?>
<?php casting_render_panel_end(); ?>
<?php else : ?>
</main>
<?php casting_render_footer(); ?>
<?php endif; ?>
