<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

casting_nocache();

$login = sanitize_user((string) ($_GET['login'] ?? $_POST['login'] ?? ''), true);
$key = trim((string) ($_GET['key'] ?? $_POST['key'] ?? ''));
$error = '';
$ready = false;

if ($login !== '' && $key !== '') {
    $check = check_password_reset_key($key, $login);
    if (!is_wp_error($check) && casting_get_user_role((int) $check->ID) !== '') {
        $ready = true;
    } else {
        $error = 'لینک بازیابی منقضی یا نامعتبر است. دوباره از صفحه فراموشی رمز درخواست دهید.';
    }
} else {
    $error = 'لینک بازیابی ناقص است.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_reset')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
        $ready = false;
    } else {
        $result = casting_reset_password_with_key(
            $login,
            $key,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password2'] ?? '')
        );
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            casting_set_flash('success', 'رمز عبور تغییر کرد. حالا وارد شوید.');
            casting_redirect('login.php');
        }
    }
}

casting_render_head('تعیین رمز جدید', 'page-login');
casting_render_header('login');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<main class="wrap panel-page">
  <section class="panel">
    <h1>تعیین رمز جدید</h1>
    <?php if ($ready) : ?>
      <p class="lede">رمز عبور جدید حساب خود را وارد کنید.</p>
      <form class="form" method="post" action="reset-password.php">
        <?php wp_nonce_field('casting_reset'); ?>
        <input type="hidden" name="login" value="<?= casting_e($login) ?>">
        <input type="hidden" name="key" value="<?= casting_e($key) ?>">
        <div class="field">
          <label for="password">رمز عبور جدید</label>
          <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="field">
          <label for="password2">تکرار رمز عبور</label>
          <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
        </div>
        <button class="btn btn-primary" type="submit">ذخیره رمز جدید</button>
      </form>
    <?php else : ?>
      <p class="form-foot"><a href="forgot-password.php">درخواست دوباره لینک بازیابی</a></p>
    <?php endif; ?>
  </section>
</main>
<?php casting_render_footer(); ?>
