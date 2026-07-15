<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_cancel')) {
        $error = 'درخواست نامعتبر است.';
    } elseif (empty($_POST['confirm'])) {
        $error = 'برای انصراف باید تأیید کنید.';
    } else {
        $result = casting_cancel_membership($user_id, (string) ($_POST['password'] ?? ''));
        if (!$result['ok']) {
            $error = $result['error'];
        } else {
            casting_set_flash('success', 'عضویت شما لغو شد. امیدواریم دوباره ببینیمتان.');
            casting_redirect('index.php');
        }
    }
}

casting_render_panel_start('انصراف از عضویت', 'cancel');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-narrow">
  <h1>انصراف از عضویت</h1>
  <p class="meta">با انصراف، پروفایل شما مخفی می‌شود و دیگر در جستجو نمایش داده نمی‌شوید. این عمل قابل بازگشت نیست مگر با ثبت‌نام مجدد.</p>
  <form class="form" method="post" action="cancel-membership.php">
    <?php wp_nonce_field('casting_cancel'); ?>
    <div class="field">
      <label for="password">رمز عبور برای تأیید</label>
      <input id="password" name="password" type="password" required>
    </div>
    <label class="checkbox-row">
      <input type="checkbox" name="confirm" value="1">
      <span>از انصراف از عضویت مطمئن هستم</span>
    </label>
    <button class="btn btn-reject" type="submit">انصراف از عضویت</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
