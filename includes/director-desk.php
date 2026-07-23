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
        'film'    => 'فیلم',
        'series'  => 'سریال',
        'theater' => 'تئاتر',
        'other'   => 'سایر',
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
    $project_type = sanitize_key((string) ($data['project_type'] ?? 'film'));
    if (!isset($types[$project_type])) {
        $project_type = 'film';
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
function casting_director_create_project(int $director_id, string $title, string $project_type = 'film', string $notes = ''): array
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
        $project_type = 'film';
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
      <p class="field-hint">بازیگر را به پروژه/نقش اضافه کنید و برای بازی (دیالوگ، ایفا، …) امتیاز بدهید. در هر نقش، بازیگران با امتیاز بالاتر بالاتر می‌آیند.</p>

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
