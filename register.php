<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

casting_nocache();

$error = '';
$name = '';
$email = '';
$role = 'talent';

$current = casting_current_user();
if ($current) {
    $existing_role = casting_get_user_role((int) $current->ID);
    if ($existing_role === 'talent') {
        casting_redirect('dashboard-talent.php');
    }
    if (casting_is_employer_role($existing_role)) {
        casting_redirect('dashboard-employer.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_register')) {
        $error = 'نشست منقضی شده. یک‌بار صفحه را رفرش کنید و دوباره فرم را بفرستید.';
    } else {
        $name = (string) ($_POST['name'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');
        $role = (string) ($_POST['role'] ?? 'talent');

        if ($password !== $password2) {
            $error = 'تکرار رمز عبور مطابقت ندارد.';
        } else {
            try {
                $result = casting_register_user($name, $email, $password, $role);
                if (!$result['ok']) {
                    $error = $result['error'];
                } else {
                    // ورود خودکار بعد از ثبت‌نام + پیام واضح در URL
                    $portal = $result['role'] === 'talent' ? 'talent' : 'employer';
                    $login = casting_login($email, $password, $portal);
                    if ($login['ok']) {
                        $dest = $result['role'] === 'talent'
                            ? 'dashboard-talent.php?welcome=1'
                            : 'dashboard-employer.php?welcome=1';
                        casting_redirect($dest);
                    }

                    // اگر ورود خودکار نشد، برو به صفحه ورود با پیام موفقیت
                    $login_page = $result['role'] === 'talent' ? 'login-talent.php' : 'login-employer.php';
                    casting_redirect($login_page . '?registered=1');
                }
            } catch (Throwable $e) {
                $error = 'خطای سرور در ثبت‌نام: ' . $e->getMessage();
            }
        }
    }
}

casting_render_head('ثبت‌نام', 'page-register');
casting_render_header('register');

if ($current && casting_get_user_role((int) $current->ID) === '') {
    echo '<div class="flash flash-error" role="alert">شما با یک حساب وردپرس وارد هستید که نقش کستینگ ندارد. اول از وردپرس خارج شوید، بعد اینجا ثبت‌نام کنید. <a href="' . casting_e(wp_logout_url(casting_url('register.php'))) . '">خروج</a></div>';
}

if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
?>
<main class="wrap panel-page">
  <section class="panel">
    <h1>ثبت‌نام</h1>
    <p class="lede">نقش خود را انتخاب کنید. بعد از ثبت‌نام مستقیم وارد پنل می‌شوید.</p>

    <form class="form" method="post" action="register.php" autocomplete="on">
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
        <label for="password">رمز عبور (حداقل ۸ کاراکتر)</label>
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
              <input type="radio" name="role" value="<?= casting_e($key) ?>" <?= $role === $key ? 'checked' : '' ?>>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <button class="btn btn-primary" type="submit" name="casting_submit" value="1">ایجاد حساب</button>
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
