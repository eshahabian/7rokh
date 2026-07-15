<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$plans = casting_premium_plans();
$plan_key = sanitize_key((string) ($_GET['plan'] ?? $_POST['plan'] ?? 'featured_30'));
if (!isset($plans[$plan_key])) {
    $plan_key = 'featured_30';
}
$plan = $plans[$plan_key];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_receipt')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $upload = casting_handle_receipt_upload($user_id);
        if (!$upload['ok']) {
            $error = $upload['error'];
        } else {
            $result = casting_submit_premium_receipt(
                $user_id,
                sanitize_key((string) ($_POST['plan'] ?? $plan_key)),
                (string) ($_POST['reference'] ?? ''),
                (string) ($_POST['note'] ?? ''),
                (int) ($upload['attachment_id'] ?? 0)
            );
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', 'فیش ثبت شد. پس از بررسی، اشتراک ویژه فعال می‌شود.');
                casting_redirect('transactions.php');
            }
        }
    }
}

casting_render_panel_start('ثبت فیش', 'receipt');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card">
  <h1>ثبت فیش کارت به کارت</h1>
  <p class="meta">پلن: <strong><?= casting_e($plan['label']) ?></strong> — <?= casting_e(number_format((int) $plan['price'])) ?> تومان</p>

  <form class="form" method="post" action="premium-receipt.php" enctype="multipart/form-data">
    <?php wp_nonce_field('casting_receipt'); ?>
    <input type="hidden" name="plan" value="<?= casting_e($plan_key) ?>">
    <div class="field">
      <label for="reference">شماره پیگیری / مرجع واریز</label>
      <input id="reference" name="reference" type="text" required maxlength="64">
    </div>
    <div class="field">
      <label for="note">توضیح (اختیاری)</label>
      <textarea id="note" name="note" rows="3" maxlength="500"></textarea>
    </div>
    <div class="field">
      <label for="receipt">تصویر فیش (اختیاری)</label>
      <input id="receipt" name="receipt" type="file" accept="image/*">
    </div>
    <button class="btn btn-primary" type="submit">ثبت فیش</button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
