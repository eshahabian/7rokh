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

function casting_opportunities_install(): void
{
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $ops = casting_opportunities_table();
    $apps = casting_opportunity_applications_table();
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

    update_option('casting_opportunities_db_version', '1');
}

function casting_opportunities_ensure_tables(): void
{
    $ver = (string) get_option('casting_opportunities_db_version', '');
    if ($ver !== '1') {
        casting_opportunities_install();
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
    if (!casting_user_can_admin_delete_opportunity($admin_id)) {
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
