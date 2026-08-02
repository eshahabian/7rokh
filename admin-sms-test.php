<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_is_super_admin($user_id)) {
    wp_die('فقط مدیر اصلی به این بخش دسترسی دارد.', 'دسترسی غیرمجاز', ['response' => 403]);
}

casting_nocache();

$error = '';
$success = '';
$test_mobile = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile', true));
$mode = 'otp';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_sms_test')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $mode = ((string) ($_POST['mode'] ?? 'otp')) === 'text' ? 'text' : 'otp';
        $test_mobile = casting_normalize_mobile((string) ($_POST['mobile'] ?? ''));
        if ($test_mobile === '' || !preg_match('/^09\d{9}$/', $test_mobile)) {
            $error = 'شماره موبایل معتبر وارد کنید.';
        } elseif (!casting_sms_is_configured()) {
            $error = 'CASTING_SMS_API_KEY در config.local.php تنظیم نشده است.';
        } elseif ($mode === 'otp') {
            $result = casting_otp_send('admin_test', $test_mobile);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                $success = 'کد OTP تست به ' . $test_mobile . ' ارسال شد.';
            }
        } else {
            $result = casting_sms_send_text(
                $test_mobile,
                'تست پیامک متنی پورتال ' . casting_brand() . ' — ' . current_time('mysql')
            );
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                $success = 'پیامک متنی تست به ' . $test_mobile . ' ارسال شد.';
            }
        }
    }
}

$api_set = defined('CASTING_SMS_API_KEY') && trim((string) CASTING_SMS_API_KEY) !== '';
$from = defined('CASTING_SMS_FROM') ? trim((string) CASTING_SMS_FROM) : '';
$otp_sender = casting_sms_otp_sender();
$api_base = casting_sms_api_base();
$pattern_id = defined('CASTING_SMS_OTP_PATTERN_ID') ? trim((string) CASTING_SMS_OTP_PATTERN_ID) : '';
$credit_info = casting_sms_is_configured() ? casting_sms_get_credit() : ['ok' => false, 'error' => 'کلید تنظیم نشده'];

casting_render_panel_start('تست پیامک', 'admin-sms');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-wide">
  <h1>تست پیامک WebOne</h1>
  <p class="lede">طبق مستند RestDocument v1.4 — قبل از استفاده عمومی، OTP و پیامک متنی را اینجا چک کنید. کلید فقط در config.local.php باشد.</p>

  <dl class="admin-mail-status">
    <dt>API Base</dt>
    <dd><code dir="ltr"><?= casting_e($api_base) ?></code></dd>
    <dt>CASTING_SMS_API_KEY</dt>
    <dd><?= $api_set ? '✓ تنظیم شده' : '✗ خالی است' ?></dd>
    <dt>CASTING_SMS_FROM</dt>
    <dd><?= $from !== '' ? '<code dir="ltr">' . casting_e($from) . '</code>' : '✗ خالی — برای لینک بازیابی لازم است' ?></dd>
    <dt>OTP Sender</dt>
    <dd><code dir="ltr"><?= casting_e($otp_sender) ?></code></dd>
    <dt>OTP Pattern</dt>
    <dd><?= $pattern_id !== '' ? '<code dir="ltr">' . casting_e($pattern_id) . '</code> (الگو)' : 'SmartOTP' ?></dd>
    <dt>مانده اعتبار</dt>
    <dd><?php
    if (!empty($credit_info['ok'])) {
        echo '<code dir="ltr">' . casting_e(number_format((float) $credit_info['credit'], 0)) . '</code> ریال';
    } else {
        echo '✗ ' . casting_e((string) ($credit_info['error'] ?? 'نامشخص'));
    }
    ?></dd>
    <dt>ارسال فعال</dt>
    <dd><?= casting_sms_is_configured() ? '✓ بله' : '✗ خیر' ?></dd>
    <dt>OTP ثبت‌نام</dt>
    <dd><?= casting_mobile_otp_enabled() ? '✓ روشن' : '✗ خاموش (CASTING_MOBILE_OTP_ENABLED)' ?></dd>
  </dl>

  <form class="form" method="post" action="admin-sms-test.php">
    <?php wp_nonce_field('casting_sms_test'); ?>
    <div class="field">
      <label for="mobile">موبایل گیرنده</label>
      <input id="mobile" name="mobile" type="tel" required pattern="09[0-9]{9}" value="<?= casting_e($test_mobile) ?>" placeholder="09121234567">
    </div>
    <fieldset class="field field-radio-row">
      <legend>نوع تست</legend>
      <label class="radio-inline"><input type="radio" name="mode" value="otp" <?= $mode === 'otp' ? 'checked' : '' ?>> OTP (SmartOTP)</label>
      <label class="radio-inline"><input type="radio" name="mode" value="text" <?= $mode === 'text' ? 'checked' : '' ?>> پیامک متنی</label>
    </fieldset>
    <button class="btn btn-primary" type="submit">ارسال تست</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
