<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_is_portal_owner($user_id)) {
    wp_die('فقط مدیر اصلی پورتال به این بخش دسترسی دارد.', 'دسترسی غیرمجاز', ['response' => 403]);
}

casting_nocache();

$error = '';
$success = '';
$test_mobile = '';
try {
    $test_mobile = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile', true));
} catch (Throwable $e) {
    $test_mobile = '';
}
$mode = 'otp';
$last_ref = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
                if (empty($result['ok'])) {
                    $error = (string) ($result['error'] ?? 'ارسال OTP ناموفق بود.');
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
                if (empty($result['ok'])) {
                    $error = (string) ($result['error'] ?? 'ارسال پیامک متنی ناموفق بود.');
                } else {
                    $success = 'پیامک متنی تست به ' . $test_mobile . ' ارسال شد.'
                        . ($last_ref !== '' ? ' (refId: ' . $last_ref . ')' : '');
                }
            }
        }
    } catch (Throwable $e) {
        $error = 'خطای داخلی هنگام ارسال پیامک: ' . $e->getMessage();
        if (function_exists('error_log')) {
            error_log('[casting-sms-test] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}

$api_set = defined('CASTING_SMS_API_KEY') && trim((string) CASTING_SMS_API_KEY) !== '';
$from = defined('CASTING_SMS_FROM') ? trim((string) CASTING_SMS_FROM) : '';
$otp_sender = '';
$api_base = '';
$pattern_id = '';
$otp_method = 'smart';
$credit_info = ['ok' => false, 'error' => 'بررسی نشده'];
$debug = null;

try {
    $otp_sender = casting_sms_otp_sender();
    $api_base = casting_sms_api_base();
    $pattern_id = casting_sms_otp_pattern_id();
    $otp_method = casting_sms_otp_method();
    // اعتبار را فقط وقتی کاربر خواست بخوان (جلوگیری از fatal/timeout هنگام باز کردن صفحه)
    $want_credit = isset($_GET['credit']) || isset($_POST['check_credit']);
    if ($want_credit && casting_sms_is_configured()) {
        $credit_info = casting_sms_get_credit();
    } elseif (casting_sms_is_configured()) {
        $credit_info = ['ok' => false, 'error' => 'برای مشاهده اعتبار، «بررسی اعتبار» را بزنید.'];
    } else {
        $credit_info = ['ok' => false, 'error' => 'کلید تنظیم نشده'];
    }
    $debug = casting_sms_last_debug();
} catch (Throwable $e) {
    $error = $error !== '' ? $error : ('خطا در آماده‌سازی صفحه: ' . $e->getMessage());
    if (function_exists('error_log')) {
        error_log('[casting-sms-test-boot] ' . $e->getMessage());
    }
}

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
    <dd><?= $from !== '' ? '<code dir="ltr">' . casting_e($from) . '</code>' : '✗ خالی — برای پیامک متنی و OTP الگویی لازم است' ?></dd>
    <dt>روش OTP</dt>
    <dd><?php if ($otp_method === 'pattern') : ?>
      الگو — <code dir="ltr">POST /SMS/Send</code> با <code>ToNumber</code> + <code>PatternId</code>
    <?php else : ?>
      SmartOTP — <code dir="ltr">POST /SMS/SmartOTP</code> با <code>OTPSender</code> + <code>ToNumber</code> + <code>Content</code>
    <?php endif; ?></dd>
    <dt>OTP Sender</dt>
    <dd><code dir="ltr"><?= casting_e($otp_sender !== '' ? $otp_sender : 'Auto') ?></code></dd>
    <dt>OTP Pattern</dt>
    <dd><?= $pattern_id !== '' ? '<code dir="ltr">' . casting_e($pattern_id) . '</code>' : 'تنظیم نشده — از SmartOTP استفاده می‌شود' ?></dd>
    <dt>مانده اعتبار</dt>
    <dd><?php
    if (!empty($credit_info['ok'])) {
        echo '<code dir="ltr">' . casting_e(number_format((float) ($credit_info['credit'] ?? 0), 0)) . '</code> ریال';
    } else {
        echo '✗ ' . casting_e((string) ($credit_info['error'] ?? 'نامشخص'));
    }
    ?></dd>
    <dt>ارسال فعال</dt>
    <dd><?= casting_sms_is_configured() ? '✓ بله' : '✗ خیر' ?></dd>
  </dl>

  <p class="cta-row" style="margin:0.75rem 0">
    <a class="btn btn-ghost btn-sm" href="admin-sms-test.php?credit=1">بررسی اعتبار</a>
  </p>

  <?php if (is_array($debug)) : ?>
    <details class="dash-card" open style="margin:1rem 0;padding:1rem;">
      <summary><strong>آخرین پاسخ API</strong></summary>
      <p class="meta" dir="ltr"><?= casting_e((string) ($debug['at'] ?? '')) ?> · HTTP <?= (int) ($debug['http'] ?? 0) ?> · <?= !empty($debug['ok']) ? 'ok' : 'fail' ?></p>
      <p class="meta" dir="ltr"><?= casting_e((string) ($debug['url'] ?? '')) ?></p>
      <?php if (!empty($debug['parsed_error'])) : ?>
        <p class="flash flash-error"><?= casting_e((string) $debug['parsed_error']) ?></p>
      <?php endif; ?>
      <pre dir="ltr" style="white-space:pre-wrap;overflow:auto;max-height:280px;font-size:0.8rem;"><?= casting_e((string) wp_json_encode([
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
      <label class="radio-inline"><input type="radio" name="mode" value="otp" <?= $mode === 'otp' ? 'checked' : '' ?>> OTP (<?= $otp_method === 'pattern' ? 'الگو' : 'SmartOTP' ?>)</label>
      <label class="radio-inline"><input type="radio" name="mode" value="text" <?= $mode === 'text' ? 'checked' : '' ?>> پیامک متنی</label>
    </fieldset>
    <button class="btn btn-primary" type="submit">ارسال تست</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
