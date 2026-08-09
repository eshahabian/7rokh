<?php
declare(strict_types=1);

function casting_portal_cookie_path(): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base === '' || $base === '/' || $base === '.') {
        return '/casting-portal/';
    }

    return $base . '/';
}

function casting_is_portal_request(): bool
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    return str_contains($script, '/casting-portal/')
        || str_contains($script, '\\casting-portal\\')
        || str_contains($uri, '/casting-portal/');
}

function casting_portal_session_user_id(): int
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return 0;
    }

    return max(0, (int) ($_SESSION['casting_portal_user_id'] ?? 0));
}

function casting_portal_set_session_user(int $user_id): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['casting_portal_user_id'] = max(0, $user_id);
}

function casting_portal_clear_session_user(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['casting_portal_user_id']);
    }
}

/** کاربر فقط پورتال — meta casting_role */
function casting_is_portal_member(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    $role = get_user_meta($user_id, 'casting_role', true);

    return is_string($role) && $role !== '';
}

/**
 * آیا این کاربر اجازه دارد کوکی لاگین وردپرس (سایت اصلی) بگیرد؟
 * - کاربر وردپرس (بدون casting_role): بله
 * - عضو پورتال: خیر — جز eshahabian
 */
function casting_user_may_use_wordpress_auth(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (!casting_is_portal_member($user_id)) {
        return true;
    }

    return function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id);
}

function casting_portal_login_user(WP_User $user, bool $remember = true): void
{
    $user_id = (int) $user->ID;

    if (!casting_is_portal_member($user_id)) {
        return;
    }

    casting_portal_set_session_user($user_id);
    if (function_exists('casting_session_issue')) {
        casting_session_issue($user_id);
    }

    if (casting_user_may_use_wordpress_auth($user_id)) {
        wp_set_auth_cookie($user_id, $remember, is_ssl());
    } else {
        wp_clear_auth_cookie();
    }

    wp_set_current_user($user_id);

    // سبد مهمان → کاربر + همگام‌سازی شمارنده سایت اصلی
    if (!function_exists('casting_cart_claim_guest_cart')) {
        $cart_lib = __DIR__ . '/cart.php';
        if (is_file($cart_lib)) {
            require_once $cart_lib;
        }
    }
    if (function_exists('casting_cart_claim_guest_cart')) {
        casting_cart_claim_guest_cart();
    } elseif (function_exists('casting_cart_sync_count_cookie')) {
        casting_cart_sync_count_cookie();
    }
}

function casting_portal_logout_user(): void
{
    $user_id = casting_portal_session_user_id();

    // قبل از پاک کردن کاربر session: badge سایت اصلی صفر شود
    if (!function_exists('casting_cart_sync_count_cookie')) {
        $cart_lib = __DIR__ . '/cart.php';
        if (is_file($cart_lib)) {
            require_once $cart_lib;
        }
    }
    if (function_exists('casting_cart_sync_count_cookie')) {
        casting_cart_sync_count_cookie(0);
    }

    if (function_exists('casting_session_clear')) {
        casting_session_clear($user_id);
    }
    casting_portal_clear_session_user();

    if ($user_id > 0 && casting_user_may_use_wordpress_auth($user_id)) {
        wp_clear_auth_cookie();
    } elseif ($user_id > 0) {
        wp_clear_auth_cookie();
    }

    wp_set_current_user(0);
}

/**
 * روی صفحات پورتال فقط session پورتال معتبر است — نه کوکی wp-login سایت اصلی.
 */
function casting_current_user(): ?WP_User
{
    if (!casting_is_portal_request()) {
        return null;
    }

    $portal_id = casting_portal_session_user_id();
    if ($portal_id > 0) {
        $user = get_user_by('id', $portal_id);
        if ($user instanceof WP_User && casting_is_portal_member($portal_id)) {
            if (function_exists('casting_session_validate_current')) {
                $check = casting_session_validate_current($portal_id);
                if (!$check['ok']) {
                    $reason = (string) ($check['reason'] ?? '');
                    casting_portal_logout_user();
                    if ($reason === 'idle') {
                        casting_set_flash('error', casting_session_idle_message());
                    } elseif ($reason === 'replaced') {
                        casting_set_flash('error', casting_session_replaced_message());
                    }

                    return null;
                }
            }
            wp_set_current_user($portal_id);

            return $user;
        }
        casting_portal_clear_session_user();
    }

    $wp_user = wp_get_current_user();
    if ($wp_user instanceof WP_User && $wp_user->ID > 0 && casting_is_portal_member((int) $wp_user->ID)) {
        casting_portal_set_session_user((int) $wp_user->ID);
        if (function_exists('casting_session_issue') && empty($_SESSION['casting_session_token'])) {
            casting_session_issue((int) $wp_user->ID);
        } elseif (function_exists('casting_session_validate_current')) {
            $check = casting_session_validate_current((int) $wp_user->ID);
            if (!$check['ok']) {
                casting_portal_logout_user();
                if (($check['reason'] ?? '') === 'idle') {
                    casting_set_flash('error', casting_session_idle_message());
                } elseif (($check['reason'] ?? '') === 'replaced') {
                    casting_set_flash('error', casting_session_replaced_message());
                }

                return null;
            }
        }
        if (!casting_user_may_use_wordpress_auth((int) $wp_user->ID)) {
            wp_clear_auth_cookie();
        }
        wp_set_current_user((int) $wp_user->ID);

        return $wp_user;
    }

    return null;
}

function casting_bootstrap_portal_auth(): void
{
    if (!casting_is_portal_request()) {
        return;
    }

    casting_current_user();
}
