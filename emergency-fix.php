<?php
/**
 * اضطراری — فقط یک‌بار اجرا کنید، بعد حذفش کنید.
 * wp-load را load نمی‌کند؛ مستقیم فایل guard خراب را پاک می‌کند.
 *
 * آپلود: public_html/emergency-fix.php
 * باز کنید: https://7rokh.ir/casting-portal/emergency-fix.php?key=7rokh-fix-now
 * بعد از موفقیت این فایل را حذف کنید.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== '7rokh-fix-now') {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$root = __DIR__;
// اگر داخل casting-portal اجرا شد، ریشه وردپرس یک پوشه بالاتر است
if (is_dir($root . '/../wp-content') && !is_dir($root . '/wp-content')) {
    $root = dirname($root);
}
$targets = [
    $root . '/wp-content/mu-plugins/casting-wp-admin-guard.php',
    $root . '/wp-content/mu-plugins/casting-wp-admin-guard-loader.php',
];

foreach ($targets as $path) {
    if (!is_file($path)) {
        echo "NOT FOUND: {$path}\n";
        continue;
    }
    if (@unlink($path)) {
        echo "DELETED: {$path}\n";
    } else {
        echo "FAILED: {$path}\n";
    }
}

echo "\nDone. Delete emergency-fix.php from server now.\n";
echo "Then open https://7rokh.ir/ and https://7rokh.ir/casting-portal/\n";
