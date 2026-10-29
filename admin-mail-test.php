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
$debug_log = '';
$test_to = (string) $user->user_email;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_mail_test')) {
        $error = 'درخواست نامعتبر است.';
    } elseif (isset($_POST['clear_rate_limits'])) {
        casting_rate_limit_clear_all();
        $success = 'محدودیت درخواست برای IP فعلی (' . casting_client_ip() . ') پاک شد.';
    } elseif (isset($_POST['clear_smtp_pass'])) {
        $result = casting_smtp_clear_stored_password();
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            $success = 'رمز ذخیره‌شده در دیتابیس پاک شد. اگر config.local.php رمز درست داشته باشد، از همان استفاده می‌شود.';
        }
    } elseif (isset($_POST['save_smtp_host'])) {
        $result = casting_smtp_store_host((string) ($_POST['smtp_host'] ?? ''));
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            $success = 'میزبان SMTP ذخیره شد. حالا یک ایمیل تست بفرستید.';
        }
    } elseif (isset($_POST['save_smtp_pass'])) {
        $pass = wp_unslash((string) ($_POST['smtp_pass'] ?? ''));
        $result = casting_smtp_store_password($pass);
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            $success = 'رمز SMTP ذخیره شد (طول ' . casting_strlen(trim($pass)) . '). حالا «تشخیص مستقیم SMTP» یا ارسال تست را بزنید.';
        }
    } elseif (isset($_POST['smtp_diagnose'])) {
        $result = casting_smtp_diagnose();
        $debug_log = (string) ($result['transcript'] ?? '');
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            $w = $result['working'] ?? null;
            $success = 'AUTH موفق شد'
                . (is_array($w) ? (': ' . $w['user'] . ' @ ' . $w['host'] . ':' . $w['port'] . '/' . $w['secure']) : '')
                . '. حالا ارسال تست را بزنید.';
        }
    } else {
        $test_to = sanitize_email((string) ($_POST['test_to'] ?? ''));
        if (!is_email($test_to)) {
            $error = 'آدرس ایمیل گیرنده معتبر نیست.';
        } else {
            $subject = sprintf('[%s] تست SMTP پورتال', casting_brand());
            $body = "این یک ایمیل تست از پورتال " . casting_brand() . " است.\n"
                . 'زمان: ' . current_time('mysql') . "\n"
                . 'فرستنده: ' . casting_mail_from_address() . "\n"
                . 'Host: ' . casting_smtp_host() . "\n";
            $result = casting_send_mail($test_to, $subject, $body, [], true);
            $debug_log = (string) ($result['debug'] ?? '');
            if (!$result['ok']) {
                $error = $result['error'];
                $raw = trim((string) ($result['raw_error'] ?? ''));
                if ($raw !== '' && $raw !== $error) {
                    $error .= ' | خطای خام: ' . $raw;
                }
                // اگر wp_mail شکست خورد، تشخیص مستقیم را هم بچسبان
                $diag = casting_smtp_diagnose();
                $debug_log .= "\n\n===== تشخیص مستقیم =====\n" . (string) ($diag['transcript'] ?? '');
                if (!empty($diag['ok']) && is_array($diag['working'] ?? null)) {
                    $w = $diag['working'];
                    $success = '';
                    $error = 'wp_mail شکست خورد ولی AUTH مستقیم موفق شد ('
                        . $w['user'] . ' @ ' . $w['host'] . ':' . $w['port'] . '/' . $w['secure']
                        . '). تنظیمات ذخیره شد — یک‌بار دیگر ارسال تست را بزنید.';
                }
            } else {
                $success = 'ایمیل تست به ' . $test_to . ' ارسال شد. اگر نرسید، Spam را هم چک کنید.';
            }
        }
    }
}

$status = casting_mail_status();
$pass_source_labels = [
    'option'   => 'دیتابیس (فرم پنل) — این مقدار روی config.local اولویت دارد',
    'constant' => 'config.local.php (لودشده)',
    'file'     => 'config.local.php (خواندن مستقیم فایل)',
];
$pass_source_label = $pass_source_labels[(string) ($status['pass_source'] ?? '')] ?? 'خالی';

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
    <dt>منبع رمز</dt>
    <dd><?= casting_e($pass_source_label) ?></dd>
    <dt>طول رمز خوانده‌شده</dt>
    <dd><code dir="ltr"><?= (int) ($status['pass_len'] ?? 0) ?></code> کاراکتر (خود رمز نمایش داده نمی‌شود)</dd>
    <dt>SMTP آماده</dt>
    <dd><?= $status['smtp_ready'] ? '✓ بله' : '✗ خیر — رمز را در فرم زیر ذخیره کنید' ?></dd>
    <dt>Host</dt>
    <dd><code><?= casting_e($status['host']) ?></code><?php if ($status['host'] !== 'localhost' && $status['host'] !== '127.0.0.1') : ?> — روی سی‌پنل معمولاً باید <code>localhost</code> باشد<?php endif; ?></dd>
    <dt>Port / Secure / Auth</dt>
    <dd><code><?= (int) $status['port'] ?></code> / <code><?= casting_e($status['secure']) ?></code> / <code><?= !empty($status['auth']) ? 'yes' : 'no' ?></code></dd>
    <dt>User / From</dt>
    <dd><code><?= casting_e($status['user']) ?></code></dd>
  </dl>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error admin-mail-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>
  <?php if ($success !== '') : ?>
    <div class="flash flash-success" role="alert"><?= casting_e($success) ?></div>
  <?php endif; ?>
  <?php if ($debug_log !== '') : ?>
    <details class="admin-mail-debug" open>
      <summary>جزئیات ارتباط SMTP (این متن را کپی کنید)</summary>
      <pre dir="ltr"><?= casting_e($debug_log) ?></pre>
    </details>
  <?php endif; ?>

  <h2 class="panel-section-title">میزبان SMTP</h2>
  <p class="meta">اگر احراز هویت شکست می‌خورد ولی رمز وب‌میل درست است، Host را روی <code>localhost</code> بگذارید.</p>
  <form class="form" method="post" action="admin-mail-test.php">
    <?php wp_nonce_field('casting_mail_test'); ?>
    <input type="hidden" name="save_smtp_host" value="1">
    <div class="field">
      <label for="smtp_host">Host</label>
      <select id="smtp_host" name="smtp_host">
        <?php
        $hosts = ['localhost', '127.0.0.1', 'mail.7rokh.com', 'mail.7rokh.ir', '7rokh.com'];
        $current_host = (string) $status['host'];
        foreach ($hosts as $host_opt) :
            ?>
          <option value="<?= casting_e($host_opt) ?>"<?= $current_host === $host_opt ? ' selected' : '' ?>><?= casting_e($host_opt) ?><?= $host_opt === 'localhost' ? ' (پیشنهادی سی‌پنل)' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">ذخیره میزبان</button>
  </form>

  <h2 class="panel-section-title">رمز SMTP</h2>
  <p class="meta">رمز اکانت <code>noreply@7rokh.com</code> را اینجا بگذارید. بعد از ذخیره، اول «تشخیص مستقیم SMTP» را بزنید.</p>
  <form class="form" method="post" action="admin-mail-test.php" autocomplete="off">
    <?php wp_nonce_field('casting_mail_test'); ?>
    <input type="hidden" name="save_smtp_pass" value="1">
    <div class="field">
      <label for="smtp_pass">رمز noreply@7rokh.com</label>
      <input id="smtp_pass" name="smtp_pass" type="password" required autocomplete="new-password" placeholder="<?= !empty($status['pass_loaded']) ? 'رمز ذخیره‌شده — برای تغییر، رمز جدید را بنویسید' : 'رمز ایمیل را وارد کنید' ?>">
    </div>
    <button class="btn btn-primary" type="submit">ذخیره رمز SMTP</button>
  </form>
  <?php if (($status['pass_source'] ?? '') === 'option') : ?>
  <form class="form" method="post" action="admin-mail-test.php" style="margin-top:0.75rem">
    <?php wp_nonce_field('casting_mail_test'); ?>
    <input type="hidden" name="clear_smtp_pass" value="1">
    <button class="btn btn-secondary" type="submit">پاک کردن رمز دیتابیس (استفاده از config.local)</button>
  </form>
  <?php endif; ?>

  <h2 class="panel-section-title">تشخیص مستقیم SMTP</h2>
  <p class="meta">بدون PHPMailer، مستقیم به سرور وصل می‌شود و کد پاسخ واقعی (مثل 535) را نشان می‌دهد.</p>
  <form class="form" method="post" action="admin-mail-test.php">
    <?php wp_nonce_field('casting_mail_test'); ?>
    <input type="hidden" name="smtp_diagnose" value="1">
    <button class="btn btn-secondary" type="submit"<?= $status['smtp_ready'] ? '' : ' disabled' ?>>تشخیص مستقیم SMTP</button>
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
  <p class="meta">اگر «تعداد درخواست زیاد بود» می‌بینید، این دکمه محدودیت IP فعلی را پاک می‌کند.</p>
  <form class="form" method="post" action="admin-mail-test.php">
    <?php wp_nonce_field('casting_mail_test'); ?>
    <input type="hidden" name="clear_rate_limits" value="1">
    <button class="btn btn-secondary" type="submit">ریست محدودیت IP من</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
