<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
casting_require_admin_permission('view_transactions');

$search = trim((string) ($_GET['q'] ?? ''));
$target_id = (int) ($_GET['user'] ?? 0);
$results = $search !== '' ? casting_admin_search_casting_users($search) : [];
$plans = casting_premium_plans();

$target = $target_id > 0 ? get_user_by('id', $target_id) : false;
$receipts = ($target && casting_get_user_role($target_id) !== '') ? casting_user_receipts($target_id) : [];
$transactions = ($target && casting_get_user_role($target_id) !== '') ? casting_user_transactions($target_id) : [];

$all_receipts = !$target ? casting_admin_list_all_receipts_with_users(200) : [];
$all_transactions = !$target ? casting_admin_list_all_account_transactions(200) : [];

casting_render_panel_start('تراکنش کاربران', 'admin-transactions');
casting_render_flash();
?>
<section class="dash-card">
  <h1>تراکنش‌های مالی کاربران</h1>
  <p class="meta">فیش‌ها و گردش حساب همه کاربران — یا جستجو برای یک نفر.</p>

  <form class="form admin-search-form" method="get" action="admin-transactions.php">
    <div class="field">
      <label for="q">جستجوی کاربر</label>
      <input id="q" name="q" type="search" value="<?= casting_e($search) ?>" placeholder="نام، ایمیل یا نام کاربری">
    </div>
    <button class="btn btn-primary" type="submit">جستجو</button>
    <?php if ($target_id > 0) : ?>
      <a class="btn btn-ghost" href="admin-transactions.php">نمایش همه</a>
    <?php endif; ?>
  </form>

  <?php if ($search !== '' && !$results) : ?>
    <p class="empty-state">کاربری پیدا نشد.</p>
  <?php elseif ($results) : ?>
    <ul class="admin-user-pick-list">
      <?php foreach ($results as $row) : ?>
        <li>
          <a href="admin-transactions.php?user=<?= (int) $row['id'] ?>">
            <strong><?= casting_e($row['name']) ?></strong>
            <span class="meta"><?= casting_e($row['login']) ?> · <?= casting_e(casting_role_label($row['role'])) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($target && casting_get_user_role($target_id) !== '') : ?>
    <div class="admin-user-detail">
      <h2 class="panel-section-title"><?= casting_e($target->display_name) ?> <span class="meta">@<?= casting_e($target->user_login) ?></span></h2>

      <h3 class="panel-section-title">فیش‌های ثبت‌شده</h3>
      <?php if (!$receipts) : ?>
        <p class="meta">فیشی ثبت نشده.</p>
      <?php else : ?>
        <ul class="panel-list">
          <?php foreach ($receipts as $row) : ?>
            <li class="panel-list-item panel-list-item-receipt">
              <div>
                <strong><?= casting_e($plans[$row['plan_key']]['label'] ?? $row['plan_key']) ?></strong>
                <span class="meta"><?= casting_e(number_format((int) $row['amount'])) ?> تومان · <?= casting_e((string) $row['reference_code']) ?> · <?= casting_e((string) $row['created_at']) ?></span>
                <?php casting_render_receipt_thumbnail((int) ($row['attachment_id'] ?? 0)); ?>
              </div>
              <span class="chip"><?= casting_e(casting_premium_status_label((string) $row['status'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <h3 class="panel-section-title" style="margin-top:1.5rem">گردش حساب</h3>
      <?php if (!$transactions) : ?>
        <p class="meta">تراکنشی نیست.</p>
      <?php else : ?>
        <ul class="panel-list">
          <?php foreach ($transactions as $row) : ?>
            <li class="panel-list-item">
              <div>
                <strong><?= casting_e((string) ($row['title'] ?? '')) ?></strong>
                <span class="meta"><?= casting_e((string) ($row['at'] ?? '')) ?><?php if (!empty($row['ref'])) : ?> · <?= casting_e((string) $row['ref']) ?><?php endif; ?></span>
              </div>
              <span class="chip"><?= casting_e(casting_premium_status_label((string) ($row['status'] ?? 'pending'))) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php else : ?>
    <h2 class="panel-section-title">همه فیش‌های ثبت‌شده</h2>
    <?php if (!$all_receipts) : ?>
      <p class="meta">فیشی ثبت نشده است.</p>
    <?php else : ?>
      <div class="admin-table-wrap">
        <table class="admin-table admin-transactions-table">
          <thead>
            <tr>
              <th>کاربر</th>
              <th>پلن</th>
              <th>مبلغ</th>
              <th>کد پیگیری</th>
              <th>تاریخ</th>
              <th>وضعیت</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($all_receipts as $row) : ?>
              <tr>
                <td>
                  <a href="admin-transactions.php?user=<?= (int) ($row['user_id'] ?? 0) ?>">
                    <strong><?= casting_e((string) ($row['user_name'] ?? '')) ?></strong>
                  </a>
                  <span class="meta">@<?= casting_e((string) ($row['user_login'] ?? '')) ?></span>
                </td>
                <td><?= casting_e($plans[$row['plan_key']]['label'] ?? (string) ($row['plan_key'] ?? '')) ?></td>
                <td><?= casting_e(number_format((int) ($row['amount'] ?? 0))) ?> تومان</td>
                <td><?= casting_e((string) ($row['reference_code'] ?? '')) ?></td>
                <td><?= casting_e((string) ($row['created_at'] ?? '')) ?></td>
                <td><span class="chip"><?= casting_e(casting_premium_status_label((string) ($row['status'] ?? 'pending'))) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <h2 class="panel-section-title" style="margin-top:1.5rem">گردش حساب همه کاربران</h2>
    <?php if (!$all_transactions) : ?>
      <p class="meta">تراکنش حسابی ثبت نشده است.</p>
    <?php else : ?>
      <div class="admin-table-wrap">
        <table class="admin-table admin-transactions-table">
          <thead>
            <tr>
              <th>کاربر</th>
              <th>عنوان</th>
              <th>مبلغ</th>
              <th>تاریخ</th>
              <th>وضعیت</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($all_transactions as $row) : ?>
              <tr>
                <td>
                  <a href="admin-transactions.php?user=<?= (int) ($row['user_id'] ?? 0) ?>">
                    <strong><?= casting_e((string) ($row['user_name'] ?? '')) ?></strong>
                  </a>
                  <span class="meta">@<?= casting_e((string) ($row['user_login'] ?? '')) ?></span>
                </td>
                <td>
                  <?= casting_e((string) ($row['title'] ?? '')) ?>
                  <?php if (!empty($row['ref'])) : ?>
                    <span class="meta"> · <?= casting_e((string) $row['ref']) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= !empty($row['amount']) ? casting_e(number_format((int) $row['amount'])) . ' تومان' : '—' ?></td>
                <td><?= casting_e((string) ($row['at'] ?? '')) ?></td>
                <td><span class="chip"><?= casting_e(casting_premium_status_label((string) ($row['status'] ?? 'pending'))) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
