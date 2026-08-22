<?php
declare(strict_types=1);

/**
 * انتخاب درگاه پرداخت — ملت یا سامان
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/gateway.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();
$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$error = '';

$order_code = sanitize_text_field((string) ($_GET['order'] ?? $_POST['order_code'] ?? ''));
$order = $order_code !== '' ? casting_get_order_by_code($order_code) : [];

if ($order === [] || (int) ($order['user_id'] ?? 0) !== $user_id) {
    casting_set_flash('error', 'سفارش پیدا نشد یا به شما تعلق ندارد.');
    casting_redirect('membership.php');
}

if ((string) ($order['status'] ?? '') === 'paid') {
    casting_redirect('checkout-result.php?order=' . rawurlencode($order_code) . '&status=success');
}

$gateway_mode = casting_gateway_mode();
$providers = casting_gateway_available_providers();
$gateway_ready = $gateway_mode === 'sandbox' || ($gateway_mode === 'live' && $providers !== []);

if (!$gateway_ready) {
    casting_set_flash('error', 'هیچ درگاه پرداختی پیکربندی نشده است.');
    casting_redirect('checkout.php?order=' . rawurlencode($order_code));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_gateway'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_checkout_gateway_' . $order_code)) {
        $error = 'درخواست نامعتبر است.';
    } elseif (!in_array((string) ($order['status'] ?? ''), ['pending', 'failed', 'awaiting_payment'], true)) {
        $error = 'این سفارش قابل پرداخت نیست.';
    } else {
        $provider = sanitize_key((string) ($_POST['gateway_provider'] ?? ''));
        if ($gateway_mode === 'sandbox') {
            $token = bin2hex(random_bytes(16));
            casting_order_update((int) $order['id'], [
                'status'      => 'awaiting_payment',
                'gateway_ref' => $token,
            ]);
            casting_order_merge_meta((int) $order['id'], ['gateway_provider' => $provider !== '' ? $provider : 'mellat']);
            casting_redirect('checkout-gateway.php?order=' . rawurlencode($order_code) . '&token=' . rawurlencode($token));
        }

        $start = casting_gateway_start_payment($order, $provider);
        if (!$start['ok']) {
            $error = $start['error'];
        } else {
            casting_redirect((string) $start['redirect']);
        }
    }
}

$default_provider = '';
if (count($providers) === 1) {
    $default_provider = (string) array_key_first($providers);
}

casting_render_panel_start('انتخاب درگاه پرداخت', 'membership');
casting_render_flash();
?>
<section class="dash-card checkout-card checkout-choose-gateway-card">
  <h1>انتخاب درگاه پرداخت</h1>
  <p class="meta">درگاه مورد نظر را انتخاب کنید تا به صفحه پرداخت بانکی هدایت شوید.</p>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>

  <ul class="info-list checkout-summary-list">
    <li><strong>شماره سفارش:</strong> <span class="membership-number"><?= casting_e((string) $order['order_code']) ?></span></li>
    <li><strong>عنوان:</strong> <?= casting_e((string) $order['title']) ?></li>
    <li class="checkout-total"><strong>مبلغ قابل پرداخت:</strong> <?= casting_e(casting_format_toman((int) $order['amount_final'])) ?></li>
  </ul>

  <form class="form checkout-gateway-pick-form" method="post" action="checkout-choose-gateway.php?order=<?= casting_e(rawurlencode($order_code)) ?>">
    <?php wp_nonce_field('casting_checkout_gateway_' . $order_code); ?>
    <input type="hidden" name="order_code" value="<?= casting_e($order_code) ?>">
    <input type="hidden" name="checkout_gateway" value="1">

    <fieldset class="checkout-gateway-options">
      <legend class="panel-section-title">درگاه بانکی</legend>
      <?php foreach ($providers as $key => $info) : ?>
        <label class="checkout-gateway-option">
          <input
            type="radio"
            name="gateway_provider"
            value="<?= casting_e($key) ?>"
            <?= ($default_provider === $key || ($default_provider === '' && $key === 'mellat')) ? 'checked' : '' ?>
            required
          >
          <span class="checkout-gateway-option-body">
            <strong><?= casting_e((string) $info['short']) ?></strong>
            <span class="meta"><?= casting_e((string) $info['label']) ?></span>
          </span>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <div class="cta-row checkout-actions">
      <button class="btn btn-primary" type="submit">ورود به درگاه و پرداخت</button>
      <a class="btn btn-ghost" href="checkout.php?order=<?= casting_e(rawurlencode($order_code)) ?>">بازگشت</a>
    </div>
  </form>
</section>
<?php casting_render_panel_end(); ?>
