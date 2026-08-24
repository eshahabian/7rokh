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
 * مسیر مطلق هاست (7rokh.com — وردپرس در public_html، پورتال در casting-portal):
 *   '/home/rokhcom/public_html/wp-load.php'
 */
define('CASTING_WP_LOAD', __DIR__ . '/../wp-load.php');

/** نام نمایشی برند (قابل تغییر) */
define('CASTING_BRAND', '۷ رخ');

/** آدرس سایت اصلی */
define('CASTING_MAIN_SITE_URL', 'https://7rokh.com');

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
define('CASTING_PAYMENT_HOLDER', '۷ رخ');

/**
 * n8n — وقتی کسی ثبت‌نام کرد، JSON به این آدرس POST می‌شود.
 * خالی بگذارید = غیرفعال
 * مثال: https://your-n8n.com/webhook/casting-register
 */
define('CASTING_N8N_REGISTER_WEBHOOK', '');

/** اختیاری — همان مقدار را در n8n چک کنید (Header: X-Webhook-Secret) */
define('CASTING_N8N_WEBHOOK_SECRET', '');

/**
 * مدیران پورتال — تأیید فیش، ارتقا، و دسترسی ادمین پورتال
 * فقط این لیست + مالک پورتال؛ قابلیت manage_options وردپرس به‌تنهایی کافی نیست.
 * نام کاربری وردپرس را بنویسید.
 */
define('CASTING_PORTAL_ADMINS', [
    'eshahabian',
    'ardavan',
]);

/**
 * آمار تخصص‌ها در صفحهٔ عمومی (index) — فعلاً غیرفعال.
 * برای نمایش دوباره روی سایت true کنید.
 */
if (!defined('CASTING_PUBLIC_HOME_STATS')) {
    define('CASTING_PUBLIC_HOME_STATS', false);
}

/** مدیر اصلی بدون محدودیت — فقط این کاربر */
define('CASTING_PORTAL_OWNER', 'eshahabian');

/** گیرنده پیام «تماس با مدیر سایت» — نام کاربری وردپرس */
define('CASTING_CONTACT_SITE_ADMIN', 'eshahabian');

/** گیرنده پیام «تماس با مدیر ۷ رخ» — نام کاربری وردپرس */
define('CASTING_CONTACT_BRAND_ADMIN', 'Ardavan');

/** @deprecated دیگر برای تماس با ما استفاده نمی‌شود */
define('CASTING_CONTACT_NOTIFY_EMAILS', [
    'info@7rokh.com',
    'eshahabian@gmail.com',
]);

/** تنظیمات محلی (رمز SMTP، درگاه ملت، …) — در git نیست؛ باید قبل از defineهای حساس لود شود */
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

/**
 * درگاه به‌پرداخت ملت — مشخصات ترمینال فقط در config.local.php
 * sandbox = شبیه‌ساز داخلی | live = درگاه واقعی | off = پرداخت بسته
 */
if (!defined('CASTING_GATEWAY_MODE')) {
    define('CASTING_GATEWAY_MODE', 'live');
}
if (!defined('CASTING_BEHPARDAKHT_TERMINAL_ID')) {
    define('CASTING_BEHPARDAKHT_TERMINAL_ID', '');
}
if (!defined('CASTING_BEHPARDAKHT_USERNAME')) {
    define('CASTING_BEHPARDAKHT_USERNAME', '');
}
if (!defined('CASTING_BEHPARDAKHT_PASSWORD')) {
    define('CASTING_BEHPARDAKHT_PASSWORD', '');
}
if (!defined('CASTING_MELLAT_WSDL')) {
    define('CASTING_MELLAT_WSDL', 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
}
if (!defined('CASTING_MELLAT_PAY_URL')) {
    define('CASTING_MELLAT_PAY_URL', 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat');
}
/** آدرس ثبت‌شده در به‌پرداخت (درخواست ۸۶۹۰) */
if (!defined('CASTING_MELLAT_CALLBACK_URL')) {
    define('CASTING_MELLAT_CALLBACK_URL', 'https://7rokh.com/casting-portal/cart.php');
}

/**
 * درگاه سامان (SEP) — شماره ترمینال در config.local.php
 * provider = mellat | sep
 */
if (!defined('CASTING_GATEWAY_PROVIDER')) {
    define('CASTING_GATEWAY_PROVIDER', 'mellat');
}
if (!defined('CASTING_SEP_TERMINAL_ID')) {
    define('CASTING_SEP_TERMINAL_ID', '');
}
if (!defined('CASTING_SEP_CALLBACK_URL')) {
    define('CASTING_SEP_CALLBACK_URL', 'https://7rokh.com/casting-portal/cart.php');
}

/**
 * SMTP — برای ارسال ایمیل (تماس با ما، بازیابی رمز، …)
 * رمز را در config.local.php بگذارید (نمونه: config.local.php.example)
 */
if (!defined('CASTING_SMTP_HOST')) {
    define('CASTING_SMTP_HOST', 'mail.7rokh.com');
}
if (!defined('CASTING_SMTP_PORT')) {
    define('CASTING_SMTP_PORT', 465);
}
if (!defined('CASTING_SMTP_USER')) {
    define('CASTING_SMTP_USER', 'noreply@7rokh.com');
}
if (!defined('CASTING_SMTP_PASS')) {
    define('CASTING_SMTP_PASS', '');
}
if (!defined('CASTING_SMTP_SECURE')) {
    define('CASTING_SMTP_SECURE', 'ssl');
}
if (!defined('CASTING_MAIL_FROM')) {
    define('CASTING_MAIL_FROM', 'noreply@7rokh.com');
}
if (!defined('CASTING_MAIL_FROM_NAME')) {
    define('CASTING_MAIL_FROM_NAME', '۷ رخ');
}

/**
 * پیامک WebOne — کلید و خط را فقط در config.local.php بگذارید
 * پنل: https://webone-sms.ir
 * API پیش‌فرض: https://api.payamakapi.ir/api/v1/
 *
 * در پنل WebOne حتماً:
 * 1) API Key بسازید (منوی وب‌سرویس)
 * 2) در تنظیمات عمومی پنل، IP سرور را در آی‌پی‌های مجاز REST ثبت کنید
 * 3) OTP با الگو (اختیاری): CASTING_SMS_OTP_PATTERN_ID از پنل
 *    POST /SMS/Send با PatternId و PatternParameterData.ParameterValue
 *    بدون PatternId: همان POST /SMS/Send متنی (خط From) — نه SmartOTP
 * 4) برای پیامک متنی، شماره فرستنده (From) را از پنل بردارید
 */
/** تأیید OTP موبایل در ثبت‌نام — روی سرور در config.local.php هم true بماند */
if (!defined('CASTING_MOBILE_OTP_ENABLED')) {
    define('CASTING_MOBILE_OTP_ENABLED', true);
}
if (!defined('CASTING_SMS_ENABLED')) {
    define('CASTING_SMS_ENABLED', true);
}
if (!defined('CASTING_SMS_API_KEY')) {
    define('CASTING_SMS_API_KEY', '');
}
/** شماره خط فرستنده برای پیامک متنی (لینک بازیابی و …) */
if (!defined('CASTING_SMS_FROM')) {
    define('CASTING_SMS_FROM', '9998624065');
}
/** فرستنده SmartOTP — RestDocument: Auto یا شماره خط OTP */
if (!defined('CASTING_SMS_OTP_SENDER')) {
    define('CASTING_SMS_OTP_SENDER', 'Auto');
}
/**
 * OTP با الگو: POST /SMS/Send + PatternId + PatternParameterData.ParameterValue
 * شناسه را از پنل بردارید: وب‌سرویس → الگوی پیام وب‌سرویس
 */
if (!defined('CASTING_SMS_OTP_PATTERN_ID')) {
    define('CASTING_SMS_OTP_PATTERN_ID', '');
}
/** نام متغیر الگو — طبق Otino فقط یک حرف انگلیسی داخل {} مثل x */
if (!defined('CASTING_SMS_OTP_PATTERN_PARAM')) {
    define('CASTING_SMS_OTP_PATTERN_PARAM', 'x');
}
/** متن الگوی پنل: بخش ثابت + {x} — باید با الگوی تأییدشده یکی باشد */
if (!defined('CASTING_SMS_OTP_TEMPLATE')) {
    define('CASTING_SMS_OTP_TEMPLATE', 'کد ورود شما {x}');
}
/** نام کاربری پنل برای ارسال HTTP GET (اختیاری) */
if (!defined('CASTING_SMS_USERNAME')) {
    define('CASTING_SMS_USERNAME', '');
}
if (!defined('CASTING_SMS_PASSWORD')) {
    define('CASTING_SMS_PASSWORD', '');
}
if (!defined('CASTING_SMS_HTTP_SEND_URL')) {
    define('CASTING_SMS_HTTP_SEND_URL', 'https://webone-sms.ir/SMSInOutBox/SendSms');
}
/** اختیاری: override آدرس API — پیش‌فرض api.payamakapi.ir */
if (!defined('CASTING_SMS_API_BASE')) {
    define('CASTING_SMS_API_BASE', 'https://api.payamakapi.ir/api/v1/');
}
