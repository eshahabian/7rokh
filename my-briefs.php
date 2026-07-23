<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/talent-briefs.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['brief_action'], $_POST['brief_id'])) {
    $brief_id = sanitize_text_field((string) $_POST['brief_id']);
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_brief_' . $brief_id)) {
        $error = 'نشست منقضی شده. دوباره تلاش کنید.';
    } elseif ((string) $_POST['brief_action'] === 'submit') {
        $result = casting_talent_submit_brief_response($user_id, $brief_id);
        if (!$result['ok']) {
            $error = $result['error'] ?? 'ارسال پاسخ ناموفق بود.';
        } else {
            casting_set_flash('success', 'پاسخ تکلیف ارسال شد.');
            casting_redirect('my-briefs.php');
        }
    }
}

$briefs = casting_talent_get_briefs($user_id);
$pending = [];
$submitted = [];
foreach ($briefs as $brief) {
    if (($brief['status'] ?? '') === 'submitted') {
        $submitted[] = $brief;
    } else {
        $pending[] = $brief;
    }
}

casting_render_panel_start('تکالیف', 'briefs');
?>
<section class="dash-card panel-wide">
  <h1>تکالیف</h1>
  <p class="lede">تکالیفی که کارگردان‌ها برای شما فرستاده‌اند. نوع هر تکلیف مشخص می‌کند چه فایلی باید ارسال کنید.</p>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>
  <?php casting_render_flash(); ?>

  <?php if ($briefs === []) : ?>
    <p class="meta">فعلاً تکلیفی ندارید.</p>
  <?php else : ?>
    <?php if ($pending !== []) : ?>
      <h2 class="panel-section-title">در انتظار پاسخ</h2>
      <?php foreach ($pending as $brief) : ?>
        <?php casting_render_talent_brief_card($brief, true); ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($submitted !== []) : ?>
      <h2 class="panel-section-title"<?= $pending !== [] ? ' style="margin-top:1.5rem"' : '' ?>>ارسال‌شده</h2>
      <?php foreach ($submitted as $brief) : ?>
        <?php casting_render_talent_brief_card($brief, false); ?>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
