<?php
/**
 * Plugin Name: Casting Portal — جداسازی کاربران
 * Description: اعضای پورتال (casting_role) فقط از /casting-portal/login.php وارد می‌شوند.
 * Version: 1.1
 *
 * نصب: public_html/wp-content/mu-plugins/casting-wp-admin-guard.php
 * (خودکار با deploy — .cpanel.yml)
 *
 * سازگار با PHP 7.4 — بدون str_contains و بدون loader جدا.
 */

declare(strict_types=1);

if (function_exists('casting_guard_portal_member_user_id')) {
    return;
}

function casting_guard_portal_member_user_id(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    $role = get_user_meta($user_id, 'casting_role', true);

    return is_string($role) && $role !== '';
}

function casting_guard_portal_owner_login(): string
{
    if (defined('CASTING_PORTAL_OWNER')) {
        $login = strtolower(trim((string) CASTING_PORTAL_OWNER));
        if ($login !== '') {
            return $login;
        }
    }

    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = 'eshahabian';
    if (defined('ABSPATH')) {
        $config = ABSPATH . 'casting-portal/config.php';
        if (is_readable($config)) {
            $src = file_get_contents($config);
            if (is_string($src) && preg_match("/define\s*\(\s*'CASTING_PORTAL_OWNER'\s*,\s*'([^']+)'/", $src, $m)) {
                $login = strtolower(trim($m[1]));
                if ($login !== '') {
                    $cached = $login;
                }
            }
        }
    }

    return $cached;
}

function casting_guard_user_may_use_wp(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (!casting_guard_portal_member_user_id($user_id)) {
        return true;
    }
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }

    return strtolower((string) $user->user_login) === casting_guard_portal_owner_login();
}

function casting_guard_is_portal_request(): bool
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    return $uri !== '' && strpos($uri, '/casting-portal/') !== false;
}

function casting_guard_portal_login_url(): string
{
    return home_url('/casting-portal/login.php');
}

function casting_guard_portal_panel_url(): string
{
    return home_url('/casting-portal/panel.php');
}

function casting_guard_portal_only_error(): WP_Error
{
    return new WP_Error(
        'casting_portal_only',
        'این حساب مربوط به پورتال است. لطفاً از صفحه ورود پورتال وارد شوید.'
    );
}

function casting_guard_lookup_user_by_login(string $login): ?WP_User
{
    $login = trim($login);
    if ($login === '') {
        return null;
    }

    $user = get_user_by('login', sanitize_user($login, true));
    if ($user instanceof WP_User) {
        return $user;
    }
    if (is_email($login)) {
        $user = get_user_by('email', sanitize_email($login));
        if ($user instanceof WP_User) {
            return $user;
        }
    }

    return null;
}

function casting_guard_strip_portal_member_wp_auth(): void
{
    if (casting_guard_is_portal_request() || wp_doing_ajax() || wp_doing_cron()) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = (int) get_current_user_id();
    if (casting_guard_user_may_use_wp($user_id)) {
        return;
    }

    wp_clear_auth_cookie();
    wp_set_current_user(0);
}

function casting_guard_reject_portal_member_auth($user)
{
    if ($user instanceof WP_User && casting_guard_portal_member_user_id((int) $user->ID) && !casting_guard_user_may_use_wp((int) $user->ID)) {
        return casting_guard_portal_only_error();
    }

    return $user;
}

function casting_guard_authenticate_filter($user, $username = '', $password = '')
{
    unset($password);

    $user = casting_guard_reject_portal_member_auth($user);
    if ($user instanceof WP_Error) {
        return $user;
    }
    if ($user instanceof WP_User) {
        return $user;
    }

    if (is_string($username) && $username !== '') {
        $lookup = casting_guard_lookup_user_by_login($username);
        if ($lookup instanceof WP_User && casting_guard_portal_member_user_id((int) $lookup->ID) && !casting_guard_user_may_use_wp((int) $lookup->ID)) {
            return casting_guard_portal_only_error();
        }
    }

    return $user;
}

add_action('init', 'casting_guard_strip_portal_member_wp_auth', 1);
add_action('template_redirect', 'casting_guard_strip_portal_member_wp_auth', 1);

add_filter('authenticate', 'casting_guard_authenticate_filter', 30, 3);
add_filter('wp_authenticate_user', 'casting_guard_reject_portal_member_auth', 30, 1);

add_filter('send_auth_cookies', static function ($send, $user_id) {
    if (casting_guard_portal_member_user_id((int) $user_id) && !casting_guard_user_may_use_wp((int) $user_id)) {
        return false;
    }

    return (bool) $send;
}, 10, 2);

add_action('login_init', static function (): void {
    if (!is_user_logged_in()) {
        return;
    }
    $user_id = (int) get_current_user_id();
    if (casting_guard_portal_member_user_id($user_id) && !casting_guard_user_may_use_wp($user_id)) {
        wp_clear_auth_cookie();
        wp_safe_redirect(casting_guard_portal_login_url());
        exit;
    }
});

add_action('admin_init', static function (): void {
    if (wp_doing_ajax() || !is_user_logged_in()) {
        return;
    }
    $user_id = (int) get_current_user_id();
    if (casting_guard_portal_member_user_id($user_id) && !casting_guard_user_may_use_wp($user_id)) {
        wp_safe_redirect(casting_guard_portal_panel_url());
        exit;
    }
}, 1);

add_filter('login_redirect', static function ($redirect_to, $requested_redirect_to, $user) {
    unset($requested_redirect_to);
    if ($user instanceof WP_User && casting_guard_portal_member_user_id((int) $user->ID) && !casting_guard_user_may_use_wp((int) $user->ID)) {
        return casting_guard_portal_panel_url();
    }

    return $redirect_to;
}, 10, 3);

add_filter('show_admin_bar', static function ($show) {
    if (!is_user_logged_in()) {
        return $show;
    }
    $user_id = (int) get_current_user_id();
    if (casting_guard_portal_member_user_id($user_id) && !casting_guard_user_may_use_wp($user_id)) {
        return false;
    }

    return $show;
});
