<?php
/**
 * Plugin Name: Casting Portal — Block wp-admin for members
 * Description: کاربران ثبت‌نام‌شده در پورتال کستینگ به wp-admin دسترسی ندارند.
 */

declare(strict_types=1);

function casting_guard_portal_member_user_id(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    $role = get_user_meta($user_id, 'casting_role', true);

    return is_string($role) && $role !== '';
}

function casting_guard_user_may_use_wp_admin(int $user_id): bool
{
    if ($user_id <= 0) {
        return true;
    }
    if (user_can($user_id, 'manage_options')) {
        return true;
    }

    return !casting_guard_portal_member_user_id($user_id);
}

function casting_guard_portal_panel_url(): string
{
    return home_url('/casting-portal/panel.php');
}

add_action('admin_init', static function (): void {
    if (wp_doing_ajax() || !is_user_logged_in()) {
        return;
    }
    if (casting_guard_user_may_use_wp_admin(get_current_user_id())) {
        return;
    }
    wp_safe_redirect(casting_guard_portal_panel_url());
    exit;
}, 1);

add_filter('login_redirect', static function ($redirect_to, $requested_redirect_to, $user) {
    if ($user instanceof WP_User && !casting_guard_user_may_use_wp_admin((int) $user->ID)) {
        return casting_guard_portal_panel_url();
    }

    return $redirect_to;
}, 10, 3);

add_filter('show_admin_bar', static function ($show) {
    if (!is_user_logged_in()) {
        return $show;
    }
    if (casting_guard_user_may_use_wp_admin(get_current_user_id())) {
        return $show;
    }

    return false;
});
