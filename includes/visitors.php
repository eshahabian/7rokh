<?php
declare(strict_types=1);

function casting_record_profile_visit(int $profile_user_id, int $visitor_id): void
{
    if ($profile_user_id <= 0 || $visitor_id <= 0 || $profile_user_id === $visitor_id) {
        return;
    }
    if (casting_get_user_role($profile_user_id) === '' || casting_get_user_role($visitor_id) === '') {
        return;
    }

    $log = get_user_meta($profile_user_id, 'casting_profile_visitors', true);
    if (!is_array($log)) {
        $log = [];
    }

    $now = current_time('mysql');
    $filtered = [];
    foreach ($log as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((int) ($row['visitor_id'] ?? 0) === $visitor_id) {
            continue;
        }
        $filtered[] = $row;
    }
    array_unshift($filtered, [
        'visitor_id' => $visitor_id,
        'visited_at' => $now,
    ]);
    update_user_meta($profile_user_id, 'casting_profile_visitors', array_slice($filtered, 0, 200));
}

/**
 * @return array<int, array{visitor_id:int,name:string,role:string,visited_at:string}>
 */
function casting_profile_visitors(int $user_id, int $limit = 50): array
{
    $log = get_user_meta($user_id, 'casting_profile_visitors', true);
    if (!is_array($log)) {
        return [];
    }

    $out = [];
    foreach (array_slice($log, 0, $limit) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $vid = (int) ($row['visitor_id'] ?? 0);
        if ($vid <= 0) {
            continue;
        }
        $u = get_user_by('id', $vid);
        $out[] = [
            'visitor_id' => $vid,
            'name'       => $u ? (string) $u->display_name : 'کاربر',
            'role'       => casting_get_user_role($vid),
            'visited_at' => (string) ($row['visited_at'] ?? ''),
        ];
    }
    return $out;
}
