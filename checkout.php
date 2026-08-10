<?php
declare(strict_types=1);

/**
 * خلاصه سفارش (Checkout) — قبل از انتقال به درگاه بانکی
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
$service = sanitize_key((string) ($_GET['service'] ?? $_POST['service'] ?? ''));
$plan = sanitize_key((string) ($_GET['plan'] ?? $_POST['plan'] ?? ''));
$project_id = max(0, (int) ($_GET['project'] ?? $_POST['project_id'] ?? 0));

$order = $order_code !== '' ? casting_get_order_by_code($order_code) : [];

// ایجاد سفارش جدید از لینک خدمت → اول سبد خرید
if ($order === [] && $service !== '') {
    if (!function_exists('casting_cart_add')) {
        require_once __DIR__ . '/includes/cart.php';
    }
    if ($service === 'casting_call' && $plan === '' && $project_id > 0) {
        require_once __DIR__ . '/includes/director-desk.php';
        $project = casting_director_get_project($user_id, $project_id);
        if ($project) {
            $plan = casting_checkout_map_project_type((string) ($project['project_type'] ?? ''));
        }
    }
    $added = casting_cart_add($service, $plan, $project_id);
    if (!$added['ok']) {
        casting_set_flash('error', $added['error']);
        casting_redirect($service === 'casting_call' ? 'director-desk.php' : 'premium.php');
    }
    casting_set_flash('success', 'به سفارش‌ها اضافه شد.');
    casting_redirect('cart.php');
}

if ($order === [] || (int) ($order['user_id'] ?? 0) !== $user_id) {
    casting_set_flash('error', 'سفارش پیدا نشد یا به شما تعلق ندارد.');
    casting_redirect('membership.php');
}

if ((string) ($order['status'] ?? '') === 'paid') {
    casting_redirect('checkout-result.php?order=' . rawurlencode((string) $order['order_code']) . '&status=success');
}

$catalog = casting_paid_services_catalog();
$svc = $catalog[(string) $order['service_key']] ?? [];
$cancel_url = (string) ($svc['cancel_url'] ?? 'membership.php');
if ((string) ($order['service_key'] ?? '') === 'cart' || !empty($order['meta']['from_cart'])) {
    $cancel_url = 'cart.php';
} elseif ((int) ($order['project_id'] ?? 0) > 0 && (string) $order['service_key'] === 'casting_call') {
    $cancel_url = 'director-desk.php?project=' . (int) $order['project_id'];
}

$gateway_mode = casting_gateway_mode();
$gateway_ready = $gateway_mode === 'live' || $gateway_mode === 'sandbox';

// پرداخت — فقط وقتی درگاه آماده باشد (فعلاً off تا دریافت درگاه بانکی)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_pay'])) {
    if (!$gateway_ready) {
        $error = 'درگاه بانکی هنوز فعال نشده است. فعلاً بعد از خلاصه سفارش پرداختی انجام نمی‌شود.';
    } elseif (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_checkout_pay_' . $order['order_code'])) {
        $error = 'درخواست نامعتبر است.';
    } elseif (empty($_POST['rules_accepted'])) {
        $error = 'برای ادامه، پذیرش قوانین و شرایط استفاده الزامی است.';
    } elseif (!in_array((string) $order['status'], ['pending', 'failed', 'awaiting_payment'], true)) {
        $error = 'این سفارش قابل پرداخت نیست.';
    } else {
        $start = casting_gateway_start_payment($order);
        if (!$start['ok']) {
            $error = $start['error'];
        } else {
            casting_redirect((string) $start['redirect']);
        }
    }
}

$meta = is_array($order['meta'] ?? null) ? $order['meta'] : [];
$plan_label = '';
if ((string) $order['service_key'] === 'premium') {
    $plan_label = (string) (($svc['plans'][(string) $order['plan_key']]['label'] ?? '') ?: (string) $order['duration_label']);
} else {
    $plan_label = (string) $order['title'];
}

casting_render_panel_start('خلاصه سفارش', 'membership');
casting_render_flash();
?>
<section class="dash-card checkout-card">
  <h1>خلاصه سفارش</h1>
  <p class="meta">جزئیات سفارش را بررسی کنید. پرداخت آنلاین پس از اتصال درگاه بانکی فعال می‌شود.</p>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>

  <?php if (!$gateway_ready) : ?>
    <div class="flash flash-error" role="status">درگاه بانکی هنوز فعال نشده است. سفارش ثبت شده؛ تا زمان دریافت درگاه از بانک، پرداخت و فعال‌سازی اعتبار انجام نمی‌شود.</div>
  <?php endif; ?>

  <div class="checkout-summary">
    <ul class="info-list checkout-summary-list">
      <li><strong>عنوان خدمت:</strong> <?= casting_e((string) $order['title']) ?></li>
      <li><strong>نوع خدمت / پلن:</strong> <?= casting_e((string) $order['service_type']) ?><?= $plan_label !== '' ? ' — ' . casting_e($plan_label) : '' ?></li>
      <?php if ((string) ($order['duration_label'] ?? '') !== '') : ?>
        <li><strong>مدت اعتبار:</strong> <?= casting_e((string) $order['duration_label']) ?></li>
      <?php endif; ?>
      <li><strong>نام کاربر:</strong> <?= casting_e((string) $user->display_name) ?></li>
      <li><strong>نام کاربری:</strong> <?= casting_e((string) $user->user_login) ?></li>
      <li><strong>شماره سفارش:</strong> <span class="membership-number"><?= casting_e((string) $order['order_code']) ?></span></li>
      <li><strong>مبلغ اصلی:</strong> <?= casting_e(casting_format_toman((int) $order['amount_base'])) ?></li>
      <?php if ((int) $order['discount'] > 0) : ?>
        <li><strong>تخفیف:</strong> <?= casting_e(casting_format_toman((int) $order['discount'])) ?></li>
      <?php else : ?>
        <li><strong>تخفیف:</strong> —</li>
      <?php endif; ?>
      <li><strong>مالیات بر ارزش افزوده (۱۰٪):</strong> <?= casting_e(casting_format_toman((int) $order['vat_amount'])) ?></li>
      <li class="checkout-total"><strong>مبلغ نهایی قابل پرداخت:</strong> <?= casting_e(casting_format_toman((int) $order['amount_final'])) ?></li>
    </ul>

    <?php if ((string) ($order['description'] ?? '') !== '') : ?>
      <div class="bio-block checkout-desc">
        <h2 class="panel-section-title">توضیح خدمت</h2>
        <p><?= casting_e((string) $order['description']) ?></p>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($gateway_ready) : ?>
    <form class="form checkout-pay-form" method="post" action="checkout.php?order=<?= casting_e(rawurlencode((string) $order['order_code'])) ?>">
      <?php wp_nonce_field('casting_checkout_pay_' . $order['order_code']); ?>
      <input type="hidden" name="order_code" value="<?= casting_e((string) $order['order_code']) ?>">
      <input type="hidden" name="checkout_pay" value="1">

      <p class="checkout-rules-link">
        <a href="rules.php" target="_blank" rel="noopener">قوانین و شرایط استفاده</a>
      </p>

      <label class="checkout-rules-accept">
        <input type="checkbox" name="rules_accepted" value="1" required>
        <span>قوانین و شرایط استفاده از خدمات ۷رخ را مطالعه کرده و می‌پذیرم.</span>
      </label>

      <div class="cta-row checkout-actions">
        <button class="btn btn-primary" type="submit">پرداخت و انتقال به درگاه بانکی</button>
        <a class="btn btn-ghost" href="<?= casting_e($cancel_url) ?>">انصراف از خرید / بازگشت</a>
      </div>
    </form>
  <?php else : ?>
    <div class="cta-row checkout-actions">
      <button class="btn btn-primary" type="button" disabled>پرداخت به‌زودی فعال می‌شود</button>
      <a class="btn btn-ghost" href="<?= casting_e($cancel_url) ?>">بازگشت به سبد / انصراف</a>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
