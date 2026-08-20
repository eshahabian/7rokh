<?php
declare(strict_types=1);

/**
 * فید عمومی فرصت‌ها / فراخوان‌های باز + اپلای (الهام Backstage)
 */

function casting_opportunities_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_open_opportunities';
}

function casting_opportunity_applications_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_opportunity_applications';
}

function casting_opportunity_saved_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'casting_saved_opportunities';
}

function casting_opportunities_install(): void
{
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $ops = casting_opportunities_table();
    $apps = casting_opportunity_applications_table();
    $saved = casting_opportunity_saved_table();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE IF NOT EXISTS {$ops} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        director_id BIGINT UNSIGNED NOT NULL,
        project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        role_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        title VARCHAR(191) NOT NULL DEFAULT '',
        message TEXT NULL,
        project_type VARCHAR(64) NOT NULL DEFAULT '',
        location VARCHAR(191) NOT NULL DEFAULT '',
        role_title VARCHAR(191) NOT NULL DEFAULT '',
        filters_json TEXT NULL,
        cover_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY status_created (status, created_at),
        KEY director_id (director_id),
        KEY project_id (project_id)
    ) {$charset};");

    dbDelta("CREATE TABLE IF NOT EXISTS {$apps} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        opportunity_id BIGINT UNSIGNED NOT NULL,
        talent_id BIGINT UNSIGNED NOT NULL,
        note TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY opp_talent (opportunity_id, talent_id),
        KEY talent_id (talent_id),
        KEY status (status)
    ) {$charset};");

    dbDelta("CREATE TABLE IF NOT EXISTS {$saved} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        opportunity_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_opp (user_id, opportunity_id),
        KEY opportunity_id (opportunity_id),
        KEY user_created (user_id, created_at)
    ) {$charset};");

    update_option('casting_opportunities_db_version', '3');
}

function casting_opportunities_ensure_cover_column(): void
{
    global $wpdb;
    $table = casting_opportunities_table();
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
    if (!in_array('cover_attachment_id', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN cover_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER filters_json");
    }
}

function casting_opportunities_ensure_tables(): void
{
    $ver = (string) get_option('casting_opportunities_db_version', '');
    if ($ver !== '3') {
        casting_opportunities_install();
        casting_opportunities_ensure_cover_column();
    }
    casting_opportunities_purge_emad_once();
}

/**
 * @return array<string, string>
 */
function casting_opportunity_application_status_labels(): array
{
    return [
        'pending'     => 'در انتظار بررسی',
        'shortlisted' => 'فهرست کوتاه',
        'accepted'    => 'پذیرفته‌شده',
        'rejected'    => 'ردشده',
        'withdrawn'   => 'انصراف',
    ];
}

/**
 * پوشه‌های Application Manager
 *
 * @return array<string, string>
 */
function casting_opportunity_application_folder_labels(): array
{
    return [
        'pending'     => 'برای بررسی',
        'shortlisted' => 'فهرست کوتاه',
        'accepted'    => 'پذیرفته',
        'rejected'    => 'رد شده',
    ];
}

/**
 * @param list<array<string, mixed>> $applicants
 * @return array<string, int>
 */
function casting_opportunity_application_folder_counts(array $applicants): array
{
    $counts = [
        'pending'     => 0,
        'shortlisted' => 0,
        'accepted'    => 0,
        'rejected'    => 0,
        'all'         => 0,
    ];
    foreach ($applicants as $app) {
        $status = (string) ($app['status'] ?? 'pending');
        if ($status === 'withdrawn') {
            continue;
        }
        $counts['all']++;
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }

    return $counts;
}

/**
 * غنی‌سازی متقاضیان با عکس و مشخصات پروفایل
 *
 * @param list<array<string, mixed>> $applicants
 * @return list<array<string, mixed>>
 */
function casting_opportunity_enrich_applicants(array $applicants): array
{
    if (!function_exists('casting_get_profile')) {
        require_once __DIR__ . '/profile.php';
    }
    $out = [];
    foreach ($applicants as $app) {
        $tid = (int) ($app['talent_id'] ?? 0);
        if ($tid <= 0) {
            continue;
        }
        $user = get_user_by('id', $tid);
        if (!$user) {
            continue;
        }
        $profile = casting_get_profile($tid);
        $app['display_name'] = (string) $user->display_name;
        $app['photo_url'] = (string) ($profile['photo_url'] ?? '');
        $app['city'] = trim((string) ($profile['city'] ?? ''));
        $app['age'] = (int) ($profile['age'] ?? 0);
        $app['role_label'] = casting_user_public_role_label($tid);
        $out[] = $app;
    }

    return $out;
}

/**
 * انتشار فراخوان باز در فید عمومی
 *
 * @param array<string, mixed> $filters
 * @return array{ok:bool,error:string,id?:int}
 */
function casting_opportunity_publish(
    int $director_id,
    int $project_id,
    string $message,
    array $filters = [],
    int $role_id = 0
): array {
    casting_opportunities_ensure_tables();
    if ($director_id <= 0 || $project_id <= 0) {
        return ['ok' => false, 'error' => 'پروژه معتبر نیست.'];
    }
    if (!function_exists('casting_director_get_project')) {
        require_once __DIR__ . '/director-desk.php';
    }
    $project = casting_director_get_project($director_id, $project_id);
    if (!$project) {
        return ['ok' => false, 'error' => 'پروژه پیدا نشد.'];
    }

    $message = sanitize_textarea_field($message);
    if ($message === '') {
        return ['ok' => false, 'error' => 'متن فراخوان را بنویسید.'];
    }
    if (casting_strlen($message) > 3000) {
        return ['ok' => false, 'error' => 'متن فراخوان خیلی بلند است.'];
    }

    $role_title = '';
    if ($role_id > 0) {
        $role = casting_director_get_role($director_id, $role_id);
        if (!$role || (int) ($role['project_id'] ?? 0) !== $project_id) {
            return ['ok' => false, 'error' => 'نقش معتبر نیست.'];
        }
        $role_title = (string) ($role['title'] ?? '');
    }
    if ($role_title === '' && !empty($filters['activity_specialty'])) {
        $labels = casting_activity_labels();
        $role_title = (string) ($labels[sanitize_key((string) $filters['activity_specialty'])] ?? '');
    } elseif ($role_title === '' && !empty($filters['activity_category'])) {
        $cats = casting_activity_categories();
        $role_title = (string) ($cats[sanitize_key((string) $filters['activity_category'])]['label'] ?? '');
    }

    $types = casting_director_project_type_labels();
    $ptype_key = sanitize_key((string) ($project['project_type'] ?? ''));
    $now = current_time('mysql');

    global $wpdb;
    $ok = $wpdb->insert(
        casting_opportunities_table(),
        [
            'director_id'  => $director_id,
            'project_id'   => $project_id,
            'role_id'      => max(0, $role_id),
            'title'        => (string) ($project['title'] ?? 'فراخوان کستینگ'),
            'message'      => $message,
            'project_type' => $types[$ptype_key] ?? (string) ($project['project_type'] ?? ''),
            'location'     => (string) ($project['location'] ?? ''),
            'role_title'   => $role_title,
            'filters_json' => wp_json_encode($filters, JSON_UNESCAPED_UNICODE),
            'status'       => 'open',
            'created_at'   => $now,
            'updated_at'   => $now,
        ],
        ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    if (!$ok) {
        return ['ok' => false, 'error' => 'ثبت فراخوان در فید عمومی ناموفق بود.'];
    }

    return ['ok' => true, 'error' => '', 'id' => (int) $wpdb->insert_id];
}

function casting_user_can_create_opportunity(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (!function_exists('casting_user_can_send_casting_requests')) {
        require_once __DIR__ . '/request.php';
    }

    return casting_user_can_send_casting_requests($user_id);
}

/**
 * @return array{ok:bool,error:string,id?:int}
 */
function casting_opportunity_handle_cover_upload(int $user_id): array
{
    if (empty($_FILES['opp_cover']['name'])) {
        return ['ok' => true, 'error' => '', 'id' => 0];
    }
    if (!function_exists('casting_media_handle_upload_as_user')) {
        require_once __DIR__ . '/profile.php';
    }
    $file = &$_FILES['opp_cover'];
    $norm = casting_normalize_uploaded_file_type($file, 'image');
    if (!$norm['ok']) {
        return ['ok' => false, 'error' => (string) ($norm['error'] ?? 'فایل عکس نامعتبر است.')];
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $ftype = (string) ($norm['type'] ?? '');
    if (!in_array($ftype, $allowed, true)) {
        return ['ok' => false, 'error' => 'فقط عکس JPG، PNG یا WebP مجاز است.'];
    }
    $size_check = casting_uploaded_file_within_limit($file, 'image');
    if (!$size_check['ok']) {
        return ['ok' => false, 'error' => (string) $size_check['error']];
    }
    $attachment_id = casting_media_handle_upload_as_user('opp_cover', $user_id);
    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'آپلود عکس ناموفق بود.'];
    }

    return ['ok' => true, 'error' => '', 'id' => (int) $attachment_id];
}

function casting_opportunity_cover_url(array $op, string $size = 'medium'): string
{
    $id = (int) ($op['cover_attachment_id'] ?? 0);
    if ($id <= 0) {
        return '';
    }
    $url = wp_get_attachment_image_url($id, $size);
    if (!is_string($url) || $url === '') {
        $url = wp_get_attachment_image_url($id, 'full');
    }

    return is_string($url) ? $url : '';
}

/**
 * ثبت فرصت مستقیم از صفحهٔ فرصت‌ها
 *
 * @param array<string, mixed> $input
 * @return array{ok:bool,error:string,id?:int}
 */
function casting_opportunity_create_from_board(int $user_id, array $input): array
{
    if (!casting_user_can_create_opportunity($user_id)) {
        return ['ok' => false, 'error' => 'برای ثبت فرصت باید کارگردان، تهیه‌کننده یا مدیر پورتال باشید.'];
    }
    casting_opportunities_ensure_tables();

    $title = sanitize_text_field((string) ($input['title'] ?? ''));
    $role_title = sanitize_text_field((string) ($input['role_title'] ?? ''));
    $location = sanitize_text_field((string) ($input['location'] ?? ''));
    $message = sanitize_textarea_field((string) ($input['message'] ?? ''));
    $type_key = sanitize_key((string) ($input['project_type'] ?? ''));
    if (!function_exists('casting_director_project_type_labels')) {
        require_once __DIR__ . '/director-desk.php';
    }
    $types = casting_director_project_type_labels();
    unset($types['film'], $types['series'], $types['other']);
    if ($title === '') {
        return ['ok' => false, 'error' => 'عنوان فرصت را بنویسید.'];
    }
    if ($role_title === '') {
        return ['ok' => false, 'error' => 'بنویسید دنبال چه نقشی هستید.'];
    }
    if ($message === '') {
        return ['ok' => false, 'error' => 'توضیح کوتاه فرصت را بنویسید.'];
    }
    if (casting_strlen($message) > 3000) {
        return ['ok' => false, 'error' => 'توضیح خیلی بلند است.'];
    }
    if ($type_key === '' || !isset($types[$type_key])) {
        $type_key = 'theater';
    }

    $cover = casting_opportunity_handle_cover_upload($user_id);
    if (empty($cover['ok'])) {
        return ['ok' => false, 'error' => (string) ($cover['error'] ?? 'آپلود عکس ناموفق بود.')];
    }

    $project_id = 0;
    if (function_exists('casting_user_is_director_role') && casting_user_is_director_role($user_id)) {
        $created = casting_director_create_project($user_id, $title, $type_key, $message);
        if (!empty($created['ok'])) {
            $project_id = (int) ($created['project_id'] ?? 0);
        }
    }

    $now = current_time('mysql');
    global $wpdb;
    $ok = $wpdb->insert(
        casting_opportunities_table(),
        [
            'director_id'         => $user_id,
            'project_id'          => $project_id,
            'role_id'             => 0,
            'title'               => $title,
            'message'             => $message,
            'project_type'        => (string) ($types[$type_key] ?? $type_key),
            'location'            => $location,
            'role_title'          => $role_title,
            'filters_json'        => wp_json_encode(['from_board' => 1], JSON_UNESCAPED_UNICODE),
            'cover_attachment_id' => (int) ($cover['id'] ?? 0),
            'status'              => 'open',
            'created_at'          => $now,
            'updated_at'          => $now,
        ],
        ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
    );
    if (!$ok) {
        return ['ok' => false, 'error' => 'ثبت فرصت ناموفق بود.'];
    }
    $opportunity_id = (int) $wpdb->insert_id;
    if ($opportunity_id > 0) {
        if (!function_exists('casting_store_sent_broadcast_call')) {
            require_once __DIR__ . '/request.php';
        }
        if (function_exists('casting_store_sent_broadcast_call')) {
            $user = get_user_by('id', $user_id);
            casting_store_sent_broadcast_call($user_id, [
                'id'             => 'opp_' . $opportunity_id,
                'employer_id'    => $user_id,
                'talent_id'      => 0,
                'employer'       => $user ? (string) $user->display_name : '',
                'talent_name'    => 'فید عمومی فرصت‌ها',
                'project'        => $title,
                'project_type'   => (string) ($types[$type_key] ?? ''),
                'role_needed'    => $role_title,
                'project_id'     => $project_id,
                'opportunity_id' => $opportunity_id,
                'message'        => $message,
                'created_at'     => $now,
                'status'         => 'public',
                'kind'           => 'casting_call',
            ]);
        }
    }

    return ['ok' => true, 'error' => '', 'id' => $opportunity_id];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_opportunity_close(int $director_id, int $opportunity_id): array
{
    casting_opportunities_ensure_tables();
    $row = casting_opportunity_get($opportunity_id);
    if (!$row || (int) ($row['director_id'] ?? 0) !== $director_id) {
        return ['ok' => false, 'error' => 'فراخوان پیدا نشد.'];
    }
    global $wpdb;
    $wpdb->update(
        casting_opportunities_table(),
        [
            'status'     => 'closed',
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $opportunity_id],
        ['%s', '%s'],
        ['%d']
    );

    return ['ok' => true, 'error' => ''];
}

/**
 * حذف کامل فرصت + اپلای‌ها (برای ادمین یا مالک)
 *
 * @return array{ok:bool,error:string}
 */
function casting_opportunity_delete(int $opportunity_id): array
{
    if ($opportunity_id <= 0) {
        return ['ok' => false, 'error' => 'فراخوان نامعتبر است.'];
    }
    casting_opportunities_ensure_tables();
    $row = casting_opportunity_get($opportunity_id);
    if (!$row) {
        return ['ok' => false, 'error' => 'فراخوان پیدا نشد.'];
    }
    global $wpdb;
    $apps = casting_opportunity_applications_table();
    $ops = casting_opportunities_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->delete($apps, ['opportunity_id' => $opportunity_id], ['%d']);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->delete($ops, ['id' => $opportunity_id], ['%d']);

    return ['ok' => true, 'error' => ''];
}

function casting_user_can_admin_delete_opportunity(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (!function_exists('casting_user_is_super_admin')) {
        require_once __DIR__ . '/admin-access.php';
    }
    if (casting_user_is_super_admin($user_id)) {
        return true;
    }
    if (function_exists('casting_user_staff_permissions') && casting_user_staff_permissions($user_id) !== []) {
        return true;
    }

    return false;
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_admin_delete_opportunity(int $admin_id, int $opportunity_id): array
{
    $row = casting_opportunity_get($opportunity_id);
    $is_owner = is_array($row) && (int) ($row['director_id'] ?? 0) === $admin_id;
    if (!$is_owner && !casting_user_can_admin_delete_opportunity($admin_id)) {
        return ['ok' => false, 'error' => 'اجازه حذف فراخوان را ندارید.'];
    }

    return casting_opportunity_delete($opportunity_id);
}

/**
 * یک‌بار فرصت‌هایی با عنوان دقیق «عماد» را پاک می‌کند.
 */
function casting_opportunities_purge_emad_once(): void
{
    static $running = false;
    if ($running) {
        return;
    }
    if ((string) get_option('casting_purged_opp_emad_v1', '') === '1') {
        return;
    }
    if ((string) get_option('casting_opportunities_db_version', '') !== '1') {
        return;
    }
    global $wpdb;
    $table = casting_opportunities_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        return;
    }
    $running = true;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$table} WHERE TRIM(title) = %s",
        'عماد'
    ));
    if (is_array($ids)) {
        foreach ($ids as $id) {
            casting_opportunity_delete((int) $id);
        }
    }
    update_option('casting_purged_opp_emad_v1', '1', false);
    $running = false;
}

/**
 * @return array<string, mixed>|null
 */
function casting_opportunity_get(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    casting_opportunities_ensure_tables();
    global $wpdb;
    $table = casting_opportunities_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id), ARRAY_A);

    return is_array($row) ? $row : null;
}

/**
 * @return list<array<string, mixed>>
 */
function casting_opportunities_list_open(int $limit = 40, int $offset = 0): array
{
    casting_opportunities_ensure_tables();
    global $wpdb;
    $table = casting_opportunities_table();
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE status = 'open' ORDER BY id DESC LIMIT %d OFFSET %d",
        $limit,
        $offset
    ), ARRAY_A);

    return is_array($rows) ? $rows : [];
}

/**
 * @return list<array<string, mixed>>
 */
function casting_opportunities_list_for_director(int $director_id, int $project_id = 0, int $limit = 50): array
{
    if ($director_id <= 0) {
        return [];
    }
    casting_opportunities_ensure_tables();
    global $wpdb;
    $table = casting_opportunities_table();
    $limit = max(1, min(100, $limit));
    if ($project_id > 0) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE director_id = %d AND project_id = %d ORDER BY id DESC LIMIT %d",
            $director_id,
            $project_id,
            $limit
        ), ARRAY_A);
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE director_id = %d ORDER BY id DESC LIMIT %d",
            $director_id,
            $limit
        ), ARRAY_A);
    }

    return is_array($rows) ? $rows : [];
}

function casting_opportunity_applicant_count(int $opportunity_id): int
{
    if ($opportunity_id <= 0) {
        return 0;
    }
    casting_opportunities_ensure_tables();
    global $wpdb;
    $table = casting_opportunity_applications_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE opportunity_id = %d AND status != 'withdrawn'",
        $opportunity_id
    ));
}

/** تعداد اپلای‌های در انتظار بررسی برای کارگردان (یا یک پروژه) */
function casting_director_pending_applicant_count(int $director_id, int $project_id = 0): int
{
    if ($director_id <= 0) {
        return 0;
    }
    casting_opportunities_ensure_tables();
    global $wpdb;
    $apps = casting_opportunity_applications_table();
    $ops = casting_opportunities_table();
    if ($project_id > 0) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$apps} a
             INNER JOIN {$ops} o ON o.id = a.opportunity_id
             WHERE o.director_id = %d AND o.project_id = %d AND a.status = 'pending'",
            $director_id,
            $project_id
        ));
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$apps} a
         INNER JOIN {$ops} o ON o.id = a.opportunity_id
         WHERE o.director_id = %d AND a.status = 'pending'",
        $director_id
    ));
}

function casting_opportunity_pending_applicant_count(int $opportunity_id): int
{
    if ($opportunity_id <= 0) {
        return 0;
    }
    casting_opportunities_ensure_tables();
    global $wpdb;
    $table = casting_opportunity_applications_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE opportunity_id = %d AND status = 'pending'",
        $opportunity_id
    ));
}

/**
 * @return array<string, mixed>|null
 */
function casting_opportunity_get_application(int $opportunity_id, int $talent_id): ?array
{
    if ($opportunity_id <= 0 || $talent_id <= 0) {
        return null;
    }
    casting_opportunities_ensure_tables();
    global $wpdb;
    $table = casting_opportunity_applications_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE opportunity_id = %d AND talent_id = %d LIMIT 1",
        $opportunity_id,
        $talent_id
    ), ARRAY_A);

    return is_array($row) ? $row : null;
}

/**
 * @return list<array<string, mixed>>
 */
function casting_opportunity_list_applicants(int $opportunity_id, int $limit = 100): array
{
    if ($opportunity_id <= 0) {
        return [];
    }
    casting_opportunities_ensure_tables();
    global $wpdb;
    $table = casting_opportunity_applications_table();
    $limit = max(1, min(200, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE opportunity_id = %d AND status != 'withdrawn' ORDER BY id DESC LIMIT %d",
        $opportunity_id,
        $limit
    ), ARRAY_A);

    return is_array($rows) ? $rows : [];
}

/**
 * @return list<array<string, mixed>>
 */
function casting_opportunity_list_my_applications(int $talent_id, int $limit = 40): array
{
    if ($talent_id <= 0) {
        return [];
    }
    casting_opportunities_ensure_tables();
    global $wpdb;
    $apps = casting_opportunity_applications_table();
    $ops = casting_opportunities_table();
    $limit = max(1, min(100, $limit));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, o.title AS opp_title, o.project_type, o.location, o.role_title, o.status AS opp_status, o.director_id
         FROM {$apps} a
         INNER JOIN {$ops} o ON o.id = a.opportunity_id
         WHERE a.talent_id = %d
         ORDER BY a.id DESC
         LIMIT %d",
        $talent_id,
        $limit
    ), ARRAY_A);

    return is_array($rows) ? $rows : [];
}

/**
 * اپلای روی فراخوان باز
 *
 * @return array{ok:bool,error:string}
 */
function casting_opportunity_apply(int $talent_id, int $opportunity_id, string $note = ''): array
{
    casting_opportunities_ensure_tables();
    if ($talent_id <= 0 || $opportunity_id <= 0) {
        return ['ok' => false, 'error' => 'درخواست نامعتبر است.'];
    }
    if (casting_get_user_role($talent_id) === '') {
        return ['ok' => false, 'error' => 'فقط اعضای ۷ رخ می‌توانند اپلای کنند.'];
    }

    $opp = casting_opportunity_get($opportunity_id);
    if (!$opp || (string) ($opp['status'] ?? '') !== 'open') {
        return ['ok' => false, 'error' => 'این فراخوان باز نیست.'];
    }
    $director_id = (int) ($opp['director_id'] ?? 0);
    if ($director_id === $talent_id) {
        return ['ok' => false, 'error' => 'نمی‌توانید روی فراخوان خودتان اپلای کنید.'];
    }
    if (function_exists('casting_users_block_each_other') && casting_users_block_each_other($talent_id, $director_id)) {
        return ['ok' => false, 'error' => 'به‌دلیل بلاک، امکان اپلای نیست.'];
    }

    $existing = casting_opportunity_get_application($opportunity_id, $talent_id);
    if ($existing && (string) ($existing['status'] ?? '') !== 'withdrawn') {
        return ['ok' => false, 'error' => 'قبلاً برای این فراخوان اپلای کرده‌اید.'];
    }

    $note = sanitize_textarea_field($note);
    if (casting_strlen($note) > 1000) {
        return ['ok' => false, 'error' => 'یادداشت خیلی بلند است.'];
    }

    $now = current_time('mysql');
    global $wpdb;
    $table = casting_opportunity_applications_table();

    if ($existing) {
        $wpdb->update(
            $table,
            [
                'note'       => $note,
                'status'     => 'pending',
                'updated_at' => $now,
            ],
            ['id' => (int) $existing['id']],
            ['%s', '%s', '%s'],
            ['%d']
        );
    } else {
        $ok = $wpdb->insert(
            $table,
            [
                'opportunity_id' => $opportunity_id,
                'talent_id'      => $talent_id,
                'note'           => $note,
                'status'         => 'pending',
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
        if (!$ok) {
            return ['ok' => false, 'error' => 'ثبت اپلای ناموفق بود.'];
        }
    }

    // اگر نقش مشخص است، به پایپ‌لاین میز کارگردان هم اضافه شود
    $role_id = (int) ($opp['role_id'] ?? 0);
    if ($role_id > 0 && function_exists('casting_director_add_talent_to_role')) {
        $add = casting_director_add_talent_to_role($director_id, $role_id, $talent_id);
        if (!empty($add['ok']) && $note !== '') {
            casting_director_save_role_talent($director_id, $role_id, $talent_id, [
                'notes'  => $note,
                'status' => 'candidate',
            ]);
        }
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_opportunity_withdraw(int $talent_id, int $opportunity_id): array
{
    $app = casting_opportunity_get_application($opportunity_id, $talent_id);
    if (!$app) {
        return ['ok' => false, 'error' => 'اپلایی پیدا نشد.'];
    }
    global $wpdb;
    $wpdb->update(
        casting_opportunity_applications_table(),
        [
            'status'     => 'withdrawn',
            'updated_at' => current_time('mysql'),
        ],
        ['id' => (int) $app['id']],
        ['%s', '%s'],
        ['%d']
    );

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_opportunity_set_application_status(int $director_id, int $application_id, string $status): array
{
    $status = sanitize_key($status);
    if (!isset(casting_opportunity_application_status_labels()[$status]) || $status === 'withdrawn') {
        return ['ok' => false, 'error' => 'وضعیت نامعتبر است.'];
    }
    casting_opportunities_ensure_tables();
    global $wpdb;
    $apps = casting_opportunity_applications_table();
    $ops = casting_opportunities_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT a.*, o.director_id, o.role_id
         FROM {$apps} a
         INNER JOIN {$ops} o ON o.id = a.opportunity_id
         WHERE a.id = %d LIMIT 1",
        $application_id
    ), ARRAY_A);
    if (!is_array($row) || (int) ($row['director_id'] ?? 0) !== $director_id) {
        return ['ok' => false, 'error' => 'اپلای پیدا نشد.'];
    }
    $wpdb->update(
        $apps,
        [
            'status'     => $status,
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $application_id],
        ['%s', '%s'],
        ['%d']
    );

    // همگام‌سازی با پایپ‌لاین نقش در میز کارگردان
    $role_id = (int) ($row['role_id'] ?? 0);
    $talent_id = (int) ($row['talent_id'] ?? 0);
    if ($role_id > 0 && $talent_id > 0) {
        if (!function_exists('casting_director_save_role_talent')) {
            require_once __DIR__ . '/director-desk.php';
        }
        $role_status_map = [
            'pending'     => 'candidate',
            'shortlisted' => 'shortlisted',
            'accepted'    => 'selected',
            'rejected'    => 'rejected',
        ];
        $role_status = $role_status_map[$status] ?? 'candidate';
        casting_director_save_role_talent($director_id, $role_id, $talent_id, [
            'status' => $role_status,
        ]);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * تغییر گروهی وضعیت اپلای‌ها
 *
 * @param list<int> $application_ids
 * @return array{ok:bool,error:string,updated:int}
 */
function casting_opportunity_bulk_set_application_status(int $director_id, array $application_ids, string $status): array
{
    $updated = 0;
    foreach ($application_ids as $app_id) {
        $app_id = (int) $app_id;
        if ($app_id <= 0) {
            continue;
        }
        $res = casting_opportunity_set_application_status($director_id, $app_id, $status);
        if (!empty($res['ok'])) {
            $updated++;
        }
    }
    if ($updated <= 0) {
        return ['ok' => false, 'error' => 'هیچ اپلایی به‌روز نشد.', 'updated' => 0];
    }

    return ['ok' => true, 'error' => '', 'updated' => $updated];
}

function casting_opportunity_format_date(string $mysql): string
{
    $mysql = trim($mysql);
    if ($mysql === '' || !function_exists('casting_gregorian_to_jalali')) {
        return $mysql;
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $mysql, $m)) {
        return $mysql;
    }
    [$jy, $jm, $jd] = casting_gregorian_to_jalali((int) $m[1], (int) $m[2], (int) $m[3]);

    return sprintf('%d/%02d/%02d', $jy, $jm, $jd);
}

/**
 * کلید یکدست نوع پروژه برای فیلتر و چیپ
 */
function casting_opportunity_type_key(string $stored): string
{
    $stored = trim($stored);
    if ($stored === '') {
        return '';
    }
    if (!function_exists('casting_director_project_type_labels')) {
        require_once __DIR__ . '/director-desk.php';
    }
    $labels = casting_director_project_type_labels();
    $key = sanitize_key($stored);
    if (isset($labels[$key])) {
        if ($key === 'film') {
            return 'cinema';
        }
        if ($key === 'series') {
            return 'tv';
        }

        return $key;
    }
    foreach ($labels as $k => $label) {
        if ($stored === $label) {
            if ($k === 'film') {
                return 'cinema';
            }
            if ($k === 'series') {
                return 'tv';
            }

            return $k;
        }
    }
    if (str_contains($stored, 'تئاتر')) {
        return 'theater';
    }
    if (str_contains($stored, 'کوتاه')) {
        return 'short_film';
    }
    if (str_contains($stored, 'سینما')) {
        return 'cinema';
    }
    if (str_contains($stored, 'تلویزیون') || str_contains($stored, 'سریال')) {
        return 'tv';
    }

    return 'other';
}

/**
 * @return array<string, string>
 */
function casting_opportunity_filter_type_labels(): array
{
    return [
        'theater'    => 'تئاتر',
        'short_film' => 'فیلم کوتاه',
        'cinema'     => 'سینمایی',
        'tv'         => 'تلویزیونی',
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function casting_opportunities_filter_by_type(array $rows, string $type_key): array
{
    $type_key = sanitize_key($type_key);
    if ($type_key === '' || $type_key === 'all') {
        return $rows;
    }
    $allowed = casting_opportunity_filter_type_labels();
    if (!isset($allowed[$type_key])) {
        return $rows;
    }
    $out = [];
    foreach ($rows as $row) {
        if (casting_opportunity_type_key((string) ($row['project_type'] ?? '')) === $type_key) {
            $out[] = $row;
        }
    }

    return $out;
}

function casting_opportunity_excerpt(string $message, int $max = 140): string
{
    $message = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
    if ($message === '') {
        return '';
    }
    $len = function_exists('casting_strlen') ? casting_strlen($message) : mb_strlen($message);
    if ($len <= $max) {
        return $message;
    }
    $cut = function_exists('mb_substr') ? mb_substr($message, 0, $max) : substr($message, 0, $max);

    return rtrim((string) $cut) . '…';
}

/**
 * برچسب تازگی برای اسکن سریع فید
 */
function casting_opportunity_freshness_label(string $mysql): string
{
    $mysql = trim($mysql);
    if ($mysql === '') {
        return '';
    }
    $ts = strtotime($mysql);
    if ($ts === false) {
        return '';
    }
    $days = (int) floor((time() - $ts) / DAY_IN_SECONDS);
    if ($days <= 0) {
        return 'امروز';
    }
    if ($days === 1) {
        return 'دیروز';
    }
    if ($days < 7) {
        return $days . ' روز پیش';
    }
    if ($days < 30) {
        $weeks = (int) floor($days / 7);

        return $weeks . ' هفته پیش';
    }

    return casting_opportunity_format_date($mysql);
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function casting_opportunities_sort_list(array $rows, string $sort, int $user_id = 0): array
{
    $sort = sanitize_key($sort);
    if ($sort !== 'relevant') {
        usort($rows, static function (array $a, array $b): int {
            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        return $rows;
    }

    $hay = '';
    if ($user_id > 0 && function_exists('casting_get_profile')) {
        $profile = casting_get_profile($user_id);
        $bits = [
            (string) ($profile['primary_activity'] ?? ''),
            (string) ($profile['specialty'] ?? ''),
            (string) ($profile['city'] ?? ''),
            (string) ($profile['category'] ?? ''),
        ];
        if (!empty($profile['activities']) && is_array($profile['activities'])) {
            foreach ($profile['activities'] as $act) {
                $bits[] = (string) $act;
            }
        }
        if (function_exists('casting_user_primary_activity_label')) {
            $bits[] = casting_user_primary_activity_label($user_id);
        }
        $hay = mb_strtolower(implode(' ', array_filter(array_map('strval', $bits))), 'UTF-8');
    }

    $score = static function (array $row) use ($hay): int {
        $s = 0;
        $blob = mb_strtolower(trim(
            (string) ($row['title'] ?? '') . ' ' .
            (string) ($row['project_type'] ?? '') . ' ' .
            (string) ($row['role_title'] ?? '') . ' ' .
            (string) ($row['location'] ?? '')
        ), 'UTF-8');
        if ($hay === '' || $blob === '') {
            return (int) ($row['id'] ?? 0);
        }
        foreach (preg_split('/\s+/u', $hay) ?: [] as $token) {
            $token = trim((string) $token);
            if (function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') < 2 : strlen($token) < 2) {
                continue;
            }
            if (str_contains($blob, $token)) {
                $s += 10;
            }
        }
        $type = casting_opportunity_type_key((string) ($row['project_type'] ?? ''));
        if ($type !== '' && str_contains($hay, $type)) {
            $s += 8;
        }

        return ($s * 100000) + (int) ($row['id'] ?? 0);
    };

    usort($rows, static function (array $a, array $b) use ($score): int {
        return $score($b) <=> $score($a);
    });

    return $rows;
}

function casting_opportunity_is_saved(int $user_id, int $opportunity_id): bool
{
    if ($user_id <= 0 || $opportunity_id <= 0) {
        return false;
    }
    global $wpdb;
    casting_opportunities_ensure_tables();
    $table = casting_opportunity_saved_table();
    $id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE user_id = %d AND opportunity_id = %d LIMIT 1",
        $user_id,
        $opportunity_id
    ));

    return $id > 0;
}

/**
 * @return array{ok:bool,error:string,saved:bool}
 */
function casting_opportunity_toggle_saved(int $user_id, int $opportunity_id): array
{
    if ($user_id <= 0 || $opportunity_id <= 0) {
        return ['ok' => false, 'error' => 'درخواست نامعتبر است.', 'saved' => false];
    }
    $opp = casting_opportunity_get($opportunity_id);
    if (!$opp) {
        return ['ok' => false, 'error' => 'فراخوان پیدا نشد.', 'saved' => false];
    }
    global $wpdb;
    casting_opportunities_ensure_tables();
    $table = casting_opportunity_saved_table();
    if (casting_opportunity_is_saved($user_id, $opportunity_id)) {
        $wpdb->delete($table, ['user_id' => $user_id, 'opportunity_id' => $opportunity_id], ['%d', '%d']);

        return ['ok' => true, 'error' => '', 'saved' => false];
    }
    $ok = $wpdb->insert(
        $table,
        [
            'user_id'        => $user_id,
            'opportunity_id' => $opportunity_id,
            'created_at'     => current_time('mysql'),
        ],
        ['%d', '%d', '%s']
    );
    if (!$ok) {
        return ['ok' => false, 'error' => 'ذخیره انجام نشد.', 'saved' => false];
    }

    return ['ok' => true, 'error' => '', 'saved' => true];
}

/**
 * @return list<array<string, mixed>>
 */
function casting_opportunity_list_saved(int $user_id, int $limit = 40): array
{
    if ($user_id <= 0) {
        return [];
    }
    global $wpdb;
    casting_opportunities_ensure_tables();
    $saved = casting_opportunity_saved_table();
    $ops = casting_opportunities_table();
    $limit = max(1, min(100, $limit));
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT o.* FROM {$saved} s
             INNER JOIN {$ops} o ON o.id = s.opportunity_id
             WHERE s.user_id = %d
             ORDER BY s.id DESC
             LIMIT %d",
            $user_id,
            $limit
        ),
        ARRAY_A
    );

    return is_array($rows) ? $rows : [];
}

/**
 * @param array<string, mixed> $op
 * @return list<array{label:string,kind:string}>
 */
function casting_opportunity_card_chips(array $op): array
{
    $chips = [];
    $fresh = casting_opportunity_freshness_label((string) ($op['created_at'] ?? ''));
    if ($fresh !== '') {
        $chips[] = ['label' => $fresh, 'kind' => 'fresh'];
    }
    $type_key = casting_opportunity_type_key((string) ($op['project_type'] ?? ''));
    $type_labels = casting_opportunity_filter_type_labels();
    if ($type_key !== '' && isset($type_labels[$type_key])) {
        $chips[] = ['label' => $type_labels[$type_key], 'kind' => 'type'];
    } elseif (trim((string) ($op['project_type'] ?? '')) !== '') {
        $chips[] = ['label' => (string) $op['project_type'], 'kind' => 'type'];
    }
    $role = trim((string) ($op['role_title'] ?? ''));
    if ($role !== '') {
        $chips[] = ['label' => $role, 'kind' => 'role'];
    }
    $location = trim((string) ($op['location'] ?? ''));
    if ($location !== '') {
        $chips[] = ['label' => $location, 'kind' => 'place'];
    }

    return $chips;
}

/**
 * @param array<string, mixed> $op
 * @param array{compact?:bool,expanded?:bool,already?:bool,status_label?:string,is_own?:bool,can_admin_delete?:bool,show_message?:bool,saved?:bool,show_save?:bool,list_query?:array<string,scalar>} $ctx
 */
function casting_render_opportunity_card(array $op, array $ctx = []): void
{
    $oid = (int) ($op['id'] ?? 0);
    $compact = !empty($ctx['compact']);
    $expanded = !empty($ctx['expanded']);
    $already = !empty($ctx['already']);
    $is_own = !empty($ctx['is_own']);
    $can_admin_delete = !empty($ctx['can_admin_delete']);
    $show_message = array_key_exists('show_message', $ctx) ? !empty($ctx['show_message']) : !$compact;
    $saved = !empty($ctx['saved']);
    $show_save = array_key_exists('show_save', $ctx) ? !empty($ctx['show_save']) : !$compact && !$is_own;
    $status_label = trim((string) ($ctx['status_label'] ?? ''));
    $list_query = is_array($ctx['list_query'] ?? null) ? $ctx['list_query'] : ['tab' => 'open'];
    $director_id = (int) ($op['director_id'] ?? 0);
    $director = $director_id > 0 ? get_user_by('id', $director_id) : null;
    $chips = casting_opportunity_card_chips($op);
    if ($status_label !== '') {
        array_unshift($chips, ['label' => $status_label, 'kind' => 'status']);
    } elseif ($already) {
        array_unshift($chips, ['label' => 'اپلای شده', 'kind' => 'status']);
    }
    $type_key = casting_opportunity_type_key((string) ($op['project_type'] ?? ''));
    $message = trim((string) ($op['message'] ?? ''));
    $excerpt = casting_opportunity_excerpt($message, $compact ? 96 : 160);
    $classes = 'opp-card home-opportunity-card';
    if ($expanded) {
        $classes .= ' is-expanded is-unread';
    }
    if ($compact) {
        $classes .= ' opp-card--compact';
    }
    if ($already) {
        $classes .= ' opp-card--applied';
    }
    if ($saved) {
        $classes .= ' opp-card--saved';
    }
    $return_qs = http_build_query(array_merge($list_query, ['id' => $oid]));
    $cover_url = casting_opportunity_cover_url($op);
    $project_id = (int) ($op['project_id'] ?? 0);
    ?>
  <article class="<?= casting_e($classes) ?>" id="opp-<?= $oid ?>"<?= $type_key !== '' ? ' data-opp-type="' . casting_e($type_key) . '"' : '' ?>>
    <?php if ($cover_url !== '') : ?>
      <div class="opp-card-cover">
        <img src="<?= casting_e($cover_url) ?>" alt="" width="240" height="240">
      </div>
    <?php endif; ?>
    <div class="opp-card-main home-opportunity-body">
      <?php if ($chips !== []) : ?>
        <div class="opp-card-chips" aria-label="خلاصه فراخوان">
          <?php foreach ($chips as $chip) : ?>
            <span class="opp-chip opp-chip--<?= casting_e((string) ($chip['kind'] ?? 'meta')) ?>"><?= casting_e((string) ($chip['label'] ?? '')) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <h3 class="opp-card-title"><?= casting_e((string) ($op['title'] ?? 'فراخوان')) ?></h3>
      <p class="opp-card-meta meta">
        <?php if ($director instanceof WP_User) : ?>
          <?php if (!$compact) : ?>
            <button type="button" class="link-button" data-member-preview="<?= (int) $director->ID ?>"><?= casting_e((string) $director->display_name) ?></button>
          <?php else : ?>
            <?= casting_e((string) $director->display_name) ?>
          <?php endif; ?>
        <?php else : ?>
          کارگردان
        <?php endif; ?>
      </p>
      <?php if ($show_message && $message !== '') : ?>
        <?php if ($expanded) : ?>
          <p class="opp-card-message"><?= nl2br(casting_e($message)) ?></p>
        <?php elseif ($excerpt !== '') : ?>
          <p class="opp-card-excerpt"><?= casting_e($excerpt) ?></p>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($status_label === '' && !$already && !$compact && !$is_own) : ?>
        <p class="opp-card-status home-opportunity-status">باز برای اپلای</p>
      <?php endif; ?>
    </div>
    <div class="opp-card-actions home-opportunity-actions">
      <?php if ($show_save) : ?>
        <form class="opp-save-form" method="post" action="opportunities.php?<?= casting_e($return_qs) ?>#opp-<?= $oid ?>">
          <?php wp_nonce_field('casting_opportunity_apply'); ?>
          <input type="hidden" name="opp_action" value="toggle_save">
          <input type="hidden" name="opportunity_id" value="<?= $oid ?>">
          <button class="btn btn-ghost btn-sm" type="submit"><?= $saved ? 'حذف ذخیره' : 'ذخیره' ?></button>
        </form>
      <?php endif; ?>
      <?php if ($can_admin_delete) : ?>
        <form method="post" action="opportunities.php?tab=open" onsubmit="return confirm('این فراخوان برای همیشه حذف شود؟');">
          <?php wp_nonce_field('casting_opportunity_admin'); ?>
          <input type="hidden" name="opp_action" value="admin_delete">
          <input type="hidden" name="opportunity_id" value="<?= $oid ?>">
          <button class="btn btn-reject btn-sm" type="submit">حذف</button>
        </form>
      <?php endif; ?>
      <?php if ($compact) : ?>
        <a class="btn btn-primary btn-sm" href="<?= casting_e(casting_url('opportunities.php?tab=open&id=' . $oid . '#opp-' . $oid)) ?>">
          <?= $already ? 'مشاهده' : 'اپلای' ?>
        </a>
      <?php elseif ($is_own) : ?>
        <?php
        $manage_href = $project_id > 0
            ? casting_url('director-desk.php?project=' . $project_id . '&opp=' . $oid)
            : casting_url('opportunities.php?tab=posted&id=' . $oid . '#opp-' . $oid);
        ?>
        <a class="btn btn-ghost btn-sm" href="<?= casting_e($manage_href) ?>">متقاضیان</a>
      <?php elseif ($already) : ?>
        <a class="btn btn-ghost btn-sm" href="opportunities.php?tab=mine">مشاهده اپلای</a>
      <?php elseif ($expanded) : ?>
        <form class="form" method="post" action="opportunities.php?<?= casting_e($return_qs) ?>#opp-<?= $oid ?>">
          <?php wp_nonce_field('casting_opportunity_apply'); ?>
          <input type="hidden" name="opp_action" value="apply">
          <input type="hidden" name="opportunity_id" value="<?= $oid ?>">
          <div class="field">
            <label for="note-<?= $oid ?>">یادداشت کوتاه (اختیاری)</label>
            <textarea id="note-<?= $oid ?>" name="note" rows="3" maxlength="1000" placeholder="چرا مناسب این نقش هستید…"></textarea>
          </div>
          <div class="cta-row">
            <button class="btn btn-primary btn-sm" type="submit">ارسال اپلای</button>
            <a class="btn btn-ghost btn-sm" href="opportunities.php?<?= casting_e(http_build_query($list_query)) ?>">انصراف</a>
          </div>
        </form>
      <?php else : ?>
        <a class="btn btn-primary btn-sm" href="opportunities.php?<?= casting_e($return_qs) ?>#opp-<?= $oid ?>">اپلای</a>
      <?php endif; ?>
    </div>
  </article>
    <?php
}

/**
 * @param array<string, mixed> $app
 * @param array<string, string> $status_labels
 */
function casting_render_opportunity_application_card(array $app, array $status_labels): void
{
    $oid = (int) ($app['opportunity_id'] ?? 0);
    $st = (string) ($app['status'] ?? 'pending');
    $director = get_user_by('id', (int) ($app['director_id'] ?? 0));
    $pseudo = [
        'id'           => $oid,
        'title'        => (string) ($app['opp_title'] ?? 'فراخوان'),
        'project_type' => (string) ($app['project_type'] ?? ''),
        'role_title'   => (string) ($app['role_title'] ?? ''),
        'location'     => (string) ($app['location'] ?? ''),
        'created_at'   => (string) ($app['created_at'] ?? ''),
        'message'      => '',
        'director_id'  => (int) ($app['director_id'] ?? 0),
        'project_id'   => (int) ($app['project_id'] ?? 0),
    ];
    $chips = casting_opportunity_card_chips($pseudo);
    $status = (string) ($status_labels[$st] ?? $st);
    if ((string) ($app['opp_status'] ?? '') !== 'open') {
        $status .= ' · فراخوان بسته';
    }
    ?>
  <article class="opp-card home-opportunity-card opp-card--application">
    <div class="opp-card-main home-opportunity-body">
      <?php if ($chips !== []) : ?>
        <div class="opp-card-chips" aria-label="خلاصه فراخوان">
          <?php foreach ($chips as $chip) : ?>
            <span class="opp-chip opp-chip--<?= casting_e((string) ($chip['kind'] ?? 'meta')) ?>"><?= casting_e((string) ($chip['label'] ?? '')) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <h3 class="opp-card-title"><?= casting_e((string) ($pseudo['title'] ?? 'فراخوان')) ?></h3>
      <p class="opp-card-meta meta"><?= $director ? casting_e((string) $director->display_name) : 'کارگردان' ?></p>
      <p class="opp-card-status home-opportunity-status"><?= casting_e($status) ?></p>
      <?php if (trim((string) ($app['note'] ?? '')) !== '') : ?>
        <p class="opp-card-excerpt meta"><?= nl2br(casting_e((string) $app['note'])) ?></p>
      <?php endif; ?>
    </div>
    <?php if ($st === 'pending') : ?>
      <div class="opp-card-actions home-opportunity-actions">
        <form method="post" action="opportunities.php?tab=mine" onsubmit="return confirm('اپلای لغو شود؟');">
          <?php wp_nonce_field('casting_opportunity_apply'); ?>
          <input type="hidden" name="opp_action" value="withdraw">
          <input type="hidden" name="opportunity_id" value="<?= $oid ?>">
          <button class="btn btn-ghost btn-sm" type="submit">انصراف</button>
        </form>
      </div>
    <?php endif; ?>
  </article>
    <?php
}

function casting_render_opportunity_type_chips(string $active_type, string $sort = 'newest'): void
{
    $active_type = sanitize_key($active_type);
    if ($active_type === '' || !isset(casting_opportunity_filter_type_labels()[$active_type])) {
        $active_type = 'all';
    }
    $sort = sanitize_key($sort) === 'relevant' ? 'relevant' : 'newest';
    $items = ['all' => 'همه'] + casting_opportunity_filter_type_labels();
    ?>
  <nav class="opp-filter-chips search-quick-chips" aria-label="فیلتر نوع فراخوان">
    <?php foreach ($items as $key => $label) :
        $query = ['tab' => 'open'];
        if ($key !== 'all') {
            $query['type'] = $key;
        }
        if ($sort !== 'newest') {
            $query['sort'] = $sort;
        }
        $href = 'opportunities.php?' . http_build_query($query);
        $is_active = $active_type === $key;
        ?>
      <a class="search-quick-chip<?= $is_active ? ' is-active' : '' ?>" href="<?= casting_e($href) ?>"<?= $is_active ? ' aria-current="page"' : '' ?>><?= casting_e($label) ?></a>
    <?php endforeach; ?>
  </nav>
    <?php
}

function casting_render_opportunity_sort_chips(string $active_sort, string $type_filter = 'all'): void
{
    $active_sort = sanitize_key($active_sort) === 'relevant' ? 'relevant' : 'newest';
    $type_filter = sanitize_key($type_filter);
    if ($type_filter === '' || !isset(casting_opportunity_filter_type_labels()[$type_filter])) {
        $type_filter = 'all';
    }
    $items = [
        'newest'   => 'جدیدترین',
        'relevant' => 'مرتبط',
    ];
    ?>
  <div class="opp-sort" aria-label="مرتب‌سازی">
    <span class="opp-sort-label">مرتب‌سازی:</span>
    <?php foreach ($items as $key => $label) :
        $query = ['tab' => 'open'];
        if ($type_filter !== 'all') {
            $query['type'] = $type_filter;
        }
        if ($key !== 'newest') {
            $query['sort'] = $key;
        }
        $href = 'opportunities.php?' . http_build_query($query);
        $is_active = $active_sort === $key;
        ?>
      <a class="search-quick-chip<?= $is_active ? ' is-active' : '' ?>" href="<?= casting_e($href) ?>"<?= $is_active ? ' aria-current="page"' : '' ?>><?= casting_e($label) ?></a>
    <?php endforeach; ?>
  </div>
    <?php
}

/**
 * @param array<string, string> $values
 */
function casting_render_opportunity_create_form(array $values = [], bool $open = false): void
{
    if (!function_exists('casting_director_project_type_labels')) {
        require_once __DIR__ . '/director-desk.php';
    }
    $types = casting_director_project_type_labels();
    unset($types['film'], $types['series'], $types['other']);
    $title = (string) ($values['title'] ?? '');
    $role_title = (string) ($values['role_title'] ?? '');
    $location = (string) ($values['location'] ?? '');
    $message = (string) ($values['message'] ?? '');
    $project_type = sanitize_key((string) ($values['project_type'] ?? 'theater'));
    if (!isset($types[$project_type])) {
        $project_type = 'theater';
    }
    ?>
  <details class="opp-create" id="opp-create"<?= $open ? ' open' : '' ?>>
    <summary class="opp-create-summary">ایجاد فرصت</summary>
    <p class="field-hint">بنویسید دنبال چه نقشی هستید تا در فید فرصت‌ها دیده شود. عکس پوستر اختیاری است.</p>
    <form class="form" method="post" action="opportunities.php?tab=open#opp-create" enctype="multipart/form-data">
      <?php wp_nonce_field('casting_opportunity_create'); ?>
      <input type="hidden" name="opp_action" value="create">
      <div class="form-grid">
        <div class="field">
          <label for="opp_title">عنوان فرصت</label>
          <input id="opp_title" name="title" type="text" required maxlength="191" value="<?= casting_e($title) ?>" placeholder="مثلاً نام فیلم یا نمایش">
        </div>
        <div class="field">
          <label for="opp_role_title">دنبال چه نقشی هستید؟</label>
          <input id="opp_role_title" name="role_title" type="text" required maxlength="191" value="<?= casting_e($role_title) ?>" placeholder="مثلاً بازیگر نقش اصلی، فیلمبردار…">
        </div>
        <div class="field">
          <label for="opp_project_type">نوع پروژه</label>
          <select id="opp_project_type" name="project_type">
            <?php foreach ($types as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $project_type === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="opp_location">شهر / محل (اختیاری)</label>
          <input id="opp_location" name="location" type="text" maxlength="191" value="<?= casting_e($location) ?>">
        </div>
      </div>
      <div class="field">
        <label for="opp_message">توضیح</label>
        <textarea id="opp_message" name="message" rows="4" required maxlength="3000" placeholder="چه می‌خواهید، شرایط، زمان تست…"><?= casting_e($message) ?></textarea>
      </div>
      <div class="field">
        <label for="opp_cover">عکس پوستر (اختیاری)</label>
        <input id="opp_cover" name="opp_cover" type="file" accept="image/jpeg,image/png,image/webp">
        <p class="field-hint">JPG، PNG یا WebP. اگر نگذارید، فرصت بدون عکس منتشر می‌شود.</p>
      </div>
      <div class="cta-row">
        <button class="btn btn-primary" type="submit">انتشار فرصت</button>
      </div>
    </form>
  </details>
    <?php
}

/**
 * @param list<array<string, mixed>> $applicants
 */
function casting_render_opportunity_posted_applicants(array $applicants): void
{
    if ($applicants === []) {
        echo '<p class="meta">هنوز اپلایی نیامده است.</p>';
        return;
    }
    echo '<ul class="opp-posted-applicants">';
    foreach ($applicants as $app) {
        $tid = (int) ($app['talent_id'] ?? 0);
        $name = (string) ($app['display_name'] ?? 'عضو');
        $photo = (string) ($app['photo_url'] ?? '');
        $city = (string) ($app['city'] ?? '');
        $status = (string) ($app['status'] ?? 'pending');
        $labels = casting_opportunity_application_status_labels();
        echo '<li class="opp-posted-applicant">';
        if ($photo !== '') {
            echo '<img src="' . casting_e($photo) . '" alt="" width="48" height="48">';
        }
        echo '<div><strong>' . casting_e($name) . '</strong>';
        echo '<p class="meta">' . casting_e((string) ($labels[$status] ?? $status));
        if ($city !== '') {
            echo ' · ' . casting_e($city);
        }
        echo '</p></div>';
        if ($tid > 0) {
            echo '<a class="btn btn-ghost btn-sm" href="' . casting_e(casting_url('member.php?id=' . $tid)) . '">پروفایل</a>';
        }
        echo '</li>';
    }
    echo '</ul>';
}
