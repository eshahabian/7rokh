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
$provider = casting_gateway_provider();
$terminal_mellat = casting_behpardakht_terminal_id();
$username = casting_behpardakht_username();
$terminal_sep = casting_sep_terminal_id();
if ($terminal_mellat === '') {
    $terminal_mellat = '9647270';
}
if ($username === '') {
    $username = 'IPG9647270';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_gateway_save')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $provider = sanitize_key((string) ($_POST['gateway_provider'] ?? $provider));
        if (!in_array($provider, ['mellat', 'sep'], true)) {
            $error = 'درگاه انتخاب‌شده معتبر نیست.';
        } else {
            $provider_result = casting_gateway_store_provider($provider);
            if (!$provider_result['ok']) {
                $error = $provider_result['error'];
            } elseif ($provider === 'sep') {
                $terminal_sep = sanitize_text_field((string) ($_POST['sep_terminal_id'] ?? ''));
                $result = casting_gateway_store_sep_credentials($terminal_sep);
                if (!$result['ok']) {
                    $error = $result['error'];
                } else {
                    $success = 'درگاه سامان (SEP) فعال شد. آدرس بازگشت را در پنل سامان ثبت کنید و یک سفارش آزمایشی بزنید.';
                }
            } else {
                $terminal_mellat = sanitize_text_field((string) ($_POST['terminal_id'] ?? ''));
                $username = sanitize_text_field((string) ($_POST['username'] ?? ''));
                $password = trim((string) ($_POST['password'] ?? ''));
                if ($password === '') {
                    $password = casting_behpardakht_password();
                }
                $result = casting_gateway_store_credentials($terminal_mellat, $username, $password);
                if (!$result['ok']) {
                    $error = $result['error'];
                } else {
                    $success = 'درگاه ملت فعال شد. از صفحه پرداخت یک سفارش آزمایشی بزنید.';
                }
            }
        }
    }
}

$mode = casting_gateway_mode();
$provider = casting_gateway_provider();
$ready = casting_gateway_has_credentials();
$soap = class_exists('SoapClient');

casting_render_panel_start('درگاه پرداخت', 'admin-gateway');
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>درگاه پرداخت آنلاین</h1>
  <p class="lede">یکی از درگاه‌های فعال را انتخاب کنید. مشخصات در دیتابیس وردپرس (و در صورت امکان در config.local.php) ذخیره می‌شوند.</p>

  <dl class="admin-mail-status">
    <dt>حالت درگاه</dt>
    <dd><code><?= casting_e($mode) ?></code></dd>
    <dt>درگاه فعال</dt>
    <dd><code><?= casting_e($provider) ?></code> — <?= casting_e(casting_gateway_label()) ?></dd>
    <dt>مشخصات پایانه</dt>
    <dd><?= $ready ? '✓ ذخیره شده' : '✗ ناقص است — فرم زیر را ذخیره کنید' ?></dd>
    <dt>PHP SOAP (ملت)</dt>
    <dd><?= $soap ? '✓ فعال' : '✗ غیرفعال — فقط برای درگاه ملت لازم است' ?></dd>
    <dt>آدرس بازگشت ملت</dt>
    <dd><code><?= casting_e(casting_mellat_callback_url()) ?></code></dd>
    <dt>آدرس بازگشت سامان</dt>
    <dd><code><?= casting_e(casting_sep_callback_url()) ?></code></dd>
  </dl>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>
  <?php if ($success !== '') : ?>
    <div class="flash flash-success" role="status"><?= casting_e($success) ?></div>
  <?php endif; ?>

  <form class="form" method="post" action="admin-gateway.php" autocomplete="off">
    <?php wp_nonce_field('casting_gateway_save'); ?>
    <fieldset>
      <legend>انتخاب درگاه</legend>
      <label class="checkout-rules-accept">
        <input type="radio" name="gateway_provider" value="mellat" <?= $provider === 'mellat' ? 'checked' : '' ?> required>
        <span>به‌پرداخت ملت (SOAP)</span>
      </label>
      <label class="checkout-rules-accept">
        <input type="radio" name="gateway_provider" value="sep" <?= $provider === 'sep' ? 'checked' : '' ?> required>
        <span>پرداخت الکترونیک سامان — SEP / neo-pg</span>
      </label>
    </fieldset>

    <div id="gateway-mellat-fields">
      <h2 class="panel-section-title">مشخصات ملت</h2>
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
    </div>

    <div id="gateway-sep-fields">
      <h2 class="panel-section-title">مشخصات سامان (SEP)</h2>
      <label>
        شماره ترمینال (TerminalId)
        <input type="text" name="sep_terminal_id" value="<?= casting_e($terminal_sep) ?>">
      </label>
      <p class="meta">IP خروجی سرور باید در پنل سامان ثبت شود. برای neo-pg آدرس هدایت از هدر X-IPG-Url خوانده می‌شود.</p>
    </div>

    <div class="cta-row">
      <button class="btn btn-primary" type="submit">ذخیره و فعال‌سازی درگاه</button>
    </div>
  </form>
</section>
<?php casting_render_panel_end(); ?>
