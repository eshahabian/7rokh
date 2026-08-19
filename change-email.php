<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$error = '';
$current_email = (string) $user->user_email;
$new_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_email')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $new_email = sanitize_email((string) ($_POST['email'] ?? ''));
        $rate_error = casting_rate_limit_check('change_email');
        if ($rate_error !== null) {
            $error = $rate_error;
        } else {
            $result = casting_change_email(
                $user_id,
                (string) ($_POST['password'] ?? ''),
                $new_email
            );
            if (!$result['ok']) {
                casting_rate_limit_hit('change_email');
                $error = $result['error'];
            } else {
                casting_rate_limit_clear('change_email');
                casting_set_flash('success', 'ایمیل با موفقیت به‌روز شد.');
                casting_redirect('change-email.php');
            }
        }
    }
}

$user = get_user_by('id', $user_id);
$current_email = $user instanceof WP_User ? (string) $user->user_email : $current_email;

casting_render_panel_start('تغییر ایمیل', 'email');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-narrow">
  <?php casting_render_panel_heading('تغییر ایمیل'); ?>
  <p class="meta">
    ایمیل فعلی:
    <strong dir="ltr"><?= $current_email !== '' ? casting_e($current_email) : '—' ?></strong>
  </p>
  <p class="lede">این ایمیل برای ورود، اعلان‌ها و بازیابی رمز استفاده می‌شود و برای دیگر اعضا نمایش داده نمی‌شود.</p>
  <form class="form" method="post" action="change-email.php">
    <?php wp_nonce_field('casting_email'); ?>
    <div class="field">
      <label for="email">ایمیل جدید</label>
      <input id="email" name="email" type="email" required autocomplete="email" value="<?= casting_e($new_email !== '' ? $new_email : $current_email) ?>">
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
    <button class="btn btn-primary" type="submit">ذخیره ایمیل</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
