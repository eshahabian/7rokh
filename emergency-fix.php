<?php
/**
 * اضطراری — wp-load را load نمی‌کند.
 * بعد از موفقیت این فایل را حذف کنید.
 *
 * https://7rokh.ir/emergency-fix.php?key=7rokh-fix-now
 * یا
 * https://7rokh.ir/casting-portal/emergency-fix.php?key=7rokh-fix-now
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== '7rokh-fix-now') {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$here = __DIR__;
$roots = array_unique([
    $here,
    dirname($here),
    dirname($here) . '/public_html',
]);

$files = [
    'wp-content/mu-plugins/casting-wp-admin-guard.php',
    'wp-content/mu-plugins/casting-wp-admin-guard-loader.php',
    'casting-portal/mu-plugin/casting-wp-admin-guard.php',
    'casting-portal/mu-plugin/casting-wp-admin-guard-loader.php',
    'casting-portal/mu-plugin/README.txt',
];

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }
    echo "ROOT: {$root}\n";
    foreach ($files as $rel) {
        $path = $root . '/' . $rel;
        if (!is_file($path)) {
            echo "  NOT FOUND: {$path}\n";
            continue;
        }
        if (@unlink($path)) {
            echo "  DELETED: {$path}\n";
        } else {
            echo "  FAILED: {$path}\n";
        }
    }
    $dir = $root . '/casting-portal/mu-plugin';
    if (is_dir($dir)) {
        @rmdir($dir);
        echo is_dir($dir) ? "  DIR STILL EXISTS: {$dir}\n" : "  REMOVED DIR: {$dir}\n";
    }
    echo "\n";
}

echo "Done. Delete emergency-fix.php from server.\n";
echo "Test: https://7rokh.ir/ and https://7rokh.ir/casting-portal/\n";
