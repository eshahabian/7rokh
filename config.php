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
