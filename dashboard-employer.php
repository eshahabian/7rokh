<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$user = casting_require_login('employer');
$role = casting_get_user_role((int) $user->ID);

casting_render_head('پنل کارفرما', 'page-dash');
casting_render_header('dash');
casting_render_flash();
?>
<main class="wrap dash">
  <section class="dash-card">
    <span class="chip"><?= casting_e(casting_role_label($role)) ?></span>
    <h1>سلام، <?= casting_e($user->display_name) ?></h1>
    <p class="meta">بین هنرجویان با فیلتر سن، شهر، جنسیت و تیپ جستجو کنید.</p>
    <div class="cta-row">
      <a class="btn btn-primary" href="talents.php">مشاهده هنرجویان</a>
      <a class="btn btn-ghost" href="logout.php">خروج</a>
    </div>
  </section>
</main>
<?php casting_render_footer(); ?>
