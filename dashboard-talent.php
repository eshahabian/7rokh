<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/layout.php';

$user = casting_require_login('talent');
$role = casting_get_user_role((int) $user->ID);
$profile = casting_get_profile((int) $user->ID);
$complete = casting_profile_complete($profile);

casting_render_head('پنل هنرجو', 'page-dash');
casting_render_header('dash');
casting_render_flash();
?>
<main class="wrap dash">
  <section class="dash-card">
    <span class="chip"><?= casting_e(casting_role_label($role)) ?></span>
    <h1>سلام، <?= casting_e($user->display_name) ?></h1>
    <?php if (!$complete) : ?>
      <p class="meta">پروفایلتان کامل نیست. سن، شهر، جنسیت و عکس را اضافه کنید تا کارفرماها شما را ببینند.</p>
    <?php else : ?>
      <p class="meta">پروفایل آماده است<?= $profile['visible'] ? ' و برای کارفرماها قابل مشاهده است' : '؛ فعلاً از دید کارفرماها مخفی است' ?>.</p>
    <?php endif; ?>
    <div class="cta-row">
      <a class="btn btn-primary" href="profile-talent.php"><?= $complete ? 'ویرایش پروفایل' : 'تکمیل پروفایل' ?></a>
      <a class="btn btn-ghost" href="logout.php">خروج</a>
    </div>
  </section>
</main>
<?php casting_render_footer(); ?>
