<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$transactions = casting_user_transactions($user_id);
$receipts = casting_user_receipts($user_id);
$plans = casting_premium_plans();

casting_render_panel_start('تراکنش‌های مالی', 'transactions');
casting_render_flash();
?>
<section class="dash-card">
  <h1>تراکنش‌های مالی</h1>

  <?php if (!$transactions && !$receipts) : ?>
    <p class="empty-state">تراکنشی ثبت نشده است.</p>
  <?php else : ?>
    <?php if ($receipts) : ?>
      <h2 class="panel-section-title">فیش‌های ثبت‌شده</h2>
      <ul class="panel-list">
        <?php foreach ($receipts as $row) : ?>
          <li class="panel-list-item">
            <div>
              <strong><?= casting_e($plans[$row['plan_key']]['label'] ?? $row['plan_key']) ?></strong>
              <span class="meta"><?= casting_e(number_format((int) $row['amount'])) ?> تومان · <?= casting_e((string) $row['reference_code']) ?></span>
            </div>
            <span class="chip"><?= casting_e(casting_premium_status_label((string) $row['status'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($transactions) : ?>
      <h2 class="panel-section-title" style="margin-top:1.5rem">گردش حساب</h2>
      <ul class="panel-list">
        <?php foreach ($transactions as $row) : ?>
          <li class="panel-list-item">
            <div>
              <strong><?= casting_e((string) ($row['title'] ?? '')) ?></strong>
              <span class="meta"><?= casting_e((string) ($row['at'] ?? '')) ?></span>
            </div>
            <span class="chip"><?= casting_e(casting_premium_status_label((string) ($row['status'] ?? 'pending'))) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
