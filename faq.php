<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/faq.php';

$user = casting_current_user();
$logged_in = $user && casting_get_user_role((int) $user->ID) !== '';
if ($logged_in) {
    require_once __DIR__ . '/includes/panel.php';
} else {
    require_once __DIR__ . '/includes/layout.php';
}

if ($logged_in) {
    casting_render_panel_start('سوالات متداول', 'faq');
} else {
    casting_render_head('سوالات متداول', 'page-faq');
    casting_render_header('faq');
}
casting_render_faq_json_ld();
casting_render_flash();
?>
<?php if (!$logged_in) : ?><main class="wrap panel-page"><?php endif; ?>
  <section class="<?= $logged_in ? 'dash-card panel-wide faq-page' : 'panel panel-wide faq-page' ?>">
    <h1>سوالات متداول (FAQ)</h1>
    <p class="lede">پاسخ پرسش‌های رایج درباره <?= casting_e(casting_brand()) ?>.</p>
    <?php casting_render_faq_accordion(); ?>
  </section>
<?php if ($logged_in) : ?>
<?php casting_render_panel_end(); ?>
<?php else : ?>
</main>
<?php casting_render_footer(); ?>
<?php endif; ?>
