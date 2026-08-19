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
                $error = 'کلید API یا نام کاربری/رمز پنل در config.local.php روی سرور تنظیم نشده است.';
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

$api_set = casting_sms_api_key() !== '';
$wp_sms = [];
try {
    $wp_sms = function_exists('casting_sms_wp_plugin_config') ? casting_sms_wp_plugin_config() : [];
} catch (Throwable $e) {
    $wp_sms = ['plugins' => [], 'option_keys' => []];
    $error = $error !== '' ? $error : ('خواندن تنظیمات افزونه پیامک ناموفق بود: ' . $e->getMessage());
}
$from = defined('CASTING_SMS_FROM') ? trim((string) CASTING_SMS_FROM) : '';
$otp_sender = '';
$api_base = '';
$pattern_id = '';
$otp_method = 'smart';
$http_set = false;
$otp_template = '';
$otp_var = 'x';
$credit_info = ['ok' => false, 'error' => 'بررسی نشده'];
$debug = null;

try {
    $from = casting_sms_line_number();
    $otp_sender = casting_sms_otp_sender();
    $api_base = casting_sms_api_base();
    $pattern_id = casting_sms_otp_pattern_id();
    $otp_method = casting_sms_otp_method();
    $http_set = casting_sms_http_is_configured();
    $otp_template = casting_sms_otp_template();
    $otp_var = 'ParameterValue';
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
  <p class="lede">طبق RestDocument: OTP با <code>PatternId</code> متن نمی‌خواهد؛ مقدار کد در <code>PatternParameterData.ParameterValue</code> می‌رود. بدون PatternId از SmartOTP با <code>OTPSender=Auto</code> استفاده می‌شود.</p>
  <p class="admin-sms-pattern" dir="rtl">
    <strong>متن الگو:</strong>
    <?= casting_e($otp_template !== '' ? $otp_template : 'کد ورود شما {x}') ?>
    <br>
    <strong>نمونه ارسال:</strong>
    <?= casting_e(casting_sms_otp_text('123456')) ?>
  </p>

  <dl class="admin-mail-status">
    <dt>افزونه پیامک وردپرس</dt>
    <dd><?php
    $plugin_list = is_array($wp_sms['plugins'] ?? null) ? $wp_sms['plugins'] : [];
    if ($plugin_list === []) {
        echo 'افزونه فعالی با نام sms/webone در wp-content/plugins پیدا نشد.';
    } else {
        echo '<code dir="ltr">' . casting_e(implode(', ', $plugin_list)) . '</code>';
    }
    ?></dd>
    <dt>درگاه افزونه</dt>
    <dd><?= ($wp_sms['gateway'] ?? '') !== '' ? '<code dir="ltr">' . casting_e((string) $wp_sms['gateway']) . '</code>' : '—' ?></dd>
    <dt>نام کاربری افزونه</dt>
    <dd><?= ($wp_sms['username'] ?? '') !== '' ? '<code dir="ltr">' . casting_e(casting_sms_mask_secret((string) $wp_sms['username'])) . '</code>' : 'پیدا نشد' ?></dd>
    <dt>رمز افزونه</dt>
    <dd><?= ($wp_sms['password'] ?? '') !== '' ? '✓ خوانده شد (' . casting_e(casting_sms_mask_secret((string) $wp_sms['password'])) . ')' : 'پیدا نشد' ?></dd>
    <dt>خط فرستنده افزونه</dt>
    <dd><?= ($wp_sms['from'] ?? '') !== '' ? '<code dir="ltr">' . casting_e((string) $wp_sms['from']) . '</code>' : 'پیدا نشد' ?></dd>
    <dt>کلید API افزونه</dt>
    <dd><?= ($wp_sms['api_key'] ?? '') !== '' ? '✓ ' . casting_e(casting_sms_mask_secret((string) $wp_sms['api_key'])) : 'پیدا نشد' ?></dd>
    <dt>PatternId افزونه</dt>
    <dd><?= ($wp_sms['pattern_id'] ?? '') !== '' ? '<code dir="ltr">' . casting_e((string) $wp_sms['pattern_id']) . '</code>' : 'پیدا نشد' ?></dd>
    <dt>قالب افزونه</dt>
    <dd><?= ($wp_sms['template'] ?? '') !== '' ? casting_e((string) $wp_sms['template']) : 'پیدا نشد' ?></dd>
    <dt>گزینه‌های wp_options</dt>
    <dd><?php
    $opt_keys = is_array($wp_sms['option_keys'] ?? null) ? $wp_sms['option_keys'] : [];
    echo $opt_keys !== [] ? '<code dir="ltr">' . casting_e(implode(', ', $opt_keys)) . '</code>' : '—';
    ?></dd>
    <dt>API Base</dt>
    <dd><code dir="ltr"><?= casting_e($api_base) ?></code></dd>
    <dt>CASTING_SMS_API_KEY</dt>
    <dd><?= $api_set ? '✓ تنظیم شده' : '✗ خالی است — روی سرور در config.local.php بگذارید' ?></dd>
    <dt>خط فرستنده پنل (From)</dt>
    <dd><?= $from !== '' ? '<code dir="ltr">' . casting_e($from) . '</code>' : '✗ خالی' ?></dd>
    <dt>روش OTP</dt>
    <dd><?php if ($otp_method === 'pattern') : ?>
      الگو با PatternId — <code dir="ltr">POST /SMS/Send</code>
    <?php else : ?>
      پیامک متنی — همان <code dir="ltr">POST /SMS/Send</code> با خط From (بدون SmartOTP)
    <?php endif; ?></dd>
    <dt>OTP Sender (SmartOTP)</dt>
    <dd><code dir="ltr"><?= casting_e($otp_sender !== '' ? $otp_sender : 'Auto') ?></code> — فقط اگر بعداً SmartOTP را روشن کنید</dd>
    <dt>کد الگو (PatternId)</dt>
    <dd><?= $pattern_id !== '' ? '<code dir="ltr">' . casting_e($pattern_id) . '</code>' : 'خالی — کد تأیید با پیامک متنی از همان خط فرستنده ارسال می‌شود. اگر الگوی پنل دارید، شناسه را در CASTING_SMS_OTP_PATTERN_ID بگذارید.' ?></dd>
    <dt>HTTP GET</dt>
    <dd><?= !empty($http_set) ? '✓ نام کاربری/رمز پنل تنظیم شده' : '✗ تنظیم نشده — برای متد GET پنل، USERNAME و PASSWORD لازم است' ?></dd>
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
      <label for="mobile">موبایل گیرنده (همان شماره پروفایل کاربر)</label>
      <input id="mobile" name="mobile" type="tel" required pattern="09[0-9]{9}" value="<?= casting_e($test_mobile) ?>" placeholder="09121234567">
      <p class="field-hint">پیش‌فرض از پروفایل شماست. OTP به <code>ToNumber</code> می‌رود، نه به خط فرستنده.</p>
    </div>
    <fieldset class="field field-radio-row">
      <legend>نوع تست</legend>
      <label class="radio-inline"><input type="radio" name="mode" value="otp" <?= $mode === 'otp' ? 'checked' : '' ?>> OTP (الگو)</label>
      <label class="radio-inline"><input type="radio" name="mode" value="text" <?= $mode === 'text' ? 'checked' : '' ?>> پیامک متنی</label>
    </fieldset>
    <button class="btn btn-primary" type="submit">ارسال تست</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
