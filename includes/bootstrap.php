<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (!file_exists(CASTING_WP_LOAD)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>خطا</title></head><body style="font-family:sans-serif;padding:2rem;direction:rtl">';
    echo '<h1>وردپرس پیدا نشد</h1>';
    echo '<p>فایل <code>config.php</code> را باز کنید و مسیر <code>CASTING_WP_LOAD</code> را به <code>wp-load.php</code> سایت خودتان تنظیم کنید.</p>';
    echo '<p>مسیر فعلی: <code>' . htmlspecialchars(CASTING_WP_LOAD, ENT_QUOTES, 'UTF-8') . '</code></p>';
    echo '</body></html>';
    exit;
}

require_once CASTING_WP_LOAD;

$casting_guard = dirname(__DIR__) . '/mu-plugin/casting-wp-admin-guard.php';
if (is_readable($casting_guard) && function_exists('casting_guard_register')) {
    casting_guard_register();
} elseif (is_readable($casting_guard)) {
    require_once $casting_guard;
}

add_action('init', static function (): void {
    add_image_size('casting_portrait', 360, 480, true);
});

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

require_once __DIR__ . '/portal-auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => casting_portal_cookie_path(),
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('casting_portal_sid');
    session_start();
}

casting_bootstrap_portal_auth();

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/rate-limit.php';

function casting_strlen(string $value): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value, 'UTF-8');
    }
    return strlen($value);
}

function casting_nocache(): void
{
    if (function_exists('nocache_headers')) {
        nocache_headers();
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function casting_brand(): string
{
    return CASTING_BRAND;
}

function casting_role_label(string $role): string
{
    return CASTING_ROLES[$role] ?? $role;
}

function casting_valid_role(string $role): bool
{
    return array_key_exists($role, CASTING_ROLES);
}

function casting_is_employer_role(string $role): bool
{
    return in_array($role, CASTING_EMPLOYER_ROLES, true);
}

function casting_portal_owner_login(): string
{
    if (defined('CASTING_PORTAL_OWNER')) {
        $login = strtolower(trim((string) CASTING_PORTAL_OWNER));
        if ($login !== '') {
            return $login;
        }
    }

    return 'eshahabian';
}

function casting_user_is_portal_owner(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }

    return strtolower((string) $user->user_login) === casting_portal_owner_login();
}

function casting_user_can_member_search(int $user_id): bool
{
    if (casting_user_is_portal_owner($user_id)) {
        return true;
    }

    if (casting_get_user_role($user_id) === 'director') {
        return true;
    }

    if (!function_exists('casting_user_is_premium')) {
        require_once __DIR__ . '/premium.php';
    }

    return casting_user_is_premium($user_id);
}

function casting_get_user_role(int $user_id): string
{
    $role = get_user_meta($user_id, 'casting_role', true);
    return is_string($role) ? $role : '';
}

function casting_set_flash(string $type, string $message): void
{
    $_SESSION['casting_flash'] = ['type' => $type, 'message' => $message];
}

function casting_get_flash(): ?array
{
    if (empty($_SESSION['casting_flash'])) {
        return null;
    }
    $flash = $_SESSION['casting_flash'];
    unset($_SESSION['casting_flash']);
    return $flash;
}

function casting_url(string $path): string
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base === '/' || $base === '\\' || $base === '.') {
        $base = '';
    }
    return $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
}

function casting_redirect(string $path): void
{
    wp_safe_redirect(casting_url($path));
    exit;
}

function casting_require_login(string $portal): WP_User
{
    $user = casting_current_user();
    if (!$user) {
        casting_set_flash('error', 'لطفاً ابتدا وارد شوید.');
        casting_redirect('login.php');
    }

    $role = casting_get_user_role((int) $user->ID);
    if ($portal === 'talent' && $role !== 'talent') {
        casting_set_flash('error', 'این بخش فقط برای هنرمندان است.');
        casting_redirect(casting_is_employer_role($role) ? 'panel.php' : 'login.php');
    }
    if ($portal === 'employer' && !casting_is_employer_role($role)) {
        casting_set_flash('error', 'این بخش فقط برای کارگردان و تهیه‌کننده است.');
        casting_redirect($role === 'talent' ? 'panel.php' : 'login.php');
    }

    return $user;
}

function casting_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function casting_asset(string $path): string
{
    return 'assets/' . ltrim($path, '/');
}
