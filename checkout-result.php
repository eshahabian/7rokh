<?php
declare(strict_types=1);

/**
 * نتیجه پرداخت — موفق / ناموفق
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();
$user = casting_require_casting_user();
$user_id = (int) $user->ID;

$order_code = sanitize_text_field((string) ($_GET['order'] ?? ''));
$status = sanitize_key((string) ($_GET['status'] ?? ''));
$order = casting_get_order_by_code($order_code);

if ($order === [] || (int) ($order['user_id'] ?? 0) !== $user_id) {
    casting_set_flash('error', 'سفارش پیدا نشد.');
    casting_redirect('membership.php');
}

$is_success = $status === 'success' || (string) ($order['status'] ?? '') === 'paid';
$page_title = $is_success ? 'پرداخت موفق' : 'پرداخت ناموفق';

$retry_url = 'checkout.php?order=' . rawurlencode($order_code);
$next_url = 'membership.php';
if ((string) ($order['service_key'] ?? '') === 'casting_call') {
    $pid = (int) ($order['project_id'] ?? 0);
    $next_url = $pid > 0 ? ('director-desk.php?project=' . $pid) : 'director-desk.php';
} elseif ((string) ($order['service_key'] ?? '') === 'premium' && $is_success) {
    $next_url = 'cart.php';
}

casting_render_panel_start($page_title, 'membership');
?>
<section class="dash-card checkout-card checkout-result-card">
  <h1><?= casting_e($page_title) ?></h1>

  <?php if ($is_success) : ?>
    <div class="flash flash-success">پرداخت شما با موفقیت انجام شد.</div>
    <ul class="info-list checkout-summary-list">
      <li><strong>وضعیت پرداخت:</strong> موفق</li>
      <li><strong>عنوان خدمت:</strong> <?= casting_e((string) $order['title']) ?></li>
      <li><strong>مبلغ پرداخت‌شده:</strong> <?= casting_e(casting_format_toman((int) $order['amount_final'])) ?></li>
      <li><strong>شماره سفارش:</strong> <span class="membership-number"><?= casting_e((string) $order['order_code']) ?></span></li>
      <li><strong>شماره پیگیری بانکی:</strong> <?= casting_e((string) ($order['gateway_trace'] !== '' ? $order['gateway_trace'] : '—')) ?></li>
      <li><strong>تاریخ و ساعت پرداخت:</strong> <?= casting_e((string) ($order['paid_at'] !== '' ? $order['paid_at'] : $order['updated_at'])) ?></li>
    </ul>
    <?php if ((string) ($order['service_key'] ?? '') === 'casting_call') : ?>
      <p class="meta">اکنون می‌توانید فراخوان را از میز کارگردان ارسال کنید.</p>
    <?php elseif ((string) ($order['service_key'] ?? '') === 'premium') : ?>
      <p class="meta">عضویت ویژه روی حساب شما فعال شد.</p>
    <?php endif; ?>
    <div class="cta-row">
      <a class="btn btn-primary" href="<?= casting_e($next_url) ?>">ادامه</a>
      <a class="btn btn-ghost" href="transactions.php">تراکنش‌های مالی</a>
    </div>
  <?php else : ?>
    <div class="flash flash-error"><?= $status === 'cancel' ? 'پرداخت لغو شد.' : 'پرداخت ناموفق بود یا توسط کاربر لغو شد.' ?></div>
    <ul class="info-list checkout-summary-list">
      <li><strong>وضعیت پرداخت:</strong> ناموفق</li>
      <li><strong>عنوان خدمت:</strong> <?= casting_e((string) $order['title']) ?></li>
      <li><strong>مبلغ:</strong> <?= casting_e(casting_format_toman((int) $order['amount_final'])) ?></li>
      <li><strong>شماره سفارش:</strong> <span class="membership-number"><?= casting_e((string) $order['order_code']) ?></span></li>
    </ul>
    <div class="cta-row">
      <a class="btn btn-primary" href="<?= casting_e($retry_url) ?>">تلاش مجدد</a>
      <a class="btn btn-ghost" href="<?= casting_e($next_url) ?>">بازگشت</a>
    </div>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
