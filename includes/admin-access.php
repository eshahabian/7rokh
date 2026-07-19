<?php
declare(strict_types=1);

require_once __DIR__ . '/premium.php';
require_once __DIR__ . '/blocks.php';

/**
 * @return array<string, string>
 */
function casting_admin_permission_definitions(): array
{
    return [
        'approve_receipts'   => 'تأیید فیش واریزی',
        'view_transactions'  => 'مشاهده تراکنش‌های مالی کاربران',
        'unblock_users'      => 'رفع بلاک کاربران',
        'view_user_blocks'   => 'مشاهده بلاک‌های کاربران',
        'suspend_users'      => 'تعلیق / رفع تعلیق کاربر',
        'view_premium_users' => 'مشاهده مشترکین ویژه',
        'manage_staff'       => 'مدیریت دسترسی مدیران',
    ];
}

/**
 * @return array<int, string>
 */
function casting_portal_admin_usernames(): array
{
    if (!defined('CASTING_PORTAL_ADMINS') || !is_array(CASTING_PORTAL_ADMINS)) {
        return [];
    }
    $out = [];
    foreach (CASTING_PORTAL_ADMINS as $login) {
        if (!is_string($login)) {
            continue;
        }
        $login = strtolower(trim($login));
        if ($login !== '') {
            $out[] = $login;
        }
    }
    return $out;
}

function casting_user_is_super_admin(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (user_can($user_id, 'manage_options')) {
        return true;
    }
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    $login = strtolower((string) $user->user_login);
    return $login !== '' && in_array($login, casting_portal_admin_usernames(), true);
}

/**
 * @return array<int, string>
 */
function casting_user_staff_permissions(int $user_id): array
{
    if (casting_user_is_super_admin($user_id)) {
        return array_keys(casting_admin_permission_definitions());
    }
    $raw = get_user_meta($user_id, 'casting_staff_permissions', true);
    if (!is_array($raw)) {
        return [];
    }
    $allowed = array_keys(casting_admin_permission_definitions());
    $out = [];
    foreach ($raw as $perm) {
        if (is_string($perm) && in_array($perm, $allowed, true)) {
            $out[] = $perm;
        }
    }
    return array_values(array_unique($out));
}

function casting_user_has_admin_permission(int $user_id, string $permission): bool
{
    if ($user_id <= 0 || !array_key_exists($permission, casting_admin_permission_definitions())) {
        return false;
    }
    if (casting_user_is_super_admin($user_id)) {
        return true;
    }
    return in_array($permission, casting_user_staff_permissions($user_id), true);
}

function casting_user_is_portal_staff(int $user_id): bool
{
    return casting_user_staff_permissions($user_id) !== [];
}

function casting_user_is_portal_admin(?int $user_id = null): bool
{
    if ($user_id === null) {
        if (!is_user_logged_in()) {
            return false;
        }
        $user_id = (int) get_current_user_id();
    }
    return casting_user_is_super_admin($user_id) || casting_user_is_portal_staff($user_id);
}

function casting_require_admin_permission(string $permission): void
{
    if (!is_user_logged_in()) {
        auth_redirect();
    }
    $user_id = (int) get_current_user_id();
    if (!casting_user_has_admin_permission($user_id, $permission)) {
        wp_die('دسترسی به این بخش برای شما فعال نیست.', 'دسترسی غیرمجاز', ['response' => 403]);
    }
}

/**
 * @param array<int, string> $permissions
 * @return array{ok:bool,error:string}
 */
function casting_save_user_staff_permissions(int $target_id, array $permissions, int $by_user_id): array
{
    if (!casting_user_has_admin_permission($by_user_id, 'manage_staff')) {
        return ['ok' => false, 'error' => 'اجازه مدیریت دسترسی مدیران را ندارید.'];
    }
    if ($target_id <= 0 || casting_get_user_role($target_id) === '') {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    if (casting_user_is_super_admin($target_id)) {
        return ['ok' => false, 'error' => 'دسترسی مدیر اصلی قابل تغییر نیست.'];
    }

    $allowed = array_keys(casting_admin_permission_definitions());
    $clean = [];
    foreach ($permissions as $perm) {
        if (!is_string($perm) || !in_array($perm, $allowed, true)) {
            continue;
        }
        if ($perm === 'manage_staff' && !casting_user_is_super_admin($by_user_id)) {
            continue;
        }
        $clean[] = $perm;
    }
    $clean = array_values(array_unique($clean));
    update_user_meta($target_id, 'casting_staff_permissions', $clean);
    update_user_meta($target_id, 'casting_staff_updated_at', current_time('mysql'));
    update_user_meta($target_id, 'casting_staff_updated_by', $by_user_id);

    return ['ok' => true, 'error' => ''];
}

function casting_user_is_suspended(int $user_id): bool
{
    return (string) get_user_meta($user_id, 'casting_suspended', true) === '1';
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_admin_suspend_user(int $target_id, int $admin_id, string $reason = ''): array
{
    if (!casting_user_has_admin_permission($admin_id, 'suspend_users')) {
        return ['ok' => false, 'error' => 'اجازه تعلیق کاربر را ندارید.'];
    }
    if ($target_id <= 0 || casting_get_user_role($target_id) === '') {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    if (casting_user_is_super_admin($target_id)) {
        return ['ok' => false, 'error' => 'مدیر اصلی قابل تعلیق نیست.'];
    }
    update_user_meta($target_id, 'casting_suspended', '1');
    update_user_meta($target_id, 'casting_suspended_at', current_time('mysql'));
    update_user_meta($target_id, 'casting_suspended_by', $admin_id);
    update_user_meta($target_id, 'casting_suspended_reason', sanitize_textarea_field(trim($reason)));
    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_admin_unsuspend_user(int $target_id, int $admin_id): array
{
    if (!casting_user_has_admin_permission($admin_id, 'suspend_users')) {
        return ['ok' => false, 'error' => 'اجازه رفع تعلیق را ندارید.'];
    }
    if ($target_id <= 0) {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    delete_user_meta($target_id, 'casting_suspended');
    delete_user_meta($target_id, 'casting_suspended_at');
    delete_user_meta($target_id, 'casting_suspended_by');
    delete_user_meta($target_id, 'casting_suspended_reason');
    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_admin_force_unblock(int $blocker_id, int $target_id, int $admin_id): array
{
    if (!casting_user_has_admin_permission($admin_id, 'unblock_users')) {
        return ['ok' => false, 'error' => 'اجازه رفع بلاک را ندارید.'];
    }
    if ($blocker_id <= 0 || $target_id <= 0) {
        return ['ok' => false, 'error' => 'کاربر معتبر نیست.'];
    }
    if (!casting_is_blocked($blocker_id, $target_id)) {
        return ['ok' => false, 'error' => 'این بلاک وجود ندارد.'];
    }
    return casting_unblock_user($blocker_id, $target_id);
}

/**
 * @return array<int, array{id:int,name:string,login:string,role:string,until:string,remaining:string,until_ts:?int}>
 */
function casting_list_premium_members(): array
{
    $users = get_users([
        'meta_key'     => 'casting_premium_until',
        'meta_compare' => 'EXISTS',
        'number'       => 500,
        'orderby'      => 'display_name',
        'order'        => 'ASC',
    ]);

    $out = [];
    foreach ($users as $user) {
        $id = (int) $user->ID;
        if (!casting_user_is_premium($id)) {
            continue;
        }
        $until_ts = casting_premium_expire_timestamp($id);
        $out[] = [
            'id'        => $id,
            'name'      => (string) $user->display_name,
            'login'     => (string) $user->user_login,
            'role'      => casting_get_user_role($id),
            'until'     => casting_premium_until_label($id),
            'remaining' => casting_premium_countdown_nav_label($id),
            'until_ts'  => $until_ts,
        ];
    }

    usort($out, static function (array $a, array $b): int {
        return ($a['until_ts'] ?? 0) <=> ($b['until_ts'] ?? 0);
    });

    return $out;
}

/**
 * @return array<int, array{id:int,name:string,login:string,role:string,email:string,suspended:bool,premium:bool}>
 */
function casting_admin_search_casting_users(string $query, int $limit = 40): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $users = get_users([
        'meta_key'     => 'casting_role',
        'meta_compare' => 'EXISTS',
        'search'       => '*' . $query . '*',
        'search_columns' => ['user_login', 'user_email', 'display_name'],
        'number'       => max(1, min(50, $limit)),
        'orderby'      => 'display_name',
        'order'        => 'ASC',
    ]);

    $out = [];
    foreach ($users as $user) {
        $id = (int) $user->ID;
        if (casting_get_user_role($id) === '') {
            continue;
        }
        $out[] = [
            'id'        => $id,
            'name'      => (string) $user->display_name,
            'login'     => (string) $user->user_login,
            'email'     => (string) $user->user_email,
            'role'      => casting_get_user_role($id),
            'suspended' => casting_user_is_suspended($id),
            'premium'   => casting_user_is_premium($id),
            'staff'     => casting_user_is_portal_staff($id) || casting_user_is_super_admin($id),
        ];
    }
    return $out;
}

/**
 * @return array<int, array{blocker_id:int,blocker_name:string,target_id:int,target_name:string,blocked_at:string,reason:string}>
 */
function casting_admin_user_blocks(int $user_id): array
{
    $out = [];
    $self = get_user_by('id', $user_id);
    $self_name = $self ? (string) $self->display_name : 'کاربر';
    $times = get_user_meta($user_id, 'casting_blocked_at', true);
    if (!is_array($times)) {
        $times = [];
    }

    foreach (casting_get_blocked_ids($user_id) as $target_id) {
        $target = get_user_by('id', $target_id);
        if (!$target) {
            continue;
        }
        $out[] = [
            'blocker_id'   => $user_id,
            'blocker_name' => $self_name,
            'target_id'    => $target_id,
            'target_name'  => (string) $target->display_name,
            'blocked_at'   => (string) ($times[(string) $target_id] ?? ''),
            'reason'       => casting_block_reason($user_id, $target_id),
        ];
    }

    $blocked_by = get_user_meta($user_id, 'casting_blocked_by', true);
    if (is_array($blocked_by)) {
        foreach ($blocked_by as $blocker_id) {
            $blocker_id = (int) $blocker_id;
            if ($blocker_id <= 0 || !casting_is_blocked($blocker_id, $user_id)) {
                continue;
            }
            $blocker = get_user_by('id', $blocker_id);
            if (!$blocker) {
                continue;
            }
            $blocker_times = get_user_meta($blocker_id, 'casting_blocked_at', true);
            $blocked_at = is_array($blocker_times) ? (string) ($blocker_times[(string) $user_id] ?? '') : '';
            $out[] = [
                'blocker_id'   => $blocker_id,
                'blocker_name' => (string) $blocker->display_name,
                'target_id'    => $user_id,
                'target_name'  => $self_name,
                'blocked_at'   => $blocked_at,
                'reason'       => casting_block_reason($blocker_id, $user_id),
            ];
        }
    }

    return $out;
}

/**
 * @return array<int, array{key:string,label:string,href:string,perm:string}>
 */
function casting_panel_admin_nav_items(int $user_id): array
{
    $items = [];
    if (casting_user_has_admin_permission($user_id, 'view_premium_users')) {
        $items[] = ['key' => 'admin-premium', 'label' => 'مشترکین ویژه', 'href' => 'admin-premium-users.php', 'perm' => 'view_premium_users'];
    }
    if (casting_user_has_admin_permission($user_id, 'approve_receipts')) {
        $items[] = ['key' => 'admin-receipts', 'label' => 'تأیید فیش‌ها', 'href' => 'premium.php#admin-receipts', 'perm' => 'approve_receipts'];
    }
    if (casting_user_has_admin_permission($user_id, 'view_transactions')) {
        $items[] = ['key' => 'admin-transactions', 'label' => 'تراکنش کاربران', 'href' => 'admin-transactions.php', 'perm' => 'view_transactions'];
    }
    if (casting_user_has_admin_permission($user_id, 'view_user_blocks')) {
        $items[] = ['key' => 'admin-blocks', 'label' => 'بلاک‌های کاربران', 'href' => 'admin-blocks.php', 'perm' => 'view_user_blocks'];
    }
    if (casting_user_has_admin_permission($user_id, 'suspend_users') || casting_user_has_admin_permission($user_id, 'unblock_users')) {
        $items[] = ['key' => 'admin-users', 'label' => 'کاربران و تعلیق', 'href' => 'admin-users.php', 'perm' => 'suspend_users'];
    }
    if (casting_user_has_admin_permission($user_id, 'manage_staff')) {
        $items[] = ['key' => 'admin-staff', 'label' => 'دسترسی مدیران', 'href' => 'admin-staff.php', 'perm' => 'manage_staff'];
    }
    return $items;
}

/**
 * @return array<int, array{id:int,name:string,login:string,permissions:array<int,string>,super:bool}>
 */
function casting_list_staff_users(): array
{
    $users = get_users([
        'meta_key'     => 'casting_staff_permissions',
        'meta_compare' => 'EXISTS',
        'number'       => 200,
        'orderby'      => 'display_name',
        'order'        => 'ASC',
    ]);

    $out = [];
    foreach ($users as $user) {
        $id = (int) $user->ID;
        $perms = casting_user_staff_permissions($id);
        if ($perms === [] && !casting_user_is_super_admin($id)) {
            continue;
        }
        $out[] = [
            'id'          => $id,
            'name'        => (string) $user->display_name,
            'login'       => (string) $user->user_login,
            'permissions' => $perms,
            'super'       => casting_user_is_super_admin($id),
        ];
    }

    foreach (casting_portal_admin_usernames() as $login) {
        $u = get_user_by('login', $login);
        if (!$u) {
            continue;
        }
        $id = (int) $u->ID;
        $found = false;
        foreach ($out as $row) {
            if ($row['id'] === $id) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $out[] = [
                'id'          => $id,
                'name'        => (string) $u->display_name,
                'login'       => (string) $u->user_login,
                'permissions' => casting_user_staff_permissions($id),
                'super'       => true,
            ];
        }
    }

    return $out;
}
