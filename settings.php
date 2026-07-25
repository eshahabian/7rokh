<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/contact-messages.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$can_photo = casting_user_can_upload_portraits($user_id);
$unread_contacts = casting_contact_unread_count_for_user($user_id);

$tiles = [
    [
        'title' => 'تغییر رمز عبور',
        'desc'  => 'به‌روزرسانی رمز ورود به حساب',
        'href'  => 'change-password.php',
    ],
];

if ($can_photo) {
    $tiles[] = [
        'title' => 'ویرایش تصویر',
        'desc'  => 'آپلود و مدیریت عکس‌های پروفایل',
        'href'  => 'profile-photo.php',
    ];
}

$tiles[] = [
    'title' => 'بلاک‌شده‌های من',
    'desc'  => 'مدیریت کاربرانی که مسدود کرده‌اید',
    'href'  => 'blocked-by-me.php',
];
$tiles[] = [
    'title' => 'جدیدترین کاربران',
    'desc'  => 'فهرست تازه‌واردهای پورتال',
    'href'  => 'newest-users.php',
];
$tiles[] = [
    'title' => 'تماس با ما',
    'desc'  => 'ارسال پیام به پشتیبانی',
    'href'  => 'contact.php',
    'badge' => $unread_contacts,
];
$tiles[] = [
    'title' => 'سوالات متداول',
    'desc'  => 'پاسخ‌های آماده درباره پورتال',
    'href'  => 'faq.php',
];
$tiles[] = [
    'title' => 'قوانین',
    'desc'  => 'شرایط و مقررات استفاده',
    'href'  => 'rules.php',
];

casting_render_panel_start('تنظیمات', 'settings');
casting_render_flash();
?>
<section class="dash-card">
  <h1>تنظیمات</h1>
  <p class="meta">امنیت حساب، تصاویر، بلاک‌ها و راهنماها در این صفحه جمع شده‌اند.</p>
  <?php casting_render_panel_hub_tiles($tiles); ?>
</section>
<?php casting_render_panel_end(); ?>
