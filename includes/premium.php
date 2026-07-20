<?php
declare(strict_types=1);

function casting_premium_plans(): array
{
    return [
        'featured_30' => [
            'label'        => 'حساب کاربری ویژه',
            'days'         => 30,
            'period_label' => '۱ ماه',
            'price'        => 150000,
            'description'  => '۱۵۰,۰۰۰ تومان برای ۱ ماه — اولویت در جستجو، برچسب ویژه روی پروفایل و دیده‌شدن بیشتر',
        ],
        // پلن قدیمی — فقط برای فیش‌های قبلی
        'featured_90' => [
            'label'        => 'حساب کاربری ویژه',
            'days'         => 30,
            'period_label' => '۱ ماه',
            'price'        => 150000,
            'description'  => '۱۵۰,۰۰۰ تومان برای ۱ ماه — اولویت در جستجو، برچسب ویژه روی پروفایل و دیده‌شدن بیشتر',
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

function casting_premium_sync_expiry(int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    $until = (string) get_user_meta($user_id, 'casting_premium_until', true);
    if ($until === '') {
        return;
    }
    $until_ts = strtotime($until);
    if ($until_ts === false || $until_ts < strtotime((string) current_time('mysql'))) {
        delete_user_meta($user_id, 'casting_premium_until');
    }
}

function casting_premium_expire_timestamp(int $user_id): ?int
{
    casting_premium_sync_expiry($user_id);
    $until = (string) get_user_meta($user_id, 'casting_premium_until', true);
    if ($until === '') {
        return null;
    }
    $until_ts = strtotime($until);
    return $until_ts !== false ? $until_ts : null;
}

function casting_user_is_premium(int $user_id): bool
{
    casting_premium_sync_expiry($user_id);
    $until = (string) get_user_meta($user_id, 'casting_premium_until', true);
    if ($until === '') {
        return false;
    }
    return strtotime($until) >= strtotime((string) current_time('mysql'));
}

function casting_premium_until_label(int $user_id): string
{
    if (!casting_user_is_premium($user_id)) {
        return '';
    }
    return (string) get_user_meta($user_id, 'casting_premium_until', true);
}

function casting_premium_countdown_summary(int $user_id): string
{
    $until_ts = casting_premium_expire_timestamp($user_id);
    if ($until_ts === null) {
        return '';
    }
    $diff = max(0, $until_ts - time());
    $days = (int) floor($diff / DAY_IN_SECONDS);
    $hours = (int) floor(($diff % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
    $minutes = (int) floor(($diff % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);
    if ($days > 0) {
        return $days . ' روز و ' . $hours . ' ساعت';
    }
    if ($hours > 0) {
        return $hours . ' ساعت و ' . $minutes . ' دقیقه';
    }
    return $minutes . ' دقیقه';
}

function casting_premium_countdown_nav_label(int $user_id): string
{
    $until_ts = casting_premium_expire_timestamp($user_id);
    if ($until_ts === null) {
        return '';
    }
    $diff = max(0, $until_ts - time());
    $days = (int) floor($diff / DAY_IN_SECONDS);
    $hours = (int) floor(($diff % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
    $minutes = (int) floor(($diff % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);
    if ($days > 0) {
        return $days . ' روز';
    }
    if ($hours > 0) {
        return $hours . ' ساعت';
    }
    return $minutes . ' دقیقه';
}

function casting_render_premium_countdown(int $user_id): void
{
    if (!casting_user_is_premium($user_id)) {
        return;
    }
    $until = casting_premium_until_label($user_id);
    $until_ts = casting_premium_expire_timestamp($user_id);
    if ($until === '' || $until_ts === null) {
        return;
    }
    ?>
<div class="premium-countdown" data-premium-until-ts="<?= (int) $until_ts ?>">
  <p class="premium-countdown-title">زمان باقی‌مانده حساب ویژه</p>
  <p class="premium-countdown-value" data-premium-countdown><?= casting_e(casting_premium_countdown_summary($user_id)) ?></p>
  <p class="premium-countdown-meta">تا <?= casting_e($until) ?> · پس از پایان، به‌صورت خودکار از ویژه خارج می‌شوید.</p>
</div>
    <?php
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
    if ($attachment_id <= 0) {
        return ['ok' => false, 'error' => 'تصویر فیش الزامی است.'];
    }

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
    $now = (string) current_time('mysql');
    $now_ts = strtotime($now);
    $base_ts = ($current !== '' && strtotime($current) > $now_ts) ? strtotime($current) : $now_ts;
    $until = wp_date('Y-m-d H:i:s', $base_ts + ($days * DAY_IN_SECONDS));
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

/**
 * @return array<int, array<string, mixed>>
 */
function casting_admin_list_all_receipts_with_users(int $limit = 200): array
{
    $rows = casting_admin_list_receipts('', $limit);
    $out = [];
    foreach ($rows as $row) {
        $user_id = (int) ($row['user_id'] ?? 0);
        $user = get_user_by('id', $user_id);
        if (!$user || casting_get_user_role($user_id) === '') {
            continue;
        }
        $out[] = array_merge($row, [
            'user_name'  => (string) $user->display_name,
            'user_login' => (string) $user->user_login,
        ]);
    }
    return $out;
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_admin_list_all_account_transactions(int $limit = 200): array
{
    $users = get_users([
        'meta_key'     => 'casting_transactions',
        'meta_compare' => 'EXISTS',
        'number'       => 400,
    ]);

    $out = [];
    foreach ($users as $user) {
        $user_id = (int) $user->ID;
        if (casting_get_user_role($user_id) === '') {
            continue;
        }
        foreach (casting_user_transactions($user_id) as $tx) {
            if (!is_array($tx)) {
                continue;
            }
            $out[] = array_merge($tx, [
                'user_id'    => $user_id,
                'user_name'  => (string) $user->display_name,
                'user_login' => (string) $user->user_login,
            ]);
        }
    }

    usort($out, static function (array $a, array $b): int {
        return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
    });

    return array_slice($out, 0, max(1, min(500, $limit)));
}

function casting_admin_pending_receipt_count(): int
{
    return count(casting_admin_list_receipts('pending', 200));
}

function casting_handle_receipt_upload(int $user_id): array
{
    if (empty($_FILES['receipt']['name'])) {
        return ['ok' => false, 'error' => 'تصویر فیش را انتخاب کنید.', 'attachment_id' => 0];
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

function casting_render_receipt_thumbnail(int $attachment_id, string $alt = 'فیش'): void
{
    if ($attachment_id <= 0) {
        return;
    }
    $full_url = wp_get_attachment_image_url($attachment_id, 'large');
    $thumb_url = wp_get_attachment_image_url($attachment_id, 'medium');
    if (!is_string($full_url) || $full_url === '' || !is_string($thumb_url) || $thumb_url === '') {
        return;
    }
    ?>
<div class="receipt-thumb-wrap">
  <a class="receipt-thumb-link" href="<?= casting_e($full_url) ?>" target="_blank" rel="noopener">
    <span class="receipt-thumb-frame">
      <img class="receipt-thumb-img" src="<?= casting_e($thumb_url) ?>" alt="<?= casting_e($alt) ?>">
    </span>
    <span class="receipt-thumb-hint">کلیک برای بزرگ‌نمایی</span>
  </a>
</div>
    <?php
}
