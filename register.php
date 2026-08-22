<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/rules-content.php';
if (is_file(__DIR__ . '/includes/webhook.php')) {
    require_once __DIR__ . '/includes/webhook.php';
}
require_once __DIR__ . '/includes/layout.php';

casting_nocache();

$intent = sanitize_key((string) ($_GET['intent'] ?? $_POST['intent'] ?? ''));
if ($intent === 'cart') {
    $_SESSION['casting_login_intent'] = 'cart';
}

$register_path = sanitize_key((string) ($_GET['path'] ?? $_POST['path'] ?? ''));
if (!in_array($register_path, ['talent', 'hire'], true)) {
    $register_path = '';
}

$error = '';
$focus_field = '';
$invalid_fields = [];
$password_mismatch = false;
$otp_notice = '';
$username = '';
$mobile = '';
$referral_code = '';

$reg_invalid = static function (string $key) use (&$invalid_fields): string {
    return in_array($key, $invalid_fields, true) ? ' is-invalid' : '';
};

$apply_register_draft = static function () use (&$username, &$mobile, &$referral_code): void {
    $draft = casting_register_draft_get();
    if ($draft === []) {
        return;
    }
    if ($username === '' && isset($draft['username'])) {
        $username = (string) $draft['username'];
    }
    if ($mobile === '' && isset($draft['mobile'])) {
        $mobile = (string) $draft['mobile'];
    }
    if ($referral_code === '' && isset($draft['referral_code'])) {
        $referral_code = (string) $draft['referral_code'];
    }
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $apply_register_draft();
}

$current = casting_current_user();
if ($current) {
    $existing_role = casting_get_user_role((int) $current->ID);
    if ($existing_role === 'talent' || casting_is_employer_role($existing_role)) {
        casting_redirect('home.php');
    }
}

if ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    $otp_action = sanitize_key((string) ($_POST['otp_action'] ?? ''));
    $is_otp_only = $otp_action === 'send' || $otp_action === 'verify';

    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_register')) {
        $error = 'نشست منقضی شده. یک‌بار صفحه را رفرش کنید و دوباره فرم را بفرستید.';
    } else {
        $username = (string) ($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');
        $mobile = (string) ($_POST['mobile'] ?? '');
        $referral_code = (string) ($_POST['referral_code'] ?? '');
        casting_register_draft_save($_POST);

        $mobile_norm = casting_normalize_mobile($mobile);
        $otp_enabled = casting_mobile_otp_enabled();

        if ($is_otp_only) {
            if (!$otp_enabled) {
                $error = 'تأیید موبایل موقتاً غیرفعال است؛ مستقیم ثبت‌نام را کامل کنید.';
            } else {
                $rate_error = casting_rate_limit_check('otp_send');
                if ($rate_error !== null) {
                    $error = $rate_error;
                } elseif ($mobile_norm === '' || !preg_match('/^09\d{9}$/', $mobile_norm)) {
                    $error = 'شماره موبایل را درست وارد کنید.';
                    $focus_field = 'mobile';
                    $invalid_fields = ['mobile'];
                    casting_rate_limit_hit('otp_send');
                } elseif (casting_mobile_is_taken($mobile_norm)) {
                    $error = 'این شماره موبایل قبلاً ثبت شده است.';
                    $focus_field = 'mobile';
                    $invalid_fields = ['mobile'];
                    casting_rate_limit_hit('otp_send');
                } elseif ($otp_action === 'send') {
                    // کپچا فقط هنگام ارسال کد
                    $captcha = casting_captcha_verify(
                        (string) ($_POST['captcha_answer'] ?? ''),
                        (string) ($_POST['captcha_token'] ?? '')
                    );
                    if (!$captcha['ok']) {
                        $error = $captcha['error'];
                        $focus_field = 'captcha_answer';
                        $invalid_fields = ['captcha_answer'];
                    } else {
                        $send = casting_otp_send('register', $mobile_norm);
                        if (!$send['ok']) {
                            $error = (string) ($send['error'] ?? 'ارسال کد ناموفق بود.');
                            casting_rate_limit_hit('otp_send');
                        } else {
                            casting_captcha_mark_register_passed($mobile_norm);
                            $otp_notice = 'کد تأیید به موبایل ارسال شد.';
                        }
                    }
                } else {
                    // تأیید کد — بدون کپچای دوباره
                    $verify = casting_otp_verify('register', $mobile_norm, (string) ($_POST['otp_code'] ?? ''));
                    if (!$verify['ok']) {
                        $error = (string) ($verify['error'] ?? 'کد تأیید نادرست است.');
                        $focus_field = 'otp_code';
                        $invalid_fields = ['otp_code'];
                        casting_rate_limit_hit('otp_send');
                    } else {
                        casting_otp_mark_session_verified('register', $mobile_norm);
                        if (!casting_captcha_register_passed_for($mobile_norm)) {
                            casting_captcha_mark_register_passed($mobile_norm);
                        }
                        $otp_notice = 'موبایل تأیید شد. حالا نام کاربری و رمز را وارد کنید.';
                    }
                }
            }
        } else {
            $rate_error = casting_rate_limit_check('register');
            if ($rate_error !== null) {
                $error = $rate_error;
            } else {
                $otp_ok = !$otp_enabled || casting_otp_session_is_verified('register', $mobile_norm);
                if ($otp_enabled && !$otp_ok) {
                    $error = 'ابتدا موبایل را با کد پیامک تأیید کنید.';
                    $focus_field = 'mobile';
                    $invalid_fields = ['mobile', 'otp_code'];
                }

                // اگر موبایل تأیید شده، کپچا از قبل در مرحله پیامک پاس شده
                $captcha_ok = false;
                if ($error === '') {
                    if ($otp_enabled && casting_captcha_register_passed_for($mobile_norm)) {
                        $captcha_ok = true;
                    } elseif (!$otp_enabled) {
                        $captcha = casting_captcha_verify(
                            (string) ($_POST['captcha_answer'] ?? ''),
                            (string) ($_POST['captcha_token'] ?? '')
                        );
                        $captcha_ok = !empty($captcha['ok']);
                        if (!$captcha_ok) {
                            $error = (string) ($captcha['error'] ?? 'کد امنیتی را درست وارد کنید.');
                            $focus_field = 'captcha_answer';
                            $invalid_fields = ['captcha_answer'];
                        }
                    } else {
                        $error = 'ابتدا موبایل را تأیید کنید (کپچا در همان مرحله ارسال کد لازم است).';
                        $focus_field = 'mobile';
                        $invalid_fields = ['mobile'];
                    }
                }

                if ($error === '') {
                    $issues = casting_register_collect_required_issues([
                        'username'       => $username,
                        'password'       => $password,
                        'password2'      => $password2,
                        'mobile'         => $mobile,
                        'rules_accepted' => !empty($_POST['rules_accepted']),
                        'captcha_ok'     => $captcha_ok,
                    ]);
                    if ($issues['errors'] !== []) {
                        $invalid_fields = $issues['fields'];
                        $password_mismatch = in_array('password2', $invalid_fields, true);
                        $error = 'لطفاً فیلدهای ستاره‌دار را کامل کنید: ' . implode(' · ', $issues['errors']);
                        $focus_field = $invalid_fields[0] ?? '';
                    } elseif (casting_mobile_is_taken($mobile_norm)) {
                        $error = 'این شماره موبایل قبلاً ثبت شده است.';
                        $focus_field = 'mobile';
                        $invalid_fields = ['mobile'];
                    }
                }

                if ($error === '' && !$password_mismatch && $otp_ok) {
                    if (!function_exists('casting_validate_referral_code_for_register')) {
                        require_once __DIR__ . '/includes/referral.php';
                    }
                    $referral_check = casting_validate_referral_code_for_register($referral_code);
                    if (!$referral_check['ok']) {
                        $error = (string) ($referral_check['error'] ?? 'کد معرفی معتبر نیست.');
                        $focus_field = 'referral_code';
                        $invalid_fields = ['referral_code'];
                    }
                }

                if ($error === '' && !$password_mismatch && $otp_ok) {
                    try {
                        $role = casting_register_role_from_path($register_path);
                        $email = casting_register_placeholder_email($username);
                        $display_name = $username;
                        $result = casting_register_user($display_name, $username, $email, $password, $role);
                        if (!$result['ok']) {
                            $error = (string) ($result['error'] ?? 'ثبت‌نام ناموفق بود.');
                            $focus_field = casting_register_focus_for_error($error);
                            if ($focus_field !== '') {
                                $invalid_fields = [$focus_field];
                            }
                        } else {
                            $user_id = (int) $result['user_id'];
                            if (trim($referral_code) !== '' && function_exists('casting_apply_referral_code')) {
                                casting_apply_referral_code($user_id, $referral_code);
                            }
                            casting_mark_mobile_verified($user_id, $mobile_norm);
                            casting_register_draft_clear();
                            casting_otp_clear_session('register');
                            casting_captcha_clear_register_passed();
                            casting_rate_limit_clear('register');
                            casting_rate_limit_clear('otp_send');
                            if (function_exists('casting_notify_n8n_registration')) {
                                casting_notify_n8n_registration($user_id);
                            }
                            if (!empty($_SESSION['casting_flash'])) {
                                unset($_SESSION['casting_flash']);
                            }
                            $login = casting_login($username, $password, '', true);
                            if (!empty($login['ok'])) {
                                casting_rate_limit_clear('login');
                                casting_set_flash('success', 'ثبت‌نام و ورود با موفقیت انجام شد.');
                                $after_intent = (string) ($_SESSION['casting_login_intent'] ?? '');
                                unset($_SESSION['casting_login_intent']);
                                if ($after_intent === 'cart') {
                                    casting_redirect('cart.php');
                                }
                                casting_redirect('edit-profile.php?welcome=1');
                            }
                            casting_redirect('login.php?registered=1' . (((string) ($_SESSION['casting_login_intent'] ?? '')) === 'cart' ? '&intent=cart' : ''));
                        }
                    } catch (Throwable $e) {
                        $error = 'خطای سرور در ثبت‌نام: ' . $e->getMessage();
                    }
                }

                if ($error !== '' || $password_mismatch) {
                    casting_rate_limit_hit('register');
                }
            }
        }
    }
}

$otp_enabled = casting_mobile_otp_enabled();
$mobile_norm_view = casting_normalize_mobile($mobile);
$mobile_verified = $otp_enabled && casting_otp_session_is_verified('register', $mobile_norm_view);
$account_unlocked = !$otp_enabled || $mobile_verified;
$rules_accepted = !empty($_POST['rules_accepted']) || !empty(casting_register_draft_get()['rules_accepted']);
$captcha_passed = $mobile_norm_view !== '' && casting_captcha_register_passed_for($mobile_norm_view);
// کپچا فقط قبل از ارسال کد (یا وقتی OTP خاموش است)
$show_captcha = !$otp_enabled || (!$mobile_verified && !$captcha_passed);

casting_render_head('ثبت‌نام', 'page-register');
casting_render_header('register');

if ($current && casting_get_user_role((int) $current->ID) === '') {
    echo '<div class="flash flash-error" role="alert">' . casting_brandify('شما با یک حساب وردپرس وارد هستید که نقش ۷ رخ ندارد. اول خارج شوید، بعد اینجا ثبت‌نام کنید.') . ' <a href="' . casting_e(wp_logout_url(casting_url('register.php'))) . '">خروج</a></div>';
}
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($otp_notice !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($otp_notice) . '</div>';
}
?>
<main class="wrap panel-page">
  <section class="panel panel-narrow">
    <h1><?= $register_path === 'hire' ? 'ثبت‌نام کارفرما' : ($register_path === 'talent' ? 'ثبت‌نام هنرمند' : 'ثبت‌نام') ?></h1>
    <p class="lede">
      <?= $otp_enabled
          ? 'اول شماره موبایل را تأیید کنید؛ بعد نام کاربری و رمز را می‌سازید.'
          : 'با نام کاربری، رمز عبور و شماره موبایل حساب بسازید.' ?>
    </p>
    <p class="lede-req-note" role="note">موارد ستاره‌دار الزامی می‌باشد.</p>

    <form class="form" method="post" action="register.php" autocomplete="on" data-register-form<?= $focus_field !== '' ? ' data-focus-field="' . casting_e($focus_field) . '"' : '' ?><?= $invalid_fields !== [] ? ' data-invalid-fields="' . casting_e(implode(',', $invalid_fields)) . '"' : '' ?>>
      <?php wp_nonce_field('casting_register'); ?>
      <?php if ($register_path !== '') : ?>
        <input type="hidden" name="path" value="<?= casting_e($register_path) ?>">
      <?php endif; ?>

      <fieldset class="register-step register-step-mobile">
        <legend class="register-step-title">۱) تأیید موبایل</legend>

        <div class="field<?= $reg_invalid('mobile') ?>">
          <label for="mobile">موبایل <span class="req-mark">*</span></label>
          <input id="mobile" name="mobile" type="tel" required inputmode="numeric" pattern="09[0-9]{9}" value="<?= casting_e($mobile) ?>" placeholder="09121234567" autocomplete="tel-national"<?= $mobile_verified ? ' readonly' : '' ?>>
          <p class="field-hint">این شماره برای دیگر اعضا نمایش داده نمی‌شود.</p>
          <?php if ($mobile_verified) : ?>
            <p class="field-hint otp-verified-hint">موبایل تأیید شد ✓</p>
          <?php endif; ?>
        </div>

        <?php if ($otp_enabled && !$mobile_verified) : ?>
          <?php if ($show_captcha) : ?>
            <?php casting_render_captcha_field(trim($reg_invalid('captcha_answer'))); ?>
          <?php endif; ?>

          <div class="otp-verify-block<?= $reg_invalid('otp_code') ?>">
            <div class="field">
              <label for="otp_code">کد تأیید موبایل <span class="req-mark">*</span></label>
              <input id="otp_code" name="otp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="۶ رقم پیامک‌شده">
            </div>
            <div class="cta-row otp-actions">
              <button class="btn btn-ghost" type="submit" name="otp_action" value="send" formnovalidate>ارسال کد پیامک</button>
              <button class="btn btn-ghost" type="submit" name="otp_action" value="verify" formnovalidate>تأیید کد</button>
            </div>
            <p class="field-hint"><?= $captcha_passed
                ? 'کد پیامک را وارد کنید و «تأیید کد» را بزنید. بقیه فیلدها بعد از تأیید فعال می‌شوند.'
                : 'اول کپچا را پر کنید و «ارسال کد» را بزنید؛ بعد کد پیامک را تأیید کنید.' ?></p>
          </div>
        <?php elseif ($otp_enabled) : ?>
          <input type="hidden" name="otp_code" value="verified">
        <?php elseif ($show_captcha) : ?>
          <?php casting_render_captcha_field(trim($reg_invalid('captcha_answer'))); ?>
        <?php endif; ?>
      </fieldset>

      <fieldset class="register-step register-step-account<?= $account_unlocked ? '' : ' is-locked' ?>"<?= $account_unlocked ? '' : ' disabled' ?>>
        <legend class="register-step-title">۲) ساخت حساب</legend>
        <?php if (!$account_unlocked) : ?>
          <p class="field-hint register-step-lock-hint">بعد از تأیید موبایل این بخش فعال می‌شود.</p>
        <?php endif; ?>

        <div class="field<?= $reg_invalid('username') ?>">
          <label for="username">نام کاربری <span class="req-mark">*</span></label>
          <input id="username" name="username" type="text"<?= $account_unlocked ? ' required' : '' ?> minlength="3" autocomplete="username" pattern="[A-Za-z0-9._\-]+" title="فقط حروف انگلیسی، عدد، نقطه، خط تیره" value="<?= casting_e($username) ?>">
          <p class="field-hint">با همین نام کاربری بعداً وارد می‌شوید</p>
        </div>

        <div class="form-grid">
          <div class="field<?= $reg_invalid('password') ?>">
            <label for="password">رمز عبور (حداقل ۸ کاراکتر) <span class="req-mark">*</span></label>
            <input id="password" name="password" type="password"<?= $account_unlocked ? ' required' : '' ?> minlength="8" autocomplete="new-password" data-password-source>
          </div>
          <div class="field<?= $reg_invalid('password2') ?>" data-password-confirm-field<?= $password_mismatch ? ' is-invalid' : '' ?>>
            <label for="password2">تکرار رمز عبور <span class="req-mark">*</span></label>
            <div class="field-control field-control--password-confirm">
              <input id="password2" name="password2" type="password"<?= $account_unlocked ? ' required' : '' ?> minlength="8" autocomplete="new-password" data-password-confirm<?= $password_mismatch ? ' aria-invalid="true"' : '' ?>>
              <span class="field-inline-error" data-password-mismatch-msg role="alert"<?= $password_mismatch ? '' : ' hidden' ?>>پسورد یکسان نیست</span>
            </div>
          </div>
        </div>

        <div class="field<?= $reg_invalid('referral_code') ?>">
          <label for="referral_code">کد معرفی (اختیاری)</label>
          <input id="referral_code" name="referral_code" type="text" maxlength="32" autocomplete="off" dir="ltr" value="<?= casting_e($referral_code) ?>" placeholder="7ROKHAB12CD34">
        </div>

        <div class="field rules-consent-field<?= $reg_invalid('rules_accepted') ?>" data-rules-consent>
          <label class="checkbox-row">
            <input type="checkbox" name="rules_accepted" value="1" id="rules_accepted" data-rules-consent-checkbox<?= $rules_accepted ? ' checked' : '' ?>>
            <span>قوانین را مطالعه کرده‌ام و می‌پذیرم. <span class="req-mark">*</span> <button type="button" class="link-button" data-rules-lightbox-open>مطالعه قوانین</button></span>
          </label>
        </div>

        <button class="btn btn-primary" type="submit" name="casting_submit" value="1" data-register-submit<?= $account_unlocked ? '' : ' disabled' ?>>ایجاد حساب</button>
        <p class="field-hint">برای فعال شدن دکمه، تیک «پذیرش قوانین» را بزنید.</p>
      </fieldset>
    </form>

    <p class="form-foot">
      قبلاً ثبت‌نام کرده‌اید؟ <a href="login.php">ورود به پنل کاربری</a>
    </p>
  </section>
</main>
<div class="rules-lightbox" data-rules-lightbox aria-hidden="true">
  <div class="rules-lightbox-panel" role="dialog" aria-modal="true" aria-labelledby="rules-lightbox-title">
    <button type="button" class="rules-lightbox-close" data-rules-lightbox-close aria-label="بستن">×</button>
    <h2 class="rules-lightbox-title" id="rules-lightbox-title">قوانین <?= casting_brand_html() ?></h2>
    <?php casting_render_rules_list(); ?>
  </div>
</div>
<?php casting_render_footer(); ?>
