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

function casting_dm_read_meta_key(): string
{
    return 'casting_dm_read';
}

function casting_dm_premium_required_notice_message(): string
{
    return 'هنرمند گرامی شما یک پیام دارین برای دریافت و یا پاسخ به آن ،عضویت ویژه الزامی است';
}

function casting_dm_support_sender_id(): int
{
    if (!function_exists('casting_contact_recipient_id')) {
        require_once __DIR__ . '/contact-messages.php';
    }
    $support_id = casting_contact_recipient_id('site_admin');
    if ($support_id > 0) {
        return $support_id;
    }

    $owner = get_user_by('login', casting_portal_owner_login());
    return $owner ? (int) $owner->ID : 0;
}

function casting_dm_is_support_peer(int $peer_id): bool
{
    $support_id = casting_dm_support_sender_id();

    return $support_id > 0 && $peer_id === $support_id;
}

function casting_dm_support_display_name(): string
{
    return 'واحد IT';
}

function casting_dm_support_role_label(): string
{
    return 'واحد IT';
}

function casting_dm_peer_role_label(int $peer_id): string
{
    if (casting_dm_is_support_peer($peer_id)) {
        return casting_dm_support_role_label();
    }

    return casting_role_label(casting_get_user_role($peer_id));
}

function casting_user_requires_premium_for_dm(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (casting_user_is_portal_owner($user_id) || casting_dm_is_support_peer($user_id)) {
        return false;
    }
    if (!function_exists('casting_user_is_premium')) {
        require_once __DIR__ . '/premium.php';
    }

    return !casting_user_is_premium($user_id);
}

function casting_dm_peer_display_name(int $peer_id): string
{
    if (casting_dm_is_support_peer($peer_id)) {
        return casting_dm_support_display_name();
    }
    $user = get_user_by('id', $peer_id);

    return $user ? (string) $user->display_name : 'کاربر';
}

function casting_dm_premium_notice_recently_sent(int $recipient_id): bool
{
    $support_id = casting_dm_support_sender_id();
    if ($support_id <= 0 || $recipient_id <= 0) {
        return true;
    }

    casting_chat_ensure_table();
    global $wpdb;
    $table = casting_dm_table();
    $notice = casting_dm_premium_required_notice_message();
    $since = wp_date('Y-m-d H:i:s', time() - 6 * HOUR_IN_SECONDS);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $found = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table}
         WHERE sender_id = %d AND recipient_id = %d AND message = %s AND created_at >= %s
         LIMIT 1",
        $support_id,
        $recipient_id,
        $notice,
        $since
    ));

    return (int) $found > 0;
}

function casting_dm_maybe_send_premium_required_notice(int $recipient_id, int $sender_id): void
{
    if ($recipient_id <= 0 || $sender_id <= 0 || $recipient_id === $sender_id) {
        return;
    }
    if (!casting_user_requires_premium_for_dm($recipient_id)) {
        return;
    }
    if (casting_dm_is_support_peer($sender_id)) {
        return;
    }
    if (casting_dm_premium_notice_recently_sent($recipient_id)) {
        return;
    }

    $support_id = casting_dm_support_sender_id();
    if ($support_id <= 0 || $support_id === $recipient_id) {
        return;
    }

    casting_dm_insert_raw(
        $support_id,
        $recipient_id,
        casting_dm_premium_required_notice_message(),
        '',
        false
    );
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_can_user_open_dm(int $from_id, int $to_id): array
{
    if (casting_dm_has_conversation($from_id, $to_id)) {
        if (casting_users_block_each_other($from_id, $to_id)) {
            return ['ok' => false, 'error' => 'به‌دلیل بلاک، امکان گفتگو وجود ندارد.'];
        }

        return ['ok' => true, 'error' => ''];
    }

    return casting_can_start_chat($from_id, $to_id);
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_can_user_send_dm(int $sender_id, int $recipient_id): array
{
    $allow = casting_can_users_chat($sender_id, $recipient_id);
    if (!$allow['ok']) {
        return $allow;
    }
    if (casting_dm_is_support_peer($recipient_id)) {
        return ['ok' => true, 'error' => ''];
    }
    if (!casting_user_requires_premium_for_dm($sender_id)) {
        return ['ok' => true, 'error' => ''];
    }
    if (casting_employer_has_free_message_quota($sender_id)) {
        return ['ok' => true, 'error' => ''];
    }

    if (casting_user_is_employer_account($sender_id)) {
        return ['ok' => false, 'error' => casting_employer_premium_send_error()];
    }

    return [
        'ok'    => false,
        'error' => casting_dm_premium_required_notice_message(),
    ];
}

function casting_user_needs_premium_to_read_inbox(int $user_id): bool
{
    if (!casting_user_requires_premium_for_dm($user_id)) {
        return false;
    }
    // کارگردان/تهیه‌کننده برای ارسال سهمیه رایگان دارند؛ inboxشان قفل نمی‌شود.
    if (casting_user_is_employer_account($user_id)) {
        return false;
    }

    return true;
}

function casting_dm_thread_locked_for_user(int $user_id, int $peer_id): bool
{
    return casting_user_needs_premium_to_read_inbox($user_id) && !casting_dm_is_support_peer($peer_id);
}

function casting_employer_free_dm_limit(): int
{
    return 3;
}

function casting_user_is_employer_account(int $user_id): bool
{
    return casting_is_employer_role(casting_get_user_role($user_id));
}

function casting_employer_free_messages_used(int $user_id): int
{
    return max(0, (int) get_user_meta($user_id, 'casting_employer_free_messages_used', true));
}

function casting_employer_free_messages_remaining(int $user_id): int
{
    if (!casting_user_is_employer_account($user_id)) {
        return 0;
    }
    if (!casting_user_requires_premium_for_dm($user_id)) {
        return casting_employer_free_dm_limit();
    }

    return max(0, casting_employer_free_dm_limit() - casting_employer_free_messages_used($user_id));
}

function casting_employer_has_free_message_quota(int $user_id): bool
{
    return casting_employer_free_messages_remaining($user_id) > 0;
}

function casting_employer_premium_send_error(): string
{
    return 'سه پیام رایگان شما تمام شده است. برای ارسال پیام بیشتر، عضویت ویژه فعال کنید.';
}

function casting_employer_free_messages_hint(int $user_id): string
{
    if (!casting_user_is_employer_account($user_id) || !casting_user_requires_premium_for_dm($user_id)) {
        return '';
    }

    $remaining = casting_employer_free_messages_remaining($user_id);
    if ($remaining <= 0) {
        return 'پیام رایگان باقی‌مانده: ۰ — برای ادامه، عضویت ویژه لازم است.';
    }

    return 'پیام رایگان باقی‌مانده: ' . $remaining . ' از ' . casting_employer_free_dm_limit();
}

function casting_employer_default_outreach_message(int $employer_id): string
{
    $user = get_user_by('id', $employer_id);
    $name = $user ? trim((string) $user->display_name) : '';
    if ($name === '') {
        $name = '…';
    }

    return "هنرمند گرامی، سلام\n\n"
        . "پروفایل شما در سامانه «هفت‌رخ» بررسی شده است. با توجه به سوابق حرفه‌ای شما، مایل هستیم درباره امکان همکاری در یک پروژه جدید با شما گفتگو کنیم. "
        . "در صورت تمایل، لطفاً آمادگی خود را اعلام فرمایید تا جزئیات همکاری ارائه شود.\n\n"
        . "با احترام\n"
        . $name . "\n"
        . "از طریق سامانه تخصصی هفت‌رخ";
}

function casting_employer_maybe_consume_free_message(int $user_id): void
{
    if (!casting_user_is_employer_account($user_id) || !casting_user_requires_premium_for_dm($user_id)) {
        return;
    }

    $used = casting_employer_free_messages_used($user_id);
    if ($used >= casting_employer_free_dm_limit()) {
        return;
    }

    update_user_meta($user_id, 'casting_employer_free_messages_used', $used + 1);
}

function casting_dm_has_conversation(int $user_a, int $user_b): bool
{
    if ($user_a <= 0 || $user_b <= 0 || $user_a === $user_b) {
        return false;
    }

    casting_chat_ensure_table();
    global $wpdb;
    $table = casting_dm_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $found = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table}
         WHERE (sender_id = %d AND recipient_id = %d)
            OR (sender_id = %d AND recipient_id = %d)
         LIMIT 1",
        $user_a,
        $user_b,
        $user_b,
        $user_a
    ));

    return (int) $found > 0;
}

/**
 * @return array<int, int> peer_id => last_read_message_id
 */
function casting_dm_read_map(int $user_id): array
{
    $raw = get_user_meta($user_id, casting_dm_read_meta_key(), true);
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $peer => $message_id) {
        $peer_id = (int) $peer;
        if ($peer_id > 0) {
            $out[$peer_id] = (int) $message_id;
        }
    }
    return $out;
}

function casting_dm_mark_read(int $user_id, int $peer_id): void
{
    if ($user_id <= 0 || $peer_id <= 0) {
        return;
    }

    casting_chat_ensure_table();
    global $wpdb;
    $table = casting_dm_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $last_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(id) FROM {$table} WHERE sender_id = %d AND recipient_id = %d",
        $peer_id,
        $user_id
    ));
    if ($last_id <= 0) {
        return;
    }

    $map = casting_dm_read_map($user_id);
    $map[$peer_id] = $last_id;
    update_user_meta($user_id, casting_dm_read_meta_key(), $map);
}

function casting_dm_unread_count(int $user_id, int $peer_id): int
{
    if ($user_id <= 0 || $peer_id <= 0) {
        return 0;
    }

    casting_chat_ensure_table();
    global $wpdb;
    $table = casting_dm_table();
    $read_map = casting_dm_read_map($user_id);
    $last_read = (int) ($read_map[$peer_id] ?? 0);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE sender_id = %d AND recipient_id = %d AND id > %d",
        $peer_id,
        $user_id,
        $last_read
    ));
}

/**
 * @return array<int, int> peer_id => unread_count
 */
function casting_dm_unread_by_peer(int $user_id): array
{
    if ($user_id <= 0) {
        return [];
    }

    casting_chat_ensure_table();
    global $wpdb;
    $table = casting_dm_table();
    $read_map = casting_dm_read_map($user_id);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT sender_id FROM {$table} WHERE recipient_id = %d",
            $user_id
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $peer_id = (int) ($row['sender_id'] ?? 0);
        if ($peer_id <= 0) {
            continue;
        }
        $last_read = (int) ($read_map[$peer_id] ?? 0);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE sender_id = %d AND recipient_id = %d AND id > %d",
            $peer_id,
            $user_id,
            $last_read
        ));
        if ($count > 0) {
            $out[$peer_id] = $count;
        }
    }

    return $out;
}

function casting_dm_unread_peer_count(int $user_id): int
{
    return count(casting_dm_unread_by_peer($user_id));
}

function casting_chat_peer_avatar_url(int $user_id): string
{
    $portraits = casting_load_all_portraits($user_id);
    $primary = casting_primary_portrait($portraits);
    if (($primary['full'] ?? '') !== '') {
        return (string) $primary['full'];
    }
    return (string) ($primary['url'] ?? '');
}

function casting_render_chat_avatar(int $user_id, string $name, bool $has_unread = false): void
{
    $avatar = casting_chat_peer_avatar_url($user_id);
    $initial = '؟';
    $trimmed = trim($name);
    if ($trimmed !== '') {
        $initial = function_exists('mb_substr')
            ? casting_e((string) mb_substr($trimmed, 0, 1, 'UTF-8'))
            : casting_e(substr($trimmed, 0, 1));
    }
    ?>
<span class="chat-avatar<?= $has_unread ? ' has-unread' : '' ?>">
  <?php if ($avatar !== '') : ?>
    <img src="<?= casting_e($avatar) ?>" alt="">
  <?php else : ?>
    <span class="chat-avatar-fallback" aria-hidden="true"><?= $initial ?></span>
  <?php endif; ?>
</span>
    <?php
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
    $allow = casting_can_user_send_dm($sender_id, $recipient_id);
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

    casting_employer_maybe_consume_free_message($sender_id);
    casting_dm_maybe_send_premium_required_notice($recipient_id, $sender_id);

    return ['ok' => true];
}

function casting_dm_insert_raw(
    int $sender_id,
    int $recipient_id,
    string $message,
    string $created_at = '',
    bool $notify_premium = true,
    bool $enforce_send_rules = true
): bool
{
    if ($sender_id <= 0 || $recipient_id <= 0) {
        return false;
    }

    if ($enforce_send_rules) {
        $allow = casting_can_user_send_dm($sender_id, $recipient_id);
        if (!$allow['ok']) {
            return false;
        }
    }

    $message = trim(sanitize_textarea_field($message));
    if ($message === '') {
        return false;
    }

    casting_chat_ensure_table();
    global $wpdb;
    if ($created_at === '') {
        $created_at = current_time('mysql');
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = (bool) $wpdb->insert(
        casting_dm_table(),
        [
            'sender_id'    => $sender_id,
            'recipient_id' => $recipient_id,
            'message'      => $message,
            'created_at'   => $created_at,
        ],
        ['%d', '%d', '%s', '%s']
    );

    if ($ok && $notify_premium) {
        casting_dm_maybe_send_premium_required_notice($recipient_id, $sender_id);
    }
    if ($ok && $enforce_send_rules) {
        casting_employer_maybe_consume_free_message($sender_id);
    }

    return $ok;
}

/**
 * @return array<int, array{id:int,sender_id:int,recipient_id:int,message:string,created_at:string,is_mine:bool}>
 */
function casting_dm_thread(int $user_id, int $peer_id, int $limit = 200): array
{
    if ($user_id <= 0 || $peer_id <= 0) {
        return [];
    }

    if (function_exists('casting_users_block_each_other') && casting_users_block_each_other($user_id, $peer_id)) {
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
 * @return array<int, array{peer_id:int,name:string,role:string,last_message:string,last_at:string,unread:int,avatar:string}>
 */
function casting_dm_conversations(int $user_id): array
{
    casting_chat_ensure_table();
    global $wpdb;
    $table = casting_dm_table();
    $unread_map = casting_dm_unread_by_peer($user_id);
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
        if (!casting_dm_has_conversation($user_id, $peer) && !casting_can_users_chat($user_id, $peer)['ok']) {
            continue;
        }
        $seen[$peer] = true;
        $user = get_user_by('id', $peer);
        $locked = casting_dm_thread_locked_for_user($user_id, $peer);
        $last_message = (string) $row['message'];
        if ($locked && (int) ($unread_map[$peer] ?? 0) > 0) {
            $last_message = 'پیام جدید — برای مشاهده عضویت ویژه لازم است';
        }
        $out[] = [
            'peer_id'      => $peer,
            'name'         => casting_dm_peer_display_name($peer),
            'role'         => casting_get_user_role($peer),
            'last_message' => $last_message,
            'last_at'      => (string) $row['created_at'],
            'unread'       => (int) ($unread_map[$peer] ?? 0),
            'avatar'       => casting_chat_peer_avatar_url($peer),
            'locked'       => $locked,
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
        if (!casting_can_user_open_dm($user_id, $uid)['ok']) {
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
