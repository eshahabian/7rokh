<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/admin-access.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$premium = casting_user_is_premium($user_id);
$is_director = casting_user_is_director_role($user_id);
$pending_receipts = casting_user_has_admin_permission($user_id, 'approve_receipts')
    ? casting_admin_pending_receipt_count()
    : 0;

$tiles = [
    [
        'title' => 'خرید و فعال‌سازی',
        'desc'  => 'اشتراک ویژه، جستجو و اولویت در نتایج',
        'href'  => 'premium.php',
        'badge' => $pending_receipts,
    ],
    [
        'title' => 'ثبت فیش کارت به کارت',
        'desc'  => 'بارگذاری تصویر فیش پرداخت',
        'href'  => 'premium-receipt.php',
    ],
    [
        'title' => 'تراکنش‌های مالی',
        'desc'  => 'تاریخچه پرداخت‌ها و وضعیت فیش‌ها',
        'href'  => 'transactions.php',
    ],
];

// موقتاً مخفی — صفحه cancel-membership.php حفظ شده است
// if (!$is_director) {
//     $tiles[] = [
//         'title' => 'انصراف از عضویت',
//         'desc'  => 'درخواست لغو حساب کاربری',
//         'href'  => 'cancel-membership.php',
//     ];
// }

casting_render_panel_start('عضویت و اعتبار', 'membership');
casting_render_flash();
?>
<section class="dash-card">
  <h1>عضویت و اعتبار</h1>
  <p class="meta">همه امکانات مربوط به اشتراک ویژه، پرداخت و وضعیت حساب در این بخش است.</p>

  <?php if ($premium) : ?>
    <div class="flash flash-success">حساب کاربری ویژه فعال است.</div>
    <?php casting_render_premium_countdown($user_id); ?>
  <?php else : ?>
    <p class="meta">حساب ویژه ندارید. از «خرید و فعال‌سازی» می‌توانید اشتراک بگیرید.</p>
  <?php endif; ?>

  <?php casting_render_panel_hub_tiles($tiles); ?>
</section>
<?php casting_render_panel_end(); ?>
