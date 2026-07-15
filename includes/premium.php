<?php
declare(strict_types=1);

function casting_premium_plans(): array
{
    return [
        'featured_30' => [
            'label'       => 'آگهی ویژه ۳۰ روزه',
            'days'        => 30,
            'price'       => 500000,
            'description' => 'نمایش در اولین نتایج جستجو و برچسب ویژه روی پروفایل',
        ],
        'featured_90' => [
            'label'       => 'آگهی ویژه ۹۰ روزه',
            'days'        => 90,
            'price'       => 1200000,
            'description' => '۳ ماه اولویت در جستجو و دیده‌شدن بیشتر',
        ],
    ];
}

function casting_premium_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'casting_premium';
}

function casting_premium_install(): void
{
    global $wpdb;
    $table = casting_premium_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        plan_key VARCHAR(32) NOT NULL DEFAULT '',
        amount BIGINT NOT NULL DEFAULT 0,
        reference_code VARCHAR(64) NOT NULL DEFAULT '',
        receipt_note TEXT NULL,
        attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL,
        reviewed_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('casting_premium_db_version', '1');
}

function casting_premium_ensure_table(): void
{
    if ((string) get_option('casting_premium_db_version', '') !== '1') {
        casting_premium_install();
    }
}

function casting_user_is_premium(int $user_id): bool
{
    $until = (string) get_user_meta($user_id, 'casting_premium_until', true);
    if ($until === '') {
        return false;
    }
    return strtotime($until) >= time();
}

function casting_premium_until_label(int $user_id): string
{
    if (!casting_user_is_premium($user_id)) {
        return '';
    }
    $until = (string) get_user_meta($user_id, 'casting_premium_until', true);
    return $until;
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_submit_premium_receipt(int $user_id, string $plan_key, string $reference, string $note, int $attachment_id = 0): array
{
    $plans = casting_premium_plans();
    if (!isset($plans[$plan_key])) {
        return ['ok' => false, 'error' => 'پلن انتخاب‌شده معتبر نیست.'];
    }
    $reference = sanitize_text_field(trim($reference));
    if ($reference === '') {
        return ['ok' => false, 'error' => 'شماره پیگیری یا مرجع واریز را وارد کنید.'];
    }
    $note = sanitize_textarea_field(trim($note));

    casting_premium_ensure_table();
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->insert(
        casting_premium_table(),
        [
            'user_id'       => $user_id,
            'plan_key'      => $plan_key,
            'amount'        => (int) $plans[$plan_key]['price'],
            'reference_code'=> $reference,
            'receipt_note'  => $note,
            'attachment_id' => max(0, $attachment_id),
            'status'        => 'pending',
            'created_at'    => current_time('mysql'),
        ],
        ['%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s']
    );

    if (!$wpdb->insert_id) {
        return ['ok' => false, 'error' => 'ثبت فیش ناموفق بود.'];
    }

    casting_add_transaction($user_id, [
        'type'    => 'receipt',
        'title'   => 'ثبت فیش — ' . $plans[$plan_key]['label'],
        'amount'  => (int) $plans[$plan_key]['price'],
        'status'  => 'pending',
        'ref'     => $reference,
        'item_id' => (int) $wpdb->insert_id,
    ]);

    return ['ok' => true, 'error' => ''];
}

/**
 * @param array{type:string,title:string,amount:int,status:string,ref:string,item_id?:int} $row
 */
function casting_add_transaction(int $user_id, array $row): void
{
    $list = get_user_meta($user_id, 'casting_transactions', true);
    if (!is_array($list)) {
        $list = [];
    }
    array_unshift($list, array_merge($row, ['at' => current_time('mysql')]));
    update_user_meta($user_id, 'casting_transactions', array_slice($list, 0, 100));
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_user_transactions(int $user_id): array
{
    $list = get_user_meta($user_id, 'casting_transactions', true);
    return is_array($list) ? $list : [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_user_receipts(int $user_id): array
{
    casting_premium_ensure_table();
    global $wpdb;
    $table = casting_premium_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 50", $user_id),
        ARRAY_A
    );
    return is_array($rows) ? $rows : [];
}

function casting_premium_status_label(string $status): string
{
    if ($status === 'approved') {
        return 'تأیید شده';
    }
    if ($status === 'rejected') {
        return 'رد شده';
    }
    return 'در انتظار بررسی';
}

/**
 * تأیید فیش توسط مدیر (فراخوانی دستی یا از wp-admin)
 *
 * @return array{ok:bool,error:string}
 */
function casting_approve_premium_receipt(int $receipt_id): array
{
    casting_premium_ensure_table();
    global $wpdb;
    $table = casting_premium_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $receipt_id), ARRAY_A);
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'فیش پیدا نشد.'];
    }
    if ((string) $row['status'] === 'approved') {
        return ['ok' => true, 'error' => ''];
    }

    $user_id = (int) $row['user_id'];
    $plans = casting_premium_plans();
    $plan_key = (string) $row['plan_key'];
    if (!isset($plans[$plan_key])) {
        return ['ok' => false, 'error' => 'پلن نامعتبر است.'];
    }
    $days = (int) $plans[$plan_key]['days'];

    $current = (string) get_user_meta($user_id, 'casting_premium_until', true);
    $now = (int) current_time('timestamp');
    $base = ($current !== '' && strtotime($current) > $now) ? strtotime($current) : $now;
    $until = date('Y-m-d H:i:s', $base + ($days * DAY_IN_SECONDS));
    update_user_meta($user_id, 'casting_premium_until', $until);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->update(
        $table,
        ['status' => 'approved', 'reviewed_at' => current_time('mysql')],
        ['id' => $receipt_id],
        ['%s', '%s'],
        ['%d']
    );

    casting_add_transaction($user_id, [
        'type'   => 'activation',
        'title'  => 'فعال‌سازی ' . $plans[$plan_key]['label'],
        'amount' => (int) $row['amount'],
        'status' => 'approved',
        'ref'    => (string) $row['reference_code'],
    ]);

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_reject_premium_receipt(int $receipt_id): array
{
    casting_premium_ensure_table();
    global $wpdb;
    $table = casting_premium_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $receipt_id), ARRAY_A);
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'فیش پیدا نشد.'];
    }
    if ((string) $row['status'] === 'rejected') {
        return ['ok' => true, 'error' => ''];
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->update(
        $table,
        ['status' => 'rejected', 'reviewed_at' => current_time('mysql')],
        ['id' => $receipt_id],
        ['%s', '%s'],
        ['%d']
    );

    $user_id = (int) $row['user_id'];
    $plans = casting_premium_plans();
    $plan_key = (string) $row['plan_key'];
    $label = $plans[$plan_key]['label'] ?? $plan_key;
    casting_add_transaction($user_id, [
        'type'   => 'receipt',
        'title'  => 'رد فیش — ' . $label,
        'amount' => (int) $row['amount'],
        'status' => 'rejected',
        'ref'    => (string) $row['reference_code'],
    ]);

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_admin_list_receipts(string $status = 'pending', int $limit = 100): array
{
    casting_premium_ensure_table();
    global $wpdb;
    $table = casting_premium_table();
    $limit = max(1, min(200, $limit));
    $status = sanitize_key($status);

    if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d",
                $status,
                $limit
            ),
            ARRAY_A
        );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit),
            ARRAY_A
        );
    }

    return is_array($rows) ? $rows : [];
}

function casting_user_is_portal_admin(): bool
{
    return is_user_logged_in() && current_user_can('manage_options');
}

function casting_require_portal_admin(): void
{
    if (!is_user_logged_in()) {
        auth_redirect();
    }
    if (!current_user_can('manage_options')) {
        wp_die('فقط مدیر سایت به این بخش دسترسی دارد.', 'دسترسی غیرمجاز', ['response' => 403]);
    }
}

function casting_handle_receipt_upload(int $user_id): array
{
    if (empty($_FILES['receipt']['name'])) {
        return ['ok' => true, 'attachment_id' => 0];
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    casting_enable_user_upload_dir($user_id);
    $attachment_id = media_handle_upload('receipt', 0);
    casting_disable_user_upload_dir();
    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'بارگذاری تصویر فیش ناموفق بود.', 'attachment_id' => 0];
    }
    return ['ok' => true, 'attachment_id' => (int) $attachment_id];
}
