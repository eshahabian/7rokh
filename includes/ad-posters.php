<?php
declare(strict_types=1);

/**
 * پوستر تبلیغات: اعتبار پس از پرداخت، ارسال کاربر، تأیید ادمین، نمایش در بنر صفحه اصلی.
 */

require_once __DIR__ . '/profile.php';

function casting_ad_posters_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_ad_posters';
}

function casting_ad_credits_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_ad_credits';
}

function casting_ad_posters_install(): void
{
    global $wpdb;
    $posters = casting_ad_posters_table();
    $credits = casting_ad_credits_table();
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE {$posters} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        credit_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        order_code VARCHAR(40) NOT NULL DEFAULT '',
        ad_type VARCHAR(40) NOT NULL DEFAULT '',
        title VARCHAR(191) NOT NULL DEFAULT '',
        attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        width INT NOT NULL DEFAULT 0,
        height INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        reject_reason TEXT NULL,
        reviewed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        reviewed_at DATETIME NULL,
        display_from DATETIME NULL,
        display_until DATETIME NULL,
        archived_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY status (status),
        KEY credit_id (credit_id),
        KEY user_status (user_id, status),
        KEY display_until (display_until)
    ) {$charset};");

    dbDelta("CREATE TABLE {$credits} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        order_code VARCHAR(40) NOT NULL DEFAULT '',
        slot_key VARCHAR(80) NOT NULL DEFAULT '',
        ad_type VARCHAR(40) NOT NULL DEFAULT '',
        status VARCHAR(16) NOT NULL DEFAULT 'open',
        poster_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY slot_key (slot_key),
        KEY user_id (user_id),
        KEY user_status (user_id, status),
        KEY order_code (order_code)
    ) {$charset};");

    update_option('casting_ad_posters_db_version', '2');
}

function casting_ad_posters_migrate_v2(): void
{
    global $wpdb;
    $table = casting_ad_posters_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query(
        "UPDATE {$table}
         SET display_from = COALESCE(NULLIF(display_from, '0000-00-00 00:00:00'), reviewed_at, created_at),
             display_until = DATE_ADD(COALESCE(NULLIF(reviewed_at, '0000-00-00 00:00:00'), created_at), INTERVAL 30 DAY)
         WHERE status = 'approved'
           AND (display_until IS NULL OR display_until = '0000-00-00 00:00:00')"
    );
}

function casting_ad_posters_archive_expired(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    global $wpdb;
    $table = casting_ad_posters_table();
    $now = current_time('mysql');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table}
         SET status = 'archived', archived_at = %s, updated_at = %s
         WHERE status = 'approved'
           AND display_until IS NOT NULL
           AND display_until < %s",
        $now,
        $now,
        $now
    ));
}

function casting_ad_posters_ensure_table(): void
{
    $ver = (string) get_option('casting_ad_posters_db_version', '');
    if ($ver !== '2') {
        casting_ad_posters_install();
        casting_ad_posters_migrate_v2();
    }
    casting_ad_posters_archive_expired();
}

/**
 * مشخصات فایل پوستر برای بنر صفحه اصلی (۱۶:۶.۷۵ با object-fit: cover).
 *
 * @return array{min_width:int,min_height:int,recommended_width:int,recommended_height:int,formats:list<string>,max_bytes:int}
 */
function casting_ad_poster_spec(): array
{
    return [
        'min_width'          => 1280,
        'min_height'         => 540,
        'recommended_width'  => 1920,
        'recommended_height' => 810,
        'formats'            => ['image/jpeg', 'image/png', 'image/webp'],
        'max_bytes'          => function_exists('casting_upload_max_bytes') ? casting_upload_max_bytes('image') : (5 * 1024 * 1024),
    ];
}

/**
 * @return array<string, string>
 */
function casting_ad_type_labels(): array
{
    return [
        'banner_theater'     => 'بنر پوستر تئاتر',
        'banner_film'        => 'بنر پوستر فیلم',
        'banner_documentary' => 'بنر پوستر فیلم مستند',
    ];
}

function casting_ad_type_label(string $ad_type): string
{
    $map = casting_ad_type_labels();

    return $map[$ad_type] ?? $ad_type;
}

function casting_ad_poster_status_label(string $status, array $poster = []): string
{
    if ($status === 'approved') {
        $period = casting_ad_poster_display_period_label($poster);
        return $period !== '' ? 'در حال نمایش · ' . $period : 'تأیید شده — در تبلیغات نمایش داده می‌شود';
    }
    if ($status === 'archived') {
        $period = casting_ad_poster_display_period_label($poster);
        return $period !== '' ? 'آرشیو · پایان ' . $period : 'آرشیو — پایان نمایش';
    }
    if ($status === 'rejected') {
        return 'رد شده';
    }
    if ($poster !== [] && casting_ad_poster_can_undo($poster)) {
        return 'ارسال شده — فرصت اصلاح';
    }

    return 'در انتظار تأیید';
}

function casting_ad_poster_undo_seconds(): int
{
    return 5 * 60;
}

function casting_ad_poster_created_ts(array $poster): int
{
    $mysql = trim((string) ($poster['created_at'] ?? ''));
    if ($mysql === '') {
        return 0;
    }
    try {
        $dt = new DateTimeImmutable($mysql, wp_timezone());
    } catch (Exception $e) {
        return 0;
    }

    return $dt->getTimestamp();
}

function casting_ad_poster_undo_remaining(array $poster): int
{
    if ((string) ($poster['status'] ?? '') !== 'pending') {
        return 0;
    }
    $ts = casting_ad_poster_created_ts($poster);
    if ($ts <= 0) {
        return 0;
    }
    $left = ($ts + casting_ad_poster_undo_seconds()) - time();

    return max(0, $left);
}

function casting_ad_poster_can_undo(array $poster): bool
{
    return (string) ($poster['status'] ?? '') === 'pending' && casting_ad_poster_undo_remaining($poster) > 0;
}

function casting_ad_poster_undo_cutoff_mysql(): string
{
    return wp_date('Y-m-d H:i:s', time() - casting_ad_poster_undo_seconds());
}

function casting_ad_poster_ymd(string $mysql): string
{
    $mysql = trim($mysql);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $mysql, $m)) {
        return $m[1];
    }

    return '';
}

function casting_ad_poster_display_period_label(array $poster): string
{
    $from = casting_ad_poster_ymd((string) ($poster['display_from'] ?? ''));
    $until = casting_ad_poster_ymd((string) ($poster['display_until'] ?? ''));
    if ($from === '' && $until === '') {
        return '';
    }
    $from_j = $from !== '' ? casting_format_jalali_from_gregorian($from) : '';
    $until_j = $until !== '' ? casting_format_jalali_from_gregorian($until) : '';
    if ($from_j !== '' && $until_j !== '') {
        return $from_j . ' تا ' . $until_j;
    }

    return $until_j !== '' ? 'تا ' . $until_j : 'از ' . $from_j;
}

function casting_ad_poster_default_display_from(): string
{
    return wp_date('Y-m-d');
}

function casting_ad_poster_default_display_until(): string
{
    return wp_date('Y-m-d', time() + (30 * DAY_IN_SECONDS));
}

/**
 * @return array{ok:bool,error:string,from:string,until:string}
 */
function casting_ad_poster_parse_display_range(array $post): array
{
    $from = function_exists('casting_ymd_from_jalali_prefixed_post')
        ? casting_ymd_from_jalali_prefixed_post($post, 'display_from')
        : null;
    $until = function_exists('casting_ymd_from_jalali_prefixed_post')
        ? casting_ymd_from_jalali_prefixed_post($post, 'display_until')
        : null;
    if ($from === null || $until === null) {
        return ['ok' => false, 'error' => 'تاریخ شروع و پایان نمایش را از تقویم انتخاب کنید.', 'from' => '', 'until' => ''];
    }
    if ($until < $from) {
        return ['ok' => false, 'error' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.', 'from' => '', 'until' => ''];
    }
    $today = wp_date('Y-m-d');
    if ($until < $today) {
        return ['ok' => false, 'error' => 'تاریخ پایان نمی‌تواند در گذشته باشد.', 'from' => '', 'until' => ''];
    }

    return [
        'ok'    => true,
        'error' => '',
        'from'  => $from . ' 00:00:00',
        'until' => $until . ' 23:59:59',
    ];
}

function casting_render_ad_publish_calendar(int $uniq, string $from_ymd = '', string $until_ymd = ''): void
{
    $from_ymd = $from_ymd !== '' ? $from_ymd : casting_ad_poster_default_display_from();
    $until_ymd = $until_ymd !== '' ? $until_ymd : casting_ad_poster_default_display_until();
    $suffix = (string) $uniq;
    ?>
    <div class="ad-publish-dates">
      <p class="ad-publish-dates-title">مدت نمایش در بنر صفحه اصلی</p>
      <?php
      casting_render_jalali_date_fields('display_from', 'از تاریخ', $from_ymd, true, $suffix);
      casting_render_jalali_date_fields('display_until', 'تا تاریخ', $until_ymd, true, $suffix);
      ?>
    </div>
    <?php
}

function casting_user_can_moderate_ad_posters(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (!function_exists('casting_user_is_super_admin')) {
        require_once __DIR__ . '/admin-access.php';
    }

    return casting_user_is_super_admin($user_id);
}

function casting_user_ads_unlocked_meta(int $user_id): string
{
    return (string) get_user_meta($user_id, 'casting_ads_unlocked', true);
}

function casting_user_set_ads_unlocked(int $user_id, bool $unlocked): void
{
    if ($user_id <= 0) {
        return;
    }
    update_user_meta($user_id, 'casting_ads_unlocked', $unlocked ? '1' : '0');
}

/**
 * پس از پرداخت تبلیغات — یک سهمیه ارسال پوستر.
 */
function casting_ad_credit_grant(int $user_id, string $order_code, string $ad_type, string $slot_key = ''): bool
{
    if ($user_id <= 0) {
        return false;
    }
    $ad_type = sanitize_key($ad_type);
    $order_code = sanitize_text_field($order_code);
    $slot_key = sanitize_text_field($slot_key);
    if ($ad_type === '' || $order_code === '') {
        return false;
    }
    if ($slot_key === '') {
        $slot_key = $order_code . ':' . $ad_type;
    }
    $labels = casting_ad_type_labels();
    if (!isset($labels[$ad_type])) {
        return false;
    }

    casting_ad_posters_ensure_table();
    global $wpdb;
    $table = casting_ad_credits_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE slot_key = %s LIMIT 1",
        $slot_key
    ));
    if ($exists > 0) {
        casting_user_set_ads_unlocked($user_id, true);

        return true;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->insert(
        $table,
        [
            'user_id'    => $user_id,
            'order_code' => $order_code,
            'slot_key'   => $slot_key,
            'ad_type'    => $ad_type,
            'status'     => 'open',
            'poster_id'  => 0,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
    );
    if ($ok) {
        casting_user_set_ads_unlocked($user_id, true);
    }

    return (bool) $ok;
}

/**
 * اعتبارهای تبلیغات داخل یک سفارش پرداخت‌شده.
 *
 * @param array<string, mixed> $order
 */
function casting_ad_credits_grant_from_order(array $order): void
{
    $user_id = (int) ($order['user_id'] ?? 0);
    $order_code = (string) ($order['order_code'] ?? '');
    $service = (string) ($order['service_key'] ?? '');
    if ($user_id <= 0 || $order_code === '') {
        return;
    }
    $meta = is_array($order['meta'] ?? null) ? $order['meta'] : [];
    $cart_items = is_array($meta['cart_items'] ?? null) ? $meta['cart_items'] : [];

    if ($service === 'advertising') {
        $ad_type = sanitize_key((string) (($meta['ad_type'] ?? '') ?: ($order['plan_key'] ?? '')));
        casting_ad_credit_grant($user_id, $order_code, $ad_type, $order_code);
        return;
    }

    if ($service === 'cart' && $cart_items !== []) {
        foreach ($cart_items as $i => $it) {
            if (!is_array($it) || (string) ($it['service_key'] ?? '') !== 'advertising') {
                continue;
            }
            $it_meta = is_array($it['meta'] ?? null) ? $it['meta'] : [];
            $ad_type = sanitize_key((string) (($it_meta['ad_type'] ?? '') ?: ($it['plan_key'] ?? '')));
            casting_ad_credit_grant($user_id, $order_code, $ad_type, $order_code . ':' . (int) $i);
        }
    }
}

/**
 * همگام‌سازی سهمیه از سفارش‌های پرداخت‌شده قبلی.
 */
function casting_ad_credits_sync_from_orders(int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    if (!function_exists('casting_orders_ensure_table')) {
        require_once __DIR__ . '/checkout.php';
    }
    casting_orders_ensure_table();
    casting_ad_posters_ensure_table();
    global $wpdb;
    $orders = casting_orders_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$orders} WHERE user_id = %d AND status = 'paid' AND (service_key = %s OR service_key = %s)",
        $user_id,
        'advertising',
        'cart'
    ), ARRAY_A);
    if (!is_array($rows)) {
        $rows = [];
    }
    foreach ($rows as $row) {
        $order = function_exists('casting_order_from_row') ? casting_order_from_row($row) : $row;
        if (is_array($order) && $order !== []) {
            casting_ad_credits_grant_from_order($order);
        }
    }

    if (casting_user_ads_unlocked_meta($user_id) === '') {
        $open = casting_user_ad_open_credits($user_id, false);
        $has_posters = casting_user_ad_poster_count($user_id) > 0;
        casting_user_set_ads_unlocked($user_id, $open !== [] || $has_posters);
    }
}

function casting_user_can_open_ad_posters(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id)) {
        return true;
    }
    if (casting_user_can_moderate_ad_posters($user_id)) {
        return true;
    }
    $flag = casting_user_ads_unlocked_meta($user_id);
    if ($flag === '1') {
        return true;
    }
    if ($flag === '0') {
        return false;
    }
    casting_ad_credits_sync_from_orders($user_id);

    return casting_user_ads_unlocked_meta($user_id) === '1';
}

function casting_ad_credit_is_tmp_test(array $credit): bool
{
    $order = (string) ($credit['order_code'] ?? '');
    $slot = (string) ($credit['slot_key'] ?? '');

    return $order === 'TMP-TEST' || str_starts_with($slot, 'tmp-test-');
}

function casting_user_has_active_ad_poster(int $user_id): bool
{
    foreach (casting_user_ad_posters_list($user_id, 50) as $row) {
        $status = (string) ($row['status'] ?? '');
        if ($status === 'pending' || $status === 'approved') {
            return true;
        }
    }

    return false;
}

function casting_ad_tmp_test_close_open_credits(int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    foreach (casting_user_ad_open_credits($user_id, false) as $credit) {
        if (!is_array($credit) || !casting_ad_credit_is_tmp_test($credit)) {
            continue;
        }
        $id = (int) ($credit['id'] ?? 0);
        if ($id > 0) {
            casting_ad_credit_mark_used($id, (int) ($credit['poster_id'] ?? 0));
        }
    }
}

function casting_user_can_submit_ad_poster(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id)) {
        return true;
    }
    foreach (casting_user_ad_posters_list($user_id, 50) as $row) {
        if ((string) ($row['status'] ?? '') === 'pending') {
            return false;
        }
    }
    foreach (casting_user_ad_open_credits($user_id, false) as $credit) {
        if ((int) ($credit['id'] ?? 0) > 0) {
            return true;
        }
    }

    return false;
}

/**
 * @param array{id:string,name?:string,disabled?:bool,required?:bool,accept?:string,max_bytes?:int} $opts
 */
function casting_render_file_pick(array $opts): void
{
    $id = (string) ($opts['id'] ?? 'poster_file');
    $name = (string) ($opts['name'] ?? 'poster_file');
    $disabled = !empty($opts['disabled']);
    $required = !empty($opts['required']) && !$disabled;
    $accept = (string) ($opts['accept'] ?? 'image/jpeg,image/png,image/webp');
    $max_bytes = (int) ($opts['max_bytes'] ?? 0);
    ?>
    <div class="file-pick<?= $disabled ? ' is-disabled' : '' ?>">
      <input
        id="<?= casting_e($id) ?>"
        class="file-pick-input"
        name="<?= casting_e($name) ?>"
        type="file"
        accept="<?= casting_e($accept) ?>"
        data-file-pick
        <?php if ($max_bytes > 0) : ?>data-upload-kind="image" data-max-bytes="<?= $max_bytes ?>"<?php endif; ?>
        <?= $required ? 'required' : '' ?>
        <?= $disabled ? 'disabled' : '' ?>
      >
      <label class="btn btn-ghost file-pick-btn<?= $disabled ? ' is-disabled' : '' ?>" for="<?= casting_e($id) ?>">انتخاب فایل</label>
      <span class="file-pick-name" data-file-pick-name>فایلی انتخاب نشده</span>
    </div>
    <?php
}

/**
 * @return list<array<string, mixed>>
 */
function casting_user_ad_open_credits(int $user_id, bool $include_owner_virtual = true): array
{
    $out = [];
    if ($include_owner_virtual && function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id)) {
        $out[] = [
            'id'         => 0,
            'user_id'    => $user_id,
            'order_code' => 'owner',
            'slot_key'   => 'owner',
            'ad_type'    => '',
            'status'     => 'open',
            'virtual'    => true,
        ];
    }
    if ($user_id <= 0) {
        return $out;
    }
    casting_ad_posters_ensure_table();
    global $wpdb;
    $table = casting_ad_credits_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d AND status = 'open' ORDER BY id ASC",
        $user_id
    ), ARRAY_A);
    if (!is_array($rows)) {
        return $out;
    }
    foreach ($rows as $row) {
        $out[] = $row;
    }

    return $out;
}

function casting_user_ad_poster_count(int $user_id): int
{
    if ($user_id <= 0) {
        return 0;
    }
    casting_ad_posters_ensure_table();
    global $wpdb;
    $table = casting_ad_posters_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
        $user_id
    ));
}

/**
 * @return array<string, mixed>|null
 */
function casting_ad_credit_get(int $credit_id, int $user_id): ?array
{
    if ($credit_id <= 0 || $user_id <= 0) {
        return null;
    }
    casting_ad_posters_ensure_table();
    global $wpdb;
    $table = casting_ad_credits_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND user_id = %d LIMIT 1",
        $credit_id,
        $user_id
    ), ARRAY_A);

    return is_array($row) ? $row : null;
}

function casting_ad_credit_mark_used(int $credit_id, int $poster_id): void
{
    if ($credit_id <= 0) {
        return;
    }
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->update(
        casting_ad_credits_table(),
        [
            'status'    => 'used',
            'poster_id' => $poster_id,
            'used_at'   => current_time('mysql'),
        ],
        ['id' => $credit_id],
        ['%s', '%d', '%s'],
        ['%d']
    );
}

function casting_ad_credit_release(int $credit_id): void
{
    if ($credit_id <= 0) {
        return;
    }
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->update(
        casting_ad_credits_table(),
        [
            'status'    => 'open',
            'poster_id' => 0,
            'used_at'   => null,
        ],
        ['id' => $credit_id],
        ['%s', '%d', '%s'],
        ['%d']
    );
}

/**
 * @return array<string, mixed>|null
 */
function casting_ad_poster_get(int $poster_id): ?array
{
    if ($poster_id <= 0) {
        return null;
    }
    casting_ad_posters_ensure_table();
    global $wpdb;
    $table = casting_ad_posters_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
        $poster_id
    ), ARRAY_A);

    return is_array($row) ? $row : null;
}

function casting_user_can_view_ad_poster(int $viewer_id, array $poster): bool
{
    if ($viewer_id <= 0) {
        return false;
    }
    if ((int) ($poster['user_id'] ?? 0) === $viewer_id) {
        return true;
    }

    return casting_user_can_moderate_ad_posters($viewer_id);
}

/**
 * @return list<array<string, mixed>>
 */
function casting_user_ad_posters_list(int $user_id, int $limit = 50): array
{
    if ($user_id <= 0) {
        return [];
    }
    casting_ad_posters_ensure_table();
    global $wpdb;
    $table = casting_ad_posters_table();
    $limit = max(1, min(200, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d",
        $user_id,
        $limit
    ), ARRAY_A);

    return is_array($rows) ? $rows : [];
}

/**
 * @return list<array<string, mixed>>
 */
function casting_admin_ad_posters_list(string $status, int $limit = 100): array
{
    casting_ad_posters_ensure_table();
    global $wpdb;
    $table = casting_ad_posters_table();
    $limit = max(1, min(200, $limit));
    $status = sanitize_key($status);
    $cutoff = casting_ad_poster_undo_cutoff_mysql();
    if ($status === 'all' || $status === '') {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE NOT (status = 'pending' AND created_at > %s)
             ORDER BY FIELD(status,'pending','approved','archived','rejected'), id DESC
             LIMIT %d",
            $cutoff,
            $limit
        ), ARRAY_A);
    } elseif ($status === 'pending') {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'pending' AND created_at <= %s
             ORDER BY id DESC
             LIMIT %d",
            $cutoff,
            $limit
        ), ARRAY_A);
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d",
            $status,
            $limit
        ), ARRAY_A);
    }

    return is_array($rows) ? $rows : [];
}

function casting_admin_pending_ad_posters_count(): int
{
    casting_ad_posters_ensure_table();
    global $wpdb;
    $table = casting_ad_posters_table();
    $cutoff = casting_ad_poster_undo_cutoff_mysql();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE status = 'pending' AND created_at <= %s",
        $cutoff
    ));
}

function casting_admin_archived_ad_posters_count(): int
{
    casting_ad_posters_ensure_table();
    global $wpdb;
    $table = casting_ad_posters_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'archived'");
}

function casting_ad_poster_url(array $poster): string
{
    $id = (int) ($poster['attachment_id'] ?? 0);
    if ($id <= 0) {
        return '';
    }
    $url = wp_get_attachment_image_url($id, 'full');
    if (is_string($url) && $url !== '') {
        return $url;
    }
    $fallback = wp_get_attachment_url($id);

    return is_string($fallback) ? $fallback : '';
}

function casting_render_ad_poster_zoom(string $url, string $alt = ''): void
{
    if ($url === '') {
        return;
    }
    ?>
    <div class="ad-poster-zoom-wrap">
      <img src="<?= casting_e($url) ?>" alt="<?= casting_e($alt) ?>" loading="lazy">
      <button type="button" class="ad-poster-zoom" data-image-zoom="<?= casting_e($url) ?>" aria-label="بزرگ‌نمایی پوستر"></button>
    </div>
    <?php
}

/**
 * اسلایدهای تأییدشده برای بنر تبلیغات صفحه اصلی.
 *
 * @return list<array{src:string,alt:string}>
 */
function casting_approved_ad_promo_slides(int $limit = 20): array
{
    casting_ad_posters_ensure_table();
    global $wpdb;
    $table = casting_ad_posters_table();
    $limit = max(1, min(40, $limit));
    $now = current_time('mysql');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE status = 'approved'
           AND attachment_id > 0
           AND (display_from IS NULL OR display_from <= %s)
           AND (display_until IS NULL OR display_until >= %s)
         ORDER BY reviewed_at DESC, id DESC
         LIMIT %d",
        $now,
        $now,
        $limit
    ), ARRAY_A);
    if (!is_array($rows) || $rows === []) {
        return [];
    }
    $slides = [];
    foreach ($rows as $row) {
        if ((string) ($row['order_code'] ?? '') === 'TMP-TEST') {
            continue;
        }
        $owner = get_user_by('id', (int) ($row['user_id'] ?? 0));
        if ($owner && strtolower((string) $owner->user_login) === 'shaverdi') {
            continue;
        }
        $src = casting_ad_poster_url($row);
        if ($src === '') {
            continue;
        }
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            $title = casting_ad_type_label((string) ($row['ad_type'] ?? ''));
        }
        $slides[] = [
            'src' => $src,
            'alt' => $title !== '' ? $title : 'پوستر تبلیغاتی',
        ];
    }

    return $slides;
}

/**
 * @param list<array{src:string,alt:string}> $fallback
 */
function casting_render_promo_banner(array $fallback, string $extra_class = '', string $heading = 'اینجا برای تبلیغات شماست'): void
{
    $slides = casting_approved_ad_promo_slides();
    if ($slides === []) {
        $slides = $fallback;
    }
    if ($slides === []) {
        return;
    }
    $class = trim('panel-promo-banner ' . $extra_class);
    ?>
  <section class="<?= casting_e($class) ?>" aria-label="<?= casting_e($heading) ?>" data-promo-slider>
    <div class="panel-promo-slides">
      <?php foreach ($slides as $i => $slide) : ?>
        <figure class="panel-promo-slide<?= $i === 0 ? ' is-active' : '' ?>">
          <img src="<?= casting_e((string) $slide['src']) ?>" alt="<?= casting_e((string) $slide['alt']) ?>" width="1920" height="810" decoding="<?= $i === 0 ? 'sync' : 'async' ?>">
        </figure>
      <?php endforeach; ?>
    </div>
    <div class="panel-promo-banner-copy">
      <h1><?= casting_e($heading) ?></h1>
    </div>
    <div class="panel-promo-dots" data-promo-dots role="tablist" aria-label="اسلایدهای تبلیغات">
      <?php foreach ($slides as $i => $slide) : ?>
        <button
          type="button"
          class="<?= $i === 0 ? 'is-active' : '' ?>"
          aria-label="اسلاید <?= (int) ($i + 1) ?>"
          aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
          data-promo-dot="<?= (int) $i ?>"
        ></button>
      <?php endforeach; ?>
    </div>
  </section>
    <?php
}

/**
 * @return array{ok:bool,error:string,poster_id?:int}
 */
function casting_ad_poster_submit(int $user_id, int $credit_id, string $field, string $title, string $owner_ad_type = ''): array
{
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'کاربر نامعتبر است.'];
    }
    if (!casting_user_can_submit_ad_poster($user_id)) {
        return ['ok' => false, 'error' => 'برای ارسال پوستر ابتدا هزینه تبلیغات را پرداخت کنید.'];
    }
    $title = sanitize_text_field($title);
    if (function_exists('mb_substr')) {
        $title = (string) mb_substr($title, 0, 120);
    } else {
        $title = substr($title, 0, 120);
    }

    $is_owner = function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id);
    $credit = null;
    $ad_type = '';
    $order_code = '';
    if ($credit_id > 0) {
        $credit = casting_ad_credit_get($credit_id, $user_id);
        if ($credit === null || (string) ($credit['status'] ?? '') !== 'open') {
            return ['ok' => false, 'error' => 'سهمیه تبلیغات معتبر نیست یا قبلاً استفاده شده.'];
        }
        $ad_type = sanitize_key((string) ($credit['ad_type'] ?? ''));
        $order_code = (string) ($credit['order_code'] ?? '');
    } elseif ($is_owner) {
        $ad_type = sanitize_key($owner_ad_type);
        $order_code = 'owner';
        if ($ad_type === '' || !isset(casting_ad_type_labels()[$ad_type])) {
            return ['ok' => false, 'error' => 'نوع بنر را انتخاب کنید.'];
        }
    } else {
        return ['ok' => false, 'error' => 'سهمیه تبلیغات پیدا نشد.'];
    }

    if (empty($_FILES[$field]['name'])) {
        return ['ok' => false, 'error' => 'فایل پوستر را انتخاب کنید.'];
    }

    $file = &$_FILES[$field];
    $norm = casting_normalize_uploaded_file_type($file, 'image');
    if (!$norm['ok']) {
        return ['ok' => false, 'error' => $norm['error']];
    }
    $ftype = (string) ($norm['type'] ?? '');
    $spec = casting_ad_poster_spec();
    if (!in_array($ftype, $spec['formats'], true)) {
        return ['ok' => false, 'error' => 'فقط تصویر JPG، PNG یا WebP مجاز است.'];
    }
    $size_check = casting_uploaded_file_within_limit($file, 'image');
    if (!$size_check['ok']) {
        return ['ok' => false, 'error' => $size_check['error']];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'آپلود نامعتبر است.'];
    }
    $info = @getimagesize($tmp);
    if (!is_array($info) || (int) ($info[0] ?? 0) <= 0) {
        return ['ok' => false, 'error' => 'ابعاد تصویر خوانده نشد. فایل معتبری انتخاب کنید.'];
    }
    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width < $spec['min_width'] || $height < $spec['min_height']) {
        return [
            'ok'    => false,
            'error' => 'حداقل ابعاد پوستر ' . $spec['min_width'] . '×' . $spec['min_height'] . ' پیکسل است. تصویر شما ' . $width . '×' . $height . ' است.',
        ];
    }
    if ($height > $width) {
        return ['ok' => false, 'error' => 'پوستر باید افقی (landscape) باشد.'];
    }

    $attachment_id = casting_media_handle_upload_as_user($field, $user_id);
    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'آپلود ناموفق بود: ' . $attachment_id->get_error_message()];
    }

    casting_ad_posters_ensure_table();
    global $wpdb;
    $now = current_time('mysql');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->insert(
        casting_ad_posters_table(),
        [
            'user_id'       => $user_id,
            'credit_id'     => $credit_id,
            'order_code'    => $order_code,
            'ad_type'       => $ad_type,
            'title'         => $title,
            'attachment_id' => (int) $attachment_id,
            'width'         => $width,
            'height'        => $height,
            'status'        => 'pending',
            'created_at'    => $now,
            'updated_at'    => $now,
        ],
        ['%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s']
    );
    if (!$ok) {
        wp_delete_attachment((int) $attachment_id, true);

        return ['ok' => false, 'error' => 'ثبت پوستر ناموفق بود.'];
    }
    $poster_id = (int) $wpdb->insert_id;
    if ($credit_id > 0) {
        casting_ad_credit_mark_used($credit_id, $poster_id);
    }

    return ['ok' => true, 'error' => '', 'poster_id' => $poster_id];
}

/**
 * ارسال مجدد پوستر ردشده یا ویرایش در مهلت ۵ دقیقه‌ای — همان سهمیه.
 *
 * @return array{ok:bool,error:string}
 */
function casting_ad_poster_resubmit(int $user_id, int $poster_id, string $field, string $title): array
{
    $poster = casting_ad_poster_get($poster_id);
    if ($poster === null || (int) ($poster['user_id'] ?? 0) !== $user_id) {
        return ['ok' => false, 'error' => 'پوستر پیدا نشد.'];
    }
    $status = (string) ($poster['status'] ?? '');
    $can_undo = $status === 'pending' && casting_ad_poster_can_undo($poster);
    if ($status !== 'rejected' && !$can_undo) {
        return ['ok' => false, 'error' => 'مهلت اصلاح این پوستر تمام شده است.'];
    }
    if (empty($_FILES[$field]['name'])) {
        return ['ok' => false, 'error' => 'فایل جدید پوستر را انتخاب کنید.'];
    }

    $file = &$_FILES[$field];
    $norm = casting_normalize_uploaded_file_type($file, 'image');
    if (!$norm['ok']) {
        return ['ok' => false, 'error' => $norm['error']];
    }
    $spec = casting_ad_poster_spec();
    if (!in_array((string) ($norm['type'] ?? ''), $spec['formats'], true)) {
        return ['ok' => false, 'error' => 'فقط تصویر JPG، PNG یا WebP مجاز است.'];
    }
    $size_check = casting_uploaded_file_within_limit($file, 'image');
    if (!$size_check['ok']) {
        return ['ok' => false, 'error' => $size_check['error']];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    $info = ($tmp !== '' && is_uploaded_file($tmp)) ? @getimagesize($tmp) : false;
    if (!is_array($info) || (int) ($info[0] ?? 0) <= 0) {
        return ['ok' => false, 'error' => 'ابعاد تصویر خوانده نشد.'];
    }
    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width < $spec['min_width'] || $height < $spec['min_height']) {
        return [
            'ok'    => false,
            'error' => 'حداقل ابعاد پوستر ' . $spec['min_width'] . '×' . $spec['min_height'] . ' پیکسل است.',
        ];
    }
    if ($height > $width) {
        return ['ok' => false, 'error' => 'پوستر باید افقی (landscape) باشد.'];
    }

    $attachment_id = casting_media_handle_upload_as_user($field, $user_id);
    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'آپلود ناموفق بود: ' . $attachment_id->get_error_message()];
    }

    $old_aid = (int) ($poster['attachment_id'] ?? 0);
    $title = sanitize_text_field($title);
    if ($title === '') {
        $title = (string) ($poster['title'] ?? '');
    }
    global $wpdb;
    $now = current_time('mysql');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->update(
        casting_ad_posters_table(),
        [
            'title'         => $title,
            'attachment_id' => (int) $attachment_id,
            'width'         => $width,
            'height'        => $height,
            'status'        => 'pending',
            'reject_reason' => '',
            'reviewed_by'   => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ],
        ['id' => $poster_id],
        ['%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s'],
        ['%d']
    );
    if ($ok === false) {
        wp_delete_attachment((int) $attachment_id, true);

        return ['ok' => false, 'error' => 'به‌روزرسانی پوستر ناموفق بود.'];
    }
    if ($old_aid > 0 && $old_aid !== (int) $attachment_id) {
        wp_delete_attachment($old_aid, true);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * انصراف در مهلت ۵ دقیقه‌ای: پوستر از صف ادمین حذف می‌شود و سهمیه برمی‌گردد.
 *
 * @return array{ok:bool,error:string}
 */
function casting_ad_poster_undo_delete(int $user_id, int $poster_id): array
{
    $poster = casting_ad_poster_get($poster_id);
    if ($poster === null || (int) ($poster['user_id'] ?? 0) !== $user_id) {
        return ['ok' => false, 'error' => 'پوستر پیدا نشد.'];
    }
    if (!casting_ad_poster_can_undo($poster)) {
        return ['ok' => false, 'error' => 'مهلت حذف و اصلاح تمام شده است.'];
    }
    $credit_id = (int) ($poster['credit_id'] ?? 0);
    $attachment_id = (int) ($poster['attachment_id'] ?? 0);
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->delete(casting_ad_posters_table(), ['id' => $poster_id], ['%d']);
    if ($ok === false) {
        return ['ok' => false, 'error' => 'حذف پوستر ناموفق بود.'];
    }
    if ($credit_id > 0) {
        casting_ad_credit_release($credit_id);
    }
    if ($attachment_id > 0) {
        wp_delete_attachment($attachment_id, true);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_ad_poster_approve(int $poster_id, int $admin_id, string $display_from = '', string $display_until = ''): array
{
    if (!casting_user_can_moderate_ad_posters($admin_id)) {
        return ['ok' => false, 'error' => 'اجازه تأیید ندارید.'];
    }
    $poster = casting_ad_poster_get($poster_id);
    if ($poster === null) {
        return ['ok' => false, 'error' => 'پوستر پیدا نشد.'];
    }
    if (casting_ad_poster_can_undo($poster)) {
        return ['ok' => false, 'error' => 'کاربر هنوز در مهلت اصلاح است. چند دقیقه بعد دوباره تلاش کنید.'];
    }
    if ((string) ($poster['status'] ?? '') === 'approved') {
        $owner_id = (int) ($poster['user_id'] ?? 0);
        if ($owner_id > 0) {
            casting_ad_tmp_test_close_open_credits($owner_id);
        }
        return ['ok' => true, 'error' => ''];
    }
    if ($display_from === '' || $display_until === '') {
        return ['ok' => false, 'error' => 'تاریخ شروع و پایان نمایش را انتخاب کنید.'];
    }
    global $wpdb;
    $now = current_time('mysql');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->update(
        casting_ad_posters_table(),
        [
            'status'        => 'approved',
            'reject_reason' => '',
            'reviewed_by'   => $admin_id,
            'reviewed_at'   => $now,
            'display_from'  => $display_from,
            'display_until' => $display_until,
            'archived_at'   => null,
            'updated_at'    => $now,
        ],
        ['id' => $poster_id],
        ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($ok === false) {
        return ['ok' => false, 'error' => 'تأیید ناموفق بود.'];
    }
    $owner_id = (int) ($poster['user_id'] ?? 0);
    if ($owner_id > 0) {
        casting_ad_tmp_test_close_open_credits($owner_id);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * باز نشر پوستر آرشیو شده با تاریخ نمایش جدید.
 *
 * @return array{ok:bool,error:string}
 */
function casting_ad_poster_republish(int $poster_id, int $admin_id, string $display_from, string $display_until): array
{
    if (!casting_user_can_moderate_ad_posters($admin_id)) {
        return ['ok' => false, 'error' => 'اجازه انتشار ندارید.'];
    }
    $poster = casting_ad_poster_get($poster_id);
    if ($poster === null) {
        return ['ok' => false, 'error' => 'پوستر پیدا نشد.'];
    }
    $status = (string) ($poster['status'] ?? '');
    if ($status !== 'archived' && $status !== 'approved') {
        return ['ok' => false, 'error' => 'فقط پوستر آرشیو یا منتشرشده را می‌توان دوباره زمان‌بندی کرد.'];
    }
    if ($display_from === '' || $display_until === '') {
        return ['ok' => false, 'error' => 'تاریخ شروع و پایان نمایش را انتخاب کنید.'];
    }
    global $wpdb;
    $now = current_time('mysql');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->update(
        casting_ad_posters_table(),
        [
            'status'        => 'approved',
            'reviewed_by'   => $admin_id,
            'reviewed_at'   => $now,
            'display_from'  => $display_from,
            'display_until' => $display_until,
            'archived_at'   => null,
            'updated_at'    => $now,
        ],
        ['id' => $poster_id],
        ['%s', '%d', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    return $ok === false ? ['ok' => false, 'error' => 'انتشار مجدد ناموفق بود.'] : ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_ad_poster_reject(int $poster_id, int $admin_id, string $reason): array
{
    if (!casting_user_can_moderate_ad_posters($admin_id)) {
        return ['ok' => false, 'error' => 'اجازه رد ندارید.'];
    }
    $poster = casting_ad_poster_get($poster_id);
    if ($poster === null) {
        return ['ok' => false, 'error' => 'پوستر پیدا نشد.'];
    }
    if (casting_ad_poster_can_undo($poster)) {
        return ['ok' => false, 'error' => 'کاربر هنوز در مهلت اصلاح است. چند دقیقه بعد دوباره تلاش کنید.'];
    }
    $reason = sanitize_text_field($reason);
    if (function_exists('mb_substr')) {
        $reason = (string) mb_substr($reason, 0, 300);
    }
    global $wpdb;
    $now = current_time('mysql');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $ok = $wpdb->update(
        casting_ad_posters_table(),
        [
            'status'        => 'rejected',
            'reject_reason' => $reason,
            'reviewed_by'   => $admin_id,
            'reviewed_at'   => $now,
            'updated_at'    => $now,
        ],
        ['id' => $poster_id],
        ['%s', '%s', '%d', '%s', '%s'],
        ['%d']
    );

    return $ok === false ? ['ok' => false, 'error' => 'رد ناموفق بود.'] : ['ok' => true, 'error' => ''];
}

function casting_order_includes_advertising(array $order): bool
{
    if ((string) ($order['service_key'] ?? '') === 'advertising') {
        return true;
    }
    $meta = is_array($order['meta'] ?? null) ? $order['meta'] : [];
    $cart_items = is_array($meta['cart_items'] ?? null) ? $meta['cart_items'] : [];
    foreach ($cart_items as $it) {
        if (is_array($it) && (string) ($it['service_key'] ?? '') === 'advertising') {
            return true;
        }
    }

    return false;
}
