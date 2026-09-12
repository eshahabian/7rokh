<?php
/**
 * بارگذاری امن فایل PHP — وردپرس را load نمی‌کند.
 * اگر آپلود SFTP فایل را ناقص گذاشته باشد، ParseError را قبل از require می‌گیرد.
 */
declare(strict_types=1);

function casting_safe_load_error(?string $set = null): string
{
    if ($set !== null) {
        $GLOBALS['casting_safe_load_error'] = $set;
    }

    return (string) ($GLOBALS['casting_safe_load_error'] ?? '');
}

function casting_php_source_parses(string $src): bool
{
    if (trim($src) === '') {
        casting_safe_load_error('فایل خالی است');

        return false;
    }
    if (!function_exists('token_get_all') || !defined('TOKEN_PARSE')) {
        return true;
    }
    try {
        token_get_all($src, TOKEN_PARSE);

        return true;
    } catch (ParseError $e) {
        casting_safe_load_error('parse: ' . $e->getMessage() . ' (خط ' . $e->getLine() . ')');

        return false;
    } catch (Throwable $e) {
        casting_safe_load_error($e->getMessage());

        return false;
    }
}

function casting_php_file_parses(string $path): bool
{
    if (!is_file($path) || !is_readable($path)) {
        casting_safe_load_error('موجود نیست: ' . basename($path));

        return false;
    }
    $src = @file_get_contents($path);
    if (!is_string($src)) {
        casting_safe_load_error('خوانده نشد: ' . basename($path));

        return false;
    }

    return casting_php_source_parses($src);
}

function casting_safe_require_once(string $path): bool
{
    if (!casting_php_file_parses($path)) {
        return false;
    }
    try {
        require_once $path;

        return true;
    } catch (Throwable $e) {
        casting_safe_load_error($e->getMessage() . ' in ' . basename($path));

        return false;
    }
}

function casting_safe_fail_page(string $title, string $detail = ''): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safe_detail = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $safe_title . '</title></head>';
    echo '<body style="font-family:Tahoma,sans-serif;padding:2rem;direction:rtl;line-height:1.7">';
    echo '<h1>' . $safe_title . '</h1>';
    if ($safe_detail !== '') {
        echo '<p>' . $safe_detail . '</p>';
    }
    echo '<p>اگر تازه فایل آپلود کرده‌اید، همان فایل را کامل از نسخه پشتیبان لپ‌تاپ دوباره بفرستید.</p>';
    echo '<p><a href="login.php">ورود</a> · <a href="contact.php">تماس با ما</a></p>';
    echo '</body></html>';
    exit;
}
