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

$status = casting_mail_status();
$guard = function_exists('casting_guard_status') ? casting_guard_status() : [
    'active' => function_exists('casting_guard_block_portal_member_on_wp'),
    'mu_file' => false,
    'loader_file' => false,
    'owner' => casting_portal_owner_login(),
];
$error = '';
$success = '';
$test_to = (string) $user->user_email;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_mail_test')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $test_to = sanitize_email((string) ($_POST['test_to'] ?? ''));
        if (!is_email($test_to)) {
            $error = 'آدرس ایمیل گیرنده معتبر نیست.';
        } else {
            $subject = sprintf('[%s] تست SMTP پورتال', casting_brand());
            $body = "این یک ایمیل تست از پورتال " . casting_brand() . " است.\n"
                . 'زمان: ' . current_time('mysql') . "\n"
                . 'فرستنده: ' . $status['from'] . "\n";
            $result = casting_send_mail($test_to, $subject, $body);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                $success = 'ایمیل تست به ' . $test_to . ' ارسال شد. اگر نرسید، Spam را هم چک کنید.';
            }
        }
    }
}

casting_render_panel_start('تست ایمیل SMTP', 'admin-mail');
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>تست ایمیل SMTP</h1>
  <p class="lede">تنظیمات ارسال ایمیل پورتال (بازیابی رمز، تماس با ما و …). این بخش ربطی به تنظیمات وردپرس اصلی ندارد.</p>

  <dl class="admin-mail-status">
    <dt>فایل config.local.php روی سرور</dt>
    <dd><?= $status['local_config'] ? '✓ موجود' : '✗ نیست — باید دستی روی سرور بسازید' ?></dd>
    <dt>SMTP آماده</dt>
    <dd><?= $status['smtp_ready'] ? '✓ بله' : '✗ خیر' ?></dd>
    <dt>رمز SMTP خوانده شده</dt>
    <dd><?= !empty($status['pass_set']) ? '✓ بله' : '✗ خیر — config.local.php را چک کنید' ?></dd>
    <dt>Host</dt>
    <dd><code><?= casting_e($status['host']) ?></code></dd>
    <dt>Port / Secure</dt>
    <dd><code><?= (int) $status['port'] ?></code> / <code><?= casting_e($status['secure']) ?></code></dd>
    <dt>User / From</dt>
    <dd><code><?= casting_e($status['user']) ?></code></dd>
  </dl>

  <?php if (!$status['local_config']) : ?>
    <div class="flash flash-error admin-mail-error" role="alert">
      <p><strong>علت احتمالی خطا:</strong> فایل <code>config.local.php</code> روی سرور وجود ندارد.</p>
      <p>این فایل در git نیست و با deploy خودکار کپی نمی‌شود. در cPanel → File Manager مسیر <code>public_html/casting-portal/</code> فایل بسازید:</p>
      <pre class="code-block">&lt;?php
define('CASTING_SMTP_PASS', 'رمز-noreply-در-cPanel');</pre>
    </div>
  <?php elseif (!$status['smtp_ready']) : ?>
    <div class="flash flash-error admin-mail-error" role="alert">
      <p><strong>علت احتمالی خطا:</strong> <code>CASTING_SMTP_PASS</code> در config.local.php خالی است یا فایل درست load نشده.</p>
    </div>
  <?php endif; ?>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error admin-mail-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>
  <?php if ($success !== '') : ?>
    <div class="flash flash-success" role="alert"><?= casting_e($success) ?></div>
  <?php endif; ?>

  <form class="form admin-mail-test-form" method="post" action="admin-mail-test.php">
    <?php wp_nonce_field('casting_mail_test'); ?>
    <div class="field">
      <label for="test_to">ارسال ایمیل تست به</label>
      <input id="test_to" name="test_to" type="email" required value="<?= casting_e($test_to) ?>">
    </div>
    <button class="btn btn-primary" type="submit"<?= $status['smtp_ready'] ? '' : ' disabled' ?>>ارسال تست</button>
  </form>

  <p class="meta">رمز باید همان رمز اکانت <code>noreply@7rokh.ir</code> در cPanel باشد (نه رمز ایمیل قدیمی contact.us).</p>

  <h2 class="panel-section-title">جداسازی ورود (mu-plugin)</h2>
  <dl class="admin-mail-status">
    <dt>Guard فعال در وردپرس</dt>
    <dd><?= !empty($guard['active']) ? '✓ بله' : '✗ خیر — فایل mu-plugin نصب نشده' ?></dd>
    <dt>فایل mu-plugins/casting-wp-admin-guard.php</dt>
    <dd><?= !empty($guard['mu_file']) ? '✓ موجود' : '✗ نیست — deploy کنید یا دستی کپی کنید' ?></dd>
    <dt>استثنا (می‌تواند wp-login بزند)</dt>
    <dd><code><?= casting_e((string) ($guard['owner'] ?? '')) ?></code></dd>
  </dl>
  <?php if (empty($guard['mu_file'])) : ?>
    <div class="flash flash-error admin-mail-error" role="alert">
      <p>برای مسدود کردن wp-login اعضای پورتال، این فایل باید روی سرور باشد:</p>
      <pre class="code-block">public_html/wp-content/mu-plugins/casting-wp-admin-guard.php</pre>
      <p class="meta">منبع: <code>casting-portal/mu-plugin/casting-wp-admin-guard.php</code></p>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
