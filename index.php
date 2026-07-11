<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$user = casting_current_user();
if ($user) {
    $role = casting_get_user_role((int) $user->ID);
    if ($role === 'talent') {
        casting_redirect('dashboard-talent.php');
    }
    if (casting_is_employer_role($role)) {
        casting_redirect('dashboard-employer.php');
    }
}

casting_render_head('خانه', 'page-home');
casting_render_header('home');
?>
<main class="wrap hero">
  <div class="hero-copy">
    <h1><?= casting_e(casting_brand()) ?></h1>
    <p>هنرجویان استعدادشان را ثبت می‌کنند؛ کارگردان‌ها و تهیه‌کنندگان برای پروژه انتخاب می‌کنند.</p>
    <div class="cta-row">
      <a class="btn btn-primary" href="register.php">ثبت‌نام</a>
      <a class="btn btn-ghost" href="#gates">ورود به پورتال</a>
    </div>

    <div class="gates" id="gates">
      <article class="gate-card">
        <h2>ورود هنرجو</h2>
        <p>برای بازیگران و هنرجویان بازیگری و فیلم‌سازی</p>
        <a class="btn btn-primary" href="login-talent.php">ورود هنرجو</a>
      </article>
      <article class="gate-card">
        <h2>ورود کارفرما</h2>
        <p>برای کارگردان‌ها و تهیه‌کنندگان</p>
        <a class="btn btn-ghost" href="login-employer.php">ورود کارفرما</a>
      </article>
    </div>
  </div>
</main>
<?php casting_render_footer(); ?>
