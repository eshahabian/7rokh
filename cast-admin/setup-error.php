<?php
namespace CastingCms;

// This helper also parses on older PHP, before the rest of the application loads.
function show_setup_error($reason) {
    $messages = array(
        'PHP_VERSION' => array('نسخهٔ PHP پنل مناسب نیست.', 'پنل به PHP 7.4 یا بالاتر نیاز دارد. برای اجرای پنل با نسخهٔ جداگانه از سایت، از پشتیبانی هاست کمک بگیرید؛ نسخهٔ PHP کل دامنه را بدون بررسی سازگاری سایت تغییر ندهید.'),
        'PHP_EXTENSIONS' => array('افزونه‌های پایهٔ PHP فعال نیستند.', 'از پشتیبانی هاست بخواهید افزونه‌های session و json را برای PHP همین دامنه فعال کند.'),
        'STORAGE_WRITE' => array('PHP اجازهٔ ساخت یا نوشتن پوشهٔ خصوصی پنل را ندارد.', 'مراحل انتشار جدید cPanel را اجرا کنید تا پوشهٔ خصوصی بیرون public_html ساخته شود. مالک پوشه باید کاربر PHP همین هاست و مجوز آن 0700 باشد. اگر open_basedir دسترسی را محدود کرده، پشتیبانی هاست باید همان مسیر خصوصی را مجاز کند.'),
        'STORAGE_LOCATION' => array('مسیر دادهٔ پنل داخل پوشهٔ عمومی قرار گرفته است.', 'مسیر CASTING_CMS_DATA_DIR باید بیرون public_html و بیرون مخزن باشد. ذخیرهٔ اطلاعات ورود در مسیر عمومی مجاز نیست.'),
        'SESSION' => array('PHP نمی‌تواند نشست امن پنل را ذخیره کند.', 'مالکیت و دسترسی نوشتن پوشهٔ خصوصی، محدودیت open_basedir و فضای آزاد هاست را بررسی کنید. نشست‌های پنل در همان فضای خصوصی ذخیره می‌شوند.'),
        'FILES' => array('فایل‌های پنل کامل بارگذاری نشده‌اند.', 'پوشهٔ cast-admin را از آخرین نسخه به‌طور کامل منتشر کنید. فایل config.php و شناسهٔ نصب موجود را حفظ کنید.'),
        'SETUP' => array('راه‌اندازی پنل با خطا روبه‌رو شد.', 'در بخش Errors یا error_log هاست، خط CAST ADMIN setup را برای بررسی بفرستید. رمز و کد فعال‌سازی لازم نیست.')
    );
    $message = isset($messages[$reason]) ? $messages[$reason] : $messages['SETUP'];
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>راه‌اندازی پنل طراحی</title><body style="font:15px/2 Tahoma,sans-serif;background:#f3f5f7;color:#203746;padding:24px"><main style="max-width:650px;margin:8vh auto;background:white;padding:28px;border-radius:12px">';
    echo '<h1 style="font-size:21px">'.htmlspecialchars($message[0],ENT_QUOTES,'UTF-8').'</h1><p>'.htmlspecialchars($message[1],ENT_QUOTES,'UTF-8').'</p><p>کد بررسی: <b dir="ltr">CMS-'.htmlspecialchars($reason,ENT_QUOTES,'UTF-8').'</b></p>';
    echo '<p>این پنل فقط برای طراحی و ویرایش سایت است.</p></main></body></html>';
}

function setup_error_reason($error) {
    $message = $error->getMessage();
    if ($message === 'Private storage is not writable') return 'STORAGE_WRITE';
    if ($message === 'Storage must be outside document root') return 'STORAGE_LOCATION';
    if ($message === 'Session unavailable') return 'SESSION';
    return 'SETUP';
}
