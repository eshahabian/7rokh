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

/**
 * پاک‌سازی و همگام‌سازی فالوهای الزامی — فقط از bootstrap پنل، نه از هر SELECT
 */
function casting_follows_bootstrap_maintenance(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    casting_follows_ensure_table();
    casting_follows_purge_orphans_once();
    casting_follow_sync_required_admins_once();
}

/**
 * پاک‌سازی روابط دنبال برای کاربرانی که دیگر عضو پورتال نیستند
 */
function casting_follows_purge_orphans_once(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    // v3: فالو به مدیران الزامی دیگر پاک نمی‌شود
    $force = (string) get_option('casting_follows_orphan_purge_v3', '') !== '1';
    $stamp = (int) get_option('casting_follows_orphan_purged_at', 0);
    if (!$force && $stamp > 0 && (time() - $stamp) < 6 * HOUR_IN_SECONDS) {
        return;
    }
    casting_follows_purge_orphans();
    update_option('casting_follows_orphan_purged_at', time(), false);
    if ($force) {
        update_option('casting_follows_orphan_purge_v3', '1', false);
    }
}

/**
 * @return list<int>
 */
function casting_default_follow_admin_ids(): array
{
    static $ids = null;
    if (is_array($ids)) {
        return $ids;
    }
    $ids = [];
    foreach (casting_default_follow_admin_logins() as $login) {
        $admin = get_user_by('login', $login);
        if (!$admin) {
            // بعضی نصب‌ها login را با حروف بزرگ ذخیره کرده‌اند
            $admin = get_user_by('slug', $login);
        }
        if ($admin) {
            $ids[] = (int) $admin->ID;
        }
    }
    $ids = array_values(array_unique(array_filter($ids)));

    return $ids;
}

function casting_follows_purge_orphans(): void
{
    global $wpdb;
    $table = casting_follows_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        return;
    }
    $usermeta = $wpdb->usermeta;
    $users = $wpdb->users;
    $admin_ids = casting_default_follow_admin_ids();
    $admin_sql = '';
    if ($admin_ids !== []) {
        $admin_sql = ' AND f.followed_id NOT IN (' . implode(',', array_map('intval', $admin_ids)) . ')';
    }
    // فالوکنندهٔ بدون نقش پورتال
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query(
        "DELETE f FROM {$table} f
         LEFT JOIN {$users} u ON u.ID = f.follower_id
         LEFT JOIN {$usermeta} m ON m.user_id = f.follower_id AND m.meta_key = 'casting_role'
         WHERE u.ID IS NULL OR m.umeta_id IS NULL OR m.meta_value = ''"
    );
    // فالوشوندهٔ بدون نقش — به‌جز مدیران الزامی (eshahabian / ardavan)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query(
        "DELETE f FROM {$table} f
         LEFT JOIN {$users} u ON u.ID = f.followed_id
         LEFT JOIN {$usermeta} m ON m.user_id = f.followed_id AND m.meta_key = 'casting_role'
         WHERE (u.ID IS NULL OR m.umeta_id IS NULL OR m.meta_value = ''){$admin_sql}"
    );
}

function casting_follow_can_target(int $follower_id, int $followed_id): bool
{
    if ($follower_id <= 0 || $followed_id <= 0 || $follower_id === $followed_id) {
        return false;
    }
    if (casting_get_user_role($follower_id) === '') {
        return false;
    }
    // مدیران الزامی همیشه قابل‌دنبال‌کردن‌اند (حتی اگر meta نقش خالی باشد)
    if (!casting_follow_target_is_required($followed_id) && casting_get_user_role($followed_id) === '') {
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
 * @return array{ok:bool,error:string,following?:bool,message?:string,locked?:bool}
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
        if (casting_follow_target_is_required($followed_id)) {
            return [
                'ok'        => false,
                'error'     => 'دنبال کردن مدیران سایت الزامی است و قابل لغو نیست.',
                'following' => true,
                'locked'    => true,
            ];
        }
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

    if (!casting_follow_ensure($follower_id, $followed_id)) {
        return ['ok' => false, 'error' => 'ذخیره ناموفق بود.'];
    }

    return [
        'ok'        => true,
        'error'     => '',
        'following' => true,
        'message'   => 'اکنون دنبال می‌کنید.',
        'locked'    => casting_follow_target_is_required($followed_id),
    ];
}

/**
 * آیا این کاربر از مدیران الزامی برای فالو است؟ (eshahabian / ardavan)
 */
function casting_follow_target_is_required(int $followed_id): bool
{
    if ($followed_id <= 0) {
        return false;
    }
    $user = get_user_by('id', $followed_id);
    if (!$user) {
        return false;
    }

    return in_array(strtolower((string) $user->user_login), casting_default_follow_admin_logins(), true);
}

function casting_follow_button_label(bool $is_following, bool $locked = false): string
{
    if ($locked) {
        return 'دنبال‌شده';
    }

    return $is_following ? 'دنبال نکردن' : 'دنبال کردن';
}

/**
 * برچسب «صفحه رسمی» برای مدیران الزامی
 */
function casting_render_official_page_badge(int $user_id): void
{
    if (!casting_follow_target_is_required($user_id)) {
        return;
    }
    ?>
<span class="chip chip-official" title="اعلامیه‌ها و پست‌های این صفحه برای همه اعضا نمایش داده می‌شود">صفحه رسمی</span>
    <?php
}

/**
 * دکمه فالو/آنفالو — برای مدیران الزامی قفل است
 */
function casting_render_follow_button(int $viewer_id, int $target_id, string $extra_class = 'btn-sm'): void
{
    if ($viewer_id <= 0 || $target_id <= 0 || $viewer_id === $target_id) {
        return;
    }
    if (!casting_follow_can_target($viewer_id, $target_id)) {
        return;
    }
    if (casting_follow_target_is_required($target_id)) {
        casting_follow_ensure($viewer_id, $target_id);
    }
    $is_following = casting_user_is_following($viewer_id, $target_id);
    $locked = $is_following && casting_follow_target_is_required($target_id);
    $label = casting_follow_button_label($is_following, $locked);
    $classes = trim('btn ' . $extra_class);
    if ($locked) {
        $classes .= ' btn-ghost is-following is-follow-locked';
    } elseif ($is_following) {
        $classes .= ' btn-ghost is-following';
    } else {
        $classes .= ' btn-primary';
    }
    ?>
<button
  type="button"
  class="<?= casting_e($classes) ?>"
  data-follow-toggle="<?= (int) $target_id ?>"
  data-following="<?= $is_following ? '1' : '0' ?>"
  data-follow-locked="<?= $locked ? '1' : '0' ?>"
  aria-pressed="<?= $is_following ? 'true' : 'false' ?>"
  <?= $locked ? ' disabled title="صفحه رسمی مدیران — دنبال کردن الزامی است"' : '' ?>
><?= casting_e($label) ?></button>
    <?php
}

/**
 * مدیران پیش‌فرض که اعضای جدید باید دنبال‌کننده‌شان شوند
 *
 * @return list<string>
 */
function casting_default_follow_admin_logins(): array
{
    $logins = [];
    if (defined('CASTING_PORTAL_ADMINS') && is_array(CASTING_PORTAL_ADMINS)) {
        foreach (CASTING_PORTAL_ADMINS as $login) {
            if (!is_string($login)) {
                continue;
            }
            $login = strtolower(trim($login));
            if ($login !== '') {
                $logins[] = $login;
            }
        }
    }
    if ($logins === []) {
        $logins = ['eshahabian', 'ardavan'];
    }

    return array_values(array_unique($logins));
}

/**
 * فالو یک‌طرفه بدون آنفالو (idempotent)
 */
function casting_follow_ensure(int $follower_id, int $followed_id): bool
{
    if ($follower_id <= 0 || $followed_id <= 0 || $follower_id === $followed_id) {
        return false;
    }
    casting_follows_ensure_table();
    if (casting_user_is_following($follower_id, $followed_id)) {
        return true;
    }
    global $wpdb;
    $table = casting_follows_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ok = $wpdb->insert($table, [
        'follower_id' => $follower_id,
        'followed_id' => $followed_id,
        'created_at'  => current_time('mysql'),
    ], ['%d', '%d', '%s']);
    if ($ok !== false) {
        return true;
    }
    // race / duplicate key → موفق حساب کن
    return casting_user_is_following($follower_id, $followed_id);
}

/**
 * بعد از عضویت جدید: کاربر دنبال‌کننده مدیران اصلی می‌شود
 */
function casting_follow_default_admins(int $user_id): void
{
    if ($user_id <= 0 || casting_get_user_role($user_id) === '') {
        return;
    }
    foreach (casting_default_follow_admin_ids() as $admin_id) {
        if ($admin_id === $user_id) {
            continue;
        }
        casting_follow_ensure($user_id, $admin_id);
    }
}

/**
 * همگام‌سازی فالوهای الزامی مدیران برای همهٔ اعضای پورتال
 * (کاربرانی که قبل از فعال شدن این قابلیت ثبت‌نام کرده‌اند یا از مسیر دیگر ساخته شده‌اند)
 */
function casting_follow_sync_required_admins(): int
{
    $admin_ids = casting_default_follow_admin_ids();
    if ($admin_ids === []) {
        return 0;
    }

    $member_ids = get_users([
        'fields'   => 'ID',
        'meta_key' => 'casting_role',
        'number'   => 5000,
    ]);
    if (!is_array($member_ids) || $member_ids === []) {
        return 0;
    }

    $created = 0;
    foreach ($member_ids as $member_id) {
        $member_id = (int) $member_id;
        if ($member_id <= 0 || casting_get_user_role($member_id) === '') {
            continue;
        }
        foreach ($admin_ids as $admin_id) {
            if ($member_id === $admin_id) {
                continue;
            }
            if (casting_user_is_following($member_id, $admin_id)) {
                continue;
            }
            if (casting_follow_ensure($member_id, $admin_id)) {
                $created++;
            }
        }
    }

    return $created;
}

/**
 * یک‌بار اجباری بعد از آپدیت، بعد هر ساعت — تا شمارندهٔ دنبال‌کننده‌های مدیران کامل بماند
 */
function casting_follow_sync_required_admins_once(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    // v3: اجبار همگام‌سازی دوباره (فالو همه اعضا به eshahabian/ardavan)
    $force = (string) get_option('casting_follow_required_sync_v3', '') !== '1';
    $stamp = (int) get_option('casting_follow_required_synced_at', 0);
    if (!$force && $stamp > 0 && (time() - $stamp) < HOUR_IN_SECONDS) {
        return;
    }
    casting_follow_sync_required_admins();
    update_option('casting_follow_required_synced_at', time(), false);
    if ($force) {
        update_option('casting_follow_required_sync_v3', '1', false);
    }
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
    $usermeta = $wpdb->usermeta;
    $users = $wpdb->users;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} f
         INNER JOIN {$users} u ON u.ID = f.follower_id
         INNER JOIN {$usermeta} m ON m.user_id = f.follower_id AND m.meta_key = 'casting_role' AND m.meta_value <> ''
         WHERE f.followed_id = %d",
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
    $usermeta = $wpdb->usermeta;
    $users = $wpdb->users;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} f
         INNER JOIN {$users} u ON u.ID = f.followed_id
         INNER JOIN {$usermeta} m ON m.user_id = f.followed_id AND m.meta_key = 'casting_role' AND m.meta_value <> ''
         WHERE f.follower_id = %d",
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
    $usermeta = $wpdb->usermeta;
    $users = $wpdb->users;
    $limit = max(1, min(200, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT f.follower_id FROM {$table} f
         INNER JOIN {$users} u ON u.ID = f.follower_id
         INNER JOIN {$usermeta} m ON m.user_id = f.follower_id AND m.meta_key = 'casting_role' AND m.meta_value <> ''
         WHERE f.followed_id = %d
         ORDER BY f.created_at DESC
         LIMIT %d",
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
    $usermeta = $wpdb->usermeta;
    $users = $wpdb->users;
    $limit = max(1, min(200, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT f.followed_id FROM {$table} f
         INNER JOIN {$users} u ON u.ID = f.followed_id
         INNER JOIN {$usermeta} m ON m.user_id = f.followed_id AND m.meta_key = 'casting_role' AND m.meta_value <> ''
         WHERE f.follower_id = %d
         ORDER BY f.created_at DESC
         LIMIT %d",
        $user_id,
        $limit
    ));

    return array_values(array_map('intval', is_array($rows) ? $rows : []));
}
