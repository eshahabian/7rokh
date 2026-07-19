<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

casting_nocache();

$user = casting_current_user();
$logged_in = $user && casting_get_user_role((int) $user->ID) !== '';
if ($logged_in) {
    require_once __DIR__ . '/includes/panel.php';
} else {
    require_once __DIR__ . '/includes/layout.php';
}

$error = '';
$success = '';
$name = '';
$email = '';
$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_contact')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
    } else {
        $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $email = sanitize_email((string) ($_POST['email'] ?? ''));
        $subject = sanitize_text_field((string) ($_POST['subject'] ?? ''));
        $message = sanitize_textarea_field((string) ($_POST['message'] ?? ''));

        if (casting_strlen($name) < 2) {
            $error = 'نام را وارد کنید.';
        } elseif (!is_email($email)) {
            $error = 'ایمیل معتبر نیست.';
        } elseif (casting_strlen($subject) < 2) {
            $error = 'موضوع را وارد کنید.';
        } elseif (casting_strlen($message) < 10) {
            $error = 'متن پیام خیلی کوتاه است.';
        } elseif (casting_strlen($message) > 3000) {
            $error = 'متن پیام خیلی بلند است.';
        } else {
            $result = casting_send_contact_message($name, $email, $subject, $message);
            if (!$result['ok']) {
                $error = 'ارسال پیام ناموفق بود.' . casting_mail_setup_hint();
                if ($result['error'] !== '' && casting_mail_is_smtp_ready()) {
                    $error .= ' (' . $result['error'] . ')';
                }
            } else {
                $success = 'پیام شما ارسال شد. به‌زودی پاسخ می‌دهیم.';
                $name = $email = $subject = $message = '';
            }
        }
    }
}

if ($logged_in) {
    casting_render_panel_start('تماس با ما', 'contact');
} else {
    casting_render_head('تماس با ما', 'page-contact');
    casting_render_header('contact');
}
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<?php if (!$logged_in) : ?><main class="wrap panel-page"><?php endif; ?>
  <section class="<?= $logged_in ? 'dash-card panel-wide' : 'panel panel-wide' ?>">
    <h1>تماس با ما</h1>
    <p class="lede">برای پشتیبانی، پیشنهاد یا سوال درباره پورتال <?= casting_e(casting_brand()) ?> پیام بگذارید.</p>

    <form class="form" method="post" action="contact.php">
      <?php wp_nonce_field('casting_contact'); ?>
      <div class="form-grid">
        <div class="field">
          <label for="name">نام</label>
          <input id="name" name="name" type="text" required value="<?= casting_e($name) ?>">
        </div>
        <div class="field">
          <label for="email">ایمیل</label>
          <input id="email" name="email" type="email" required value="<?= casting_e($email) ?>">
        </div>
      </div>
      <div class="field">
        <label for="subject">موضوع</label>
        <input id="subject" name="subject" type="text" required value="<?= casting_e($subject) ?>">
      </div>
      <div class="field">
        <label for="message">پیام</label>
        <textarea id="message" name="message" rows="6" required maxlength="3000"><?= casting_e($message) ?></textarea>
      </div>
      <button class="btn btn-primary" type="submit">ارسال پیام</button>
    </form>
  </section>
<?php if ($logged_in) : ?>
<?php casting_render_panel_end(); ?>
<?php else : ?>
</main>
<?php casting_render_footer(); ?>
<?php endif; ?>
