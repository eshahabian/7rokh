<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

casting_nocache();

$user = casting_current_user();
if ($user && casting_get_user_role((int) $user->ID) === 'talent') {
    casting_redirect('dashboard-talent.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_login_talent')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
    } else {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $result = casting_login($email, $password, 'talent');
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            casting_redirect('dashboard-talent.php');
        }
    }
}

casting_render_head('ورود هنرجو', 'page-login');
casting_render_header('talent');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<main class="wrap panel-page">
  <section class="panel">
    <h1>ورود هنرجو</h1>
    <p class="lede">فقط حساب‌های با نقش هنرجو از این درگاه وارد می‌شوند.</p>

    <form class="form" method="post" action="" data-loading>
      <?php wp_nonce_field('casting_login_talent'); ?>

      <div class="field">
        <label for="email">ایمیل</label>
        <input id="email" name="email" type="email" required autocomplete="email" value="<?= casting_e($email) ?>">
      </div>

      <div class="field">
        <label for="password">رمز عبور</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
      </div>

      <button class="btn btn-primary" type="submit">ورود</button>
    </form>

    <p class="form-foot">
      حساب ندارید؟ <a href="register.php">ثبت‌نام</a>
      ·
      کارفرما هستید؟ <a href="login-employer.php">ورود کارفرما</a>
    </p>
  </section>
</main>
<?php casting_render_footer(); ?>
