<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
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
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rate_error = casting_rate_limit_check('login');
    if ($rate_error !== null) {
        $error = $rate_error;
    } elseif (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_login')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
    } else {
        $login = (string) ($_POST['login'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $result = casting_login($login, $password);
        if (!$result['ok']) {
            casting_rate_limit_hit('login');
            $error = $result['error'];
        } else {
            casting_rate_limit_clear('login');
            casting_redirect(casting_dashboard_for_role((string) $result['role']));
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
casting_render_flash();
?>
<main class="wrap panel-page">
  <section class="panel">
    <h1>ورود</h1>
    <p class="lede">با نام کاربری یا ایمیل وارد شوید. بعد از ورود به پنل خودتان هدایت می‌شوید.</p>

    <form class="form" method="post" action="login.php">
      <?php wp_nonce_field('casting_login'); ?>

      <div class="field">
        <label for="login">نام کاربری یا ایمیل</label>
        <input id="login" name="login" type="text" required autocomplete="username" value="<?= casting_e($login) ?>">
      </div>

      <div class="field">
        <label for="password">رمز عبور</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
      </div>

      <p class="form-inline-link">
        <a href="forgot-password.php">فراموشی رمز عبور؟</a>
      </p>

      <button class="btn btn-primary" type="submit">ورود</button>
    </form>

    <p class="form-foot">
      حساب ندارید؟ <a href="register.php">عضویت</a>
    </p>
  </section>
</main>
<?php casting_render_footer(); ?>
