<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
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

$counts = casting_member_counts();

casting_render_head('خانه', 'page-home');
casting_render_header('home');
?>
<main class="wrap hero">
  <div class="hero-copy">
    <p class="hero-lead">هنرجویان استعدادشان را ثبت می‌کنند؛ کارگردان‌ها و تهیه‌کنندگان برای پروژه انتخاب می‌کنند.</p>
    <div class="cta-row hero-cta">
      <a class="btn btn-primary" href="register.php">ثبت‌نام</a>
      <a class="btn btn-primary" href="chat.php">تالار گفتگو</a>
    </div>

    <div class="home-stats" aria-label="آمار اعضا">
      <div class="stat-item">
        <strong><?= (int) $counts['talents'] ?></strong>
        <span>هنرجو</span>
      </div>
      <div class="stat-item">
        <strong><?= (int) $counts['employers'] ?></strong>
        <span>کارفرما</span>
      </div>
      <div class="stat-item">
        <strong><?= (int) $counts['total'] ?></strong>
        <span>کل اعضا</span>
      </div>
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
        <a class="btn btn-primary" href="login-employer.php">ورود کارفرما</a>
      </article>
    </div>
  </div>
</main>
<?php casting_render_footer(); ?>
