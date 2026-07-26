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
    casting_profile_view_stats_increment($profile_user_id, $now);
}

/**
 * @return array<string, int> Y-m-d => count
 */
function casting_profile_view_stats_days(int $user_id): array
{
    $raw = get_user_meta($user_id, 'casting_profile_view_days', true);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $day => $count) {
        $day = (string) $day;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            continue;
        }
        $out[$day] = max(0, (int) $count);
    }

    return $out;
}

function casting_profile_view_stats_increment(int $user_id, string $mysql_datetime = ''): void
{
    if ($user_id <= 0) {
        return;
    }
    $mysql_datetime = trim($mysql_datetime);
    if ($mysql_datetime === '') {
        $mysql_datetime = current_time('mysql');
    }
    $day = substr($mysql_datetime, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        return;
    }

    $days = casting_profile_view_stats_days($user_id);
    $days[$day] = ($days[$day] ?? 0) + 1;

    // نگه داشتن حدود ۴۵ روز اخیر برای آمار ماهانه
    krsort($days);
    $days = array_slice($days, 0, 45, true);
    ksort($days);
    update_user_meta($user_id, 'casting_profile_view_days', $days);
}

/**
 * @return array{day:int,month:int,total:int}
 */
function casting_profile_view_stats(int $user_id): array
{
    $days = casting_profile_view_stats_days($user_id);
    $today = wp_date('Y-m-d');
    $month_prefix = wp_date('Y-m');
    $day_count = (int) ($days[$today] ?? 0);
    $month_count = 0;
    $total = 0;
    foreach ($days as $day => $count) {
        $n = (int) $count;
        $total += $n;
        if (str_starts_with($day, $month_prefix)) {
            $month_count += $n;
        }
    }

    return [
        'day'   => $day_count,
        'month' => $month_count,
        'total' => $total,
    ];
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
