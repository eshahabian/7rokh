<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$error = '';
$mobile = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile', true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_phone')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $mobile = casting_normalize_mobile((string) ($_POST['mobile'] ?? ''));
        $result = casting_change_phone(
            $user_id,
            (string) ($_POST['password'] ?? ''),
            $mobile
        );
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            casting_set_flash('success', 'شماره تلفن با موفقیت تغییر کرد.');
            casting_redirect('change-phone.php');
        }
    }
}

casting_render_panel_start('تغییر شماره تلفن', 'phone');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-narrow">
  <h1>تغییر شماره تلفن</h1>
  <p class="meta">شماره فعلی: <strong dir="ltr"><?= $mobile !== '' ? casting_e($mobile) : '—' ?></strong></p>
  <form class="form" method="post" action="change-phone.php">
    <?php wp_nonce_field('casting_phone'); ?>
    <div class="field">
      <label for="mobile">شماره موبایل جدید</label>
      <input id="mobile" name="mobile" type="tel" required inputmode="numeric" pattern="09[0-9]{9}" value="<?= casting_e($mobile) ?>" placeholder="09121234567" autocomplete="tel-national">
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
    <button class="btn btn-primary" type="submit">ذخیره</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
