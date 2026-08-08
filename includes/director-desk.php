<?php
declare(strict_types=1);

require_once __DIR__ . '/director-workspace.php';

function casting_director_projects_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'casting_director_projects';
}

function casting_director_roles_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'casting_director_roles';
}

function casting_director_role_talents_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'casting_director_role_talents';
}

function casting_director_desk_install(): void
{
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $projects = casting_director_projects_table();
    $roles = casting_director_roles_table();
    $role_talents = casting_director_role_talents_table();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE IF NOT EXISTS {$projects} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        director_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(191) NOT NULL DEFAULT '',
        project_type VARCHAR(32) NOT NULL DEFAULT 'film',
        notes TEXT NULL,
        actors_needed INT UNSIGNED NOT NULL DEFAULT 0,
        supporting_needed INT UNSIGNED NOT NULL DEFAULT 0,
        genre VARCHAR(64) NOT NULL DEFAULT '',
        location VARCHAR(191) NOT NULL DEFAULT '',
        shoot_period VARCHAR(191) NOT NULL DEFAULT '',
        duration_label VARCHAR(64) NOT NULL DEFAULT '',
        synopsis TEXT NULL,
        production_status VARCHAR(32) NOT NULL DEFAULT 'planning',
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY director_id (director_id)
    ) {$charset};");

    dbDelta("CREATE TABLE IF NOT EXISTS {$roles} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        project_id BIGINT UNSIGNED NOT NULL,
        director_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(191) NOT NULL DEFAULT '',
        description TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY project_id (project_id),
        KEY director_id (director_id)
    ) {$charset};");

    dbDelta("CREATE TABLE IF NOT EXISTS {$role_talents} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        role_id BIGINT UNSIGNED NOT NULL,
        director_id BIGINT UNSIGNED NOT NULL,
        talent_id BIGINT UNSIGNED NOT NULL,
        notes TEXT NULL,
        ratings TEXT NULL,
        score_avg DECIMAL(4,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'candidate',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY role_talent (role_id, talent_id),
        KEY director_id (director_id),
        KEY role_score (role_id, score_avg)
    ) {$charset};");

    casting_director_workspace_install();
    update_option('casting_director_desk_db_version', '2');
}

function casting_director_desk_ensure_tables(): void
{
    casting_director_desk_install();
    casting_director_workspace_ensure_table();
}

function casting_director_require_director(int $user_id): bool
{
    return casting_user_is_director_role($user_id);
}

/**
 * @return array<string, string>
 */
function casting_director_project_type_labels(): array
{
    return [
        'theater'    => 'تئاتر',
        'short_film' => 'فیلم کوتاه',
        'cinema'     => 'فیلم سینمایی',
        'tv'         => 'تلویزیونی / سریال',
        'film'       => 'فیلم (قدیمی)',
        'series'     => 'سریال (قدیمی)',
        'other'      => 'سایر',
    ];
}

/**
 * @return array<string, string>
 */
function casting_director_production_status_labels(): array
{
    return [
        'planning'      => 'برنامه‌ریزی',
        'casting'       => 'کستینگ',
        'preproduction' => 'پیش‌تولید',
        'production'    => 'تولید / اجرا',
        'post'          => 'پس‌تولید',
        'done'          => 'تمام‌شده',
    ];
}

/**
 * @param array<string, mixed>|null $row
 * @return array<string, mixed>
 */
function casting_director_project_from_row(?array $row): array
{
    if (!$row) {
        return [];
    }

    return [
        'id'                => (int) ($row['id'] ?? 0),
        'director_id'       => (int) ($row['director_id'] ?? 0),
        'title'             => (string) ($row['title'] ?? ''),
        'project_type'      => (string) ($row['project_type'] ?? 'film'),
        'notes'             => (string) ($row['notes'] ?? ''),
        'actors_needed'     => (int) ($row['actors_needed'] ?? 0),
        'supporting_needed' => (int) ($row['supporting_needed'] ?? 0),
        'genre'             => (string) ($row['genre'] ?? ''),
        'location'          => (string) ($row['location'] ?? ''),
        'shoot_period'      => (string) ($row['shoot_period'] ?? ''),
        'duration_label'    => (string) ($row['duration_label'] ?? ''),
        'synopsis'          => (string) ($row['synopsis'] ?? ''),
        'production_status' => (string) ($row['production_status'] ?? 'planning'),
        'created_at'        => (string) ($row['created_at'] ?? ''),
        'updated_at'        => (string) ($row['updated_at'] ?? ''),
    ];
}

/**
 * @param array<string, mixed> $data
 * @return array{ok:bool,error?:string}
 */
function casting_director_save_project(int $director_id, int $project_id, array $data): array
{
    casting_director_desk_ensure_tables();
    if (!casting_director_get_project($director_id, $project_id)) {
        return ['ok' => false, 'error' => 'پروژه پیدا نشد.'];
    }

    $types = casting_director_project_type_labels();
    $statuses = casting_director_production_status_labels();
    $project_type = sanitize_key((string) ($data['project_type'] ?? 'theater'));
    if (!isset($types[$project_type])) {
        $project_type = 'theater';
    }
    $production_status = sanitize_key((string) ($data['production_status'] ?? 'planning'));
    if (!isset($statuses[$production_status])) {
        $production_status = 'planning';
    }

    global $wpdb;
    $wpdb->update(
        casting_director_projects_table(),
        [
            'title'             => sanitize_text_field((string) ($data['title'] ?? '')),
            'project_type'      => $project_type,
            'actors_needed'     => max(0, (int) ($data['actors_needed'] ?? 0)),
            'supporting_needed' => max(0, (int) ($data['supporting_needed'] ?? 0)),
            'genre'             => sanitize_text_field((string) ($data['genre'] ?? '')),
            'location'          => sanitize_text_field((string) ($data['location'] ?? '')),
            'shoot_period'      => sanitize_text_field((string) ($data['shoot_period'] ?? '')),
            'duration_label'    => sanitize_text_field((string) ($data['duration_label'] ?? '')),
            'synopsis'          => sanitize_textarea_field((string) ($data['synopsis'] ?? '')),
            'notes'             => sanitize_textarea_field((string) ($data['notes'] ?? '')),
            'production_status' => $production_status,
            'updated_at'        => current_time('mysql'),
        ],
        [
            'id'          => $project_id,
            'director_id' => $director_id,
        ],
        ['%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        ['%d', '%d']
    );

    return ['ok' => true];
}

/**
 * @return array{roles:int,talents:int}
 */
function casting_director_project_stats(int $director_id, int $project_id): array
{
    global $wpdb;
    $roles = casting_director_list_roles($director_id, $project_id);
    $role_ids = array_map(static fn(array $r): int => (int) ($r['id'] ?? 0), $roles);
    $talents = 0;
    if ($role_ids !== []) {
        $placeholders = implode(',', array_fill(0, count($role_ids), '%d'));
        $sql = 'SELECT COUNT(*) FROM ' . casting_director_role_talents_table()
            . ' WHERE director_id = %d AND role_id IN (' . $placeholders . ')';
        $count = $wpdb->get_var($wpdb->prepare($sql, array_merge([$director_id], $role_ids)));
        $talents = (int) $count;
    }

    return ['roles' => count($roles), 'talents' => $talents];
}

/**
 * تعداد پاسخ/اپلای جدید مرتبط با پروژه‌ها (برای نشان منوی پروژه‌ها)
 * - اپلای‌های در انتظار روی فراخوان‌های عمومی پروژه
 * - پذیرش‌های ندیده‌شدهٔ فراخوان‌های مستقیم (interested)
 */
function casting_director_new_project_response_count(int $director_id, int $project_id = 0): int
{
    if ($director_id <= 0 || !casting_user_is_director_role($director_id)) {
        return 0;
    }

    $count = 0;
    if (!function_exists('casting_director_pending_applicant_count')) {
        require_once __DIR__ . '/opportunities.php';
    }
    $count += casting_director_pending_applicant_count($director_id, $project_id);

    if (!function_exists('casting_user_sent_requests')) {
        require_once __DIR__ . '/request.php';
    }

    $project_title = '';
    if ($project_id > 0) {
        $project = casting_director_get_project($director_id, $project_id);
        $project_title = trim((string) ($project['title'] ?? ''));
    }

    foreach (casting_user_sent_requests($director_id) as $req) {
        if (!is_array($req)) {
            continue;
        }
        if (($req['kind'] ?? '') !== 'casting_call') {
            continue;
        }
        if (casting_request_status_key($req) !== 'interested') {
            continue;
        }
        if ((string) ($req['employer_seen_at'] ?? '') !== '') {
            continue;
        }
        $req_project_id = (int) ($req['project_id'] ?? 0);
        if ($project_id > 0) {
            if ($req_project_id > 0) {
                if ($req_project_id !== $project_id) {
                    continue;
                }
            } elseif ($project_title === '' || trim((string) ($req['project'] ?? '')) !== $project_title) {
                continue;
            }
        }
        $count++;
    }

    return $count;
}

/**
 * @return array<string, string>
 */
function casting_director_rating_criteria(): array
{
    return [
        'dialogue'  => 'گفتار و دیالوگ',
        'role_play' => 'ایفای نقش',
        'emotion'   => 'بازتاب احساس',
        'presence'  => 'حضور صحنه',
        'timing'    => 'ریتم و تنفس',
    ];
}

/**
 * @return array<string, string>
 */
function casting_director_role_talent_status_labels(): array
{
    return [
        'candidate'   => 'نامزد',
        'shortlisted' => 'فهرست کوتاه',
        'selected'    => 'انتخاب‌شده',
        'rejected'    => 'رد شده',
    ];
}

/**
 * @param mixed $raw
 * @return array<string, int>
 */
function casting_director_normalize_ratings($raw): array
{
    $criteria = casting_director_rating_criteria();
    $out = [];
    foreach ($criteria as $key => $label) {
        unset($label);
        $value = 0;
        if (is_array($raw) && array_key_exists($key, $raw)) {
            $value = (int) $raw[$key];
        }
        if ($value < 0) {
            $value = 0;
        }
        if ($value > 10) {
            $value = 10;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * @param array<string, int> $ratings
 */
function casting_director_compute_score_average(array $ratings): float
{
    $values = array_values(array_filter($ratings, static fn(int $v): bool => $v > 0));
    if ($values === []) {
        return 0.0;
    }
    return round(array_sum($values) / count($values), 2);
}

/**
 * @param array<string, mixed>|null $row
 * @return array<string, mixed>
 */
function casting_director_role_talent_from_row(?array $row): array
{
    $ratings = [];
    if ($row && !empty($row['ratings'])) {
        $decoded = json_decode((string) $row['ratings'], true);
        $ratings = casting_director_normalize_ratings($decoded);
    } else {
        $ratings = casting_director_normalize_ratings([]);
    }

    return [
        'id'         => (int) ($row['id'] ?? 0),
        'role_id'    => (int) ($row['role_id'] ?? 0),
        'director_id'=> (int) ($row['director_id'] ?? 0),
        'talent_id'  => (int) ($row['talent_id'] ?? 0),
        'notes'      => (string) ($row['notes'] ?? ''),
        'ratings'    => $ratings,
        'score_avg'  => (float) ($row['score_avg'] ?? 0),
        'status'     => (string) ($row['status'] ?? 'candidate'),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function casting_director_get_project(int $director_id, int $project_id): ?array
{
    casting_director_desk_ensure_tables();
    global $wpdb;
    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM ' . casting_director_projects_table() . ' WHERE id = %d AND director_id = %d LIMIT 1',
            $project_id,
            $director_id
        ),
        ARRAY_A
    );
    if (!is_array($row)) {
        return null;
    }

    return casting_director_project_from_row($row);
}

function casting_director_get_role(int $director_id, int $role_id): ?array
{
    casting_director_desk_ensure_tables();
    global $wpdb;
    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM ' . casting_director_roles_table() . ' WHERE id = %d AND director_id = %d LIMIT 1',
            $role_id,
            $director_id
        ),
        ARRAY_A
    );
    return is_array($row) ? $row : null;
}

/**
 * @return list<array<string, mixed>>
 */
function casting_director_list_projects(int $director_id): array
{
    casting_director_desk_ensure_tables();
    if (!casting_director_require_director($director_id)) {
        return [];
    }

    global $wpdb;
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM ' . casting_director_projects_table() . ' WHERE director_id = %d ORDER BY sort_order ASC, updated_at DESC, id DESC',
            $director_id
        ),
        ARRAY_A
    );

    return is_array($rows) ? $rows : [];
}

/**
 * @return list<array<string, mixed>>
 */
function casting_director_list_roles(int $director_id, int $project_id): array
{
    casting_director_desk_ensure_tables();
    if (!casting_director_get_project($director_id, $project_id)) {
        return [];
    }

    global $wpdb;
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM ' . casting_director_roles_table() . ' WHERE director_id = %d AND project_id = %d ORDER BY sort_order ASC, updated_at DESC, id DESC',
            $director_id,
            $project_id
        ),
        ARRAY_A
    );

    return is_array($rows) ? $rows : [];
}

/**
 * @return list<array<string, mixed>>
 */
function casting_director_list_role_talents(int $director_id, int $role_id): array
{
    casting_director_desk_ensure_tables();
    if (!casting_director_get_role($director_id, $role_id)) {
        return [];
    }

    global $wpdb;
    $table = casting_director_role_talents_table();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM ' . $table . ' WHERE director_id = %d AND role_id = %d ORDER BY score_avg DESC, updated_at DESC, id DESC',
            $director_id,
            $role_id
        ),
        ARRAY_A
    );
    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $item = casting_director_role_talent_from_row($row);
        $talent = get_user_by('id', (int) $item['talent_id']);
        if (!$talent) {
            continue;
        }
        $profile = casting_get_profile((int) $item['talent_id']);
        $item['talent_name'] = (string) $talent->display_name;
        $item['photo_url'] = (string) ($profile['photo_url'] ?? '');
        $item['city'] = (string) ($profile['city'] ?? '');
        $out[] = $item;
    }

    return $out;
}

function casting_director_get_role_talent(int $director_id, int $role_id, int $talent_id): ?array
{
    casting_director_desk_ensure_tables();
    global $wpdb;
    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM ' . casting_director_role_talents_table()
            . ' WHERE director_id = %d AND role_id = %d AND talent_id = %d LIMIT 1',
            $director_id,
            $role_id,
            $talent_id
        ),
        ARRAY_A
    );
    return is_array($row) ? casting_director_role_talent_from_row($row) : null;
}

/**
 * @return array{ok:bool,error?:string,project_id?:int}
 */
function casting_director_create_project(int $director_id, string $title, string $project_type = 'theater', string $notes = ''): array
{
    casting_director_desk_ensure_tables();
    if (!casting_director_require_director($director_id)) {
        return ['ok' => false, 'error' => 'فقط کارگردان به میز کار دسترسی دارد.'];
    }

    $title = sanitize_text_field($title);
    if ($title === '') {
        return ['ok' => false, 'error' => 'نام پروژه را وارد کنید.'];
    }
    $types = casting_director_project_type_labels();
    $project_type = sanitize_key($project_type);
    if (!isset($types[$project_type])) {
        $project_type = 'theater';
    }
    $notes = sanitize_textarea_field($notes);

    global $wpdb;
    $now = current_time('mysql');
    $inserted = $wpdb->insert(
        casting_director_projects_table(),
        [
            'director_id'       => $director_id,
            'title'             => $title,
            'project_type'      => $project_type,
            'notes'             => $notes,
            'actors_needed'     => 0,
            'supporting_needed' => 0,
            'production_status' => 'planning',
            'sort_order'        => 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        ],
        ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s']
    );

    if ($inserted === false) {
        error_log('[casting-portal] create_project db error: ' . $wpdb->last_error);
        return ['ok' => false, 'error' => 'ذخیره پروژه ناموفق بود. یک بار صفحه را رفرش کنید و دوباره تلاش کنید.'];
    }

    $new_id = (int) $wpdb->insert_id;
    if ($new_id <= 0) {
        return ['ok' => false, 'error' => 'پروژه ساخته نشد.'];
    }

    return ['ok' => true, 'project_id' => $new_id];
}

/**
 * @return array{ok:bool,error?:string,role_id?:int}
 */
function casting_director_create_role(int $director_id, int $project_id, string $title, string $description = ''): array
{
    casting_director_desk_ensure_tables();
    if (!casting_director_get_project($director_id, $project_id)) {
        return ['ok' => false, 'error' => 'پروژه پیدا نشد.'];
    }

    $title = sanitize_text_field($title);
    if ($title === '') {
        return ['ok' => false, 'error' => 'نام نقش را وارد کنید.'];
    }
    $description = sanitize_textarea_field($description);

    global $wpdb;
    $now = current_time('mysql');
    $wpdb->insert(
        casting_director_roles_table(),
        [
            'project_id'  => $project_id,
            'director_id' => $director_id,
            'title'       => $title,
            'description' => $description,
            'sort_order'  => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ],
        ['%d', '%d', '%s', '%s', '%d', '%s', '%s']
    );

    return ['ok' => true, 'role_id' => (int) $wpdb->insert_id];
}

/**
 * @return array{ok:bool,error?:string}
 */
function casting_director_add_talent_to_role(int $director_id, int $role_id, int $talent_id): array
{
    casting_director_desk_ensure_tables();
    if (!casting_director_get_role($director_id, $role_id)) {
        return ['ok' => false, 'error' => 'نقش پیدا نشد.'];
    }
    if (!casting_director_can_manage_talent($director_id, $talent_id)) {
        return ['ok' => false, 'error' => 'این بازیگر قابل افزودن نیست.'];
    }

    global $wpdb;
    $table = casting_director_role_talents_table();
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT id FROM ' . $table . ' WHERE role_id = %d AND talent_id = %d LIMIT 1',
            $role_id,
            $talent_id
        )
    );
    if ($exists) {
        return ['ok' => false, 'error' => 'این بازیگر قبلاً در این نقش ثبت شده است.'];
    }

    $now = current_time('mysql');
    $wpdb->insert(
        $table,
        [
            'role_id'     => $role_id,
            'director_id' => $director_id,
            'talent_id'   => $talent_id,
            'ratings'     => wp_json_encode(casting_director_normalize_ratings([]), JSON_UNESCAPED_UNICODE),
            'score_avg'   => 0,
            'status'      => 'candidate',
            'created_at'  => $now,
            'updated_at'  => $now,
        ],
        ['%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s']
    );

    casting_director_record_talent_view($director_id, $talent_id);

    return ['ok' => true];
}

/**
 * @param array<string, mixed> $data
 * @return array{ok:bool,error?:string}
 */
function casting_director_save_role_talent(int $director_id, int $role_id, int $talent_id, array $data): array
{
    casting_director_desk_ensure_tables();
    $existing = casting_director_get_role_talent($director_id, $role_id, $talent_id);
    if (!$existing) {
        $add = casting_director_add_talent_to_role($director_id, $role_id, $talent_id);
        if (!$add['ok']) {
            return $add;
        }
        $existing = casting_director_get_role_talent($director_id, $role_id, $talent_id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'ذخیره ناموفق بود.'];
        }
    }

    $ratings = casting_director_normalize_ratings($data['ratings'] ?? []);
    $score = casting_director_compute_score_average($ratings);
    $notes = sanitize_textarea_field((string) ($data['notes'] ?? ''));
    $status = sanitize_key((string) ($data['status'] ?? 'candidate'));
    $statuses = casting_director_role_talent_status_labels();
    if (!isset($statuses[$status])) {
        $status = 'candidate';
    }

    global $wpdb;
    $wpdb->update(
        casting_director_role_talents_table(),
        [
            'notes'     => $notes,
            'ratings'   => wp_json_encode($ratings, JSON_UNESCAPED_UNICODE),
            'score_avg' => $score,
            'status'    => $status,
            'updated_at'=> current_time('mysql'),
        ],
        [
            'director_id' => $director_id,
            'role_id'     => $role_id,
            'talent_id'   => $talent_id,
        ],
        ['%s', '%s', '%f', '%s', '%s'],
        ['%d', '%d', '%d']
    );

    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function casting_director_remove_role_talent(int $director_id, int $role_id, int $talent_id): array
{
    casting_director_desk_ensure_tables();
    global $wpdb;
    $wpdb->delete(
        casting_director_role_talents_table(),
        [
            'director_id' => $director_id,
            'role_id'     => $role_id,
            'talent_id'   => $talent_id,
        ],
        ['%d', '%d', '%d']
    );
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function casting_director_delete_role(int $director_id, int $role_id): array
{
    if (!casting_director_get_role($director_id, $role_id)) {
        return ['ok' => false, 'error' => 'نقش پیدا نشد.'];
    }
    global $wpdb;
    $wpdb->delete(
        casting_director_role_talents_table(),
        ['role_id' => $role_id, 'director_id' => $director_id],
        ['%d', '%d']
    );
    $wpdb->delete(
        casting_director_roles_table(),
        ['id' => $role_id, 'director_id' => $director_id],
        ['%d', '%d']
    );
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function casting_director_delete_project(int $director_id, int $project_id): array
{
    $project = casting_director_get_project($director_id, $project_id);
    if (!$project) {
        return ['ok' => false, 'error' => 'پروژه پیدا نشد.'];
    }

    global $wpdb;
    $roles = casting_director_list_roles($director_id, $project_id);
    foreach ($roles as $role) {
        casting_director_delete_role($director_id, (int) $role['id']);
    }
    $wpdb->delete(
        casting_director_projects_table(),
        ['id' => $project_id, 'director_id' => $director_id],
        ['%d', '%d']
    );
    return ['ok' => true];
}

/**
 * @return list<array{role_id:int,project_id:int,project_title:string,role_title:string,score_avg:float,status:string}>
 */
function casting_director_talent_role_entries(int $director_id, int $talent_id): array
{
    casting_director_desk_ensure_tables();
    if (!casting_director_require_director($director_id)) {
        return [];
    }

    global $wpdb;
    $rt = casting_director_role_talents_table();
    $r = casting_director_roles_table();
    $p = casting_director_projects_table();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT rt.role_id, rt.score_avg, rt.status, r.title AS role_title, r.project_id, p.title AS project_title
             FROM {$rt} rt
             INNER JOIN {$r} r ON r.id = rt.role_id AND r.director_id = rt.director_id
             INNER JOIN {$p} p ON p.id = r.project_id AND p.director_id = rt.director_id
             WHERE rt.director_id = %d AND rt.talent_id = %d
             ORDER BY rt.score_avg DESC, rt.updated_at DESC",
            $director_id,
            $talent_id
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'role_id'       => (int) ($row['role_id'] ?? 0),
            'project_id'    => (int) ($row['project_id'] ?? 0),
            'project_title' => (string) ($row['project_title'] ?? ''),
            'role_title'    => (string) ($row['role_title'] ?? ''),
            'score_avg'     => (float) ($row['score_avg'] ?? 0),
            'status'        => (string) ($row['status'] ?? 'candidate'),
        ];
    }
    return $out;
}

/**
 * @param list<int> $talent_ids
 * @return array<int, float>
 */
function casting_director_best_scores_for_talents(int $director_id, array $talent_ids): array
{
    casting_director_desk_ensure_tables();
    $out = [];
    $talent_ids = array_values(array_unique(array_filter(array_map('intval', $talent_ids))));
    if (!casting_user_is_director_role($director_id) || $talent_ids === []) {
        return $out;
    }

    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($talent_ids), '%d'));
    $sql = 'SELECT talent_id, MAX(score_avg) AS best_score FROM ' . casting_director_role_talents_table()
        . ' WHERE director_id = %d AND talent_id IN (' . $placeholders . ') GROUP BY talent_id';
    $params = array_merge([$director_id], $talent_ids);
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    if (!is_array($rows)) {
        return $out;
    }
    foreach ($rows as $row) {
        $tid = (int) ($row['talent_id'] ?? 0);
        $score = (float) ($row['best_score'] ?? 0);
        if ($tid > 0 && $score > 0) {
            $out[$tid] = $score;
        }
    }
    return $out;
}

function casting_director_format_score(float $score): string
{
    if ($score <= 0) {
        return '—';
    }
    return number_format($score, 1, '.', '');
}

function casting_render_director_rating_fields(string $prefix, array $ratings): void
{
    ?>
    <div class="director-rating-grid">
      <?php foreach (casting_director_rating_criteria() as $key => $label) : ?>
        <div class="field director-rating-field">
          <label for="<?= casting_e($prefix . '_rating_' . $key) ?>"><?= casting_e($label) ?></label>
          <input
            id="<?= casting_e($prefix . '_rating_' . $key) ?>"
            name="ratings[<?= casting_e($key) ?>]"
            type="number"
            min="0"
            max="10"
            step="1"
            value="<?= (int) ($ratings[$key] ?? 0) ?>"
          >
          <span class="field-hint">۰ تا ۱۰</span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
}

function casting_render_director_desk_talent_panel(int $director_id, int $talent_id, int $active_role_id = 0): void
{
    if (!casting_director_can_manage_talent($director_id, $talent_id)) {
        return;
    }

    $projects = casting_director_list_projects($director_id);
    $entries = casting_director_talent_role_entries($director_id, $talent_id);
    $status_labels = casting_director_role_talent_status_labels();
    ?>
    <div class="director-desk-inline" id="director-desk">
      <h4>میز کار — امتیاز و نقش</h4>
      <p class="field-hint">بازیگر را به پروژه/نقش اضافه کنید و برای بازی (دیالوگ، ایفا، …) امتیاز بدهید. در هر نقش، نفرات با امتیاز بالاتر بالاتر می‌آیند.</p>

      <?php if ($entries) : ?>
        <div class="director-desk-entries">
          <?php foreach ($entries as $entry) :
              $row = casting_director_get_role_talent($director_id, (int) $entry['role_id'], $talent_id);
              if (!$row) {
                  continue;
              }
              $open = $active_role_id === (int) $entry['role_id'];
              ?>
            <details class="director-desk-entry"<?= $open ? ' open' : '' ?>>
              <summary>
                <span><?= casting_e($entry['project_title']) ?> · <?= casting_e($entry['role_title']) ?></span>
                <span class="director-score-pill"><?= casting_e(casting_director_format_score((float) $entry['score_avg'])) ?></span>
              </summary>
              <form class="form" method="post" action="member.php?id=<?= $talent_id ?>#director-desk">
                <?php wp_nonce_field('casting_director_desk_' . $talent_id); ?>
                <input type="hidden" name="director_desk" value="1">
                <input type="hidden" name="role_id" value="<?= (int) $entry['role_id'] ?>">
                <?php casting_render_director_rating_fields('desk_' . (int) $entry['role_id'], $row['ratings']); ?>
                <div class="field">
                  <label for="desk_status_<?= (int) $entry['role_id'] ?>">وضعیت</label>
                  <select id="desk_status_<?= (int) $entry['role_id'] ?>" name="status">
                    <?php foreach ($status_labels as $key => $label) : ?>
                      <option value="<?= casting_e($key) ?>" <?= ($row['status'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="desk_notes_<?= (int) $entry['role_id'] ?>">یادداشت این نقش</label>
                  <textarea id="desk_notes_<?= (int) $entry['role_id'] ?>" name="role_notes" rows="2" maxlength="3000"><?= casting_e((string) ($row['notes'] ?? '')) ?></textarea>
                </div>
                <div class="cta-row">
                  <button class="btn btn-primary btn-sm" type="submit" name="director_desk_action" value="save_role_talent">ذخیره امتیاز</button>
                  <button class="btn btn-ghost btn-sm" type="submit" name="director_desk_action" value="remove_role_talent" onclick="return confirm('از این نقش حذف شود؟');">حذف از نقش</button>
                </div>
              </form>
            </details>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($projects) : ?>
        <form class="form director-desk-add" method="post" action="member.php?id=<?= $talent_id ?>#director-desk">
          <?php wp_nonce_field('casting_director_desk_' . $talent_id); ?>
          <input type="hidden" name="director_desk" value="1">
          <div class="form-grid">
            <div class="field">
              <label for="desk_add_project">افزودن به پروژه</label>
              <select id="desk_add_project" name="project_id" required data-desk-project-select>
                <option value="">انتخاب پروژه</option>
                <?php foreach ($projects as $project) : ?>
                  <option value="<?= (int) $project['id'] ?>"><?= casting_e((string) $project['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="desk_add_role">نقش</label>
              <select id="desk_add_role" name="role_id" required data-desk-role-select>
                <option value="">ابتدا پروژه را انتخاب کنید</option>
              </select>
            </div>
          </div>
          <button class="btn btn-ghost btn-sm" type="submit" name="director_desk_action" value="add_to_role">افزودن به این نقش</button>
        </form>
        <script>
          window.CASTING_DESK_ROLES = <?= wp_json_encode(array_reduce(
              $projects,
              static function (array $carry, array $project) use ($director_id): array {
                  $pid = (int) ($project['id'] ?? 0);
                  $carry[(string) $pid] = array_map(
                      static fn(array $role): array => [
                          'id'    => (int) ($role['id'] ?? 0),
                          'title' => (string) ($role['title'] ?? ''),
                      ],
                      casting_director_list_roles($director_id, $pid)
                  );
                  return $carry;
              },
              []
          ), JSON_UNESCAPED_UNICODE) ?>;
        </script>
      <?php else : ?>
        <p class="field-hint">هنوز پروژه‌ای نساخته‌اید. از <a href="director-desk.php">پروژه‌ها</a> یک فیلم یا نمایش بسازید.</p>
      <?php endif; ?>
    </div>
    <?php
}

/**
 * @param array<string, string> $input
 * @return array<string, string>
 */
function casting_director_parse_call_filters(array $input): array
{
    if (!function_exists('casting_parse_member_search_filters')) {
        require_once __DIR__ . '/panel.php';
    }

    return casting_parse_member_search_filters([
        'gender'             => (string) ($input['gender'] ?? ''),
        'height_min'         => (string) ($input['height_min'] ?? ''),
        'height_max'         => (string) ($input['height_max'] ?? ''),
        'weight_min'         => (string) ($input['weight_min'] ?? ''),
        'weight_max'         => (string) ($input['weight_max'] ?? ''),
        'age_min'            => (string) ($input['age_min'] ?? ''),
        'age_max'            => (string) ($input['age_max'] ?? ''),
        'activity_category'  => (string) ($input['activity_category'] ?? ''),
        'activity_specialty' => (string) ($input['activity_specialty'] ?? ''),
    ]);
}

function casting_director_call_has_filters(array $filters): bool
{
    return ($filters['gender'] ?? '') !== ''
        || ($filters['height_range'] ?? '') !== ''
        || ($filters['weight_range'] ?? '') !== ''
        || ($filters['age_range'] ?? '') !== ''
        || ($filters['activity_category'] ?? '') !== ''
        || ($filters['activity_specialty'] ?? '') !== '';
}

/**
 * @param array<string, string> $filters
 */
function casting_director_call_filter_summary(array $filters): string
{
    $parts = [];
    if (!empty($filters['activity_specialty'])) {
        $labels = casting_activity_labels();
        $key = sanitize_key((string) $filters['activity_specialty']);
        if (isset($labels[$key])) {
            $parts[] = 'تخصص: ' . $labels[$key];
        }
    } elseif (!empty($filters['activity_category'])) {
        $cats = casting_activity_categories();
        $key = sanitize_key((string) $filters['activity_category']);
        if (isset($cats[$key]['label'])) {
            $parts[] = 'نوع فعالیت: ' . $cats[$key]['label'];
        }
    }
    if (!empty($filters['gender']) && array_key_exists($filters['gender'], casting_gender_labels())) {
        $parts[] = 'جنسیت: ' . casting_gender_labels()[$filters['gender']];
    }
    if (($filters['age_range'] ?? '') !== '') {
        $parts[] = 'سن: ' . str_replace('-', ' تا ', (string) $filters['age_range']);
    }
    if (($filters['height_range'] ?? '') !== '') {
        $parts[] = 'قد: ' . str_replace('-', ' تا ', (string) $filters['height_range']) . ' سانتی‌متر';
    }
    if (($filters['weight_range'] ?? '') !== '') {
        $parts[] = 'وزن: ' . str_replace('-', ' تا ', (string) $filters['weight_range']) . ' کیلو';
    }

    return $parts === [] ? '' : implode(' · ', $parts);
}

/**
 * @return list<WP_User>
 */
function casting_director_query_call_talents(int $director_id, array $filters, int $max = 250): array
{
    if (!function_exists('casting_query_members')) {
        require_once __DIR__ . '/panel.php';
    }

    $all = [];
    $page = 1;
    $per_page = 50;
    $filters['viewer_id'] = $director_id;
    while (count($all) < $max) {
        $result = casting_query_members($director_id, $filters, $page, $per_page);
        foreach ($result['users'] as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }
            $uid = (int) $user->ID;
            if ($uid <= 0 || casting_get_user_role($uid) === '') {
                continue;
            }
            $all[$uid] = $user;
            if (count($all) >= $max) {
                break 2;
            }
        }
        if ($page * $per_page >= $result['total']) {
            break;
        }
        $page++;
    }

    return array_values($all);
}

/**
 * @return array{ok:bool,error?:string,sent?:int,matched?:int,opportunity_id?:int}
 */
function casting_director_send_casting_call(
    int $director_id,
    int $project_id,
    array $filters,
    string $message,
    bool $publish_public = true,
    int $role_id = 0
): array
{
    if (!casting_director_get_project($director_id, $project_id)) {
        return ['ok' => false, 'error' => 'پروژه پیدا نشد.'];
    }
    if (!casting_director_call_has_filters($filters)) {
        return ['ok' => false, 'error' => 'حداقل نوع فعالیت یا یکی از فیلترهای جنسیت، سن، قد یا وزن را انتخاب کنید.'];
    }

    $message = sanitize_textarea_field($message);
    if ($message === '') {
        return ['ok' => false, 'error' => 'متن فراخوان را بنویسید.'];
    }
    if (casting_strlen($message) > 3000) {
        return ['ok' => false, 'error' => 'متن فراخوان خیلی بلند است.'];
    }

    $project = casting_director_get_project($director_id, $project_id);
    $director = get_user_by('id', $director_id);
    if (!$project || !$director) {
        return ['ok' => false, 'error' => 'اطلاعات پروژه پیدا نشد.'];
    }

    if (!function_exists('casting_user_has_casting_call_credit')) {
        require_once __DIR__ . '/checkout.php';
    }
    if (!casting_user_has_casting_call_credit($director_id, $project_id)) {
        $type_key = casting_checkout_map_project_type((string) ($project['project_type'] ?? ''));
        if ($type_key === '') {
            return ['ok' => false, 'error' => 'برای انتشار فراخوان، نوع پروژه باید تئاتر، فیلم کوتاه، سینمایی یا تلویزیونی باشد.'];
        }

        return [
            'ok'            => false,
            'error'         => 'برای انتشار فراخوان ابتدا هزینه آن را پرداخت کنید.',
            'need_checkout' => true,
            'checkout_url'  => 'checkout.php?service=casting_call&plan=' . rawurlencode($type_key) . '&project=' . $project_id,
        ];
    }

    $talents = casting_director_query_call_talents($director_id, $filters);
    $matched = count($talents);
    if ($matched === 0 && !$publish_public) {
        return ['ok' => false, 'error' => 'عضوی با این مشخصات پیدا نشد.'];
    }

    if (!function_exists('casting_send_talent_request')) {
        require_once __DIR__ . '/request.php';
    }

    $filter_summary = casting_director_call_filter_summary($filters);
    $role_needed = '';
    if ($role_id > 0) {
        $role = casting_director_get_role($director_id, $role_id);
        if ($role && (int) ($role['project_id'] ?? 0) === $project_id) {
            $role_needed = (string) ($role['title'] ?? '');
        } else {
            $role_id = 0;
        }
    }
    if ($role_needed === '' && !empty($filters['activity_specialty'])) {
        $labels = casting_activity_labels();
        $role_needed = (string) ($labels[sanitize_key((string) $filters['activity_specialty'])] ?? '');
    } elseif ($role_needed === '' && !empty($filters['activity_category'])) {
        $cats = casting_activity_categories();
        $role_needed = (string) ($cats[sanitize_key((string) $filters['activity_category'])]['label'] ?? '');
    }
    if ($filter_summary !== '' && $message !== '') {
        $full_message = $message . "\n\nمشخصات فراخوان: " . $filter_summary;
    } else {
        $full_message = $message;
    }

    $project_types = casting_director_project_type_labels();
    $project_type_key = sanitize_key((string) ($project['project_type'] ?? ''));
    $extra = [
        'kind'               => 'casting_call',
        'project_id'         => $project_id,
        'project_type'       => $project_types[$project_type_key] ?? (string) ($project['project_type'] ?? ''),
        'role_needed'        => $role_needed,
        'activity_category'  => sanitize_key((string) ($filters['activity_category'] ?? '')),
        'activity_specialty' => sanitize_key((string) ($filters['activity_specialty'] ?? '')),
    ];
    $options = [
        'kind'            => 'casting_call',
        'skip_rate_limit' => true,
        'skip_chat_rules' => true,
        'skip_mail'       => false,
    ];

    $sent = 0;
    $call_id = uniqid('call_', true);
    $sent_at = current_time('mysql');

    foreach ($talents as $talent) {
        $talent_id = (int) $talent->ID;
        if ($talent_id <= 0 || $talent_id === $director_id) {
            continue;
        }
        $result = casting_send_talent_request(
            $director_id,
            $talent_id,
            $full_message,
            (string) ($project['title'] ?? 'فراخوان کستینگ'),
            $extra,
            $options
        );
        if (!empty($result['ok'])) {
            $sent++;
        }
    }

    $opportunity_id = 0;
    if ($publish_public) {
        if (!function_exists('casting_opportunity_publish')) {
            require_once __DIR__ . '/opportunities.php';
        }
        $pub = casting_opportunity_publish($director_id, $project_id, $message, $filters, $role_id);
        if (!empty($pub['ok'])) {
            $opportunity_id = (int) ($pub['id'] ?? 0);
        } elseif ($sent === 0) {
            return ['ok' => false, 'error' => (string) ($pub['error'] ?? 'انتشار فراخوان ناموفق بود.')];
        }
    }

    if ($sent === 0 && $opportunity_id <= 0) {
        return ['ok' => false, 'error' => 'به هیچ عضوی ارسال نشد و فید عمومی هم ثبت نشد.'];
    }

    $log = get_user_meta($director_id, 'casting_director_call_log', true);
    if (!is_array($log)) {
        $log = [];
    }
    array_unshift($log, [
        'call_id'         => $call_id,
        'project_id'      => $project_id,
        'role_id'         => $role_id,
        'opportunity_id' => $opportunity_id,
        'project_title'   => (string) ($project['title'] ?? ''),
        'filters'         => $filter_summary,
        'message'         => $message,
        'matched'         => $matched,
        'sent'            => $sent,
        'sent_at'         => $sent_at,
        'public'          => $opportunity_id > 0,
    ]);
    update_user_meta($director_id, 'casting_director_call_log', array_slice($log, 0, 50));

    if (function_exists('casting_consume_casting_call_credit')) {
        casting_consume_casting_call_credit($director_id, $project_id);
    }

    return [
        'ok'             => true,
        'sent'           => $sent,
        'matched'        => $matched,
        'opportunity_id' => $opportunity_id,
    ];
}

/**
 * @param array<string, string> $filters
 */
function casting_render_director_casting_call_form(int $project_id, array $filters = [], string $message = '', int $director_id = 0): void
{
    if (!function_exists('casting_paid_services_catalog')) {
        require_once __DIR__ . '/checkout.php';
    }
    $project = $director_id > 0 ? casting_director_get_project($director_id, $project_id) : [];
    $type_key = casting_checkout_map_project_type((string) ($project['project_type'] ?? ''));
    $catalog = casting_paid_services_catalog();
    $call_types = $catalog['casting_call']['types'] ?? [];
    $price_base = ($type_key !== '' && isset($call_types[$type_key])) ? (int) $call_types[$type_key]['amount_base'] : 0;
    $price_final = $price_base > 0 ? casting_checkout_calc_amounts($price_base)['final'] : 0;
    $has_credit = $director_id > 0 && casting_user_has_casting_call_credit($director_id, $project_id);
    $checkout_href = $type_key !== ''
        ? ('checkout.php?service=casting_call&plan=' . rawurlencode($type_key) . '&project=' . (int) $project_id)
        : '';

    if (!function_exists('casting_render_body_metric_group')) {
        require_once __DIR__ . '/panel.php';
    }
    if (!function_exists('casting_opportunities_list_for_director')) {
        require_once __DIR__ . '/opportunities.php';
    }
    if ($director_id <= 0) {
        $user = wp_get_current_user();
        $director_id = (int) ($user->ID ?? 0);
    }
    $genders = casting_gender_labels();
    $defs = casting_body_metric_defs();
    $roles = casting_director_list_roles($director_id, $project_id);
    $open_ops = casting_opportunities_list_for_director($director_id, $project_id, 20);
    $metric_defs = [
        [
            'prefix'    => 'height',
            'kind'      => 'height',
            'label'     => $defs['height']['label'],
            'unit'      => $defs['height']['unit'],
            'floor'     => $defs['height']['min'],
            'ceil'      => casting_body_metric_plus_value('height'),
            'range_key' => 'height_range',
        ],
        [
            'prefix'    => 'weight',
            'kind'      => 'weight',
            'label'     => $defs['weight']['label'],
            'unit'      => $defs['weight']['unit'],
            'floor'     => $defs['weight']['min'],
            'ceil'      => casting_body_metric_plus_value('weight'),
            'range_key' => 'weight_range',
        ],
        [
            'prefix'    => 'age',
            'kind'      => 'age',
            'label'     => $defs['age']['label'],
            'unit'      => $defs['age']['unit'],
            'floor'     => $defs['age']['min'],
            'ceil'      => casting_body_metric_plus_value('age'),
            'range_key' => 'age_range',
        ],
    ];
    ?>
    <div class="director-casting-call">
      <h2 class="panel-section-title">فراخوان کستینگ</h2>
      <p class="field-hint">برای اعضای منطبق ارسال می‌شود و به‌صورت پیش‌فرض در فید عمومی «فرصت‌ها» هم منتشر می‌شود تا دیگران بتوانند اپلای کنند.</p>
      <?php if ($type_key === '') : ?>
        <div class="flash flash-error">نوع پروژه برای قیمت‌گذاری فراخوان مناسب نیست. نوع را روی تئاتر، فیلم کوتاه، سینمایی یا تلویزیونی تنظیم کنید.</div>
      <?php else : ?>
        <div class="bio-block checkout-call-price">
          <p><strong>هزینه انتشار این فراخوان:</strong> <?= casting_e(casting_format_toman($price_base)) ?> + مالیات ۱۰٪ = <strong><?= casting_e(casting_format_toman($price_final)) ?></strong></p>
          <?php if ($has_credit) : ?>
            <p class="meta flash-success" style="margin:0.35rem 0 0">پرداخت این فراخوان انجام شده — می‌توانید ارسال کنید.</p>
          <?php elseif ($checkout_href !== '') : ?>
            <p class="meta" style="margin:0.35rem 0 0.65rem">قبل از ارسال، هزینه را پرداخت کنید (خلاصه سفارش → درگاه).</p>
            <a class="btn btn-primary btn-sm" href="<?= casting_e($checkout_href) ?>">پرداخت فراخوان و رفتن به خلاصه سفارش</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <form class="form" method="post" action="director-desk.php?project=<?= $project_id ?>" onsubmit="return confirm('فراخوان ارسال و در فید فرصت‌ها منتشر شود؟');">
        <?php wp_nonce_field('casting_director_desk_page'); ?>
        <input type="hidden" name="desk_action" value="send_casting_call">
        <?php casting_render_member_search_activity_fields($filters, [
            'category'  => 'نوع فعالیت',
            'specialty' => 'تخصص',
        ]); ?>
        <div class="director-call-filters form-grid">
          <div class="field">
            <label for="call_gender">جنسیت</label>
            <select id="call_gender" name="gender">
              <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
              <?php foreach ($genders as $key => $label) : ?>
                <option value="<?= casting_e($key) ?>" <?= ($filters['gender'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php casting_render_body_metric_group($filters, $metric_defs[0]); ?>
          <?php casting_render_body_metric_group($filters, $metric_defs[1]); ?>
          <?php casting_render_body_metric_group($filters, $metric_defs[2]); ?>
        </div>
        <div class="field">
          <label for="call_role_id">نقش مرتبط (اختیاری)</label>
          <select id="call_role_id" name="call_role_id">
            <option value="0">— بدون نقش مشخص —</option>
            <?php foreach ($roles as $role) : ?>
              <option value="<?= (int) $role['id'] ?>"><?= casting_e((string) $role['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="field-hint">اگر نقش را انتخاب کنید، اپلای‌ها به همان نقش در میز کار اضافه می‌شوند.</p>
        </div>
        <div class="field">
          <label for="call_message">متن فراخوان</label>
          <textarea id="call_message" name="call_message" rows="4" required maxlength="3000" placeholder="توضیح نقش، زمان تست، محل حضور…"><?= casting_e($message) ?></textarea>
        </div>
        <label class="checkbox-inline">
          <input type="checkbox" name="publish_public" value="1" checked>
          در فید عمومی فرصت‌ها هم منتشر شود (اپلای باز)
        </label>
        <button class="btn btn-primary" type="submit"<?= (!$has_credit || $type_key === '') ? ' disabled title="ابتدا هزینه فراخوان را پرداخت کنید"' : '' ?>>ارسال و انتشار فراخوان</button>
        <?php if (!$has_credit && $checkout_href !== '') : ?>
          <a class="btn btn-ghost" href="<?= casting_e($checkout_href) ?>">پرداخت و خلاصه سفارش</a>
        <?php endif; ?>
      </form>

      <?php if ($open_ops !== []) : ?>
        <div class="director-open-ops" style="margin-top:1.5rem;">
          <h3 class="panel-section-title">فراخوان‌های منتشرشده این پروژه</h3>
          <ul class="home-opportunity-list">
            <?php foreach ($open_ops as $op) :
                $oid = (int) ($op['id'] ?? 0);
                $count = casting_opportunity_applicant_count($oid);
                $pending_count = casting_opportunity_pending_applicant_count($oid);
                $is_open = (string) ($op['status'] ?? '') === 'open';
                ?>
              <li class="home-opportunity-card">
                <div class="home-opportunity-body">
                  <h3>
                    <?= casting_e((string) ($op['title'] ?? '')) ?><?php if (!empty($op['role_title'])) : ?> · <?= casting_e((string) $op['role_title']) ?><?php endif; ?>
                    <?php if ($pending_count > 0) : ?>
                      <span class="nav-badge" aria-label="<?= (int) $pending_count ?> اپلای جدید"><?= (int) $pending_count ?></span>
                    <?php endif; ?>
                  </h3>
                  <p class="meta">
                    <?= $is_open ? 'باز' : 'بسته' ?>
                    · <?= (int) $count ?> اپلای<?= $pending_count > 0 ? ' (' . (int) $pending_count . ' جدید)' : '' ?>
                    · <?= casting_e(casting_opportunity_format_date((string) ($op['created_at'] ?? ''))) ?>
                  </p>
                </div>
                <div class="home-opportunity-actions">
                  <a class="btn btn-ghost btn-sm" href="director-desk.php?project=<?= (int) $project_id ?>&amp;opp=<?= $oid ?>">متقاضیان</a>
                  <?php if ($is_open) : ?>
                    <form method="post" action="director-desk.php?project=<?= (int) $project_id ?>" style="display:inline;" onsubmit="return confirm('این فراخوان بسته شود؟');">
                      <?php wp_nonce_field('casting_director_desk_page'); ?>
                      <input type="hidden" name="desk_action" value="close_opportunity">
                      <input type="hidden" name="opportunity_id" value="<?= $oid ?>">
                      <button class="btn btn-ghost btn-sm" type="submit">بستن</button>
                    </form>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
    <?php
}
