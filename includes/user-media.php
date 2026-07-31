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
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        media_type VARCHAR(16) NOT NULL DEFAULT 'photo',
        caption TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        sort_order INT NOT NULL DEFAULT 0,
        reject_reason TEXT NULL,
        created_at DATETIME NOT NULL,
        reviewed_at DATETIME NULL,
        reviewed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status),
        KEY user_status (user_id, status),
        KEY media_type (media_type),
        KEY reviewed_by (reviewed_by)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('casting_user_media_db_version', '2');
}

function casting_user_media_ensure_table(): void
{
    if ((string) get_option('casting_user_media_db_version', '') !== '2') {
        casting_user_media_install();
    }
}

function casting_user_can_manage_gallery(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (casting_get_user_role($user_id) !== 'talent') {
        return false;
    }

    return casting_user_uses_actor_portrait_set($user_id);
}

function casting_user_media_status_label(string $status): string
{
    if ($status === 'approved') {
        return 'تأیید شده';
    }
    if ($status === 'rejected') {
        return 'رد شده';
    }

    return 'در انتظار تأیید';
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
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY FIELD(status,'pending','approved','rejected'), sort_order ASC, id DESC LIMIT %d",
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = %s ORDER BY id ASC LIMIT %d",
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
        return ['ok' => false, 'error' => 'گالری فقط برای بازیگران فعال است.'];
    }
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
    $attachment_id = media_handle_upload($field, 0);
    casting_disable_user_upload_dir();
    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'آپلود ناموفق بود: ' . $attachment_id->get_error_message()];
    }

    casting_user_media_ensure_table();
    global $wpdb;
    $table = casting_user_media_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ok = $wpdb->insert($table, [
        'user_id'       => $user_id,
        'attachment_id' => (int) $attachment_id,
        'media_type'    => $media_type,
        'caption'       => casting_user_media_sanitize_caption($caption),
        'status'        => 'pending',
        'sort_order'    => 0,
        'created_at'    => current_time('mysql'),
    ], ['%d', '%d', '%s', '%s', '%s', '%d', '%s']);

    if ($ok === false) {
        wp_delete_attachment((int) $attachment_id, true);

        return ['ok' => false, 'error' => 'ثبت فایل ناموفق بود.'];
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
        return ['ok' => false, 'error' => 'گالری فقط برای بازیگران فعال است.'];
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

    $update = [
        'caption'       => casting_user_media_sanitize_caption($caption),
        'status'        => 'pending',
        'reviewed_at'   => null,
        'reviewed_by'   => 0,
        'reject_reason' => null,
    ];
    $formats = ['%s', '%s', '%s', '%d', '%s'];
    if ($new_attachment > 0) {
        $update['attachment_id'] = $new_attachment;
        $formats[] = '%d';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ok = $wpdb->update($table, $update, ['id' => $media_id], $formats, ['%d']);
    if ($ok === false) {
        if ($new_attachment > 0) {
            wp_delete_attachment($new_attachment, true);
        }

        return ['ok' => false, 'error' => 'ذخیره ویرایش ناموفق بود.'];
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
        "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
        $media_id,
        $user_id
    ), ARRAY_A);
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'مورد پیدا نشد.'];
    }
    $attachment_id = (int) ($row['attachment_id'] ?? 0);
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
    $wpdb->update($table, [
        'status'      => 'approved',
        'reviewed_at' => current_time('mysql'),
        'reviewed_by' => $admin_id,
        'reject_reason' => null,
    ], ['id' => $media_id], ['%s', '%s', '%d', '%s'], ['%d']);

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
    $wpdb->update($table, [
        'status'        => 'rejected',
        'reviewed_at'   => current_time('mysql'),
        'reviewed_by'   => $admin_id,
        'reject_reason' => sanitize_textarea_field($reason),
    ], ['id' => $media_id], ['%s', '%s', '%d', '%s'], ['%d']);

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
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE reviewed_by = %d AND status = 'approved' ORDER BY reviewed_at DESC, id DESC LIMIT %d",
        $admin_id,
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
    <p class="meta">پست‌هایی که این مدیر تأیید و منتشر کرده است.</p>
    <div class="profile-media-grid">
      <?php foreach ($items as $item) :
          $url = casting_user_media_url($item);
          $thumb = casting_user_media_thumb_url($item);
          if ($url === '') {
              continue;
          }
          $owner = get_user_by('id', (int) ($item['user_id'] ?? 0));
          $caption = trim((string) ($item['caption'] ?? ''));
          ?>
        <figure class="profile-media-item">
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
            <p class="meta profile-media-approver"><?= casting_e(casting_user_media_approver_line($item)) ?></p>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </section>
    <?php
}
