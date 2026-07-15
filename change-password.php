<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_password')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $result = casting_change_password(
            $user_id,
            (string) ($_POST['current'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password2'] ?? '')
        );
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            casting_set_flash('success', 'رمز عبور با موفقیت تغییر کرد.');
            casting_redirect('change-password.php');
        }
    }
}

casting_render_panel_start('تغییر رمز عبور', 'password');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-narrow">
  <h1>تغییر رمز عبور</h1>
  <form class="form" method="post" action="change-password.php">
    <?php wp_nonce_field('casting_password'); ?>
    <div class="field">
      <label for="current">رمز فعلی</label>
      <input id="current" name="current" type="password" required autocomplete="current-password">
    </div>
    <div class="field">
      <label for="password">رمز جدید</label>
      <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
    </div>
    <div class="field">
      <label for="password2">تکرار رمز جدید</label>
      <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
    </div>
    <button class="btn btn-primary" type="submit">ذخیره</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
