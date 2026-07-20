<?php
declare(strict_types=1);

const CASTING_BLOCK_HISTORY_MAX = 1000;

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

function casting_get_block_reasons(int $user_id): array
{
    $raw = get_user_meta($user_id, 'casting_blocked_reasons', true);
    return is_array($raw) ? $raw : [];
}

function casting_block_reason(int $blocker_id, int $target_id): string
{
    $reasons = casting_get_block_reasons($blocker_id);
    return (string) ($reasons[(string) $target_id] ?? '');
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_block_user(int $blocker_id, int $target_id, string $reason = ''): array
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

    $reason = sanitize_textarea_field(trim($reason));
    if ($reason === '' || casting_strlen($reason) < 3) {
        return ['ok' => false, 'error' => 'علت بلاک را بنویسید (حداقل ۳ کاراکتر).'];
    }
    if (casting_strlen($reason) > 500) {
        return ['ok' => false, 'error' => 'علت بلاک حداکثر ۵۰۰ کاراکتر است.'];
    }

    $list = casting_get_blocked_ids($blocker_id);
    if (!in_array($target_id, $list, true)) {
        $list[] = $target_id;
        update_user_meta($blocker_id, 'casting_blocked_users', $list);
        $times = get_user_meta($blocker_id, 'casting_blocked_at', true);
        if (!is_array($times)) {
            $times = [];
        }
        $blocked_at = current_time('mysql');
        $times[(string) $target_id] = $blocked_at;
        update_user_meta($blocker_id, 'casting_blocked_at', $times);

        $reasons = casting_get_block_reasons($blocker_id);
        $reasons[(string) $target_id] = $reason;
        update_user_meta($blocker_id, 'casting_blocked_reasons', $reasons);

        $by = get_user_meta($target_id, 'casting_blocked_by', true);
        if (!is_array($by)) {
            $by = [];
        }
        if (!in_array($blocker_id, $by, true)) {
            $by[] = $blocker_id;
            update_user_meta($target_id, 'casting_blocked_by', $by);
        }

        casting_log_block_action('block', $blocker_id, $target_id, $reason, 0, $blocked_at);
    } else {
        $reasons = casting_get_block_reasons($blocker_id);
        $reasons[(string) $target_id] = $reason;
        update_user_meta($blocker_id, 'casting_blocked_reasons', $reasons);
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_unblock_user(int $blocker_id, int $target_id, int $admin_id = 0): array
{
    if (!casting_is_blocked($blocker_id, $target_id)) {
        return ['ok' => false, 'error' => 'این بلاک وجود ندارد.'];
    }

    $reason = casting_block_reason($blocker_id, $target_id);
    $times = get_user_meta($blocker_id, 'casting_blocked_at', true);
    $blocked_at = is_array($times) ? (string) ($times[(string) $target_id] ?? '') : '';

    $list = casting_get_blocked_ids($blocker_id);
    $list = array_values(array_filter($list, static fn(int $id): bool => $id !== $target_id));
    update_user_meta($blocker_id, 'casting_blocked_users', $list);

    if (is_array($times)) {
        unset($times[(string) $target_id]);
        update_user_meta($blocker_id, 'casting_blocked_at', $times);
    }

    $reasons = casting_get_block_reasons($blocker_id);
    if (isset($reasons[(string) $target_id])) {
        unset($reasons[(string) $target_id]);
        update_user_meta($blocker_id, 'casting_blocked_reasons', $reasons);
    }

    $by = get_user_meta($target_id, 'casting_blocked_by', true);
    if (is_array($by)) {
        $by = array_values(array_filter($by, static fn($id): bool => (int) $id !== $blocker_id));
        update_user_meta($target_id, 'casting_blocked_by', $by);
    }

    casting_log_block_action('unblock', $blocker_id, $target_id, $reason, $admin_id, $blocked_at);

    return ['ok' => true, 'error' => ''];
}

/**
 * @param 'block'|'unblock' $action
 */
function casting_log_block_action(
    string $action,
    int $blocker_id,
    int $target_id,
    string $reason = '',
    int $admin_id = 0,
    string $blocked_at = ''
): void {
    if ($blocker_id <= 0 || $target_id <= 0) {
        return;
    }
    if ($action !== 'block' && $action !== 'unblock') {
        return;
    }

    $entry = [
        'id'         => uniqid('blk_', true),
        'action'     => $action,
        'blocker_id' => $blocker_id,
        'target_id'  => $target_id,
        'reason'     => sanitize_textarea_field($reason),
        'blocked_at' => $blocked_at,
        'at'         => current_time('mysql'),
        'admin_id'   => $admin_id > 0 ? $admin_id : 0,
    ];

    $history = get_option('casting_block_history', []);
    if (!is_array($history)) {
        $history = [];
    }
    array_unshift($history, $entry);
    if (count($history) > CASTING_BLOCK_HISTORY_MAX) {
        $history = array_slice($history, 0, CASTING_BLOCK_HISTORY_MAX);
    }
    update_option('casting_block_history', $history, false);
}

/**
 * @return array<int, array{
 *   id:string,action:string,blocker_id:int,blocker_name:string,blocker_login:string,
 *   target_id:int,target_name:string,target_login:string,reason:string,blocked_at:string,
 *   at:string,admin_id:int,admin_name:string,active:bool
 * }>
 */
function casting_list_block_history(int $limit = 500, int $user_id = 0): array
{
    $history = get_option('casting_block_history', []);
    if (!is_array($history)) {
        return [];
    }

    $out = [];
    foreach ($history as $row) {
        if (!is_array($row)) {
            continue;
        }
        $blocker_id = (int) ($row['blocker_id'] ?? 0);
        $target_id = (int) ($row['target_id'] ?? 0);
        if ($blocker_id <= 0 || $target_id <= 0) {
            continue;
        }
        if ($user_id > 0 && $blocker_id !== $user_id && $target_id !== $user_id) {
            continue;
        }

        $blocker = get_user_by('id', $blocker_id);
        $target = get_user_by('id', $target_id);
        if (!$blocker || !$target) {
            continue;
        }
        if (casting_get_user_role($blocker_id) === '' || casting_get_user_role($target_id) === '') {
            continue;
        }

        $admin_id = (int) ($row['admin_id'] ?? 0);
        $admin_name = '';
        if ($admin_id > 0) {
            $admin = get_user_by('id', $admin_id);
            $admin_name = $admin ? (string) $admin->display_name : '';
        }

        $action = (string) ($row['action'] ?? '');
        if ($action !== 'block' && $action !== 'unblock') {
            continue;
        }

        $out[] = [
            'id'            => (string) ($row['id'] ?? ''),
            'action'        => $action,
            'blocker_id'    => $blocker_id,
            'blocker_name'  => (string) $blocker->display_name,
            'blocker_login' => (string) $blocker->user_login,
            'target_id'     => $target_id,
            'target_name'   => (string) $target->display_name,
            'target_login'  => (string) $target->user_login,
            'reason'        => (string) ($row['reason'] ?? ''),
            'blocked_at'    => (string) ($row['blocked_at'] ?? ''),
            'at'            => (string) ($row['at'] ?? ''),
            'admin_id'      => $admin_id,
            'admin_name'    => $admin_name,
            'active'        => $action === 'block' && casting_is_blocked($blocker_id, $target_id),
        ];

        if (count($out) >= max(1, $limit)) {
            break;
        }
    }

    return $out;
}

/**
 * @return array<int, array{
 *   id:string,action:string,blocker_id:int,blocker_name:string,target_id:int,target_name:string,
 *   reason:string,blocked_at:string,at:string,admin_id:int,admin_name:string,active:bool,
 *   relation:string
 * }>
 */
function casting_admin_user_block_history(int $user_id, int $limit = 100): array
{
    $rows = casting_list_block_history($limit, $user_id);
    foreach ($rows as &$row) {
        if ($row['blocker_id'] === $user_id) {
            $row['relation'] = 'blocked_other';
        } elseif ($row['target_id'] === $user_id) {
            $row['relation'] = 'was_blocked';
        } else {
            $row['relation'] = '';
        }
    }
    unset($row);
    return $rows;
}

/**
 * @return array<int, array{id:int,name:string,role:string,blocked_at:string,reason:string}>
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
            'reason'     => casting_block_reason($user_id, $id),
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

/**
 * @return array<int, array{
 *   blocker_id:int,blocker_name:string,blocker_login:string,
 *   target_id:int,target_name:string,target_login:string,
 *   blocked_at:string,reason:string
 * }>
 */
function casting_list_all_user_blocks(int $limit = 500): array
{
    $users = get_users([
        'meta_key'     => 'casting_blocked_users',
        'meta_compare' => 'EXISTS',
        'number'       => 300,
        'orderby'      => 'ID',
        'order'        => 'DESC',
    ]);

    $out = [];
    foreach ($users as $blocker) {
        $blocker_id = (int) $blocker->ID;
        if (casting_get_user_role($blocker_id) === '') {
            continue;
        }
        $times = get_user_meta($blocker_id, 'casting_blocked_at', true);
        if (!is_array($times)) {
            $times = [];
        }
        foreach (casting_get_blocked_ids($blocker_id) as $target_id) {
            $target = get_user_by('id', $target_id);
            if (!$target || casting_get_user_role($target_id) === '') {
                continue;
            }
            $out[] = [
                'blocker_id'    => $blocker_id,
                'blocker_name'  => (string) $blocker->display_name,
                'blocker_login' => (string) $blocker->user_login,
                'target_id'     => $target_id,
                'target_name'   => (string) $target->display_name,
                'target_login'  => (string) $target->user_login,
                'blocked_at'    => (string) ($times[(string) $target_id] ?? ''),
                'reason'        => casting_block_reason($blocker_id, $target_id),
            ];
        }
    }

    usort($out, static function (array $a, array $b): int {
        return strcmp($b['blocked_at'], $a['blocked_at']);
    });

    return array_slice($out, 0, max(1, $limit));
}

function casting_render_block_user_form(
    string $action_url,
    int $target_id,
    string $nonce_action = 'casting_block',
    string $mode = 'member'
): void {
    ?>
    <form class="form block-user-form" method="post" action="<?= casting_e($action_url) ?>">
      <?php wp_nonce_field($nonce_action); ?>
      <?php if ($mode === 'chat') : ?>
        <input type="hidden" name="action" value="block">
        <input type="hidden" name="peer_id" value="<?= $target_id ?>">
      <?php else : ?>
        <input type="hidden" name="block_id" value="<?= $target_id ?>">
        <input type="hidden" name="block_action" value="block">
      <?php endif; ?>
      <div class="field">
        <label for="block_reason_<?= $target_id ?>">علت بلاک</label>
        <textarea id="block_reason_<?= $target_id ?>" name="block_reason" rows="2" required minlength="3" maxlength="500" placeholder="چرا این کاربر را بلاک می‌کنید؟"></textarea>
      </div>
      <button class="btn btn-reject btn-sm" type="submit">بلاک</button>
    </form>
    <?php
}
