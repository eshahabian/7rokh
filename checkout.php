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
$checkout_step = sanitize_key((string) ($_GET['step'] ?? ''));

$order = $order_code !== '' ? casting_get_order_by_code($order_code) : [];

// ایجاد سفارش جدید از لینک خدمت → اول سبد خرید (فقط POST یا GET با nonce)
if ($order === [] && $service !== '') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $add_nonce = (string) ($_GET['_wpnonce'] ?? '');
        if ($add_nonce === '' || !wp_verify_nonce($add_nonce, 'casting_cart_add')) {
            casting_set_flash('error', 'درخواست نامعتبر است. از داخل پورتال اضافه کنید.');
            casting_redirect('cart.php');
        }
    }
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
        casting_redirect($service === 'casting_call' ? 'director-desk.php' : 'cart.php');
    }
    casting_set_flash('success', 'به خرید اشتراک اضافه شد.');
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
$back_url = (string) ($svc['cancel_url'] ?? 'cart.php');
if ((string) ($order['service_key'] ?? '') === 'cart' || !empty($order['meta']['from_cart'])) {
    $back_url = 'cart.php';
} elseif ((int) ($order['project_id'] ?? 0) > 0 && (string) $order['service_key'] === 'casting_call') {
    $back_url = 'director-desk.php?project=' . (int) $order['project_id'];
}
$cancel_url = 'cart.php';

$gateway_mode = casting_gateway_mode();
$gateway_options = casting_gateway_provider_options();
$gateway_ready = $gateway_mode === 'sandbox' || ($gateway_mode === 'live' && casting_gateway_has_any_credentials());
$show_gateway_step = $checkout_step === 'gateway';

if ($show_gateway_step && $gateway_mode === 'live' && casting_gateway_available_providers() === []) {
    casting_set_flash('error', 'هیچ درگاه پرداختی پیکربندی نشده است. از ادمین، ملت یا سامان را ذخیره کنید.');
    casting_redirect('checkout.php?order=' . rawurlencode((string) $order['order_code']));
}

// انصراف — سبد خالی می‌شود و به خرید اشتراک برمی‌گردد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_cancel'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_checkout_cancel_' . $order['order_code'])) {
        $error = 'درخواست نامعتبر است.';
    } else {
        if (!function_exists('casting_cart_clear')) {
            require_once __DIR__ . '/includes/cart.php';
        }
        casting_cart_clear();
        if (in_array((string) ($order['status'] ?? ''), ['pending', 'failed', 'awaiting_payment', 'draft'], true)) {
            casting_order_update((int) $order['id'], ['status' => 'cancelled']);
        }
        casting_set_flash('success', 'از خرید انصراف دادید و لیست خرید اشتراک خالی شد.');
        casting_redirect($cancel_url);
    }
}

// مرحله ۱ — پذیرش قوانین و رفتن به انتخاب درگاه
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
        casting_redirect('checkout.php?order=' . rawurlencode((string) $order['order_code']) . '&step=gateway');
    }
}

// مرحله ۲ — انتخاب درگاه (ملت یا سامان) و شروع پرداخت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_gateway'])) {
    if (!$gateway_ready) {
        $error = 'درگاه بانکی هنوز فعال نشده است.';
        $show_gateway_step = true;
    } elseif (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_checkout_gateway_' . $order['order_code'])) {
        $error = 'درخواست نامعتبر است.';
        $show_gateway_step = true;
    } elseif (!in_array((string) ($order['status'] ?? ''), ['pending', 'failed', 'awaiting_payment'], true)) {
        $error = 'این سفارش قابل پرداخت نیست.';
        $show_gateway_step = true;
    } else {
        $provider = sanitize_key((string) ($_POST['gateway_provider'] ?? ''));
        $picked = $gateway_options[$provider] ?? null;
        if ($picked === null) {
            $error = 'درگاه انتخاب‌شده معتبر نیست.';
            $show_gateway_step = true;
        } elseif (empty($picked['ready'])) {
            $error = (string) ($picked['missing'] ?? 'این درگاه هنوز پیکربندی نشده است.');
            $show_gateway_step = true;
        } elseif ($gateway_mode === 'sandbox') {
            $token = bin2hex(random_bytes(16));
            casting_order_update((int) $order['id'], [
                'status'      => 'awaiting_payment',
                'gateway_ref' => $token,
            ]);
            casting_order_merge_meta((int) $order['id'], ['gateway_provider' => $provider]);
            casting_redirect('checkout-gateway.php?order=' . rawurlencode((string) $order['order_code']) . '&token=' . rawurlencode($token));
        } else {
            $start = casting_gateway_start_payment($order, $provider);
            if (!$start['ok']) {
                $error = $start['error'];
                $show_gateway_step = true;
            } else {
                casting_redirect((string) $start['redirect']);
            }
        }
    }
}

$default_provider = '';
foreach (['sep', 'mellat'] as $candidate) {
    if (!empty($gateway_options[$candidate]['ready'])) {
        $default_provider = $candidate;
        break;
    }
}

$meta = is_array($order['meta'] ?? null) ? $order['meta'] : [];
$plan_label = '';
if ((string) $order['service_key'] === 'premium') {
    $plan_label = (string) (($svc['plans'][(string) $order['plan_key']]['label'] ?? '') ?: (string) $order['duration_label']);
} else {
    $plan_label = (string) $order['title'];
}

casting_render_panel_start($show_gateway_step ? 'انتخاب درگاه پرداخت' : 'پرداخت', 'membership');
casting_render_flash();
?>
<section class="dash-card checkout-card">
  <?php if ($show_gateway_step) : ?>
    <h1>انتخاب درگاه پرداخت</h1>
    <p class="meta">مطابق نمونه‌کد سامان (SEP) و به‌پرداخت ملت — درگاه مورد نظر را انتخاب کنید.</p>
  <?php else : ?>
    <h1>پرداخت</h1>
    <p class="meta">جزئیات سفارش را ببینید. مالیات بر ارزش افزوده ۱۰٪ در این مرحله اعمال شده است.</p>
  <?php endif; ?>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>

  <?php if (!$gateway_ready && !$show_gateway_step) : ?>
    <div class="flash flash-error" role="status">درگاه پرداخت در حال حاضر خاموش است. مدیر سایت می‌تواند آن را از بخش «درگاه پرداخت» فعال کند.</div>
  <?php endif; ?>

  <div class="checkout-summary">
    <ul class="info-list checkout-summary-list">
      <li><strong>عنوان خدمت:</strong> <?= casting_e((string) $order['title']) ?></li>
      <?php if (!$show_gateway_step) : ?>
        <li><strong>نوع خدمت / پلن:</strong> <?= casting_e((string) $order['service_type']) ?><?= $plan_label !== '' ? ' — ' . casting_e($plan_label) : '' ?></li>
        <?php if ((string) ($order['duration_label'] ?? '') !== '') : ?>
          <li><strong>مدت اعتبار:</strong> <?= casting_e((string) $order['duration_label']) ?></li>
        <?php endif; ?>
        <li><strong>نام کاربر:</strong> <?= casting_e((string) $user->display_name) ?></li>
        <li><strong>نام کاربری:</strong> <?= casting_e((string) $user->user_login) ?></li>
      <?php endif; ?>
      <li><strong>شماره سفارش:</strong> <span class="membership-number"><?= casting_e((string) $order['order_code']) ?></span></li>
      <?php if (!$show_gateway_step) : ?>
        <li><strong>مبلغ اصلی:</strong> <?= casting_e(casting_format_toman((int) $order['amount_base'])) ?></li>
        <?php if ((int) $order['discount'] > 0) : ?>
          <li><strong>تخفیف:</strong> <?= casting_e(casting_format_toman((int) $order['discount'])) ?></li>
        <?php else : ?>
          <li><strong>تخفیف:</strong> —</li>
        <?php endif; ?>
      <?php endif; ?>
    </ul>

    <?php if (!$show_gateway_step && (string) ($order['description'] ?? '') !== '') : ?>
      <div class="bio-block checkout-desc">
        <h2 class="panel-section-title">توضیح خدمت</h2>
        <p><?= casting_e((string) $order['description']) ?></p>
      </div>
    <?php endif; ?>
  </div>

  <div class="bio-block checkout-vat-block" aria-live="polite">
    <ul class="info-list checkout-summary-list">
      <?php if (!$show_gateway_step) : ?>
        <li><strong>مالیات بر ارزش افزوده (۱۰٪):</strong> <?= casting_e(casting_format_toman((int) $order['vat_amount'])) ?></li>
      <?php endif; ?>
      <li class="checkout-total"><strong>مبلغ نهایی قابل پرداخت:</strong> <?= casting_e(casting_format_toman((int) $order['amount_final'])) ?></li>
    </ul>
  </div>

  <?php if ($show_gateway_step && $gateway_ready) : ?>
    <form class="form checkout-gateway-pick-form" method="post" action="checkout.php?order=<?= casting_e(rawurlencode((string) $order['order_code'])) ?>&step=gateway">
      <?php wp_nonce_field('casting_checkout_gateway_' . $order['order_code']); ?>
      <input type="hidden" name="order_code" value="<?= casting_e((string) $order['order_code']) ?>">
      <input type="hidden" name="checkout_gateway" value="1">

      <fieldset class="checkout-gateway-options">
        <legend class="panel-section-title">درگاه بانکی</legend>
        <?php foreach ($gateway_options as $key => $info) : ?>
          <label class="checkout-gateway-option<?= empty($info['ready']) ? ' is-disabled' : '' ?>">
            <input
              type="radio"
              name="gateway_provider"
              value="<?= casting_e($key) ?>"
              <?= empty($info['ready']) ? 'disabled' : '' ?>
              <?= ($default_provider === $key) ? 'checked' : '' ?>
              required
            >
            <span class="checkout-gateway-option-body">
              <strong><?= casting_e((string) $info['short']) ?></strong>
              <span class="meta"><?= casting_e((string) $info['label']) ?></span>
              <?php if (empty($info['ready'])) : ?>
                <span class="meta checkout-gateway-option-warn"><?= casting_e((string) $info['missing']) ?></span>
              <?php endif; ?>
            </span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <div class="cta-row checkout-actions">
        <button class="btn btn-primary" type="submit">ورود به درگاه و پرداخت</button>
        <a class="btn btn-ghost" href="checkout.php?order=<?= casting_e(rawurlencode((string) $order['order_code'])) ?>">بازگشت</a>
      </div>
    </form>
  <?php elseif ($gateway_ready) : ?>
    <form class="form checkout-pay-form" method="post" action="checkout.php?order=<?= casting_e(rawurlencode((string) $order['order_code'])) ?>">
      <?php wp_nonce_field('casting_checkout_pay_' . $order['order_code']); ?>
      <input type="hidden" name="order_code" value="<?= casting_e((string) $order['order_code']) ?>">
      <input type="hidden" name="checkout_pay" value="1">

      <p class="checkout-rules-link">
        <a href="rules.php">قوانین و شرایط استفاده</a>
      </p>

      <label class="checkout-rules-accept">
        <input type="checkbox" name="rules_accepted" value="1" required>
        <span>قوانین و شرایط استفاده از خدمات ۷رخ را مطالعه کرده و می‌پذیرم.</span>
      </label>

      <div class="cta-row checkout-actions">
        <button class="btn btn-primary" type="submit">ادامه — انتخاب درگاه پرداخت</button>
        <a class="btn btn-ghost" href="<?= casting_e($back_url) ?>">بازگشت به خرید اشتراک</a>
      </div>
    </form>
    <form class="cta-row checkout-actions" method="post" action="checkout.php?order=<?= casting_e(rawurlencode((string) $order['order_code'])) ?>" onsubmit="return confirm('از خرید انصراف می‌دهید؟ لیست خرید اشتراک خالی می‌شود.');">
      <?php wp_nonce_field('casting_checkout_cancel_' . $order['order_code']); ?>
      <input type="hidden" name="order_code" value="<?= casting_e((string) $order['order_code']) ?>">
      <button class="btn btn-ghost" type="submit" name="checkout_cancel" value="1">انصراف</button>
    </form>
  <?php else : ?>
    <div class="cta-row checkout-actions">
      <button class="btn btn-primary" type="button" disabled>پرداخت به‌زودی فعال می‌شود — <?= casting_e(casting_format_toman((int) $order['amount_final'])) ?></button>
      <a class="btn btn-ghost" href="<?= casting_e($back_url) ?>">بازگشت به خرید اشتراک</a>
      <form method="post" action="checkout.php?order=<?= casting_e(rawurlencode((string) $order['order_code'])) ?>" onsubmit="return confirm('از خرید انصراف می‌دهید؟ لیست خرید اشتراک خالی می‌شود.');">
        <?php wp_nonce_field('casting_checkout_cancel_' . $order['order_code']); ?>
        <input type="hidden" name="order_code" value="<?= casting_e((string) $order['order_code']) ?>">
        <button class="btn btn-ghost" type="submit" name="checkout_cancel" value="1">انصراف</button>
      </form>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
