<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/contact-messages.php';
require_once __DIR__ . '/includes/layout.php';

$user = casting_current_user();
if ($user) {
    $role = casting_get_user_role((int) $user->ID);
    if ($role !== '') {
        casting_redirect('panel.php');
    }
}

$counts = casting_member_counts();
$error = '';
$name = '';
$email = '';
$subject = '';
$message = '';
$channel = sanitize_key((string) ($_POST['channel'] ?? 'site_admin'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_contact')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'send') {
            $channel = sanitize_key((string) ($_POST['channel'] ?? ''));
            $subject = sanitize_text_field((string) ($_POST['subject'] ?? ''));
            $message = sanitize_textarea_field((string) ($_POST['message'] ?? ''));
            $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
            $email = sanitize_email((string) ($_POST['email'] ?? ''));

            $result = casting_contact_send_message($channel, $subject, $message, 0, $name, $email);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', 'پیام شما ثبت شد. به‌زودی پاسخ می‌دهیم.');
                casting_redirect('index.php#contact');
            }
        }
    }
}

casting_render_head('خانه', 'page-home');
casting_render_header('home');
?>
<main class="wrap hero">
  <div class="hero-copy">
    <p class="hero-lead">هفت رخ - پرتابل ارتباط هنرمندان سینما و تئاتر با پروژه های هنری</p>
    <div class="cta-row hero-cta">
      <a class="btn btn-primary" href="register.php">عضویت</a>
      <a class="btn btn-primary" href="login.php">ورود</a>
    </div>

    <div class="home-stats" aria-label="آمار اعضا">
      <div class="stat-item">
        <strong><?= (int) $counts['talents'] ?></strong>
        <span>هنرمند</span>
      </div>
      <div class="stat-item">
        <strong><?= (int) $counts['employers'] ?></strong>
        <span>کارفرما</span>
      </div>
      <div class="stat-item">
        <strong><?= (int) $counts['total'] ?></strong>
        <span>کل اعضا</span>
      </div>
    </div>

    <section id="contact" class="home-contact">
      <h2>تماس با ما</h2>
      <p class="lede">بدون نیاز به ورود، پیام خود را برای مدیر سایت یا مدیر هفت رخ بفرستید.</p>
      <?php if ($error !== '') : ?>
        <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
      <?php endif; ?>
      <?php casting_render_flash(); ?>
      <?php
      casting_render_contact_send_form([
          'name'      => $name,
          'email'     => $email,
          'subject'   => $subject,
          'message'   => $message,
          'channel'   => $channel,
          'logged_in' => false,
          'user_id'   => 0,
          'action'    => 'index.php',
          'form_id'   => 'home-contact-form',
          'return_to' => 'home',
      ]);
      ?>
    </section>
  </div>
</main>
<?php casting_render_footer(); ?>
