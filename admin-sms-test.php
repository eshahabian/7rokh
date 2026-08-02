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
$last_ref = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_sms_test')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $mode = ((string) ($_POST['mode'] ?? 'otp')) === 'text' ? 'text' : 'otp';
        $test_mobile = casting_normalize_mobile((string) ($_POST['mobile'] ?? ''));
        if ($test_mobile === '' || !preg_match('/^09\d{9}$/', $test_mobile)) {
            $error = 'شماره موبایل معتبر وارد کنید.';
        } elseif (!casting_sms_is_configured()) {
            $error = 'CASTING_SMS_API_KEY در config.local.php روی سرور تنظیم نشده است.';
        } elseif ($mode === 'otp') {
            $result = casting_otp_send('admin_test', $test_mobile);
            $debug = casting_sms_last_debug();
            $last_ref = is_array($debug) ? (string) ($debug['ref_id'] ?? '') : '';
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                $success = 'کد OTP تست به ' . $test_mobile . ' ارسال شد.'
                    . ($last_ref !== '' ? ' (refId: ' . $last_ref . ')' : '');
            }
        } else {
            $result = casting_sms_send_text(
                $test_mobile,
                'تست پیامک متنی پورتال ' . casting_brand() . ' — ' . current_time('mysql')
            );
            $last_ref = (string) ($result['ref_id'] ?? '');
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                $success = 'پیامک متنی تست به ' . $test_mobile . ' ارسال شد.'
                    . ($last_ref !== '' ? ' (refId: ' . $last_ref . ')' : '');
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
$debug = casting_sms_last_debug();

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
  <p class="lede">اگر سایت «موفق» نشان داد ولی پیامک نیامد، بلوک «آخرین پاسخ API» را ببینید و همان را بفرستید.</p>

  <dl class="admin-mail-status">
    <dt>API Base</dt>
    <dd><code dir="ltr"><?= casting_e($api_base) ?></code></dd>
    <dt>CASTING_SMS_API_KEY</dt>
    <dd><?= $api_set ? '✓ تنظیم شده' : '✗ خالی است — روی سرور در config.local.php بگذارید' ?></dd>
    <dt>CASTING_SMS_FROM</dt>
    <dd><?= $from !== '' ? '<code dir="ltr">' . casting_e($from) . '</code>' : '✗ خالی — برای پیامک متنی لازم است' ?></dd>
    <dt>OTP Sender</dt>
    <dd><?= $otp_sender !== '' ? '<code dir="ltr">' . casting_e($otp_sender) . '</code>' : 'پیش‌فرض SmartOTP (بدون OTPSender)' ?></dd>
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
  </dl>

  <?php if (is_array($debug)) : ?>
    <details class="dash-card" open style="margin:1rem 0;padding:1rem;">
      <summary><strong>آخرین پاسخ API</strong></summary>
      <p class="meta" dir="ltr"><?= casting_e((string) ($debug['at'] ?? '')) ?> · HTTP <?= (int) ($debug['http'] ?? 0) ?> · <?= !empty($debug['ok']) ? 'ok' : 'fail' ?></p>
      <p class="meta" dir="ltr"><?= casting_e((string) ($debug['url'] ?? '')) ?></p>
      <?php if (!empty($debug['parsed_error'])) : ?>
        <p class="flash flash-error"><?= casting_e((string) $debug['parsed_error']) ?></p>
      <?php endif; ?>
      <pre dir="ltr" style="white-space:pre-wrap;overflow:auto;max-height:280px;font-size:0.8rem;"><?= casting_e(wp_json_encode([
          'request' => $debug['request'] ?? null,
          'body'    => $debug['body'] ?? null,
          'ref_id'  => $debug['ref_id'] ?? null,
      ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
    </details>
  <?php endif; ?>

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
