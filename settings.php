<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$can_photo = casting_user_can_upload_portraits($user_id);

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

casting_render_panel_start('تنظیمات', 'settings');
casting_render_flash();
?>
<section class="dash-card">
  <?php casting_render_panel_heading('تنظیمات'); ?>
  <p class="meta">رمز عبور، تصویر پروفایل و کاربران بلاک‌شده.</p>
  <?php casting_render_panel_hub_tiles($tiles); ?>
</section>
<?php casting_render_panel_end(); ?>
