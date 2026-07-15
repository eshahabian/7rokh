<?php
declare(strict_types=1);

require_once __DIR__ . '/chat-rules.php';

function casting_chat_preview(string $message, int $max = 48): string
{
    $message = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
    if (casting_strlen($message) <= $max) {
        return $message;
    }
    if (function_exists('mb_substr')) {
        return rtrim((string) mb_substr($message, 0, $max, 'UTF-8')) . '…';
    }
    return rtrim(substr($message, 0, $max)) . '…';
}

function casting_chat_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'casting_chat';
}

function casting_dm_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'casting_dm';
}

function casting_chat_install(): void
{
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $public = casting_chat_table();
    $sql_public = "CREATE TABLE IF NOT EXISTS {$public} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) {$charset};";

    $dm = casting_dm_table();
    $sql_dm = "CREATE TABLE IF NOT EXISTS {$dm} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sender_id BIGINT UNSIGNED NOT NULL,
        recipient_id BIGINT UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY sender_id (sender_id),
        KEY recipient_id (recipient_id),
        KEY pair_created (sender_id, recipient_id, created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_public);
    dbDelta($sql_dm);
    update_option('casting_chat_db_version', '2');
}

function casting_chat_ensure_table(): void
{
    $ver = (string) get_option('casting_chat_db_version', '');
    if ($ver !== '2') {
        casting_chat_install();
        return;
    }
    global $wpdb;
    $dm = casting_dm_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $dm));
    if ($exists !== $dm) {
        casting_chat_install();
    }
}

function casting_chat_post(int $user_id, string $message): array
{
    casting_chat_ensure_table();
    $message = trim(sanitize_textarea_field($message));
    if ($message === '') {
        return ['ok' => false, 'error' => 'پیام خالی است.'];
    }
    if (casting_strlen($message) > 1000) {
        return ['ok' => false, 'error' => 'پیام حداکثر ۱۰۰۰ کاراکتر باشد.'];
    }

    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->insert(
        casting_chat_table(),
        [
            'user_id'    => $user_id,
            'message'    => $message,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s']
    );

    if (!$ok) {
        return ['ok' => false, 'error' => 'ارسال پیام ناموفق بود.'];
    }

    return ['ok' => true];
}

/**
 * @return array<int, array{id:int,user_id:int,message:string,created_at:string,name:string,role:string}>
 */
function casting_chat_list(int $limit = 80): array
{
    casting_chat_ensure_table();
    global $wpdb;
    $table = casting_chat_table();
    $limit = max(1, min(200, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT id, user_id, message, created_at FROM {$table} ORDER BY id DESC LIMIT %d", $limit),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach (array_reverse($rows) as $row) {
        $uid = (int) $row['user_id'];
        $user = get_user_by('id', $uid);
        $out[] = [
            'id'         => (int) $row['id'],
            'user_id'    => $uid,
            'message'    => (string) $row['message'],
            'created_at' => (string) $row['created_at'],
            'name'       => $user ? (string) $user->display_name : 'کاربر',
            'role'       => casting_get_user_role($uid),
        ];
    }
    return $out;
}

function casting_dm_send(int $sender_id, int $recipient_id, string $message): array
{
    $allow = casting_can_users_chat($sender_id, $recipient_id);
    if (!$allow['ok']) {
        return $allow;
    }

    $message = trim(sanitize_textarea_field($message));
    if ($message === '') {
        return ['ok' => false, 'error' => 'پیام خالی است.'];
    }
    if (casting_strlen($message) > 2000) {
        return ['ok' => false, 'error' => 'پیام حداکثر ۲۰۰۰ کاراکتر باشد.'];
    }

    casting_chat_ensure_table();
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->insert(
        casting_dm_table(),
        [
            'sender_id'    => $sender_id,
            'recipient_id' => $recipient_id,
            'message'      => $message,
            'created_at'   => current_time('mysql'),
        ],
        ['%d', '%d', '%s', '%s']
    );

    if (!$ok) {
        return ['ok' => false, 'error' => 'ارسال پیام ناموفق بود.'];
    }

    return ['ok' => true];
}

/**
 * @return array<int, array{id:int,sender_id:int,recipient_id:int,message:string,created_at:string,is_mine:bool}>
 */
function casting_dm_thread(int $user_id, int $peer_id, int $limit = 200): array
{
    if ($user_id <= 0 || $peer_id <= 0) {
        return [];
    }

    casting_chat_ensure_table();
    global $wpdb;
    $table = casting_dm_table();
    $limit = max(1, min(500, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, sender_id, recipient_id, message, created_at
             FROM {$table}
             WHERE (sender_id = %d AND recipient_id = %d)
                OR (sender_id = %d AND recipient_id = %d)
             ORDER BY id DESC
             LIMIT %d",
            $user_id,
            $peer_id,
            $peer_id,
            $user_id,
            $limit
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach (array_reverse($rows) as $row) {
        $out[] = [
            'id'           => (int) $row['id'],
            'sender_id'    => (int) $row['sender_id'],
            'recipient_id' => (int) $row['recipient_id'],
            'message'      => (string) $row['message'],
            'created_at'   => (string) $row['created_at'],
            'is_mine'      => (int) $row['sender_id'] === $user_id,
        ];
    }
    return $out;
}

/**
 * @return array<int, array{peer_id:int,name:string,role:string,last_message:string,last_at:string}>
 */
function casting_dm_conversations(int $user_id): array
{
    casting_chat_ensure_table();
    global $wpdb;
    $table = casting_dm_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, sender_id, recipient_id, message, created_at
             FROM {$table}
             WHERE sender_id = %d OR recipient_id = %d
             ORDER BY id DESC
             LIMIT 500",
            $user_id,
            $user_id
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [];
    }

    $seen = [];
    $out = [];
    foreach ($rows as $row) {
        $sender = (int) $row['sender_id'];
        $recipient = (int) $row['recipient_id'];
        $peer = $sender === $user_id ? $recipient : $sender;
        if ($peer <= 0 || isset($seen[$peer])) {
            continue;
        }
        if (!casting_can_users_chat($user_id, $peer)['ok']) {
            continue;
        }
        $seen[$peer] = true;
        $user = get_user_by('id', $peer);
        $out[] = [
            'peer_id'      => $peer,
            'name'         => $user ? (string) $user->display_name : 'کاربر',
            'role'         => casting_get_user_role($peer),
            'last_message' => (string) $row['message'],
            'last_at'      => (string) $row['created_at'],
        ];
    }
    return $out;
}

/**
 * مخاطبان مجاز برای شروع گفتگو
 *
 * @return array<int, array{id:int,name:string,role:string}>
 */
function casting_dm_allowed_contacts(int $user_id): array
{
    $users = get_users([
        'meta_key' => 'casting_role',
        'number'   => 500,
        'fields'   => ['ID', 'display_name'],
        'orderby'  => 'display_name',
        'order'    => 'ASC',
    ]);

    $out = [];
    foreach ($users as $user) {
        $uid = (int) $user->ID;
        if ($uid === $user_id) {
            continue;
        }
        if (!casting_can_users_chat($user_id, $uid)['ok']) {
            continue;
        }
        $out[] = [
            'id'   => $uid,
            'name' => (string) $user->display_name,
            'role' => casting_get_user_role($uid),
        ];
    }
    return $out;
}
