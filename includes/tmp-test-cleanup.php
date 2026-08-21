<?php
declare(strict_types=1);

/**
 * یک‌بار — پاکسازی فراخوان‌های تست eshahabian و پوسترهای تست
 * (از صندوق همهٔ کاربرها، فید فرصت‌ها، و صف تأیید پوستر).
 */

function casting_tmp_cleanup_table_exists(string $table): bool
{
    global $wpdb;
    if ($table === '') {
        return false;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

    return $found === $table;
}

/**
 * @param mixed $list
 * @param list<int> $deleted_opp_ids
 * @return list<array<string, mixed>>
 */
function casting_tmp_cleanup_filter_requests($list, int $owner_id, array $deleted_opp_ids): array
{
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }
        $employer_id = (int) ($item['employer_id'] ?? 0);
        $kind = (string) ($item['kind'] ?? '');
        $status = (string) ($item['status'] ?? '');
        $talent_id = (int) ($item['talent_id'] ?? 0);
        $opp_id = (int) ($item['opportunity_id'] ?? 0);
        $from_owner = $owner_id > 0 && $employer_id === $owner_id;
        $is_call = $kind === 'casting_call' || $status === 'public' || ($from_owner && $talent_id === 0);
        if ($from_owner && $is_call) {
            continue;
        }
        if ($opp_id > 0 && in_array($opp_id, $deleted_opp_ids, true)) {
            continue;
        }
        $out[] = $item;
    }

    return $out;
}

function casting_purge_test_calls_and_posters_once(): void
{
    if (!function_exists('get_option') || (string) get_option('casting_purged_test_calls_posters_v1', '') === '1') {
        return;
    }
    static $running = false;
    if ($running) {
        return;
    }
    $running = true;

    global $wpdb;
    $owner = get_user_by('login', function_exists('casting_portal_owner_login') ? casting_portal_owner_login() : 'eshahabian');
    $owner_id = $owner instanceof WP_User ? (int) $owner->ID : 0;
    $tester = get_user_by('login', 'shaverdi');
    $tester_id = $tester instanceof WP_User ? (int) $tester->ID : 0;
    $deleted_opp_ids = [];
    $poster_user_ids = [];

    if ($owner_id > 0) {
        require_once __DIR__ . '/opportunities.php';
        $ops = casting_opportunities_table();
        $apps = casting_opportunity_applications_table();
        $saved = casting_opportunity_saved_table();
        if (casting_tmp_cleanup_table_exists($ops)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, cover_attachment_id FROM {$ops} WHERE director_id = %d",
                $owner_id
            ), ARRAY_A);
            if (!is_array($rows)) {
                $rows = [];
            }
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $deleted_opp_ids[] = $id;
                $cover = (int) ($row['cover_attachment_id'] ?? 0);
                if (casting_tmp_cleanup_table_exists($apps)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->delete($apps, ['opportunity_id' => $id], ['%d']);
                }
                if (casting_tmp_cleanup_table_exists($saved)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->delete($saved, ['opportunity_id' => $id], ['%d']);
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->delete($ops, ['id' => $id], ['%d']);
                if ($cover > 0) {
                    wp_delete_attachment($cover, true);
                }
            }
        }

        delete_user_meta($owner_id, 'casting_director_call_log');
        $poster_user_ids[$owner_id] = true;
    }

    $meta_keys = [
        'casting_requests',
        'casting_requests_archive',
        'casting_sent_requests',
        'casting_sent_requests_archive',
    ];
    $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $meta_user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ({$placeholders})",
        $meta_keys[0],
        $meta_keys[1],
        $meta_keys[2],
        $meta_keys[3]
    ));
    if (!is_array($meta_user_ids)) {
        $meta_user_ids = [];
    }
    foreach ($meta_user_ids as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0) {
            continue;
        }
        foreach ($meta_keys as $key) {
            $list = get_user_meta($uid, $key, true);
            if (!is_array($list) || $list === []) {
                continue;
            }
            $filtered = casting_tmp_cleanup_filter_requests($list, $owner_id, $deleted_opp_ids);
            if ($filtered === $list) {
                continue;
            }
            if ($filtered === []) {
                delete_user_meta($uid, $key);
            } else {
                update_user_meta($uid, $key, $filtered);
            }
        }
    }

    require_once __DIR__ . '/ad-posters.php';
    casting_ad_posters_ensure_table();
    $posters_table = casting_ad_posters_table();
    $credits_table = casting_ad_credits_table();
    if ($tester_id > 0) {
        $poster_user_ids[$tester_id] = true;
    }

    if (casting_tmp_cleanup_table_exists($posters_table)) {
        $poster_where = ["order_code = 'TMP-TEST'"];
        if ($poster_user_ids !== []) {
            $ids = array_map('intval', array_keys($poster_user_ids));
            $poster_where[] = 'user_id IN (' . implode(',', $ids) . ')';
        }
        $where_sql = implode(' OR ', $poster_where);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $poster_rows = $wpdb->get_results("SELECT id, user_id, attachment_id FROM {$posters_table} WHERE {$where_sql}", ARRAY_A);
        if (!is_array($poster_rows)) {
            $poster_rows = [];
        }
        foreach ($poster_rows as $row) {
            $pid = (int) ($row['id'] ?? 0);
            $uid = (int) ($row['user_id'] ?? 0);
            $aid = (int) ($row['attachment_id'] ?? 0);
            if ($pid > 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->delete($posters_table, ['id' => $pid], ['%d']);
            }
            if ($uid > 0) {
                $poster_user_ids[$uid] = true;
            }
            if ($aid > 0) {
                wp_delete_attachment($aid, true);
            }
        }
    }

    if (casting_tmp_cleanup_table_exists($credits_table)) {
        $like = $wpdb->esc_like('tmp-test-') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $credit_user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$credits_table} WHERE order_code = %s OR slot_key LIKE %s",
            'TMP-TEST',
            $like
        ));
        if (is_array($credit_user_ids)) {
            foreach ($credit_user_ids as $uid) {
                $uid = (int) $uid;
                if ($uid > 0) {
                    $poster_user_ids[$uid] = true;
                }
            }
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$credits_table} WHERE order_code = %s OR slot_key LIKE %s",
            'TMP-TEST',
            $like
        ));
    }

    foreach (array_keys($poster_user_ids) as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0 || (function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($uid))) {
            continue;
        }
        $open = 0;
        $left = 0;
        if (casting_tmp_cleanup_table_exists($credits_table)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $open = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$credits_table} WHERE user_id = %d AND status = 'open'",
                $uid
            ));
        }
        if (casting_tmp_cleanup_table_exists($posters_table)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $left = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$posters_table} WHERE user_id = %d",
                $uid
            ));
        }
        if ($open === 0 && $left === 0) {
            casting_user_set_ads_unlocked($uid, false);
        }
    }

    delete_option('casting_tmp_test_shaverdi_v1');
    delete_option('casting_tmp_test_shaverdi_v3');
    update_option('casting_purged_test_calls_posters_v1', '1', false);
    $running = false;
}
