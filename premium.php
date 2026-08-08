<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$premium = casting_user_is_premium($user_id);
$plans = casting_premium_plans();
$catalog = casting_paid_services_catalog();
$can_approve_receipts = casting_user_has_admin_permission($user_id, 'approve_receipts');
$admin_filter = sanitize_key((string) ($_GET['status'] ?? 'pending'));
if (!in_array($admin_filter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $admin_filter = 'pending';
}

if ($can_approve_receipts && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_receipt'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_admin_receipt')) {
        casting_set_flash('error', 'درخواست نامعتبر است.');
    } else {
        $receipt_id = (int) ($_POST['receipt_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'approve') {
            $result = casting_approve_premium_receipt($receipt_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'فیش تأیید و حساب کاربری ویژه فعال شد.' : $result['error']);
        } elseif ($action === 'reject') {
            $result = casting_reject_premium_receipt($receipt_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'فیش رد شد.' : $result['error']);
        }
    }
    casting_redirect('premium.php?status=' . $admin_filter . '#admin-receipts');
}

$admin_receipts = $can_approve_receipts ? casting_admin_list_receipts($admin_filter === 'all' ? '' : $admin_filter) : [];

/** @var list<array{group:string,label:string,meta:string,href:string,price_final:int}> $shop_items */
$shop_items = [];
foreach ($plans as $key => $p) {
    if ($key === 'featured_30') {
        continue;
    }
    $calc = casting_checkout_calc_amounts((int) $p['price']);
    $shop_items[] = [
        'group'       => 'عضویت ویژه',
        'label'       => 'عضویت ویژه — ' . (string) ($p['period_label'] ?? ''),
        'meta'        => 'ارتقای حساب کاربری · ماهیانه ۷۰٬۰۰۰ تومان',
        'href'        => casting_cart_add_url('premium', $key),
        'price_final' => $calc['final'],
        'price_base'  => $calc['base'],
        'vat'         => $calc['vat'],
    ];
}
$call_types = $catalog['casting_call']['types'] ?? [];
foreach ($call_types as $type_key => $type) {
    $calc = casting_checkout_calc_amounts((int) $type['amount_base']);
    $shop_items[] = [
        'group'       => 'فراخوان کستینگ',
        'label'       => (string) $type['label'],
        'meta'        => 'انتشار یک فراخوان · ' . (string) ($catalog['casting_call']['service_type'] ?? ''),
        'href'        => casting_cart_add_url('casting_call', (string) $type_key),
        'price_final' => $calc['final'],
        'price_base'  => $calc['base'],
        'vat'         => $calc['vat'],
    ];
}

casting_render_panel_start('خرید اشتراک', 'premium');
casting_render_flash();
?>
<section class="dash-card">
  <h1>خرید اشتراک و خدمات</h1>
  <p class="meta">خدمت را به سبد اضافه کنید؛ سپس از سبد به خلاصه سفارش و درگاه می‌روید. حساب فقط پس از پرداخت موفق فعال می‌شود.</p>

  <?php if ($premium) : ?>
    <div class="flash flash-success">حساب کاربری ویژه فعال است.</div>
    <?php casting_render_premium_countdown($user_id); ?>
  <?php endif; ?>

  <?php if ($can_approve_receipts) : ?>
    <?php $pending_count = casting_admin_pending_receipt_count(); ?>
    <div class="premium-admin-notice">
      <strong>مدیریت پرداخت‌ها (مدیران)</strong>
      <p class="meta">تأیید فیش‌های قدیمی در صورت نیاز.</p>
      <?php if ($pending_count > 0) : ?>
        <p><a class="btn btn-primary btn-sm" href="#admin-receipts"><?= (int) $pending_count ?> فیش در انتظار تأیید</a></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="shop-list">
    <?php
    $last_group = '';
    foreach ($shop_items as $item) :
        if ($last_group !== $item['group']) :
            $last_group = $item['group'];
            ?>
      <h2 class="panel-section-title shop-group-title"><?= casting_e($last_group) ?></h2>
        <?php endif; ?>
    <article class="shop-item">
      <div class="shop-item-body">
        <strong><?= casting_e($item['label']) ?></strong>
        <p class="meta"><?= casting_e($item['meta']) ?></p>
        <p class="shop-item-price">
          <?= casting_e(number_format((int) $item['price_base'])) ?> + مالیات
          = <strong><?= casting_e(number_format((int) $item['price_final'])) ?> تومان</strong>
        </p>
      </div>
      <a class="btn btn-primary" href="<?= casting_e($item['href']) ?>">افزودن به سبد</a>
    </article>
    <?php endforeach; ?>
  </div>

  <div class="bio-block premium-payment-block" style="margin-top:1.25rem">
    <h2>نکته مهم</h2>
    <ul class="info-list">
      <li>با زدن «افزودن به سبد» وارد سبد خرید می‌شوید؛ سپس خلاصه سفارش و درگاه. تا پرداخت موفق، حساب شارژ نمی‌شود.</li>
      <li>عضویت ویژه: حداقل ۳ ماه · ماهیانه ۷۰٬۰۰۰ تومان · مالیات بر ارزش افزوده ۱۰٪.</li>
      <li>فراخوان تئاتر و فیلم کوتاه: ۷۰۰٬۰۰۰ تومان (+ مالیات) · سینمایی و تلویزیونی: ۷٬۰۰۰٬۰۰۰ تومان (+ مالیات).</li>
    </ul>
  </div>
</section>

<?php if ($can_approve_receipts) : ?>
<section class="dash-card" id="admin-receipts" style="margin-top:1rem">
  <h2 class="panel-section-title">مدیریت فیش‌ها و ارتقا به ویژه</h2>
  <p class="meta">تأیید فیش = فعال‌سازی حساب کاربری ویژه طبق پلن · پس از پایان اعتبار خودکار غیرفعال می‌شود.</p>

  <nav class="admin-tabs" aria-label="فیلتر وضعیت">
    <?php foreach (['pending' => 'در انتظار', 'approved' => 'تأیید شده', 'rejected' => 'رد شده', 'all' => 'همه'] as $key => $label) : ?>
      <a class="admin-tab <?= $admin_filter === $key ? 'is-active' : '' ?>" href="premium.php?status=<?= casting_e($key) ?>#admin-receipts"><?= casting_e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$admin_receipts) : ?>
    <p class="empty-state">فیشی در این بخش نیست.</p>
  <?php else : ?>
    <div class="admin-receipt-list">
      <?php foreach ($admin_receipts as $row) :
          $uid = (int) $row['user_id'];
          $u = get_user_by('id', $uid);
          $row_plan_key = (string) $row['plan_key'];
          $plan_label = $plans[$row_plan_key]['label'] ?? $row_plan_key;
          $status = (string) $row['status'];
          ?>
        <article class="admin-receipt-item">
          <header>
            <div>
              <strong>#<?= (int) $row['id'] ?> — <?= casting_e($u ? $u->display_name : 'کاربر') ?></strong>
              <span class="meta"><?= casting_e($plan_label) ?> · <?= casting_e(number_format((int) $row['amount'])) ?> تومان</span>
            </div>
            <span class="chip"><?= casting_e(casting_premium_status_label($status)) ?></span>
          </header>
          <ul class="info-list admin-receipt-meta">
            <li><strong>شماره پیگیری:</strong> <?= casting_e((string) $row['reference_code']) ?></li>
            <li><strong>تاریخ:</strong> <?= casting_e((string) $row['created_at']) ?></li>
            <?php if ($u) : ?>
              <li><strong>ایمیل:</strong> <?= casting_e($u->user_email) ?></li>
            <?php endif; ?>
          </ul>
          <?php if ((int) ($row['attachment_id'] ?? 0) > 0) : ?>
            <?php casting_render_receipt_thumbnail((int) $row['attachment_id']); ?>
          <?php endif; ?>
          <?php if ($status === 'pending') : ?>
            <div class="cta-row">
              <form method="post" action="premium.php?status=<?= casting_e($admin_filter) ?>#admin-receipts">
                <?php wp_nonce_field('casting_admin_receipt'); ?>
                <input type="hidden" name="admin_receipt" value="1">
                <input type="hidden" name="receipt_id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn-primary" type="submit" name="action" value="approve">تأیید و فعال‌سازی ویژه</button>
                <button class="btn btn-reject" type="submit" name="action" value="reject">رد</button>
              </form>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php casting_render_panel_end(); ?>
