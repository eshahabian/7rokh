<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contact-messages.php';

casting_nocache();

$user = casting_current_user();
$logged_in = $user && (
    casting_get_user_role((int) $user->ID) !== ''
    || (function_exists('casting_user_can_use_member_portal') && casting_user_can_use_member_portal((int) $user->ID))
);
$user_id = $logged_in ? (int) $user->ID : 0;

if ($logged_in) {
    require_once __DIR__ . '/includes/panel.php';
} else {
    require_once __DIR__ . '/includes/layout.php';
}

$channels = casting_contact_channel_labels();
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
            if (casting_contact_mark_thread_read($message_id, $user_id) || casting_contact_mark_read_for_recipient($message_id, $user_id)) {
                casting_set_flash('success', 'پیام خوانده شد.');
            } else {
                casting_set_flash('error', 'پیام پیدا نشد.');
            }
            casting_redirect('contact.php#contact-inbox');
        }

        if ($action === 'reply' && $logged_in) {
            $message_id = (string) ($_POST['message_id'] ?? '');
            $reply = (string) ($_POST['reply'] ?? '');
            $result = casting_contact_reply($message_id, $user_id, $reply);
            if ($result['ok']) {
                $note = $result['via'] === 'email'
                    ? 'پاسخ در صندوق تماس با ما ثبت شد و با ایمیل هم ارسال شد.'
                    : 'پاسخ در صندوق تماس با ما ثبت شد.';
                casting_set_flash('success', $note);
            } else {
                casting_set_flash('error', $result['error']);
            }
            casting_redirect('contact.php#contact-inbox');
        }

        if ($action === 'send') {
            $rate_error = casting_rate_limit_check('contact_send');
            if ($rate_error !== null) {
                $error = $rate_error;
            } else {
            casting_rate_limit_hit('contact_send');
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
}

$can_manage_inbox = $logged_in && casting_contact_user_can_manage_inbox($user_id);
if ($can_manage_inbox) {
    $purged = casting_contact_maybe_purge_except_keep_login();
    if ($purged > 0) {
        casting_set_flash('success', 'صندوق تماس با ما خلوت شد؛ فقط پیام‌های mn_niky باقی ماند.');
        casting_redirect('contact.php#contact-inbox');
    }
}
$inbox_rows = $can_manage_inbox ? casting_contact_list_for_manager($user_id, 200) : [];
$inbox_threads = $can_manage_inbox ? casting_contact_group_threads($inbox_rows) : [];
$my_contact_rows = ($logged_in && !$can_manage_inbox) ? casting_contact_list_for_sender($user_id, 50) : [];
$my_contact_threads = $my_contact_rows !== [] ? casting_contact_group_threads($my_contact_rows) : [];

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
    <p class="lede">برای پشتیبانی، پیشنهاد یا سوال درباره پورتال <?= casting_brand_html() ?> از همین صفحه به مدیران سایت پیام بگذارید. این پیام‌ها فقط در تماس با ما ثبت می‌شوند و با پیام‌رسان قاطی نمی‌شوند. بدون عضویت ویژه هم می‌توانید به هر دو مدیر (eshahabian و ardavan) پیام بدهید.</p>

    <?php if ($can_manage_inbox) :
        $unread = count(array_filter($inbox_rows, static fn(array $row): bool => !$row['read']));
        ?>
      <div id="contact-inbox" class="contact-inbox-block">
        <h2 class="panel-section-title">
          پاسخگویی به پیام‌های تماس با ما
          <?php if ($unread > 0) : ?><span class="chip chip-active"><?= (int) $unread ?> جدید</span><?php endif; ?>
        </h2>
        <p class="meta">فقط پیام‌های فرم تماس با ما. گفتگو مثل واتساپ جدا می‌شود؛ پیام‌رسان جداست.</p>
        <?php if ($inbox_threads === []) : ?>
          <p class="empty-state">هنوز پیامی دریافت نشده است.</p>
        <?php else : ?>
          <?php foreach ($inbox_threads as $thread) : ?>
            <?php casting_render_contact_chat_thread($thread, $channels, true); ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($my_contact_threads !== []) : ?>
      <div class="contact-inbox-block">
        <h2 class="panel-section-title">پیام‌های من در تماس با ما</h2>
        <p class="meta">پاسخ مدیران را همین‌جا می‌بینید. این بخش جدا از پیام‌رسان است.</p>
        <?php foreach ($my_contact_threads as $thread) : ?>
          <?php casting_render_contact_chat_thread($thread, $channels, false); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php casting_render_contact_send_form($form_state); ?>
  </section>
<?php if ($logged_in) : ?>
<?php casting_render_panel_end(); ?>
<?php else : ?>
</main>
<?php casting_render_footer(); ?>
<?php endif; ?>
