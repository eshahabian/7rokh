<?php
declare(strict_types=1);

/**
 * شبیه‌سازی درگاه بانکی (sandbox) — برای تست فرآیند و ارائه به به‌پرداخت
 * در حالت live با درگاه واقعی جایگزین می‌شود.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/gateway.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();
$user = casting_require_casting_user();
$user_id = (int) $user->ID;

$order_code = sanitize_text_field((string) ($_GET['order'] ?? $_POST['order_code'] ?? ''));
$token = sanitize_text_field((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$order = casting_get_order_by_code($order_code);

if ($order === [] || (int) ($order['user_id'] ?? 0) !== $user_id) {
    casting_set_flash('error', 'سفارش پیدا نشد.');
    casting_redirect('membership.php');
}

if ((string) ($order['status'] ?? '') === 'paid') {
    casting_redirect('checkout-result.php?order=' . rawurlencode($order_code) . '&status=success');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_gateway_sandbox_' . $order_code)) {
        casting_set_flash('error', 'درخواست نامعتبر است.');
        casting_redirect('checkout.php?order=' . rawurlencode($order_code));
    }
    $action = (string) ($_POST['gateway_action'] ?? '');
    $success = $action === 'success';
    $result = casting_gateway_complete_payment($order_code, $token, $success);
    $status = $success && $result['ok'] ? 'success' : 'failed';
    casting_redirect('checkout-result.php?order=' . rawurlencode($order_code) . '&status=' . $status);
}

casting_render_panel_start('درگاه پرداخت', 'membership');
?>
<section class="dash-card checkout-card checkout-gateway-card">
  <h1>انتقال به درگاه بانکی</h1>
  <p class="meta">حالت آزمایشی درگاه — برای بررسی فرآیند پرداخت و ارائه به شرکت به‌پرداخت. در نسخه نهایی به درگاه بانکی متصل می‌شود.</p>

  <ul class="info-list">
    <li><strong>شماره سفارش:</strong> <?= casting_e((string) $order['order_code']) ?></li>
    <li><strong>عنوان:</strong> <?= casting_e((string) $order['title']) ?></li>
    <li><strong>مبلغ قابل پرداخت:</strong> <?= casting_e(casting_format_toman((int) $order['amount_final'])) ?></li>
  </ul>

  <form method="post" action="checkout-gateway.php?order=<?= casting_e(rawurlencode($order_code)) ?>&token=<?= casting_e(rawurlencode($token)) ?>" class="checkout-gateway-form">
    <?php wp_nonce_field('casting_gateway_sandbox_' . $order_code); ?>
    <input type="hidden" name="order_code" value="<?= casting_e($order_code) ?>">
    <input type="hidden" name="token" value="<?= casting_e($token) ?>">
    <div class="cta-row">
      <button class="btn btn-primary" type="submit" name="gateway_action" value="success">شبیه‌سازی پرداخت موفق</button>
      <button class="btn btn-ghost" type="submit" name="gateway_action" value="fail">شبیه‌سازی پرداخت ناموفق</button>
    </div>
    <p class="meta" style="margin-top:1rem">
      <a href="checkout.php?order=<?= casting_e(rawurlencode($order_code)) ?>">بازگشت به خلاصه سفارش</a>
    </p>
  </form>
</section>
<?php casting_render_panel_end(); ?>
