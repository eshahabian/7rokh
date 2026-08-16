<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mobile-app.php';

casting_nocache();

$file = casting_android_apk_file();
$name = casting_android_apk_filename();

if (!casting_android_apk_ready()) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>یافت نشد</title></head><body>';
    echo '<p>فایل نصب اپلیکیشن هنوز در دسترس نیست.</p>';
    echo '</body></html>';
    exit;
}

$size = (int) filesize($file);
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
