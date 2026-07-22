<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/rules-content.php';

$user = casting_current_user();
$logged_in = $user && casting_get_user_role((int) $user->ID) !== '';
if ($logged_in) {
    require_once __DIR__ . '/includes/panel.php';
} else {
    require_once __DIR__ . '/includes/layout.php';
}

if ($logged_in) {
    casting_render_panel_start('قوانین', 'rules');
} else {
    casting_render_head('قوانین', 'page-rules');
    casting_render_header('rules');
}
casting_render_flash();
?>
<?php if (!$logged_in) : ?><main class="wrap panel-page"><?php endif; ?>
  <section class="<?= $logged_in ? 'dash-card panel-wide rules-page' : 'panel panel-wide rules-page' ?>">
    <h1>قوانین <?= casting_e(casting_brand()) ?></h1>
    <p class="lede">با عضویت و استفاده از پورتال، این قوانین را می‌پذیرید.</p>
    <?php casting_render_rules_list(); ?>
  </section>
<?php if ($logged_in) : ?>
<?php casting_render_panel_end(); ?>
<?php else : ?>
</main>
<?php casting_render_footer(); ?>
<?php endif; ?>
