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
require_once __DIR__ . '/session-guard.php';

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
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/otp.php';

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

/**
 * نمایش رنگی برند: ۷ سفید، رخ زرد
 */
function casting_brand_html(): string
{
    return '<span class="brand-mark"><span class="brand-mark-7">۷</span> <span class="brand-mark-rokh">رخ</span></span>';
}

/**
 * جایگزینی امن «۷ رخ» با نسخه رنگی داخل متن HTML-escaped
 */
function casting_brandify(string $text): string
{
    $safe = casting_e($text);
    $mark = casting_brand_html();
    $out = preg_replace('/۷\s*رخ|7\s*رخ/u', $mark, $safe);

    return is_string($out) ? $out : $safe;
}

function casting_role_label(string $role): string
{
    return CASTING_ROLES[$role] ?? $role;
}

/**
 * برچسب نقش عمومی کاربر برای کارت‌ها، چت، پروفایل و …
 * اولویت با اولین نوع فعالیت است (مثلاً «بازیگر تئاتر» به‌جای «هنرمند»).
 * مدیر اصلی پورتال همیشه «مدیر سایت» دیده می‌شود.
 */
function casting_user_public_role_label(int $user_id): string
{
    if ($user_id <= 0) {
        return '';
    }
    if (casting_user_is_portal_owner($user_id)) {
        return 'مدیر سایت';
    }
    if (function_exists('casting_dm_is_support_peer') && casting_dm_is_support_peer($user_id)) {
        return 'مدیر سایت';
    }

    if (!function_exists('casting_user_primary_activity_label')) {
        $activities_file = __DIR__ . '/activities.php';
        if (is_file($activities_file)) {
            require_once $activities_file;
        }
    }
    if (function_exists('casting_user_primary_activity_label')) {
        $activity = casting_user_primary_activity_label($user_id);
        if ($activity !== '') {
            return $activity;
        }
    }

    return casting_role_label(casting_get_user_role($user_id));
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

/**
 * آیا بیننده می‌تواند پروفایل عضو را ببیند؟
 * مدیر اصلی (eshahabian) بالاترین سطح را دارد و همه پروفایل‌ها را می‌بیند.
 */
function casting_user_can_view_member_profile(int $viewer_id, int $member_id): bool
{
    if ($viewer_id <= 0 || $member_id <= 0) {
        return false;
    }
    if ($viewer_id === $member_id) {
        return true;
    }
    // بالاترین سطح دسترسی — بدون محدودیت مخفی‌بودن یا بلاک
    if (casting_user_is_portal_owner($viewer_id)) {
        return (bool) get_user_by('id', $member_id);
    }
    if (casting_get_user_role($member_id) === '') {
        return false;
    }
    if (casting_get_user_role($viewer_id) === '') {
        return false;
    }
    $visible = get_user_meta($member_id, 'casting_visible', true) !== '0';
    if (!$visible) {
        return false;
    }
    if (function_exists('casting_users_block_each_other') && casting_users_block_each_other($viewer_id, $member_id)) {
        return false;
    }

    return true;
}

/**
 * مدیرانی که جدول دسترسی پیام‌رسان را می‌بینند
 *
 * @return list<string>
 */
function casting_message_access_manager_logins(): array
{
    return ['eshahabian', 'ardavan'];
}

function casting_user_can_manage_message_access(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }

    return in_array(strtolower((string) $user->user_login), casting_message_access_manager_logins(), true);
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

    $hash = '';
    $query = '';
    if (str_contains($path, '#')) {
        [$path, $hashPart] = explode('#', $path, 2);
        $hash = '#' . $hashPart;
    }
    if (str_contains($path, '?')) {
        [$path, $queryPart] = explode('?', $path, 2);
        $query = '?' . $queryPart;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base === '/' || $base === '\\' || $base === '.') {
        $base = '';
    }

    return $scheme . '://' . $host . $base . '/' . ltrim($path, '/') . $query . $hash;
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
        if (empty($_SESSION['casting_flash'])) {
            casting_set_flash('error', 'لطفاً ابتدا وارد شوید.');
        }
        casting_redirect('login.php');
    }

    $role = casting_get_user_role((int) $user->ID);
    if ($portal === 'talent' && $role !== 'talent') {
        casting_set_flash('error', 'این بخش فقط برای هنرمندان است.');
        casting_redirect(casting_is_employer_role($role) ? 'home.php' : 'login.php');
    }
    if ($portal === 'employer' && !casting_is_employer_role($role)) {
        casting_set_flash('error', 'این بخش فقط برای کارگردان و تهیه‌کننده است.');
        casting_redirect($role === 'talent' ? 'home.php' : 'login.php');
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
