<?php
/**
 * مسیر فایل wp-load.php وردپرس را تنظیم کنید.
 *
 * اگر پوشه casting-portal را داخل ریشه وردپرس بگذارید
 * (کنار wp-config.php)، مقدار پیش‌فرض درست است.
 *
 * مثال‌ها:
 *   __DIR__ . '/../wp-load.php'          → پوشه داخل ریشه وردپرس
 *   __DIR__ . '/../../wp-load.php'       → یک سطح عمیق‌تر
 *   '/home/USER/public_html/wp-load.php' → مسیر مطلق هاست
 */
define('CASTING_WP_LOAD', __DIR__ . '/../wp-load.php');

/** نام نمایشی برند (قابل تغییر) */
define('CASTING_BRAND', 'هفت رخ');

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

/** ایمیل‌های دریافت پیام «تماس با ما» */
define('CASTING_CONTACT_NOTIFY_EMAILS', [
    'info@7rokh.ir',
    'eshahabian@gmail.com',
]);

/**
 * SMTP — برای ارسال ایمیل (تماس با ما، بازیابی رمز، …)
 * رمز را در config.local.php بگذارید (نمونه: config.local.php.example)
 */
define('CASTING_SMTP_HOST', 'mail.7rokh.ir');
define('CASTING_SMTP_PORT', 465);
define('CASTING_SMTP_USER', 'contact.us@7rokh.ir');
define('CASTING_SMTP_PASS', '');
define('CASTING_SMTP_SECURE', 'ssl');
define('CASTING_MAIL_FROM', 'contact.us@7rokh.ir');
define('CASTING_MAIL_FROM_NAME', 'هفت رخ');

/** تنظیمات محلی (رمز SMTP و …) — در git نیست */
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
