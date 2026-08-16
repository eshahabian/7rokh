<?php
declare(strict_types=1);

function casting_android_apk_filename(): string
{
    return '7rokh.apk';
}

function casting_android_apk_file(): string
{
    return dirname(__DIR__) . '/downloads/' . casting_android_apk_filename();
}

function casting_android_apk_ready(): bool
{
    $file = casting_android_apk_file();

    return is_file($file) && is_readable($file) && filesize($file) > 1024;
}

function casting_android_apk_download_url(): string
{
    return casting_url('app-download.php');
}
