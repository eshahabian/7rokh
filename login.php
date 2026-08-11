<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/layout.php';

casting_nocache();

$user = casting_current_user();
if ($user) {
    $role = casting_get_user_role((int) $user->ID);
    if ($role !== '') {
        casting_redirect(casting_dashboard_for_role($role));
    }
}

$error = '';
$success = '';
$login = '';
$mobile = '';
$mode = ((string) ($_GET['mode'] ?? $_POST['mode'] ?? 'password')) === 'otp' ? 'otp' : 'password';
$otp_sent = false;
$need_confirm = false;
$force_login = !empty($_POST['force_login']);

$intent = sanitize_key((string) ($_GET['intent'] ?? $_POST['intent'] ?? ''));
if ($intent === 'cart') {
    $_SESSION['casting_login_intent'] = 'cart';
}
$login_intent = (string) ($_SESSION['casting_login_intent'] ?? '');
$intent_notice = '';
if ($login_intent === 'cart' && $error === '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $intent_notice = 'برای خرید ابتدا وارد شوید.';
}

$login_redirect_after = static function (string $role): void {
    $intent = (string) ($_SESSION['casting_login_intent'] ?? '');
    unset($_SESSION['casting_login_intent']);
    if ($intent === 'cart') {
        casting_redirect('cart.php');
    }
    casting_redirect(casting_dashboard_for_role($role));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = ((string) ($_POST['mode'] ?? 'password')) === 'otp' ? 'otp' : 'password';
    $otp_action = sanitize_key((string) ($_POST['otp_action'] ?? ''));

    if ($mode === 'otp') {
        $rate_action = $otp_action === 'send' ? 'otp_send' : 'login_otp';
        $rate_error = casting_rate_limit_check($rate_action);
        if ($rate_error !== null) {
            $error = $rate_error;
        } elseif (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_login_otp')) {
            $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
        } else {
            $mobile = (string) ($_POST['mobile'] ?? '');
            if ($otp_action === 'send') {
                $norm = casting_normalize_mobile($mobile);
                $found = casting_find_user_by_mobile($norm);
                if (empty($found['ok'])) {
                    // پیام عمومی — برای جلوگیری از افشا، مثل ارسال موفق رفتار می‌کنیم
                    $success = 'اگر حسابی با این موبایل باشد، کد تأیید ارسال می‌شود.';
                    $otp_sent = true;
                } else {
                    $send = casting_otp_send('login', $norm);
                    if (!$send['ok']) {
                        $error = $send['error'];
                        casting_rate_limit_hit('otp_send');
                    } else {
                        $success = 'کد تأیید به شماره موبایل ارسال شد.';
                        $otp_sent = true;
                    }
                }
            } else {
                $result = casting_login_with_otp($mobile, (string) ($_POST['otp_code'] ?? ''), $force_login);
                if (!$result['ok']) {
                    $need_confirm = !empty($result['need_confirm']);
                    if (!$need_confirm) {
                        casting_rate_limit_hit('login_otp');
                    }
                    $error = $result['error'] ?? 'ورود ناموفق بود.';
                    $otp_sent = true;
                } else {
                    casting_rate_limit_clear('login_otp');
                    casting_rate_limit_clear('login');
                    casting_rate_limit_clear('otp_send');
                    $login_redirect_after((string) $result['role']);
                }
            }
        }
    } else {
        $rate_error = casting_rate_limit_check('login');
        if ($rate_error !== null) {
            $error = $rate_error;
        } elseif (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_login')) {
            $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
        } else {
            $login = (string) ($_POST['login'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $result = casting_login($login, $password, '', $force_login);
            if (!$result['ok']) {
                $need_confirm = !empty($result['need_confirm']);
                if (!$need_confirm) {
                    casting_rate_limit_hit('login');
                }
                $error = $result['error'];
            } else {
                casting_rate_limit_clear('login');
                casting_rate_limit_clear('login_otp');
                casting_rate_limit_clear('otp_send');
                $login_redirect_after((string) $result['role']);
            }
        }
    }
}

casting_render_head('ورود', 'page-login');
casting_render_header('login');
if (isset($_GET['registered'])) {
    echo '<div class="flash flash-success" role="alert">ثبت‌نام موفق بود. حالا وارد شوید.</div>';
}
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($intent_notice !== '') {
    echo '<div class="flash flash-error" role="status">' . casting_e($intent_notice) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<main class="wrap panel-page">
  <section class="panel">
    <h1>ورود</h1>
    <nav class="admin-tabs" aria-label="روش ورود">
      <a class="admin-tab <?= $mode === 'password' ? 'is-active' : '' ?>" href="login.php<?= $login_intent === 'cart' ? '?intent=cart' : '' ?>">ورود با رمز</a>
      <a class="admin-tab <?= $mode === 'otp' ? 'is-active' : '' ?>" href="login.php?mode=otp<?= $login_intent === 'cart' ? '&intent=cart' : '' ?>">ورود با پیامک</a>
    </nav>

    <?php if ($mode === 'otp') : ?>
      <p class="lede">شماره موبایل ثبت‌شده در حساب را وارد کنید تا کد تأیید برایتان پیامک شود.</p>
      <form class="form" method="post" action="login.php?mode=otp<?= $login_intent === 'cart' ? '&intent=cart' : '' ?>">
        <?php wp_nonce_field('casting_login_otp'); ?>
        <input type="hidden" name="mode" value="otp">
        <?php if ($login_intent === 'cart') : ?>
          <input type="hidden" name="intent" value="cart">
        <?php endif; ?>
        <div class="field">
          <label for="mobile">موبایل</label>
          <input id="mobile" name="mobile" type="tel" required inputmode="numeric" pattern="09[0-9]{9}" value="<?= casting_e(casting_normalize_mobile($mobile)) ?>" placeholder="09121234567" autocomplete="tel-national">
        </div>
        <?php if ($otp_sent || (string) ($_POST['otp_code'] ?? '') !== '') : ?>
          <div class="field">
            <label for="otp_code">کد تأیید</label>
            <input id="otp_code" name="otp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="۶ رقم" value="<?= casting_e((string) ($_POST['otp_code'] ?? '')) ?>">
          </div>
          <?php if ($need_confirm) : ?>
            <input type="hidden" name="force_login" value="1">
            <p class="lede" role="status">با ادامه، نشست دستگاه قبلی قطع می‌شود.</p>
            <div class="cta-row">
              <button class="btn btn-primary" type="submit" name="otp_action" value="verify">ادامه ورود و قطع نشست قبلی</button>
              <a class="btn btn-ghost" href="login.php?mode=otp">انصراف</a>
            </div>
          <?php else : ?>
            <div class="cta-row">
              <button class="btn btn-primary" type="submit" name="otp_action" value="verify">ورود</button>
              <button class="btn btn-ghost" type="submit" name="otp_action" value="send">ارسال مجدد کد</button>
            </div>
          <?php endif; ?>
        <?php else : ?>
          <button class="btn btn-primary" type="submit" name="otp_action" value="send">ارسال کد تأیید</button>
        <?php endif; ?>
      </form>
    <?php else : ?>
      <p class="lede">با نام کاربری یا ایمیل وارد شوید. بعد از ورود به پنل خودتان هدایت می‌شوید.</p>
      <form class="form" method="post" action="login.php<?= $login_intent === 'cart' ? '?intent=cart' : '' ?>" data-remember-credentials>
        <?php wp_nonce_field('casting_login'); ?>
        <input type="hidden" name="mode" value="password">
        <?php if ($login_intent === 'cart') : ?>
          <input type="hidden" name="intent" value="cart">
        <?php endif; ?>

        <div class="field">
          <label for="login">نام کاربری یا ایمیل</label>
          <input id="login" name="login" type="text" required autocomplete="username" value="<?= casting_e($login) ?>">
        </div>

        <div class="field">
          <label for="password">رمز عبور</label>
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

        <div class="field">
          <label class="checkbox-row" for="remember_credentials">
            <input id="remember_credentials" type="checkbox" data-remember-credentials-check>
            <span>ذخیره یوزر و پسورد</span>
          </label>
        </div>

        <p class="form-inline-link">
          <a href="forgot-password.php">فراموشی رمز عبور؟</a>
        </p>

          <?php if ($need_confirm) : ?>
            <input type="hidden" name="force_login" value="1">
            <p class="lede" role="status">با ادامه، نشست دستگاه قبلی قطع می‌شود. رمز را دوباره وارد کنید و تأیید کنید.</p>
            <div class="cta-row">
              <button class="btn btn-primary" type="submit">ادامه ورود و قطع نشست قبلی</button>
              <a class="btn btn-ghost" href="login.php">انصراف</a>
            </div>
          <?php else : ?>
            <button class="btn btn-primary" type="submit">ورود</button>
          <?php endif; ?>
      </form>
    <?php endif; ?>

    <p class="form-foot">
      حساب ندارید؟ <a href="register.php">ثبت نام</a>
    </p>
  </section>
</main>
<?php casting_render_footer(); ?>
