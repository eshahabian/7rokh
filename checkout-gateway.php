<?php
declare(strict_types=1);

/**
 * انتقال به درگاه: live = POST به ملت | sandbox = شبیه‌ساز
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/gateway.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();
$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (casting_gateway_mode() === 'off') {
    $back = sanitize_text_field((string) ($_GET['order'] ?? ''));
    casting_set_flash('error', 'درگاه بانکی هنوز فعال نشده است.');
    casting_redirect($back !== '' ? ('checkout.php?order=' . rawurlencode($back)) : 'cart.php');
}

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

if (casting_gateway_mode() === 'live') {
    $provider = casting_order_payment_provider($order);
    if ($provider === 'sep') {
        $token = (string) ($order['gateway_ref'] ?? '');
        $meta = is_array($order['meta'] ?? null) ? $order['meta'] : [];
        $sep_meta = is_array($meta['sep'] ?? null) ? $meta['sep'] : [];
        $pay_url = (string) (($sep_meta['pay_url'] ?? '') ?: casting_sep_pay_url());
        if ($token === '' || !in_array((string) ($order['status'] ?? ''), ['awaiting_payment', 'pending', 'failed'], true)) {
            casting_set_flash('error', 'برای این سفارش درخواست درگاه معتبر نیست. از صفحه پرداخت دوباره اقدام کنید.');
            casting_redirect('checkout.php?order=' . rawurlencode($order_code));
        }

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>انتقال به درگاه بانک سامان</title>
  <style>
    body { font-family: Tahoma, sans-serif; background: #111; color: #eee; text-align: center; padding: 3rem 1rem; }
    button { font-size: 1rem; padding: .7rem 1.4rem; cursor: pointer; }
  </style>
</head>
<body>
  <p>در حال انتقال به درگاه بانک سامان…</p>
  <form id="sep-pay" method="post" action="<?= casting_e($pay_url) ?>">
    <input type="hidden" name="Token" value="<?= casting_e($token) ?>">
    <noscript>
      <p>جاوااسکریپت غیرفعال است. برای ورود به درگاه دکمه زیر را بزنید.</p>
      <button type="submit">ورود به درگاه بانک سامان</button>
    </noscript>
  </form>
  <script>
    document.getElementById('sep-pay').submit();
  </script>
</body>
</html>
        <?php
        exit;
    }

    $ref_id = (string) ($order['gateway_ref'] ?? '');
    if ($ref_id === '' || !in_array((string) ($order['status'] ?? ''), ['awaiting_payment', 'pending', 'failed'], true)) {
        casting_set_flash('error', 'برای این سفارش درخواست درگاه معتبر نیست. از صفحه پرداخت دوباره اقدام کنید.');
        casting_redirect('checkout.php?order=' . rawurlencode($order_code));
    }

    $pay_url = casting_mellat_pay_url();
    $mobile = casting_mellat_mobile_no($user_id);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>انتقال به درگاه بانک ملت</title>
  <style>
    body { font-family: Tahoma, sans-serif; background: #111; color: #eee; text-align: center; padding: 3rem 1rem; }
    button { font-size: 1rem; padding: .7rem 1.4rem; cursor: pointer; }
  </style>
</head>
<body>
  <p>در حال انتقال به درگاه بانک ملت…</p>
  <form id="mellat-pay" method="post" action="<?= casting_e($pay_url) ?>">
    <input type="hidden" name="RefId" value="<?= casting_e($ref_id) ?>">
    <?php if ($mobile !== '') : ?>
      <input type="hidden" name="MobileNo" value="<?= casting_e($mobile) ?>">
    <?php endif; ?>
    <noscript>
      <p>جاوااسکریپت غیرفعال است. برای ورود به درگاه دکمه زیر را بزنید.</p>
      <button type="submit">ورود به درگاه بانک ملت</button>
    </noscript>
  </form>
  <script>
    document.getElementById('mellat-pay').submit();
  </script>
</body>
</html>
    <?php
    exit;
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
  <p class="meta">حالت آزمایشی درگاه — پرداخت واقعی انجام نمی‌شود.</p>

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
      <button class="btn btn-primary" type="submit" name="gateway_action" value="success">پرداخت موفق — فعال‌سازی حساب</button>
      <button class="btn btn-ghost" type="submit" name="gateway_action" value="fail">پرداخت ناموفق — بدون فعال‌سازی</button>
    </div>
    <p class="meta" style="margin-top:1rem">فقط در صورت پرداخت موفق، اشتراک یا اعتبار فراخوان روی حساب اعمال می‌شود.</p>
    <p class="meta">
      <a href="checkout.php?order=<?= casting_e(rawurlencode($order_code)) ?>">بازگشت به خلاصه سفارش</a>
    </p>
  </form>
</section>
<?php casting_render_panel_end(); ?>
