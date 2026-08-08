<?php
declare(strict_types=1);

/**
 * سبد خرید — مرحله قبل از خلاصه سفارش
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();
$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$error = '';

// همگام‌سازی شمارنده برای badge سایت اصلی (سبدهای قدیمی بدون کوکی)
casting_cart_sync_count_cookie();

$action = sanitize_key((string) ($_GET['action'] ?? $_POST['cart_action'] ?? ''));

// افزودن از لینک فروشگاه
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $service = sanitize_key((string) ($_GET['service'] ?? ''));
    $plan = sanitize_key((string) ($_GET['plan'] ?? ''));
    $project_id = max(0, (int) ($_GET['project'] ?? 0));
    $result = casting_cart_add($service, $plan, $project_id);
    if ($result['ok']) {
        casting_set_flash('success', 'به سبد خرید اضافه شد.');
    } else {
        casting_set_flash('error', $result['error']);
    }
    casting_redirect('cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_cart')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $action = sanitize_key((string) ($_POST['cart_action'] ?? ''));
        if ($action === 'remove') {
            $result = casting_cart_remove((string) ($_POST['item_id'] ?? ''));
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'از سبد حذف شد.' : $result['error']);
            casting_redirect('cart.php');
        }
        if ($action === 'clear') {
            casting_cart_clear();
            casting_set_flash('success', 'سبد خرید خالی شد.');
            casting_redirect('cart.php');
        }
        if ($action === 'checkout') {
            $created = casting_cart_create_order_from_cart($user_id);
            if (!$created['ok']) {
                $error = $created['error'];
            } else {
                casting_redirect('checkout.php?order=' . rawurlencode((string) $created['order']['order_code']));
            }
        }
    }
}

$cart = casting_cart_get();
$totals = casting_cart_totals($cart);

casting_render_panel_start('سبد خرید', 'cart');
casting_render_flash();
?>
<section class="dash-card cart-card">
  <h1>سبد خرید</h1>
  <p class="meta">اقلام انتخاب‌شده را بررسی کنید؛ سپس به خلاصه سفارش و درگاه بانکی بروید.</p>

  <?php if ($error !== '') : ?>
    <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
  <?php endif; ?>

  <?php if ($cart['items'] === []) : ?>
    <p class="empty-state">سبد خرید خالی است.</p>
    <div class="cta-row">
      <a class="btn btn-primary" href="premium.php">مشاهده خدمات و پلن‌ها</a>
    </div>
  <?php else : ?>
    <div class="cart-list">
      <?php foreach ($cart['items'] as $item) : ?>
        <article class="cart-item">
          <div class="cart-item-body">
            <strong><?= casting_e((string) ($item['title'] ?? '')) ?></strong>
            <p class="meta">
              <?= casting_e((string) ($item['service_type'] ?? '')) ?>
              <?php if ((string) ($item['plan_label'] ?? '') !== '') : ?>
                · <?= casting_e((string) $item['plan_label']) ?>
              <?php endif; ?>
              <?php if ((string) ($item['duration_label'] ?? '') !== '') : ?>
                · <?= casting_e((string) $item['duration_label']) ?>
              <?php endif; ?>
            </p>
            <p class="cart-item-price">
              <?= casting_e(casting_format_toman((int) ($item['amount_base'] ?? 0))) ?>
              + مالیات
              = <strong><?= casting_e(casting_format_toman((int) ($item['amount_final'] ?? 0))) ?></strong>
            </p>
          </div>
          <form method="post" action="cart.php" class="cart-item-remove">
            <?php wp_nonce_field('casting_cart'); ?>
            <input type="hidden" name="cart_action" value="remove">
            <input type="hidden" name="item_id" value="<?= casting_e((string) ($item['id'] ?? '')) ?>">
            <button class="btn btn-ghost btn-sm" type="submit">حذف</button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="cart-totals bio-block">
      <ul class="info-list">
        <li><strong>تعداد اقلام:</strong> <?= (int) $totals['count'] ?></li>
        <li><strong>جمع مبلغ اصلی:</strong> <?= casting_e(casting_format_toman((int) $totals['base'])) ?></li>
        <?php if ((int) $totals['discount'] > 0) : ?>
          <li><strong>تخفیف:</strong> <?= casting_e(casting_format_toman((int) $totals['discount'])) ?></li>
        <?php endif; ?>
        <li><strong>مالیات بر ارزش افزوده:</strong> <?= casting_e(casting_format_toman((int) $totals['vat'])) ?></li>
        <li class="checkout-total"><strong>مبلغ قابل پرداخت:</strong> <?= casting_e(casting_format_toman((int) $totals['final'])) ?></li>
      </ul>
    </div>

    <div class="cta-row cart-actions">
      <form method="post" action="cart.php">
        <?php wp_nonce_field('casting_cart'); ?>
        <input type="hidden" name="cart_action" value="checkout">
        <button class="btn btn-primary" type="submit">ادامه به خلاصه سفارش</button>
      </form>
      <form method="post" action="cart.php" onsubmit="return confirm('سبد خرید خالی شود؟');">
        <?php wp_nonce_field('casting_cart'); ?>
        <input type="hidden" name="cart_action" value="clear">
        <button class="btn btn-ghost" type="submit">خالی کردن سبد</button>
      </form>
      <a class="btn btn-ghost" href="premium.php">افزودن خدمت دیگر</a>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
