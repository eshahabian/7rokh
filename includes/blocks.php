<?php
declare(strict_types=1);

function casting_get_blocked_ids(int $user_id): array
{
    $raw = get_user_meta($user_id, 'casting_blocked_users', true);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $out[] = $id;
        }
    }
    return array_values(array_unique($out));
}

function casting_is_blocked(int $blocker_id, int $target_id): bool
{
    if ($blocker_id <= 0 || $target_id <= 0) {
        return false;
    }
    return in_array($target_id, casting_get_blocked_ids($blocker_id), true);
}

function casting_users_block_each_other(int $a, int $b): bool
{
    return casting_is_blocked($a, $b) || casting_is_blocked($b, $a);
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_block_user(int $blocker_id, int $target_id): array
{
    if ($blocker_id <= 0 || $target_id <= 0) {
        return ['ok' => false, 'error' => 'کاربر معتبر نیست.'];
    }
    if ($blocker_id === $target_id) {
        return ['ok' => false, 'error' => 'نمی‌توانید خودتان را بلاک کنید.'];
    }
    if (casting_get_user_role($target_id) === '') {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }

    $list = casting_get_blocked_ids($blocker_id);
    if (!in_array($target_id, $list, true)) {
        $list[] = $target_id;
        update_user_meta($blocker_id, 'casting_blocked_users', $list);
        $times = get_user_meta($blocker_id, 'casting_blocked_at', true);
        if (!is_array($times)) {
            $times = [];
        }
        $times[(string) $target_id] = current_time('mysql');
        update_user_meta($blocker_id, 'casting_blocked_at', $times);

        $by = get_user_meta($target_id, 'casting_blocked_by', true);
        if (!is_array($by)) {
            $by = [];
        }
        if (!in_array($blocker_id, $by, true)) {
            $by[] = $blocker_id;
            update_user_meta($target_id, 'casting_blocked_by', $by);
        }
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_unblock_user(int $blocker_id, int $target_id): array
{
    $list = casting_get_blocked_ids($blocker_id);
    $list = array_values(array_filter($list, static fn(int $id): bool => $id !== $target_id));
    update_user_meta($blocker_id, 'casting_blocked_users', $list);

    $by = get_user_meta($target_id, 'casting_blocked_by', true);
    if (is_array($by)) {
        $by = array_values(array_filter($by, static fn($id): bool => (int) $id !== $blocker_id));
        update_user_meta($target_id, 'casting_blocked_by', $by);
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * @return array<int, array{id:int,name:string,role:string,blocked_at:string}>
 */
function casting_blocked_by_me(int $user_id): array
{
    $times = get_user_meta($user_id, 'casting_blocked_at', true);
    if (!is_array($times)) {
        $times = [];
    }
    $out = [];
    foreach (casting_get_blocked_ids($user_id) as $id) {
        $u = get_user_by('id', $id);
        if (!$u) {
            continue;
        }
        $out[] = [
            'id'         => $id,
            'name'       => (string) $u->display_name,
            'role'       => casting_get_user_role($id),
            'blocked_at' => (string) ($times[(string) $id] ?? ''),
        ];
    }
    return $out;
}

/**
 * @return array<int, array{id:int,name:string,role:string}>
 */
function casting_users_who_blocked_me(int $user_id): array
{
    $ids = get_user_meta($user_id, 'casting_blocked_by', true);
    if (!is_array($ids)) {
        return [];
    }
    $out = [];
    foreach ($ids as $id) {
        $blocker_id = (int) $id;
        if ($blocker_id <= 0 || !casting_is_blocked($blocker_id, $user_id)) {
            continue;
        }
        $u = get_user_by('id', $blocker_id);
        if (!$u) {
            continue;
        }
        $out[] = [
            'id'   => $blocker_id,
            'name' => (string) $u->display_name,
            'role' => casting_get_user_role($blocker_id),
        ];
    }
    return $out;
}
