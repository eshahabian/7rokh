<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/layout.php';

$user = casting_current_user();
if ($user) {
    $role = casting_get_user_role((int) $user->ID);
    if ($role !== '') {
        casting_redirect('panel.php');
    }
}

$counts = casting_member_counts();

casting_render_head('خانه', 'page-home');
casting_render_header('home');
?>
<main class="wrap hero">
  <div class="hero-copy">
    <p class="hero-lead">هنرمندان استعدادشان را ثبت می‌کنند؛ کارگردان‌ها و تهیه‌کنندگان برای پروژه انتخاب می‌کنند.</p>
    <div class="cta-row hero-cta">
      <a class="btn btn-primary" href="register.php">عضویت</a>
      <a class="btn btn-primary" href="login.php">ورود</a>
    </div>

    <div class="home-stats" aria-label="آمار اعضا">
      <div class="stat-item">
        <strong><?= (int) $counts['talents'] ?></strong>
        <span>هنرمند</span>
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
  </div>
</main>
<?php casting_render_footer(); ?>
