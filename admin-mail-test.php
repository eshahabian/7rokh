<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_is_super_admin($user_id)) {
    wp_die('فقط مدیر اصلی به این بخش دسترسی دارد.', 'دسترسی غیرمجاز', ['response' => 403]);
}

casting_nocache();

$error = '';
$success = '';
$test_to = (string) $user->user_email;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_mail_test')) {
        $error = 'درخواست نامعتبر است.';
    } elseif (isset($_POST['clear_rate_limits'])) {
        casting_rate_limit_clear_all();
        $success = 'محدودیت درخواست برای IP فعلی (' . casting_client_ip() . ') پاک شد.';
    } elseif (isset($_POST['save_smtp_pass'])) {
        $result = casting_smtp_store_password((string) ($_POST['smtp_pass'] ?? ''));
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            $success = 'رمز SMTP ذخیره شد. حالا یک ایمیل تست بفرستید.';
        }
    } else {
        $test_to = sanitize_email((string) ($_POST['test_to'] ?? ''));
        if (!is_email($test_to)) {
            $error = 'آدرس ایمیل گیرنده معتبر نیست.';
        } else {
            $subject = sprintf('[%s] تست SMTP پورتال', casting_brand());
            $body = "این یک ایمیل تست از پورتال " . casting_brand() . " است.\n"
                . 'زمان: ' . current_time('mysql') . "\n"
                . 'فرستنده: ' . casting_mail_from_address() . "\n";
            $result = casting_send_mail($test_to, $subject, $body);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                $success = 'ایمیل تست به ' . $test_to . ' ارسال شد. اگر نرسید، Spam را هم چک کنید.';
            }
        }
    }
}

$status = casting_mail_status();

casting_render_panel_start('تست ایمیل SMTP', 'admin-mail');
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>تست ایمیل SMTP</h1>
  <p class="lede">تنظیمات ارسال ایمیل پورتال (بازیابی رمز، تماس با ما و …). این بخش ربطی به تنظیمات وردپرس اصلی ندارد.</p>

  <dl class="admin-mail-status">
    <dt>فایل config.local.php روی سرور</dt>
    <dd><?= $status['local_config'] ? '✓ موجود' : '✗ نیست — باید دستی روی سرور بسازید' ?></dd>
    <dt>رمز SMTP خوانده شد</dt>
    <dd><?= !empty($status['pass_loaded']) ? '✓ بله' : '✗ خیر' ?></dd>
    <dt>SMTP آماده</dt>
    <dd><?= $status['smtp_ready'] ? '✓ بله' : '✗ خیر — رمز را در فرم زیر ذخیره کنید' ?></dd>
    <dt>Host</dt>
    <dd><code><?= casting_e($status['host']) ?></code></dd>
    <dt>Port / Secure</dt>
    <dd><code><?= (int) $status['port'] ?></code> / <code><?= casting_e($status['secure']) ?></code></dd>
    <dt>User / From</dt>
    <dd><code><?= casting_e($status['user']) ?></code></dd>
  </dl>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error admin-mail-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>
  <?php if ($success !== '') : ?>
    <div class="flash flash-success" role="alert"><?= casting_e($success) ?></div>
  <?php endif; ?>

  <h2 class="panel-section-title">رمز SMTP</h2>
  <p class="meta">رمز اکانت <code>noreply@7rokh.ir</code> در cPanel را اینجا بگذارید (همان رمزی که برای ورود به وب‌میل عوض کردید). لازم نیست فایل سرور را دستی ویرایش کنید.</p>
  <form class="form" method="post" action="admin-mail-test.php" autocomplete="off">
    <?php wp_nonce_field('casting_mail_test'); ?>
    <input type="hidden" name="save_smtp_pass" value="1">
    <div class="field">
      <label for="smtp_pass">رمز noreply@7rokh.ir</label>
      <input id="smtp_pass" name="smtp_pass" type="password" required autocomplete="new-password" placeholder="<?= !empty($status['pass_loaded']) ? 'رمز ذخیره‌شده — برای تغییر، رمز جدید را بنویسید' : 'رمز ایمیل را وارد کنید' ?>">
    </div>
    <button class="btn btn-primary" type="submit">ذخیره رمز SMTP</button>
  </form>

  <h2 class="panel-section-title">ارسال تست</h2>
  <form class="form admin-mail-test-form" method="post" action="admin-mail-test.php">
    <?php wp_nonce_field('casting_mail_test'); ?>
    <div class="field">
      <label for="test_to">ارسال ایمیل تست به</label>
      <input id="test_to" name="test_to" type="email" required value="<?= casting_e($test_to) ?>">
    </div>
    <button class="btn btn-primary" type="submit"<?= $status['smtp_ready'] ? '' : ' disabled' ?>>ارسال تست</button>
  </form>

  <h2 class="panel-section-title">محدودیت درخواست (rate limit)</h2>
  <p class="meta">اگر «تعداد درخواست زیاد بود» می‌بینید (مثلاً در فراموشی رمز)، این دکمه محدودیت IP فعلی را پاک می‌کند.</p>
  <form class="form" method="post" action="admin-mail-test.php">
    <?php wp_nonce_field('casting_mail_test'); ?>
    <input type="hidden" name="clear_rate_limits" value="1">
    <button class="btn btn-secondary" type="submit">ریست محدودیت IP من</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
