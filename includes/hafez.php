<?php
declare(strict_types=1);

/**
 * عکس‌های خطاطی فوتر صفحهٔ اصلی — PNG شفاف.
 *
 * @return list<string> مسیر نسبی asset
 */
function casting_hafez_image_files(): array
{
    $dir = dirname(__DIR__) . '/assets/images/hafez';
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/verse-*.png');
    if (!is_array($files) || $files === []) {
        return [];
    }

    sort($files);
    $out = [];
    foreach ($files as $file) {
        $out[] = 'images/hafez/' . basename($file);
    }

    return $out;
}

function casting_hafez_random_image(): string
{
    $files = casting_hafez_image_files();
    if ($files === []) {
        return '';
    }

    return casting_asset($files[array_rand($files)]);
}
