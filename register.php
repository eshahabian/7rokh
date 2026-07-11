<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if (casting_current_user()) {
    casting_redirect('index.php');
}

$error = '';
$name = '';
$email = '';
$role = 'talent';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_register')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
    } else {
        $name = (string) ($_POST['name'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');
        $role = (string) ($_POST['role'] ?? 'talent');

        if ($password !== $password2) {
            $error = 'تکرار رمز عبور مطابقت ندارد.';
        } else {
            $result = casting_register_user($name, $email, $password, $role);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', 'ثبت‌نام موفق بود. حالا وارد شوید.');
                if ($result['role'] === 'talent') {
                    casting_redirect('login-talent.php');
                }
                casting_redirect('login-employer.php');
            }
        }
    }
}

casting_render_head('ثبت‌نام', 'page-register');
casting_render_header('register');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<main class="wrap panel-page">
  <section class="panel">
    <h1>ثبت‌نام</h1>
    <p class="lede">نقش خود را انتخاب کنید؛ بعداً از درگاه مناسب وارد می‌شوید.</p>

    <form class="form" method="post" action="" data-loading>
      <?php wp_nonce_field('casting_register'); ?>

      <div class="field">
        <label for="name">نام و نام خانوادگی</label>
        <input id="name" name="name" type="text" required autocomplete="name" value="<?= casting_e($name) ?>">
      </div>

      <div class="field">
        <label for="email">ایمیل</label>
        <input id="email" name="email" type="email" required autocomplete="email" value="<?= casting_e($email) ?>">
      </div>

      <div class="field">
        <label for="password">رمز عبور</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
      </div>

      <div class="field">
        <label for="password2">تکرار رمز عبور</label>
        <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
      </div>

      <fieldset class="field">
        <legend style="font-size:0.9rem;font-weight:500;margin-bottom:0.4rem">نقش شما</legend>
        <div class="role-grid">
          <?php foreach (CASTING_ROLES as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="role" value="<?= casting_e($key) ?>" <?= $role === $key ? 'checked' : '' ?> required>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <button class="btn btn-primary" type="submit">ایجاد حساب</button>
    </form>

    <p class="form-foot">
      قبلاً ثبت‌نام کرده‌اید؟
      <a href="login-talent.php">ورود هنرجو</a>
      ·
      <a href="login-employer.php">ورود کارفرما</a>
    </p>
  </section>
</main>
<?php casting_render_footer(); ?>
