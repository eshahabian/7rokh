<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

casting_nocache();

$user = casting_current_user();
if ($user && casting_get_user_role((int) $user->ID) !== '') {
    casting_redirect(casting_dashboard_for_role(casting_get_user_role((int) $user->ID)));
}

$error = '';
$success = '';
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_forgot')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
    } else {
        $login = (string) ($_POST['login'] ?? '');
        $result = casting_request_password_reset($login);
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            $success = $result['message'];
            $login = '';
        }
    }
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
    <p class="lede">نام کاربری یا ایمیل حساب خود را وارد کنید تا لینک بازیابی برایتان ارسال شود.</p>

    <form class="form" method="post" action="forgot-password.php">
      <?php wp_nonce_field('casting_forgot'); ?>
      <div class="field">
        <label for="login">نام کاربری یا ایمیل</label>
        <input id="login" name="login" type="text" required autocomplete="username" value="<?= casting_e($login) ?>">
      </div>
      <button class="btn btn-primary" type="submit">ارسال لینک بازیابی</button>
    </form>

    <p class="form-foot">
      <a href="login.php">بازگشت به ورود</a>
    </p>
  </section>
</main>
<?php casting_render_footer(); ?>
