<?php
declare(strict_types=1);

function casting_follows_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_follows';
}

function casting_follows_install(): void
{
    global $wpdb;
    $table = casting_follows_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        follower_id BIGINT UNSIGNED NOT NULL,
        followed_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY follower_followed (follower_id, followed_id),
        KEY followed_id (followed_id),
        KEY follower_id (follower_id)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('casting_follows_db_version', '1');
}

function casting_follows_ensure_table(): void
{
    if ((string) get_option('casting_follows_db_version', '') !== '1') {
        casting_follows_install();
    }
}

function casting_follow_can_target(int $follower_id, int $followed_id): bool
{
    if ($follower_id <= 0 || $followed_id <= 0 || $follower_id === $followed_id) {
        return false;
    }
    if (casting_get_user_role($follower_id) === '' || casting_get_user_role($followed_id) === '') {
        return false;
    }
    if (function_exists('casting_users_block_each_other') && casting_users_block_each_other($follower_id, $followed_id)) {
        return false;
    }

    return true;
}

function casting_user_is_following(int $follower_id, int $followed_id): bool
{
    if ($follower_id <= 0 || $followed_id <= 0) {
        return false;
    }
    casting_follows_ensure_table();
    global $wpdb;
    $table = casting_follows_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE follower_id = %d AND followed_id = %d LIMIT 1",
        $follower_id,
        $followed_id
    ));

    return (int) $id > 0;
}

/**
 * @return array{ok:bool,error:string,following?:bool,message?:string}
 */
function casting_follow_toggle(int $follower_id, int $followed_id): array
{
    if (!casting_follow_can_target($follower_id, $followed_id)) {
        return ['ok' => false, 'error' => 'امکان دنبال کردن این کاربر نیست.'];
    }
    casting_follows_ensure_table();
    global $wpdb;
    $table = casting_follows_table();

    if (casting_user_is_following($follower_id, $followed_id)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->delete($table, [
            'follower_id' => $follower_id,
            'followed_id' => $followed_id,
        ], ['%d', '%d']);

        return [
            'ok'        => true,
            'error'     => '',
            'following' => false,
            'message'   => 'دیگر دنبال نمی‌کنید.',
        ];
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ok = $wpdb->insert($table, [
        'follower_id' => $follower_id,
        'followed_id' => $followed_id,
        'created_at'  => current_time('mysql'),
    ], ['%d', '%d', '%s']);

    if ($ok === false) {
        return ['ok' => false, 'error' => 'ذخیره ناموفق بود.'];
    }

    return [
        'ok'        => true,
        'error'     => '',
        'following' => true,
        'message'   => 'اکنون دنبال می‌کنید.',
    ];
}

function casting_followers_seen_meta_key(): string
{
    return 'casting_followers_seen_at';
}

function casting_followers_seen_at(int $user_id): string
{
    if ($user_id <= 0) {
        return '';
    }

    return trim((string) get_user_meta($user_id, casting_followers_seen_meta_key(), true));
}

function casting_mark_followers_seen(int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    update_user_meta($user_id, casting_followers_seen_meta_key(), current_time('mysql'));
}

/** تعداد دنبال‌کننده‌های جدید از آخرین بازدید صفحهٔ دنبال‌کننده‌ها */
function casting_new_followers_count(int $user_id): int
{
    if ($user_id <= 0) {
        return 0;
    }
    casting_follows_ensure_table();
    global $wpdb;
    $table = casting_follows_table();
    $seen = casting_followers_seen_at($user_id);
    if ($seen === '') {
        // هنوز صفحه‌ای ندیده — فقط فالوهای ۴۸ ساعت اخیر را جدید حساب کن
        $since = gmdate('Y-m-d H:i:s', current_time('timestamp') - 2 * DAY_IN_SECONDS);
    } else {
        $since = $seen;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE followed_id = %d AND created_at > %s",
        $user_id,
        $since
    ));
}

/**
 * @return list<array{follower_id:int,created_at:string,name:string}>
 */
function casting_new_followers_list(int $user_id, int $limit = 5): array
{
    if ($user_id <= 0) {
        return [];
    }
    casting_follows_ensure_table();
    global $wpdb;
    $table = casting_follows_table();
    $limit = max(1, min(20, $limit));
    $seen = casting_followers_seen_at($user_id);
    if ($seen === '') {
        $since = gmdate('Y-m-d H:i:s', current_time('timestamp') - 2 * DAY_IN_SECONDS);
    } else {
        $since = $seen;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT follower_id, created_at FROM {$table} WHERE followed_id = %d AND created_at > %s ORDER BY id DESC LIMIT %d",
        $user_id,
        $since,
        $limit
    ), ARRAY_A);
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $fid = (int) ($row['follower_id'] ?? 0);
        $user = $fid > 0 ? get_user_by('id', $fid) : null;
        if (!$user) {
            continue;
        }
        $out[] = [
            'follower_id' => $fid,
            'created_at'  => (string) ($row['created_at'] ?? ''),
            'name'        => (string) $user->display_name,
        ];
    }

    return $out;
}

function casting_followers_count(int $user_id): int
{
    if ($user_id <= 0) {
        return 0;
    }
    casting_follows_ensure_table();
    global $wpdb;
    $table = casting_follows_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE followed_id = %d",
        $user_id
    ));
}

function casting_following_count(int $user_id): int
{
    if ($user_id <= 0) {
        return 0;
    }
    casting_follows_ensure_table();
    global $wpdb;
    $table = casting_follows_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE follower_id = %d",
        $user_id
    ));
}

/**
 * @return list<int>
 */
function casting_list_follower_ids(int $user_id, int $limit = 100): array
{
    if ($user_id <= 0) {
        return [];
    }
    casting_follows_ensure_table();
    global $wpdb;
    $table = casting_follows_table();
    $limit = max(1, min(200, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT follower_id FROM {$table} WHERE followed_id = %d ORDER BY created_at DESC LIMIT %d",
        $user_id,
        $limit
    ));

    return array_values(array_map('intval', is_array($rows) ? $rows : []));
}

/**
 * @return list<int>
 */
function casting_list_following_ids(int $user_id, int $limit = 100): array
{
    if ($user_id <= 0) {
        return [];
    }
    casting_follows_ensure_table();
    global $wpdb;
    $table = casting_follows_table();
    $limit = max(1, min(200, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT followed_id FROM {$table} WHERE follower_id = %d ORDER BY created_at DESC LIMIT %d",
        $user_id,
        $limit
    ));

    return array_values(array_map('intval', is_array($rows) ? $rows : []));
}
