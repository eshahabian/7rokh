<?php
/**
 * مسیر فایل wp-load.php وردپرس را تنظیم کنید.
 *
 * اگر wp-load.php کنار config.php است (همان پوشه):
 *   __DIR__ . '/wp-load.php'
 *
 * اگر پورتال داخل زیرپوشه وردپرس است:
 *   __DIR__ . '/../wp-load.php'
 *
 * مسیر مطلق هاست (7rokh.ir — وردپرس در public_html، پورتال در casting-portal):
 *   '/home/rokhir/public_html/wp-load.php'
 */
define('CASTING_WP_LOAD', __DIR__ . '/../wp-load.php');

/** نام نمایشی برند (قابل تغییر) */
define('CASTING_BRAND', 'هفت رخ');

/** آدرس سایت اصلی */
define('CASTING_MAIN_SITE_URL', 'https://7rokh.ir');

/** نقش‌های مجاز */
define('CASTING_ROLES', [
    'talent'   => 'هنرمند',
    'director' => 'کارگردان',
    'producer' => 'تهیه‌کننده',
]);

/** نقش‌هایی که از درگاه کارفرما وارد می‌شوند */
define('CASTING_EMPLOYER_ROLES', ['director', 'producer']);

/** اطلاعات واریز کارت به کارت */
define('CASTING_PAYMENT_CARD', '6037-9971-0000-0000');
define('CASTING_PAYMENT_HOLDER', 'هفت رخ');

/**
 * n8n — وقتی کسی ثبت‌نام کرد، JSON به این آدرس POST می‌شود.
 * خالی بگذارید = غیرفعال
 * مثال: https://your-n8n.com/webhook/casting-register
 */
define('CASTING_N8N_REGISTER_WEBHOOK', '');

/** اختیاری — همان مقدار را در n8n چک کنید (Header: X-Webhook-Secret) */
define('CASTING_N8N_WEBHOOK_SECRET', '');

/**
 * مدیران پورتال — تأیید فیش و ارتقا به حساب ویژه (علاوه بر manage_options وردپرس)
 * نام کاربری وردپرس را بنویسید.
 */
define('CASTING_PORTAL_ADMINS', [
    'eshahabian',
]);

/** مدیر اصلی بدون محدودیت — فقط این کاربر */
define('CASTING_PORTAL_OWNER', 'eshahabian');

/** گیرنده پیام «تماس با مدیر سایت» — نام کاربری وردپرس */
define('CASTING_CONTACT_SITE_ADMIN', 'eshahabian');

/** گیرنده پیام «تماس با مدیر هفت رخ» — نام کاربری وردپرس */
define('CASTING_CONTACT_BRAND_ADMIN', 'Ardavan');

/** @deprecated دیگر برای تماس با ما استفاده نمی‌شود */
define('CASTING_CONTACT_NOTIFY_EMAILS', [
    'info@7rokh.ir',
    'eshahabian@gmail.com',
]);

/** تنظیمات محلی (رمز SMTP و …) — در git نیست؛ باید قبل از defineهای SMTP لود شود */
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

/**
 * SMTP — برای ارسال ایمیل (تماس با ما، بازیابی رمز، …)
 * رمز را در config.local.php بگذارید (نمونه: config.local.php.example)
 */
if (!defined('CASTING_SMTP_HOST')) {
    define('CASTING_SMTP_HOST', 'mail.7rokh.ir');
}
if (!defined('CASTING_SMTP_PORT')) {
    define('CASTING_SMTP_PORT', 465);
}
if (!defined('CASTING_SMTP_USER')) {
    define('CASTING_SMTP_USER', 'noreply@7rokh.ir');
}
if (!defined('CASTING_SMTP_PASS')) {
    define('CASTING_SMTP_PASS', '');
}
if (!defined('CASTING_SMTP_SECURE')) {
    define('CASTING_SMTP_SECURE', 'ssl');
}
if (!defined('CASTING_MAIL_FROM')) {
    define('CASTING_MAIL_FROM', 'noreply@7rokh.ir');
}
if (!defined('CASTING_MAIL_FROM_NAME')) {
    define('CASTING_MAIL_FROM_NAME', 'هفت رخ');
}
