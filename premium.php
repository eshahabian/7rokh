<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/panel.php';

casting_nocache();

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$premium = casting_user_is_premium($user_id);
$plans = casting_premium_plans();
$is_admin = casting_user_is_portal_admin();
$admin_filter = sanitize_key((string) ($_GET['status'] ?? 'pending'));
if (!in_array($admin_filter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $admin_filter = 'pending';
}

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_receipt'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_admin_receipt')) {
        casting_set_flash('error', 'درخواست نامعتبر است.');
    } else {
        $receipt_id = (int) ($_POST['receipt_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'approve') {
            $result = casting_approve_premium_receipt($receipt_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'فیش تأیید و اشتراک ویژه فعال شد.' : $result['error']);
        } elseif ($action === 'reject') {
            $result = casting_reject_premium_receipt($receipt_id);
            casting_set_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'فиш رد شد.' : $result['error']);
        }
    }
    casting_redirect('premium.php?status=' . $admin_filter . '#admin-receipts');
}

$admin_receipts = $is_admin ? casting_admin_list_receipts($admin_filter === 'all' ? '' : $admin_filter) : [];

casting_render_panel_start('خرید و فعال‌سازی', 'premium');
casting_render_flash();
?>
<section class="dash-card">
  <h1>خرید و فعال‌سازی</h1>
  <p class="meta">با اشتراک ویژه، پروفایل شما در اولین نتایج جستجو نمایش داده می‌شود.</p>

  <?php if ($premium) : ?>
    <div class="flash flash-success">اشتراک ویژه شما تا <strong><?= casting_e(casting_premium_until_label($user_id)) ?></strong> فعال است.</div>
  <?php endif; ?>

  <div class="premium-grid">
    <?php foreach ($plans as $key => $plan) : ?>
      <article class="premium-plan">
        <h2><?= casting_e($plan['label']) ?></h2>
        <p class="premium-price"><?= casting_e(number_format((int) $plan['price'])) ?> تومان</p>
        <p><?= casting_e($plan['description']) ?></p>
        <a class="btn btn-primary" href="premium-receipt.php?plan=<?= casting_e($key) ?>">ثبت فیش و فعال‌سازی</a>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="bio-block" style="margin-top:1.5rem">
    <h2>نحوه پرداخت</h2>
    <ul class="info-list">
      <li><strong>کارت:</strong> <?= casting_e(CASTING_PAYMENT_CARD) ?></li>
      <li><strong>به نام:</strong> <?= casting_e(CASTING_PAYMENT_HOLDER) ?></li>
    </ul>
    <p class="meta">پس از واریز، فیش را ثبت کنید. پس از تأیید مدیر، حساب ویژه فعال می‌شود.</p>
  </div>
</section>

<?php if ($is_admin) : ?>
<section class="dash-card" id="admin-receipts" style="margin-top:1rem">
  <h2 class="panel-section-title">مدیریت فیش‌ها (فقط مدیر)</h2>
  <p class="meta">تأیید یا رد فیش‌های کاربران — همین صفحه، بدون رفتن جای دیگر.</p>

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
          $plan_key = (string) $row['plan_key'];
          $plan_label = $plans[$plan_key]['label'] ?? $plan_key;
          $attach_id = (int) ($row['attachment_id'] ?? 0);
          $img_url = $attach_id > 0 ? wp_get_attachment_image_url($attach_id, 'medium') : '';
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
          <?php if (is_string($img_url) && $img_url !== '') : ?>
            <p><a href="<?= casting_e($img_url) ?>" target="_blank" rel="noopener"><img class="admin-receipt-img" src="<?= casting_e($img_url) ?>" alt="فیش"></a></p>
          <?php endif; ?>
          <?php if ($status === 'pending') : ?>
            <div class="cta-row">
              <form method="post" action="premium.php?status=<?= casting_e($admin_filter) ?>#admin-receipts">
                <?php wp_nonce_field('casting_admin_receipt'); ?>
                <input type="hidden" name="admin_receipt" value="1">
                <input type="hidden" name="receipt_id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn-primary" type="submit" name="action" value="approve">تأیید</button>
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
