<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/layout.php';

casting_nocache();

$user = casting_current_user();
if ($user && casting_get_user_role((int) $user->ID) !== '') {
    casting_redirect(casting_dashboard_for_role(casting_get_user_role((int) $user->ID)));
}

$error = '';
$success = '';
$mobile = '';
$otp_sent = false;
$otp_verified = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_action = sanitize_key((string) ($_POST['otp_action'] ?? 'send'));
    if (!in_array($otp_action, ['send', 'verify', 'save'], true)) {
        $otp_action = 'send';
    }
    $mobile = (string) ($_POST['mobile'] ?? '');
    $norm_preview = casting_normalize_mobile($mobile);
    if ($otp_action !== 'send') {
        $otp_sent = true;
    }
    if ($norm_preview !== '' && casting_otp_session_is_verified('reset', $norm_preview)) {
        $otp_verified = true;
        $otp_sent = true;
    }
    $rate_action = $otp_action === 'send' ? 'otp_send' : 'forgot_password';
    $rate_error = casting_rate_limit_check($rate_action);
    if ($rate_error !== null) {
        $error = $rate_error;
    } elseif (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_forgot')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
    } else {
        $norm = $norm_preview;
        $otp_verified = $norm !== '' && casting_otp_session_is_verified('reset', $norm);

        if ($norm === '' || !preg_match('/^09\d{9}$/', $norm)) {
            $error = 'شماره موبایل را درست وارد کنید (مثلاً ۰۹۱۲۱۲۳۴۵۶۷).';
        } elseif ($otp_action === 'send') {
            $found = casting_find_user_by_mobile($norm);
            if (empty($found['ok'])) {
                $success = 'اگر حسابی با این موبایل باشد، کد تأیید ارسال می‌شود.';
                $otp_sent = true;
            } elseif (!casting_sms_is_configured()) {
                $error = 'ارسال پیامک در حال حاضر ممکن نیست.';
                casting_rate_limit_hit('otp_send');
            } else {
                $send = casting_otp_send('reset', $norm);
                if (!$send['ok']) {
                    $error = $send['error'];
                    casting_rate_limit_hit('otp_send');
                } else {
                    $success = 'کد تأیید به شماره موبایل ارسال شد.';
                    $otp_sent = true;
                    casting_otp_clear_session('reset');
                    $otp_verified = false;
                }
            }
        } elseif ($otp_action === 'verify') {
            $otp_sent = true;
            $verify = casting_otp_verify('reset', $norm, (string) ($_POST['otp_code'] ?? ''));
            if (!$verify['ok']) {
                $error = $verify['error'];
                casting_rate_limit_hit('forgot_password');
            } else {
                casting_otp_mark_session_verified('reset', $norm);
                $otp_verified = true;
                $success = 'موبایل تأیید شد. رمز عبور جدید را دو بار وارد کنید.';
                casting_rate_limit_clear('otp_send');
            }
        } else {
            $result = casting_reset_password_with_otp(
                $norm,
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password2'] ?? '')
            );
            if (!$result['ok']) {
                $error = $result['error'];
                $otp_verified = casting_otp_session_is_verified('reset', $norm);
                $otp_sent = true;
                casting_rate_limit_hit('forgot_password');
            } else {
                casting_rate_limit_clear('forgot_password');
                casting_rate_limit_clear('otp_send');
                casting_set_flash('success', 'رمز عبور تغییر کرد. حالا وارد شوید.');
                casting_redirect('login.php');
            }
        }
    }
} else {
    $mobile = (string) ($_GET['mobile'] ?? '');
}

$mobile = casting_normalize_mobile($mobile);
if (!$otp_verified && $mobile !== '' && casting_otp_session_is_verified('reset', $mobile)) {
    $otp_verified = true;
    $otp_sent = true;
}

casting_render_head('فراموشی رمز عبور', 'page-login');
casting_render_header('login');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<main class="wrap panel-page">
  <section class="panel">
    <h1>فراموشی رمز عبور</h1>
    <?php if ($otp_verified) : ?>
      <p class="lede">رمز عبور جدید را وارد کنید و یک‌بار تکرار کنید.</p>
    <?php elseif ($otp_sent) : ?>
      <p class="lede">کد ۶ رقمی پیامک‌شده را وارد کنید.</p>
    <?php else : ?>
      <p class="lede">شماره موبایل ثبت‌شده در حساب را وارد کنید تا کد تأیید برایتان پیامک شود.</p>
    <?php endif; ?>

    <form class="form" method="post" action="forgot-password.php">
      <?php wp_nonce_field('casting_forgot'); ?>
      <div class="field">
        <label for="mobile">موبایل</label>
        <input id="mobile" name="mobile" type="tel" required inputmode="numeric" pattern="09[0-9]{9}" value="<?= casting_e($mobile) ?>" placeholder="09121234567" autocomplete="tel-national" <?= $otp_verified ? 'readonly' : '' ?>>
      </div>

      <?php if ($otp_verified) : ?>
        <div class="field">
          <label for="password">رمز عبور جدید</label>
          <div class="password-field">
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" data-password-input>
            <button type="button" class="password-toggle" data-password-toggle aria-label="نمایش رمز عبور" title="نمایش رمز عبور" aria-pressed="false">
              <svg class="password-toggle-icon password-toggle-icon--show" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                <path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                <circle fill="none" stroke="currentColor" stroke-width="1.8" cx="12" cy="12" r="3"/>
              </svg>
              <svg class="password-toggle-icon password-toggle-icon--hide" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" hidden>
                <path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-4.4M9.9 5.1A10.5 10.5 0 0 1 12 5c6.5 0 10 7 10 7a17.3 17.3 0 0 1-4.1 4.7M6.1 6.1A17.5 17.5 0 0 0 2 12s3.5 7 10 7a10.4 10.4 0 0 0 4.2-.9"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="field">
          <label for="password2">تکرار رمز عبور</label>
          <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
        </div>
        <button class="btn btn-primary" type="submit" name="otp_action" value="save">ذخیره رمز جدید</button>
      <?php elseif ($otp_sent) : ?>
        <div class="field">
          <label for="otp_code">کد تأیید</label>
          <input id="otp_code" name="otp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="۶ رقم" value="<?= casting_e((string) ($_POST['otp_code'] ?? '')) ?>">
        </div>
        <div class="cta-row">
          <button class="btn btn-primary" type="submit" name="otp_action" value="verify">تأیید کد</button>
          <button class="btn btn-ghost" type="submit" name="otp_action" value="send">ارسال مجدد کد</button>
        </div>
      <?php else : ?>
        <button class="btn btn-primary" type="submit" name="otp_action" value="send">ارسال کد تأیید</button>
      <?php endif; ?>
    </form>

    <p class="form-foot">
      <a href="login.php">بازگشت به ورود</a>
    </p>
  </section>
</main>
<?php casting_render_footer(); ?>
