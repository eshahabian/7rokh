<?php
declare(strict_types=1);

function casting_premium_plans(): array
{
    return [
        'featured_90' => [
            'label'        => 'عضویت ویژه پورتال ۷رخ',
            'days'         => 90,
            'months'       => 3,
            'period_label' => '۳ ماه',
            'price'        => 210000,
            'unit_price'   => 70000,
            'description'  => 'بسته ۳ ماهه — ۲۱۰٬۰۰۰ تومان. دسترسی به جستجو، شروع گفتگو و اولویت در نتایج.',
        ],
        'featured_180' => [
            'label'        => 'عضویت ویژه پورتال ۷رخ',
            'days'         => 180,
            'months'       => 6,
            'period_label' => '۶ ماه',
            'price'        => 370000,
            'unit_price'   => 61667,
            'description'  => 'بسته ۶ ماهه — ۳۷۰٬۰۰۰ تومان.',
        ],
        'featured_365' => [
            'label'        => 'عضویت ویژه پورتال ۷رخ',
            'days'         => 365,
            'months'       => 12,
            'period_label' => '۱۲ ماه',
            'price'        => 700000,
            'unit_price'   => 58333,
            'description'  => 'بسته ۱۲ ماهه — ۷۰۰٬۰۰۰ تومان.',
        ],
        // سازگاری با فیش‌ها / لینک‌های قدیمی (حداقل ۳ ماه)
        'featured_30' => [
            'label'        => 'عضویت ویژه پورتال ۷رخ',
            'days'         => 90,
            'months'       => 3,
            'period_label' => '۳ ماه',
            'price'        => 210000,
            'unit_price'   => 70000,
            'description'  => 'بسته ۳ ماهه — ۲۱۰٬۰۰۰ تومان.',
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

/**
 * فعال‌سازی / تمدید عضویت ویژه پس از پرداخت موفق
 *
 * @return array{ok:bool,error:string,until?:string}
 */
function casting_premium_activate_for_user(int $user_id, int $days, string $plan_key = '', int $amount = 0, string $ref = ''): array
{
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'کاربر نامعتبر است.'];
    }
    $days = max(1, $days);
    $current = (string) get_user_meta($user_id, 'casting_premium_until', true);
    $now = (string) current_time('mysql');
    $now_ts = strtotime($now);
    $base_ts = ($current !== '' && strtotime($current) > $now_ts) ? strtotime($current) : $now_ts;
    $until = wp_date('Y-m-d H:i:s', $base_ts + ($days * DAY_IN_SECONDS));
    update_user_meta($user_id, 'casting_premium_until', $until);
    if ($plan_key !== '') {
        update_user_meta($user_id, 'casting_premium_last_plan', sanitize_key($plan_key));
    }
    if ($ref !== '') {
        update_user_meta($user_id, 'casting_premium_last_ref', sanitize_text_field($ref));
    }

    return ['ok' => true, 'error' => '', 'until' => $until];
}

function casting_premium_until_label(int $user_id): string
{
    if (!casting_user_is_premium($user_id)) {
        return '';
    }
    return (string) get_user_meta($user_id, 'casting_premium_until', true);
}

/**
 * جدا کردن تاریخ و ساعت پایان اشتراک برای نمایش فشرده در جدول
 *
 * @return array{date:string,time:string}
 */
function casting_premium_until_parts(string $until): array
{
    $until = trim($until);
    if ($until === '') {
        return ['date' => '', 'time' => ''];
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}(?::\d{2})?)/', $until, $m)) {
        return ['date' => $m[1], 'time' => $m[2]];
    }

    return ['date' => $until, 'time' => ''];
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
 * فعال‌سازی یا تمدید دستی اشتراک ویژه توسط مدیر
 *
 * @return array{ok:bool,error:string,until?:string}
 */
function casting_admin_grant_premium(int $target_id, int $admin_id, string $plan_key = 'featured_90'): array
{
    if (!function_exists('casting_user_has_admin_permission')) {
        require_once __DIR__ . '/admin-access.php';
    }
    if ($admin_id <= 0 || (!casting_user_has_admin_permission($admin_id, 'view_premium_users') && !casting_user_is_super_admin($admin_id))) {
        return ['ok' => false, 'error' => 'اجازه فعال‌سازی اشتراک ویژه را ندارید.'];
    }
    if ($target_id <= 0 || casting_get_user_role($target_id) === '') {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }

    $plans = casting_premium_plans();
    if (!isset($plans[$plan_key])) {
        $plan_key = 'featured_90';
    }
    if (!isset($plans[$plan_key])) {
        return ['ok' => false, 'error' => 'پلن ویژه تعریف نشده است.'];
    }

    $days = max(1, (int) $plans[$plan_key]['days']);
    $current = (string) get_user_meta($target_id, 'casting_premium_until', true);
    $now = (string) current_time('mysql');
    $now_ts = strtotime($now);
    $base_ts = ($current !== '' && strtotime($current) > $now_ts) ? strtotime($current) : $now_ts;
    if ($base_ts === false || $now_ts === false) {
        return ['ok' => false, 'error' => 'محاسبه تاریخ اشتراک ممکن نشد.'];
    }
    $until = wp_date('Y-m-d H:i:s', $base_ts + ($days * DAY_IN_SECONDS));
    update_user_meta($target_id, 'casting_premium_until', $until);
    update_user_meta($target_id, 'casting_premium_last_plan', sanitize_key($plan_key));
    update_user_meta($target_id, 'casting_premium_last_ref', 'admin:' . $admin_id);

    casting_add_transaction($target_id, [
        'type'   => 'activation',
        'title'  => 'فعال‌سازی دستی ' . $plans[$plan_key]['label'] . ' توسط مدیر',
        'amount' => 0,
        'status' => 'approved',
        'ref'    => 'admin:' . $admin_id,
    ]);

    return ['ok' => true, 'error' => '', 'until' => $until];
}

/**
 * مدت انتظار تأیید دومرحله‌ای ویژه کردن دستی (ثانیه)
 */
function casting_admin_grant_premium_wait_seconds(): int
{
    return 10;
}

/**
 * شروع مرحلهٔ تأیید ویژه کردن دستی
 *
 * @return array{ok:bool,error:string}
 */
function casting_admin_grant_premium_begin(int $admin_id, int $target_id, string $mode = 'grant'): array
{
    if (!function_exists('casting_user_has_admin_permission')) {
        require_once __DIR__ . '/admin-access.php';
    }
    if ($admin_id <= 0 || (!casting_user_has_admin_permission($admin_id, 'view_premium_users') && !casting_user_is_super_admin($admin_id))) {
        return ['ok' => false, 'error' => 'اجازه فعال‌سازی اشتراک ویژه را ندارید.'];
    }
    if ($target_id <= 0 || casting_get_user_role($target_id) === '') {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    $mode = $mode === 'renew' ? 'renew' : 'grant';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return ['ok' => false, 'error' => 'نشست منقضی شده. دوباره تلاش کنید.'];
    }
    $_SESSION['casting_grant_premium'] = [
        'admin_id'   => $admin_id,
        'target_id'  => $target_id,
        'mode'       => $mode,
        'started_at' => time(),
    ];

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{admin_id:int,target_id:int,mode:string,started_at:int}|null
 */
function casting_admin_grant_premium_pending(?int $target_id = null): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }
    $pending = $_SESSION['casting_grant_premium'] ?? null;
    if (!is_array($pending)) {
        return null;
    }
    $row = [
        'admin_id'   => (int) ($pending['admin_id'] ?? 0),
        'target_id'  => (int) ($pending['target_id'] ?? 0),
        'mode'       => ((string) ($pending['mode'] ?? 'grant')) === 'renew' ? 'renew' : 'grant',
        'started_at' => (int) ($pending['started_at'] ?? 0),
    ];
    if ($row['admin_id'] <= 0 || $row['target_id'] <= 0 || $row['started_at'] <= 0) {
        return null;
    }
    // منقضی بعد از ۲ دقیقه بدون تأیید
    if (time() - $row['started_at'] > 120) {
        casting_admin_grant_premium_clear();

        return null;
    }
    if ($target_id !== null && $row['target_id'] !== $target_id) {
        return null;
    }

    return $row;
}

function casting_admin_grant_premium_clear(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['casting_grant_premium']);
    }
}

/**
 * آیا می‌توان ویژه کردن دستی را نهایی کرد؟ (تیک + حداقل ۱۰ ثانیه)
 *
 * @return array{ok:bool,error:string,remaining:int}
 */
function casting_admin_grant_premium_can_finalize(int $admin_id, int $target_id, bool $confirmed): array
{
    $pending = casting_admin_grant_premium_pending($target_id);
    if ($pending === null || (int) $pending['admin_id'] !== $admin_id) {
        return ['ok' => false, 'error' => 'ابتدا دکمهٔ ویژه کردن را بزنید و مرحلهٔ تأیید را کامل کنید.', 'remaining' => 0];
    }
    if (!$confirmed) {
        return ['ok' => false, 'error' => 'برای ادامه، تیک «از انجام این کار مطمئن هستم» را بزنید.', 'remaining' => 0];
    }
    $wait = casting_admin_grant_premium_wait_seconds();
    $elapsed = time() - (int) $pending['started_at'];
    $remaining = max(0, $wait - $elapsed);
    if ($remaining > 0) {
        return [
            'ok'        => false,
            'error'     => 'لطفاً ' . $remaining . ' ثانیه دیگر صبر کنید و دوباره تأیید کنید.',
            'remaining' => $remaining,
        ];
    }

    return ['ok' => true, 'error' => '', 'remaining' => 0];
}

/**
 * آیا برای این کاربر مدرک پرداخت واقعی (پول/فیش تأییدشده) وجود دارد؟
 */
function casting_premium_has_paid_money_evidence(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }

    $last_ref = trim((string) get_user_meta($user_id, 'casting_premium_last_ref', true));
    if ($last_ref !== '' && !str_starts_with($last_ref, 'admin:') && !str_starts_with($last_ref, 'admin-revoke:')) {
        return true;
    }

    foreach (casting_user_transactions($user_id) as $tx) {
        if (!is_array($tx)) {
            continue;
        }
        $status = (string) ($tx['status'] ?? '');
        if ($status !== 'approved') {
            continue;
        }
        $type = (string) ($tx['type'] ?? '');
        $amount = (int) ($tx['amount'] ?? 0);
        if ($type === 'gateway_payment' || $amount > 0) {
            return true;
        }
    }

    foreach (casting_user_receipts($user_id) as $row) {
        if ((string) ($row['status'] ?? '') === 'approved') {
            return true;
        }
    }

    return false;
}

/**
 * آیا برای این کاربر مدرک پرداخت/فیش/فعال‌سازی دستی وجود دارد؟
 */
function casting_premium_has_activation_evidence(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (casting_premium_has_paid_money_evidence($user_id)) {
        return true;
    }

    $last_ref = trim((string) get_user_meta($user_id, 'casting_premium_last_ref', true));
    if (str_starts_with($last_ref, 'admin:')) {
        return true;
    }

    foreach (casting_user_transactions($user_id) as $tx) {
        if (!is_array($tx)) {
            continue;
        }
        $status = (string) ($tx['status'] ?? '');
        if ($status !== 'approved') {
            continue;
        }
        $type = (string) ($tx['type'] ?? '');
        $ref = (string) ($tx['ref'] ?? '');
        if ($type === 'activation' || str_starts_with($ref, 'admin:')) {
            return true;
        }
    }

    return false;
}

/**
 * پاک کردن متای ویژه و ثبت لاگ (بدون چک دسترسی — فقط از مسیرهای ادمین صدا زده شود).
 */
function casting_premium_clear_for_user(int $user_id, string $title, string $ref): void
{
    delete_user_meta($user_id, 'casting_premium_until');
    delete_user_meta($user_id, 'casting_premium_last_plan');
    delete_user_meta($user_id, 'casting_premium_last_ref');

    casting_add_transaction($user_id, [
        'type'   => 'revocation',
        'title'  => $title,
        'amount' => 0,
        'status' => 'approved',
        'ref'    => $ref,
    ]);
}

/**
 * لغو عضویت ویژه توسط مدیر
 *
 * @return array{ok:bool,error:string}
 */
function casting_admin_revoke_premium(int $target_id, int $admin_id, string $note = ''): array
{
    if (!function_exists('casting_user_has_admin_permission')) {
        require_once __DIR__ . '/admin-access.php';
    }
    if ($admin_id <= 0 || (!casting_user_has_admin_permission($admin_id, 'view_premium_users') && !casting_user_is_super_admin($admin_id))) {
        return ['ok' => false, 'error' => 'اجازه لغو اشتراک ویژه را ندارید.'];
    }
    if ($target_id <= 0 || casting_get_user_role($target_id) === '') {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    if (!casting_user_is_premium($target_id)) {
        return ['ok' => true, 'error' => ''];
    }

    $note = trim(sanitize_text_field($note));
    $title = 'لغو عضویت ویژه توسط مدیر';
    if ($note !== '') {
        $title .= ' — ' . $note;
    }

    casting_premium_clear_for_user($target_id, $title, 'admin-revoke:' . $admin_id);

    return ['ok' => true, 'error' => ''];
}

/**
 * یک‌بار: ویژه‌های بدون مدرک پرداخت را برمی‌دارد؛ زهرا محمدی اگر پول نداده باشد حتماً لغو می‌شود.
 *
 * @return array{ran:bool,revoked:array<int,string>}
 */
function casting_premium_repair_orphan_once(int $admin_id): array
{
    $flag = 'casting_premium_orphan_repair_v1';
    if ((string) get_option($flag, '') === '1') {
        return ['ran' => false, 'revoked' => []];
    }
    if ($admin_id <= 0) {
        return ['ran' => false, 'revoked' => []];
    }
    if (!function_exists('casting_user_has_admin_permission')) {
        require_once __DIR__ . '/admin-access.php';
    }
    if (!casting_user_has_admin_permission($admin_id, 'view_premium_users') && !casting_user_is_super_admin($admin_id)) {
        return ['ran' => false, 'revoked' => []];
    }

    $revoked = [];
    $mark = static function (int $uid, string $label) use (&$revoked, $admin_id): void {
        if (!casting_user_is_premium($uid)) {
            return;
        }
        casting_premium_clear_for_user(
            $uid,
            'لغو خودکار ویژه بدون مدرک پرداخت — ' . $label,
            'orphan-repair:' . $admin_id
        );
        $revoked[$uid] = $label;
    };

    $users = get_users([
        'meta_key'     => 'casting_premium_until',
        'meta_compare' => 'EXISTS',
        'number'       => 500,
        'fields'       => ['ID', 'display_name', 'user_login'],
    ]);

    foreach ($users as $user) {
        $uid = (int) $user->ID;
        if (!casting_user_is_premium($uid)) {
            continue;
        }
        $name = trim((string) $user->display_name);
        $login = (string) $user->user_login;
        $is_zahra = ($name === 'زهرا محمدی')
            || (function_exists('mb_strpos') ? mb_strpos($name, 'زهرا محمدی') !== false : strpos($name, 'زهرا محمدی') !== false);

        if ($is_zahra) {
            if (!casting_premium_has_paid_money_evidence($uid)) {
                $mark($uid, $name !== '' ? $name : $login);
            }
            continue;
        }

        if (!casting_premium_has_activation_evidence($uid)) {
            $mark($uid, $name !== '' ? $name : $login);
        }
    }

    // جستجوی صریح نام در صورت تفاوت جزئی
    $named = get_users([
        'search'         => '*زهرا*',
        'search_columns' => ['display_name'],
        'number'         => 50,
        'fields'         => ['ID', 'display_name', 'user_login'],
    ]);
    foreach ($named as $user) {
        $uid = (int) $user->ID;
        $name = trim((string) $user->display_name);
        $is_zahra = ($name === 'زهرا محمدی')
            || (function_exists('mb_strpos') ? mb_strpos($name, 'زهرا محمدی') !== false : strpos($name, 'زهرا محمدی') !== false);
        if (!$is_zahra || isset($revoked[$uid])) {
            continue;
        }
        if (casting_user_is_premium($uid) && !casting_premium_has_paid_money_evidence($uid)) {
            $mark($uid, $name);
        }
    }

    update_option($flag, '1', false);

    return ['ran' => true, 'revoked' => $revoked];
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
  <a class="receipt-thumb-link" href="<?= casting_e($full_url) ?>">
    <span class="receipt-thumb-frame">
      <img class="receipt-thumb-img" src="<?= casting_e($thumb_url) ?>" alt="<?= casting_e($alt) ?>">
    </span>
    <span class="receipt-thumb-hint">کلیک برای بزرگ‌نمایی</span>
  </a>
</div>
    <?php
}
