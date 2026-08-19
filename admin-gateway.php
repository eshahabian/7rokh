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
$terminal = casting_behpardakht_terminal_id();
$username = casting_behpardakht_username();
if ($terminal === '') {
    $terminal = '9647270';
}
if ($username === '') {
    $username = 'IPG9647270';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_gateway_save')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $terminal = sanitize_text_field((string) ($_POST['terminal_id'] ?? ''));
        $username = sanitize_text_field((string) ($_POST['username'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));
        if ($password === '') {
            $password = casting_behpardakht_password();
        }
        $result = casting_gateway_store_credentials($terminal, $username, $password);
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            $success = 'درگاه ملت فعال شد. از صفحه پرداخت یک سفارش آزمایشی بزنید.';
        }
    }
}

$mode = casting_gateway_mode();
$ready = casting_behpardakht_has_credentials();
$soap = class_exists('SoapClient');

casting_render_panel_start('درگاه ملت', 'admin-gateway');
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>درگاه به‌پرداخت ملت</h1>
  <p class="lede">با ذخیره مشخصات پایانه، پرداخت آنلاین روی پورتال فعال می‌شود. این مقادیر در دیتابیس وردپرس (و در صورت امکان در config.local.php) ذخیره می‌شوند.</p>

  <dl class="admin-mail-status">
    <dt>حالت درگاه</dt>
    <dd><code><?= casting_e($mode) ?></code></dd>
    <dt>مشخصات پایانه</dt>
    <dd><?= $ready ? '✓ ذخیره شده' : '✗ ناقص است — فرم زیر را ذخیره کنید' ?></dd>
    <dt>PHP SOAP</dt>
    <dd><?= $soap ? '✓ فعال' : '✗ غیرفعال — از هاست php-soap بخواهید' ?></dd>
    <dt>آدرس بازگشت</dt>
    <dd><code><?= casting_e(casting_mellat_callback_url()) ?></code></dd>
  </dl>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>
  <?php if ($success !== '') : ?>
    <div class="flash flash-success" role="status"><?= casting_e($success) ?></div>
  <?php endif; ?>

  <form class="form" method="post" action="admin-gateway.php" autocomplete="off">
    <?php wp_nonce_field('casting_gateway_save'); ?>
    <label>
      شماره پایانه
      <input type="text" name="terminal_id" value="<?= casting_e($terminal) ?>" required>
    </label>
    <label>
      نام کاربری
      <input type="text" name="username" value="<?= casting_e($username) ?>" required>
    </label>
    <label>
      رمز عبور پایانه
      <input type="password" name="password" value="" placeholder="<?= casting_behpardakht_password() !== '' ? 'ذخیره شده — برای تغییر پر کنید' : 'رمز درگاه' ?>" <?= casting_behpardakht_password() === '' ? 'required' : '' ?>>
    </label>
    <div class="cta-row">
      <button class="btn btn-primary" type="submit">فعال‌سازی درگاه</button>
    </div>
  </form>
</section>
<?php casting_render_panel_end(); ?>
