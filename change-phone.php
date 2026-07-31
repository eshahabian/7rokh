<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$error = '';
$success = '';
$mobile = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile', true));
$new_mobile = '';
$otp_sent = false;
$step = 'request';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_phone')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $action = sanitize_key((string) ($_POST['phone_action'] ?? 'send'));
        $new_mobile = casting_normalize_mobile((string) ($_POST['mobile'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $rate_error = casting_rate_limit_check('change_phone');
        if ($rate_error !== null) {
            $error = $rate_error;
        } elseif ($action === 'confirm') {
            $result = casting_change_phone_confirm(
                $user_id,
                $password,
                $new_mobile,
                (string) ($_POST['otp_code'] ?? '')
            );
            if (!$result['ok']) {
                casting_rate_limit_hit('change_phone');
                $error = $result['error'];
                $otp_sent = true;
                $step = 'confirm';
            } else {
                casting_rate_limit_clear('change_phone');
                casting_set_flash('success', 'شماره تلفن با موفقیت تأیید و ذخیره شد.');
                casting_redirect('change-phone.php');
            }
        } else {
            $result = casting_change_phone_request_otp($user_id, $password, $new_mobile);
            if (!$result['ok']) {
                casting_rate_limit_hit('change_phone');
                $error = $result['error'];
            } else {
                casting_rate_limit_hit('otp_send');
                $success = 'کد تأیید به شماره جدید ارسال شد.';
                $otp_sent = true;
                $step = 'confirm';
            }
        }
    }
}

$mobile = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile', true));
$verified = casting_user_mobile_is_verified($user_id);

casting_render_panel_start('تغییر شماره تلفن', 'phone');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-narrow">
  <?php casting_render_panel_heading('تغییر شماره تلفن'); ?>
  <p class="meta">
    شماره فعلی:
    <strong dir="ltr"><?= $mobile !== '' ? casting_e($mobile) : '—' ?></strong>
    <?php if ($mobile !== '') : ?>
      <span class="meta">(<?= $verified ? 'تأییدشده' : 'تأییدنشده' ?>)</span>
    <?php endif; ?>
  </p>
  <form class="form" method="post" action="change-phone.php">
    <?php wp_nonce_field('casting_phone'); ?>
    <div class="field">
      <label for="mobile">شماره موبایل جدید</label>
      <input id="mobile" name="mobile" type="tel" required inputmode="numeric" pattern="09[0-9]{9}" value="<?= casting_e($new_mobile !== '' ? $new_mobile : '') ?>" placeholder="09121234567" autocomplete="tel-national" <?= $otp_sent ? 'readonly' : '' ?>>
    </div>
    <div class="field">
      <label for="password">رمز عبور برای تأیید</label>
      <div class="password-field">
        <input id="password" name="password" type="password" required autocomplete="current-password" data-password-input>
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
    <?php if ($otp_sent || $step === 'confirm') : ?>
      <div class="field">
        <label for="otp_code">کد تأیید پیامک‌شده</label>
        <input id="otp_code" name="otp_code" type="text" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="۶ رقم">
      </div>
      <div class="cta-row">
        <button class="btn btn-primary" type="submit" name="phone_action" value="confirm">تأیید و ذخیره</button>
        <button class="btn btn-ghost" type="submit" name="phone_action" value="send">ارسال مجدد کد</button>
      </div>
    <?php else : ?>
      <button class="btn btn-primary" type="submit" name="phone_action" value="send">ارسال کد تأیید</button>
    <?php endif; ?>
  </form>
</section>
<?php casting_render_panel_end(); ?>
