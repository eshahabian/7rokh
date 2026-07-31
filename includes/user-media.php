<?php
declare(strict_types=1);

require_once __DIR__ . '/profile.php';

function casting_user_media_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_user_media';
}

function casting_user_media_install(): void
{
    global $wpdb;
    $table = casting_user_media_table();
    $charset = $wpdb->get_charset_collate();
    // dbDelta بهتر است بدون IF NOT EXISTS کار کند تا ستون‌های جدید اضافه شوند
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        media_type VARCHAR(16) NOT NULL DEFAULT 'photo',
        caption TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        is_resubmit TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        reject_reason TEXT NULL,
        created_at DATETIME NOT NULL,
        reviewed_at DATETIME NULL,
        reviewed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        deleted_at DATETIME NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY status (status),
        KEY user_status (user_id, status),
        KEY media_type (media_type),
        KEY reviewed_by (reviewed_by),
        KEY deleted_at (deleted_at)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    casting_user_media_ensure_columns();
    update_option('casting_user_media_db_version', '4');
}

/**
 * اطمینان از وجود ستون‌های لازم (برای جداول قدیمی قبل از کپشن)
 */
function casting_user_media_ensure_columns(): void
{
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        return;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);
    if (!is_array($columns)) {
        $columns = [];
    }
    if (!in_array('caption', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN caption TEXT NULL AFTER media_type");
    }
    if (!in_array('reviewed_by', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN reviewed_by BIGINT UNSIGNED NOT NULL DEFAULT 0");
    }
    if (!in_array('is_resubmit', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN is_resubmit TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    }
    if (!in_array('deleted_at', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN deleted_at DATETIME NULL AFTER reviewed_by");
    }
}

function casting_user_media_ensure_table(): void
{
    $ver = (string) get_option('casting_user_media_db_version', '');
    if ($ver !== '4') {
        casting_user_media_install();
    } else {
        casting_user_media_ensure_columns();
    }
    casting_user_media_purge_expired_deleted();
}

function casting_user_can_manage_gallery(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }

    // همه اعضای پورتال (بازیگر، کارگردان، مدیر و …)
    if (casting_get_user_role($user_id) !== '') {
        return true;
    }
    if (!function_exists('casting_user_is_super_admin')) {
        $admin = __DIR__ . '/admin-access.php';
        if (is_file($admin)) {
            require_once $admin;
        }
    }
    if (function_exists('casting_user_is_super_admin') && casting_user_is_super_admin($user_id)) {
        return true;
    }
    if (function_exists('casting_user_is_portal_staff') && casting_user_is_portal_staff($user_id)) {
        return true;
    }

    return false;
}

function casting_user_media_status_label(string $status): string
{
    if ($status === 'approved') {
        return 'تأیید شده';
    }
    if ($status === 'rejected') {
        return 'رد شده';
    }
    if ($status === 'deleted') {
        return 'حذف‌شده توسط کاربر';
    }

    return 'در انتظار تأیید';
}

/**
 * پاکسازی پست‌هایی که کاربر حذف کرده و بیش از ۳۰ روز از حذف گذشته
 */
function casting_user_media_purge_expired_deleted(): void
{
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        return;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);
    if (!is_array($columns) || !in_array('deleted_at', $columns, true)) {
        return;
    }
    $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - 30 * DAY_IN_SECONDS);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, attachment_id FROM {$table}
         WHERE status = 'deleted' AND deleted_at IS NOT NULL AND deleted_at < %s
         LIMIT 50",
        $cutoff
    ), ARRAY_A);
    if (!is_array($rows) || $rows === []) {
        return;
    }
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $attachment_id = (int) ($row['attachment_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->delete($table, ['id' => $id], ['%d']);
        if ($attachment_id > 0) {
            wp_delete_attachment($attachment_id, true);
        }
    }
}

function casting_user_media_max_pending(int $user_id): int
{
    unset($user_id);

    return 12;
}

function casting_user_media_pending_count_for_user(int $user_id): int
{
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = 'pending'",
        $user_id
    ));
}

function casting_admin_pending_media_count(): int
{
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");
}

/**
 * @return list<array<string, mixed>>
 */
function casting_user_media_list(int $user_id, string $status = '', int $limit = 50): array
{
    if ($user_id <= 0) {
        return [];
    }
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    $limit = max(1, min(100, $limit));
    if ($status !== '') {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = %s ORDER BY sort_order ASC, id DESC LIMIT %d",
            $user_id,
            $status,
            $limit
        ), ARRAY_A);
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE user_id = %d AND status <> 'deleted'
             ORDER BY FIELD(status,'pending','approved','rejected'), sort_order ASC, id DESC
             LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);
    }

    return is_array($rows) ? $rows : [];
}

function casting_user_media_public_count(int $user_id): int
{
    if ($user_id <= 0) {
        return 0;
    }
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = 'approved'",
        $user_id
    ));
}

/**
 * @return list<array<string, mixed>>
 */
function casting_user_media_public(int $user_id, int $limit = 24): array
{
    return casting_user_media_list($user_id, 'approved', $limit);
}

/**
 * @return list<array<string, mixed>>
 */
function casting_admin_list_media(string $status = 'pending', int $limit = 80): array
{
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    $limit = max(1, min(200, $limit));
    if ($status === 'all') {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
            $limit
        ), ARRAY_A);
    } else {
        // جدیدترین‌ها اول — تا ویرایش‌های مجدد هم دیده شوند
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d",
            $status,
            $limit
        ), ARRAY_A);
    }

    return is_array($rows) ? $rows : [];
}

function casting_user_media_sanitize_caption(string $caption): string
{
    $caption = sanitize_textarea_field($caption);
    if (function_exists('mb_substr')) {
        return mb_substr($caption, 0, 500, 'UTF-8');
    }

    return substr($caption, 0, 500);
}

/**
 * نام کوتاه مدیر تأییدکننده برای نمایش روی پست
 */
function casting_user_media_approver_label(int $admin_id): string
{
    if ($admin_id <= 0) {
        return '';
    }
    $user = get_user_by('id', $admin_id);
    if (!$user) {
        return '';
    }
    $login = strtolower((string) $user->user_login);
    $known = [
        'eshahabian' => 'شهابیان',
        'ardavan'    => 'اردوان',
    ];
    if (isset($known[$login])) {
        return $known[$login];
    }
    $name = trim((string) $user->display_name);

    return $name !== '' ? $name : $login;
}

function casting_user_media_approver_line(array $row): string
{
    if (($row['status'] ?? '') !== 'approved') {
        return '';
    }
    $label = casting_user_media_approver_label((int) ($row['reviewed_by'] ?? 0));
    if ($label === '') {
        return '';
    }

    return $label . ' تأیید کرده';
}

/**
 * @return array{ok:bool,error:string,id?:int}
 */
function casting_user_media_submit_upload(int $user_id, string $field, string $media_type, string $caption = ''): array
{
    if (!casting_user_can_manage_gallery($user_id)) {
        return ['ok' => false, 'error' => 'برای افزودن پست باید عضو پورتال باشید.'];
    }
    casting_user_media_ensure_table();
    if (!in_array($media_type, ['photo', 'video'], true)) {
        return ['ok' => false, 'error' => 'نوع فایل نامعتبر است.'];
    }
    if (empty($_FILES[$field]['name'])) {
        return ['ok' => false, 'error' => 'فایلی انتخاب نشده است.'];
    }
    if (casting_user_media_pending_count_for_user($user_id) >= casting_user_media_max_pending($user_id)) {
        return ['ok' => false, 'error' => 'تعداد فایل‌های در انتظار تأیید زیاد است. تا بررسی مدیر صبر کنید.'];
    }

    casting_require_media_includes();
    $file = $_FILES[$field];
    $ftype = (string) ($file['type'] ?? '');
    // بعضی مرورگرها type خالی یا octet-stream می‌فرستند
    if ($ftype === '' || $ftype === 'application/octet-stream') {
        $check = wp_check_filetype_and_ext(
            (string) ($file['tmp_name'] ?? ''),
            (string) ($file['name'] ?? '')
        );
        if (!empty($check['type'])) {
            $ftype = (string) $check['type'];
            $_FILES[$field]['type'] = $ftype;
        }
    }
    if ($media_type === 'photo') {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($ftype, $allowed, true)) {
            return ['ok' => false, 'error' => 'فقط عکس JPG، PNG یا WebP مجاز است.'];
        }
        if ((int) $file['size'] > 5 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'حجم عکس حداکثر ۵ مگابایت باشد.'];
        }
    } else {
        $allowed = ['video/mp4', 'video/webm', 'video/quicktime'];
        if (!in_array($ftype, $allowed, true)) {
            return ['ok' => false, 'error' => 'فقط ویدیو MP4، WebM یا MOV مجاز است.'];
        }
        if ((int) $file['size'] > 40 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'حجم ویدیو حداکثر ۴۰ مگابایت باشد.'];
        }
    }

    casting_enable_user_upload_dir($user_id);
    $attachment_id = media_handle_upload($field, 0);
    casting_disable_user_upload_dir();
    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'آپلود ناموفق بود: ' . $attachment_id->get_error_message()];
    }

    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    $row = [
        'user_id'       => $user_id,
        'attachment_id' => (int) $attachment_id,
        'media_type'    => $media_type,
        'caption'       => casting_user_media_sanitize_caption($caption),
        'status'        => 'pending',
        'sort_order'    => 0,
        'created_at'    => current_time('mysql'),
    ];
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ok = $wpdb->insert($table, $row, ['%d', '%d', '%s', '%s', '%s', '%d', '%s']);

    // اگر هنوز ستون caption نباشد، بدون آن دوباره تلاش کن
    if ($ok === false && is_string($wpdb->last_error) && str_contains($wpdb->last_error, 'caption')) {
        casting_user_media_ensure_columns();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $ok = $wpdb->insert($table, $row, ['%d', '%d', '%s', '%s', '%s', '%d', '%s']);
    }

    if ($ok === false) {
        wp_delete_attachment((int) $attachment_id, true);
        $detail = trim((string) $wpdb->last_error);
        $msg = 'ثبت فایل ناموفق بود.';
        if ($detail !== '') {
            $msg .= ' (' . $detail . ')';
        }

        return ['ok' => false, 'error' => $msg];
    }

    return ['ok' => true, 'error' => '', 'id' => (int) $wpdb->insert_id];
}

/**
 * ویرایش پست — پس از تغییر دوباره به صف تأیید می‌رود
 *
 * @return array{ok:bool,error:string}
 */
function casting_user_media_edit_own(int $user_id, int $media_id, string $caption, string $file_field = ''): array
{
    if (!casting_user_can_manage_gallery($user_id)) {
        return ['ok' => false, 'error' => 'برای افزودن پست باید عضو پورتال باشید.'];
    }
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
        $media_id,
        $user_id
    ), ARRAY_A);
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'مورد پیدا نشد.'];
    }

    $attachment_id = (int) ($row['attachment_id'] ?? 0);
    $media_type = (string) ($row['media_type'] ?? 'photo');
    $new_attachment = 0;

    if ($file_field !== '' && !empty($_FILES[$file_field]['name'])) {
        casting_require_media_includes();
        $file = $_FILES[$file_field];
        if ($media_type === 'photo') {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array((string) ($file['type'] ?? ''), $allowed, true)) {
                return ['ok' => false, 'error' => 'فقط عکس JPG، PNG یا WebP مجاز است.'];
            }
            if ((int) $file['size'] > 5 * 1024 * 1024) {
                return ['ok' => false, 'error' => 'حجم عکس حداکثر ۵ مگابایت باشد.'];
            }
        } else {
            $allowed = ['video/mp4', 'video/webm', 'video/quicktime'];
            if (!in_array((string) ($file['type'] ?? ''), $allowed, true)) {
                return ['ok' => false, 'error' => 'فقط ویدیو MP4، WebM یا MOV مجاز است.'];
            }
            if ((int) $file['size'] > 40 * 1024 * 1024) {
                return ['ok' => false, 'error' => 'حجم ویدیو حداکثر ۴۰ مگابایت باشد.'];
            }
        }
        casting_enable_user_upload_dir($user_id);
        $uploaded = media_handle_upload($file_field, 0);
        casting_disable_user_upload_dir();
        if (is_wp_error($uploaded)) {
            return ['ok' => false, 'error' => 'آپلود ناموفق بود: ' . $uploaded->get_error_message()];
        }
        $new_attachment = (int) $uploaded;
    }

    $caption_clean = casting_user_media_sanitize_caption($caption);

    if ($new_attachment > 0) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $ok = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET caption = %s,
                 attachment_id = %d,
                 status = 'pending',
                 is_resubmit = 1,
                 reviewed_at = NULL,
                 reviewed_by = 0,
                 reject_reason = NULL
             WHERE id = %d AND user_id = %d",
            $caption_clean,
            $new_attachment,
            $media_id,
            $user_id
        ));
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $ok = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET caption = %s,
                 status = 'pending',
                 is_resubmit = 1,
                 reviewed_at = NULL,
                 reviewed_by = 0,
                 reject_reason = NULL
             WHERE id = %d AND user_id = %d",
            $caption_clean,
            $media_id,
            $user_id
        ));
    }

    if ($ok === false) {
        if ($new_attachment > 0) {
            wp_delete_attachment($new_attachment, true);
        }
        $detail = trim((string) $wpdb->last_error);
        $msg = 'ذخیره ویرایش ناموفق بود.';
        if ($detail !== '') {
            $msg .= ' (' . $detail . ')';
        }

        return ['ok' => false, 'error' => $msg];
    }

    // اطمینان: بعد از ویرایش حتماً pending باشد
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $status_now = (string) $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$table} WHERE id = %d AND user_id = %d",
        $media_id,
        $user_id
    ));
    if ($status_now !== 'pending') {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'pending', is_resubmit = 1, reviewed_at = NULL, reviewed_by = 0 WHERE id = %d AND user_id = %d",
            $media_id,
            $user_id
        ));
    }

    if ($new_attachment > 0 && $attachment_id > 0 && $attachment_id !== $new_attachment) {
        wp_delete_attachment($attachment_id, true);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_user_media_delete_own(int $user_id, int $media_id): array
{
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND user_id = %d AND status <> 'deleted'",
        $media_id,
        $user_id
    ), ARRAY_A);
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'مورد پیدا نشد.'];
    }
    $attachment_id = (int) ($row['attachment_id'] ?? 0);
    $status = (string) ($row['status'] ?? '');
    $reviewed_by = (int) ($row['reviewed_by'] ?? 0);

    // پست تأییدشده توسط مدیر: یک ماه در پروفایل ادمین بماند، بعد پاک شود
    if ($status === 'approved' && $reviewed_by > 0) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'deleted', deleted_at = %s
             WHERE id = %d AND user_id = %d",
            current_time('mysql'),
            $media_id,
            $user_id
        ));

        return ['ok' => true, 'error' => ''];
    }

    // بقیه (در انتظار / ردشده): حذف کامل
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->delete($table, ['id' => $media_id], ['%d']);
    if ($attachment_id > 0) {
        wp_delete_attachment($attachment_id, true);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_approve_user_media(int $media_id, int $admin_id): array
{
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $media_id), ARRAY_A);
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'مورد پیدا نشد.'];
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table}
         SET status = 'approved',
             is_resubmit = 0,
             reviewed_at = %s,
             reviewed_by = %d,
             reject_reason = NULL,
             deleted_at = NULL
         WHERE id = %d",
        current_time('mysql'),
        $admin_id,
        $media_id
    ));

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_reject_user_media(int $media_id, int $admin_id, string $reason = ''): array
{
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $media_id), ARRAY_A);
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'مورد پیدا نشد.'];
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table}
         SET status = 'rejected',
             is_resubmit = 0,
             reviewed_at = %s,
             reviewed_by = %d,
             reject_reason = %s
         WHERE id = %d",
        current_time('mysql'),
        $admin_id,
        sanitize_textarea_field($reason),
        $media_id
    ));

    return ['ok' => true, 'error' => ''];
}

function casting_user_media_url(array $row): string
{
    $id = (int) ($row['attachment_id'] ?? 0);
    if ($id <= 0) {
        return '';
    }
    $url = wp_get_attachment_url($id);

    return is_string($url) ? $url : '';
}

function casting_user_media_thumb_url(array $row): string
{
    $id = (int) ($row['attachment_id'] ?? 0);
    if ($id <= 0) {
        return '';
    }
    if (($row['media_type'] ?? '') === 'video') {
        $thumb = wp_get_attachment_image_url($id, 'medium');
        if (is_string($thumb) && $thumb !== '') {
            return $thumb;
        }

        return casting_user_media_url($row);
    }
    $thumb = wp_get_attachment_image_url($id, 'medium');

    return is_string($thumb) && $thumb !== '' ? $thumb : casting_user_media_url($row);
}

/**
 * نمایش گالری عمومی تأییدشده
 */
function casting_render_public_media_gallery(int $user_id): void
{
    if (!function_exists('casting_render_media_engagement')) {
        require_once __DIR__ . '/media-engagement.php';
    }
    $items = casting_user_media_public($user_id);
    if ($items === []) {
        return;
    }
    ?>
  <section class="profile-media-gallery" aria-label="گالری">
    <h3 class="panel-section-title">گالری</h3>
    <div class="profile-media-grid">
      <?php foreach ($items as $item) :
          $url = casting_user_media_url($item);
          $thumb = casting_user_media_thumb_url($item);
          if ($url === '') {
              continue;
          }
          $is_video = ($item['media_type'] ?? '') === 'video';
          $caption = trim((string) ($item['caption'] ?? ''));
          ?>
        <figure class="profile-media-item<?= $is_video ? ' is-video' : '' ?>">
          <?php if ($is_video) : ?>
            <video src="<?= casting_e($url) ?>" controls preload="metadata" playsinline<?= $thumb !== '' && $thumb !== $url ? ' poster="' . casting_e($thumb) . '"' : '' ?>></video>
          <?php else : ?>
            <a href="<?= casting_e($url) ?>" target="_blank" rel="noopener">
              <img src="<?= casting_e($thumb !== '' ? $thumb : $url) ?>" alt="" loading="lazy">
            </a>
          <?php endif; ?>
          <?php if ($caption !== '') : ?>
            <figcaption class="profile-media-caption">
              <p><?= nl2br(casting_e($caption)) ?></p>
            </figcaption>
          <?php endif; ?>
          <?php
          $viewer = casting_current_user();
          casting_render_media_engagement((int) ($item['id'] ?? 0), $viewer ? (int) $viewer->ID : 0, false);
          ?>
        </figure>
      <?php endforeach; ?>
    </div>
  </section>
    <?php
}

/**
 * پست‌هایی که این مدیر تأیید کرده (برای نمایش در پروفایل مدیر)
 *
 * @return list<array<string, mixed>>
 */
function casting_user_media_approved_by(int $admin_id, int $limit = 40): array
{
    if ($admin_id <= 0) {
        return [];
    }
    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    $limit = max(1, min(100, $limit));
    $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - 30 * DAY_IN_SECONDS);
    // تأییدشده‌های فعال + حذف‌شده‌های کاربر تا ۳۰ روز برای آرشیو پروفایل ادمین
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE reviewed_by = %d
           AND (
             status = 'approved'
             OR (status = 'deleted' AND deleted_at IS NOT NULL AND deleted_at >= %s)
           )
         ORDER BY COALESCE(deleted_at, reviewed_at) DESC, id DESC
         LIMIT %d",
        $admin_id,
        $cutoff,
        $limit
    ), ARRAY_A);

    return is_array($rows) ? $rows : [];
}

function casting_render_admin_approved_media_section(int $admin_id): void
{
    if (!function_exists('casting_user_has_admin_permission')) {
        require_once __DIR__ . '/admin-access.php';
    }
    if (!casting_user_has_admin_permission($admin_id, 'approve_media') && !casting_user_is_super_admin($admin_id)) {
        return;
    }
    $items = casting_user_media_approved_by($admin_id, 24);
    if ($items === []) {
        return;
    }
    $approver = casting_user_media_approver_label($admin_id);
    ?>
  <section class="profile-media-gallery profile-media-gallery--approvals" aria-label="پست‌های تأییدشده">
    <h3 class="panel-section-title">تأییدهای <?= casting_e($approver !== '' ? $approver : 'مدیر') ?></h3>
    <p class="meta">پست‌هایی که این مدیر تأیید کرده؛ اگر کاربر حذف کند تا یک ماه اینجا می‌ماند.</p>
    <div class="profile-media-grid">
      <?php foreach ($items as $item) :
          $url = casting_user_media_url($item);
          $thumb = casting_user_media_thumb_url($item);
          if ($url === '') {
              continue;
          }
          $owner = get_user_by('id', (int) ($item['user_id'] ?? 0));
          $caption = trim((string) ($item['caption'] ?? ''));
          $is_deleted = (($item['status'] ?? '') === 'deleted');
          ?>
        <figure class="profile-media-item<?= $is_deleted ? ' is-user-deleted' : '' ?>">
          <a href="<?= casting_e($url) ?>" target="_blank" rel="noopener">
            <img src="<?= casting_e($thumb !== '' ? $thumb : $url) ?>" alt="" loading="lazy">
          </a>
          <figcaption class="profile-media-caption">
            <?php if ($owner) : ?>
              <p><strong><?= casting_e((string) $owner->display_name) ?></strong></p>
            <?php endif; ?>
            <?php if ($caption !== '') : ?>
              <p><?= nl2br(casting_e($caption)) ?></p>
            <?php endif; ?>
            <?php if ($is_deleted) : ?>
              <p class="meta profile-media-deleted">حذف‌شده توسط کاربر · تا یک ماه در آرشیو ادمین</p>
            <?php else : ?>
              <p class="meta profile-media-approver"><?= casting_e(casting_user_media_approver_line($item)) ?></p>
            <?php endif; ?>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </section>
    <?php
}
