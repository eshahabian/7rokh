<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/gateway.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_is_super_admin($user_id)) {
    wp_die('فقط مدیر اصلی به این بخش دسترسی دارد.', 'دسترسی غیرمجاز', ['response' => 403]);
}

casting_nocache();

$error = '';
$success = '';
$terminal_mellat = casting_behpardakht_terminal_id();
$username = casting_behpardakht_username();
$terminal_sep = casting_sep_terminal_id();
if ($terminal_mellat === '') {
    $terminal_mellat = '9647270';
}
if ($username === '') {
    $username = 'IPG9647270';
}
if ($terminal_sep === '') {
    $terminal_sep = '15724096';
}

$sep_report_user = defined('CASTING_SEP_REPORT_USERNAME') ? (string) CASTING_SEP_REPORT_USERNAME : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_gateway_save')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $saved = [];
        $terminal_mellat = sanitize_text_field((string) ($_POST['terminal_id'] ?? ''));
        $username = sanitize_text_field((string) ($_POST['username'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));
        if ($password === '') {
            $password = casting_behpardakht_password();
        }
        if ($terminal_mellat !== '' && $username !== '' && $password !== '') {
            $result = casting_gateway_store_credentials($terminal_mellat, $username, $password);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                $saved[] = 'ملت';
            }
        }

        $terminal_sep = sanitize_text_field((string) ($_POST['sep_terminal_id'] ?? ''));
        if ($error === '' && $terminal_sep !== '') {
            $result = casting_gateway_store_sep_credentials($terminal_sep);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                $saved[] = 'سامان';
            }
        }

        if ($error === '' && $saved === []) {
            $error = 'حداقل مشخصات یک درگاه (ملت یا سامان) را کامل وارد کنید.';
        } elseif ($error === '') {
            $success = 'تنظیمات درگاه ذخیره شد: ' . implode(' و ', $saved) . '. کاربر هنگام پرداخت درگاه را انتخاب می‌کند.';
        }
    }
}

$mode = casting_gateway_mode();
$mellat_ready = casting_behpardakht_has_credentials();
$sep_ready = casting_sep_has_credentials();
$soap = class_exists('SoapClient');

casting_render_panel_start('درگاه پرداخت', 'admin-gateway');
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>درگاه پرداخت آنلاین</h1>
  <p class="lede">هر دو درگاه را می‌توانید هم‌زمان پیکربندی کنید. کاربر هنگام پرداخت، ملت یا سامان را خودش انتخاب می‌کند.</p>

  <dl class="admin-mail-status">
    <dt>حالت درگاه</dt>
    <dd><code><?= casting_e($mode) ?></code></dd>
    <dt>به‌پرداخت ملت</dt>
    <dd><?= $mellat_ready ? '✓ آماده' : '✗ مشخصات ناقص' ?></dd>
    <dt>سامان (SEP)</dt>
    <dd><?= $sep_ready ? '✓ آماده' : '✗ مشخصات ناقص' ?></dd>
    <dt>PHP SOAP (ملت)</dt>
    <dd><?= $soap ? '✓ فعال' : '✗ غیرفعال — از هاست php-soap بخواهید' ?></dd>
    <dt>آدرس بازگشت ملت</dt>
    <dd><code><?= casting_e(casting_mellat_callback_url()) ?></code></dd>
    <dt>آدرس بازگشت سامان</dt>
    <dd><code><?= casting_e(casting_sep_callback_url()) ?></code></dd>
    <?php if ($sep_report_user !== '') : ?>
    <dt>گزارش‌گیری سامان</dt>
    <dd><code><?= casting_e($sep_report_user) ?></code> — ورود از <a href="https://report.sep.ir/" target="_blank" rel="noopener">report.sep.ir</a> (رمز جدا از API پرداخت)</dd>
    <?php endif; ?>
  </dl>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>
  <?php if ($success !== '') : ?>
    <div class="flash flash-success" role="status"><?= casting_e($success) ?></div>
  <?php endif; ?>

  <form class="form" method="post" action="admin-gateway.php" autocomplete="off">
    <?php wp_nonce_field('casting_gateway_save'); ?>

    <h2 class="panel-section-title">به‌پرداخت ملت</h2>
    <label>
      شماره پایانه
      <input type="text" name="terminal_id" value="<?= casting_e($terminal_mellat) ?>">
    </label>
    <label>
      نام کاربری
      <input type="text" name="username" value="<?= casting_e($username) ?>">
    </label>
    <label>
      رمز عبور پایانه
      <input type="password" name="password" value="" placeholder="<?= casting_behpardakht_password() !== '' ? 'ذخیره شده — برای تغییر پر کنید' : 'رمز درگاه' ?>">
    </label>

    <h2 class="panel-section-title">پرداخت الکترونیک سامان (SEP)</h2>
    <label>
      شماره ترمینال (TerminalId)
      <input type="text" name="sep_terminal_id" value="<?= casting_e($terminal_sep) ?>">
    </label>
    <p class="meta">IP خروجی سرور باید در پنل سامان ثبت شود. برای neo-pg آدرس هدایت از هدر X-IPG-Url خوانده می‌شود.</p>

    <div class="cta-row">
      <button class="btn btn-primary" type="submit">ذخیره تنظیمات درگاه‌ها</button>
    </div>
  </form>
</section>
<?php casting_render_panel_end(); ?>
