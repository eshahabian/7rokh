<?php
declare(strict_types=1);

require_once __DIR__ . '/activities.php';

const CASTING_MSG_ACCESS_OPTION = 'casting_message_access_v1';
/**
 * v3: فقط overrideهای کوچک ذخیره می‌شوند (نه کل ماتریس).
 * خاموش کردن = deny صریح روی پیش‌فرض.
 */
const CASTING_MSG_ACCESS_VERSION = 3;

/**
 * مدیران بخش‌ها (ارتباط بین‌بخشی فقط در این سطح)
 *
 * @return list<string>
 */
function casting_message_access_dept_head_keys(): array
{
    return [
        'producer',
        'production_manager',
        'executive',
        'dop',
        'sound_mixer',
        'set_designer',
        'costume_designer',
        'makeup_designer',
        'post_manager',
        'vfx_manager',
        'casting_director',
        'logistics_manager',
        'lighting_designer',
        'composer',
        'location_manager',
        'pr_manager',
        'promo_manager',
        'finance_manager',
        'transport_manager',
        'coordinator',
        'stage_manager',
        'stunt_coordinator',
        'choreographer',
        'sfx',
    ];
}

/**
 * @return list<string>
 */
function casting_message_access_director_keys(): array
{
    return [
        'director_theater',
        'director_short_film',
        'director_tv',
        'director_cinema',
    ];
}

/**
 * @return list<string>
 */
function casting_message_access_actor_keys(): array
{
    return [
        'actor_cinema',
        'actor_theater',
        'actor_tv',
        'actor_youth',
        'extra',
        'stunt',
    ];
}

/**
 * @return list<string>
 */
function casting_message_access_all_specialty_keys(): array
{
    return array_keys(casting_activity_labels());
}

/**
 * کلید لبه from|to
 */
function casting_message_access_edge_key(string $from, string $to): string
{
    return sanitize_key($from) . '|' . sanitize_key($to);
}

/**
 * @param array<string, array{can_start:bool,require_project:bool,enabled:bool}> $edges
 * @param list<string>|string $froms
 * @param list<string>|string $tos
 */
function casting_message_access_add_edges(array &$edges, $froms, $tos, bool $require_project = false, bool $bidirectional = false): void
{
    $froms = is_array($froms) ? $froms : [$froms];
    $tos = is_array($tos) ? $tos : [$tos];
    foreach ($froms as $from) {
        $from = sanitize_key((string) $from);
        if ($from === '') {
            continue;
        }
        foreach ($tos as $to) {
            $to = sanitize_key((string) $to);
            if ($to === '' || $to === $from) {
                continue;
            }
            $edges[casting_message_access_edge_key($from, $to)] = [
                'can_start'        => true,
                'require_project'  => $require_project,
                'enabled'          => true,
            ];
            if ($bidirectional) {
                $edges[casting_message_access_edge_key($to, $from)] = [
                    'can_start'        => true,
                    'require_project'  => $require_project,
                    'enabled'          => true,
                ];
            }
        }
    }
}

/**
 * نقش‌هایی که بازیگر در پیش‌فرض سیستم فقط با پروژه/رابطه به آن‌ها پیام می‌دهد
 *
 * @return list<string>
 */
function casting_message_access_actor_gated_targets(): array
{
    return array_values(array_unique(array_merge(
        casting_message_access_director_keys(),
        [
            'producer',
            'first_ad',
            'second_ad',
            'third_ad',
            'production_manager',
            'executive',
            'scheduler',
            'script_supervisor',
            'costume_designer',
            'makeup_designer',
            'dop',
            'sound_mixer',
            'set_designer',
            'post_manager',
            'vfx_manager',
            'logistics_manager',
            'location_manager',
            'finance_manager',
            'transport_manager',
            'coordinator',
            'composer',
            'pr_manager',
            'promo_manager',
        ]
    )));
}

/**
 * ساخت ماتریس پیش‌فرض طبق قوانین سلسله‌مراتبی هفت‌رخ
 *
 * @return array<string, array{can_start:bool,require_project:bool,enabled:bool}>
 */
function casting_message_access_build_default_edges(): array
{
    $edges = [];
    $directors = casting_message_access_director_keys();
    $actors = casting_message_access_actor_keys();
    $heads = casting_message_access_dept_head_keys();
    $labels = casting_activity_labels();

    // --- تهیه‌کننده → مدیران اصلی (نه دستیاران اجرایی) ---
    $producer_targets = array_values(array_unique(array_merge(
        $directors,
        [
            'production_manager', 'executive', 'scheduler',
            'dop', 'sound_mixer', 'set_designer', 'costume_designer', 'makeup_designer',
            'post_manager', 'vfx_manager', 'logistics_manager', 'pr_manager', 'promo_manager',
            'finance_manager', 'casting_director', 'location_manager', 'lighting_designer',
            'composer', 'transport_manager', 'coordinator', 'stunt_coordinator', 'choreographer',
            'first_ad', 'script_supervisor',
            'writer', 'playwright', 'screenwriter',
        ]
    )));
    // یک‌طرفه از تهیه‌کننده به مدیران؛ برگشت جداگانه از قوانین همان نقش
    casting_message_access_add_edges($edges, 'producer', $producer_targets, false, false);
    casting_message_access_add_edges($edges, $producer_targets, 'producer', false, false);
    casting_message_access_add_edges($edges, 'producer', $actors, true, false);

    // --- کارگردان → مدیران هنری/فنی ---
    $director_targets = [
        'producer', 'executive', 'production_manager', 'scheduler', 'first_ad', 'script_supervisor',
        'dop', 'videographer', 'sound_mixer', 'casting_director',
        'set_designer', 'costume_designer', 'makeup_designer',
        'post_manager', 'vfx_manager', 'composer',
        'stunt_coordinator', 'choreographer', 'sfx',
        'location_manager', 'lighting_designer',
    ];
    casting_message_access_add_edges($edges, $directors, $director_targets, false, false);
    casting_message_access_add_edges($edges, $director_targets, $directors, false, false);
    // کارگردان می‌تواند به بازیگر پیام بدهد (شروع)، بازیگر برنمی‌گردد مگر با رابطه
    casting_message_access_add_edges($edges, $directors, $actors, false, false);

    // --- مدیر تولید ---
    $pm_targets = array_values(array_unique(array_merge(
        ['producer'],
        $directors,
        [
            'executive', 'scheduler', 'production_assistant',
            'logistics_manager', 'logistics_assistant', 'logistics_driver',
            'transport_manager', 'location_manager', 'finance_manager', 'coordinator',
        ],
        $heads
    )));
    casting_message_access_add_edges($edges, 'production_manager', $pm_targets, false, false);
    casting_message_access_add_edges($edges, $pm_targets, 'production_manager', false, false);

    casting_message_access_add_edges($edges, 'executive', array_merge($directors, [
        'producer', 'production_manager', 'scheduler',
    ], $heads), false, false);
    casting_message_access_add_edges($edges, array_merge($directors, [
        'producer', 'production_manager', 'scheduler',
    ], $heads), 'executive', false, false);

    casting_message_access_add_edges($edges, 'scheduler', array_merge($directors, [
        'producer', 'production_manager', 'executive', 'first_ad', 'script_supervisor',
        'dop', 'sound_mixer', 'casting_director', 'location_manager', 'coordinator',
    ]), false, false);

    // --- مدیران بخش ---
    foreach ($heads as $head) {
        if ($head === 'producer' || $head === 'production_manager') {
            continue;
        }
        casting_message_access_add_edges($edges, $head, $heads, false, false);
        casting_message_access_add_edges($edges, $head, array_merge($directors, ['producer', 'production_manager', 'executive', 'scheduler']), false, false);
        casting_message_access_add_edges($edges, array_merge($directors, ['producer', 'production_manager', 'executive', 'scheduler']), $head, false, false);

        $guild = casting_activity_category_for_specialty($head);
        if ($guild === '') {
            continue;
        }
        $cats = casting_activity_categories();
        $members = array_keys($cats[$guild]['items'] ?? []);
        // مدیر بخش ↔ نیروهای همان بخش
        casting_message_access_add_edges($edges, $head, $members, false, true);
    }

    casting_message_access_add_edges($edges, 'dop', [
        'lighting_designer', 'gaffer', 'cameraman', 'camera_assistant',
        'camera_first_assistant', 'camera_second_assistant', 'camera_third_assistant',
        'camera_technical_crew', 'videographer', 'crane_op', 'steadicam_op', 'gimbal_op', 'drone_op',
    ], false, true);

    casting_message_access_add_edges($edges, 'sound_mixer', [
        'sound_assistant', 'boom_op', 'sound_editor',
    ], false, true);

    casting_message_access_add_edges($edges, 'set_designer', [
        'art_assistant', 'stage_manager', 'stage_assistant', 'set_deco', 'props', 'costume_designer',
    ], false, true);

    // طراح لباس/گریم → بازیگر فقط با پروژه
    casting_message_access_add_edges($edges, ['costume_designer', 'makeup_designer'], $actors, true, false);
    casting_message_access_add_edges($edges, 'costume_designer', ['art_assistant'], false, true);
    casting_message_access_add_edges($edges, 'makeup_designer', ['makeup_artist'], false, true);

    casting_message_access_add_edges($edges, 'post_manager', [
        'dop', 'sound_mixer', 'editor', 'colorist', 'vfx_manager', 'vfx', 'motion', 'animator',
        'sound_editor', 'composer',
    ], false, true);
    casting_message_access_add_edges($edges, 'vfx_manager', [
        'post_manager', 'vfx', 'motion', 'animator', 'editor',
    ], false, true);

    // مدیر انتخاب بازیگر → بازیگر (آزاد برای شروع از طرف کستینگ)
    // مهم: دوطرفه نباشد تا بازیگر نتواند آزادانه به کارگردان برسد
    casting_message_access_add_edges($edges, 'casting_director', array_merge($directors, [
        'producer', 'production_manager', 'casting_assistant', 'actor_coordinator', 'acting_coach',
    ]), false, false);
    casting_message_access_add_edges($edges, array_merge($directors, [
        'producer', 'production_manager', 'casting_assistant', 'actor_coordinator', 'acting_coach',
    ]), 'casting_director', false, false);
    casting_message_access_add_edges($edges, 'casting_director', $actors, false, false);

    casting_message_access_add_edges($edges, 'first_ad', array_merge($directors, [
        'producer', 'production_manager', 'scheduler', 'script_supervisor', 'second_ad', 'third_ad',
        'casting_director', 'actor_coordinator',
    ]), false, false);
    casting_message_access_add_edges($edges, 'first_ad', $actors, false, false);

    // دستیاران: فقط همان بخش
    $lead_or_head = array_flip(array_merge(
        ['producer'],
        $directors,
        $heads,
        $actors,
        ['activity_none']
    ));
    foreach (array_keys($labels) as $spec) {
        if (isset($lead_or_head[$spec])) {
            continue;
        }
        $guild = casting_activity_category_for_specialty($spec);
        if ($guild === '' || $guild === 'none') {
            continue;
        }
        $cats = casting_activity_categories();
        $members = array_keys($cats[$guild]['items'] ?? []);
        casting_message_access_add_edges($edges, $spec, $members, false, false);
        casting_message_access_add_edges($edges, $spec, ['production_manager', 'scheduler'], false, false);
        foreach ($heads as $head) {
            if (casting_activity_category_for_specialty($head) === $guild) {
                casting_message_access_add_edges($edges, $spec, $head, false, false);
            }
        }
    }

    // --- بازیگر: فقط کستینگ آزاد؛ بقیه فقط با رابطه/پروژه/پیام قبلی ---
    $actor_free = ['casting_director', 'casting_assistant', 'actor_coordinator', 'acting_coach'];
    casting_message_access_add_edges($edges, $actors, $actor_free, false, false);
    casting_message_access_add_edges($edges, $actors, casting_message_access_actor_gated_targets(), true, false);

    casting_message_access_add_edges($edges, 'activity_none', ['casting_director'], false, false);

    casting_message_access_add_edges($edges, ['writer', 'playwright', 'screenwriter', 'script_consultant'], array_merge($directors, [
        'producer', 'production_manager',
    ]), false, false);
    casting_message_access_add_edges($edges, array_merge($directors, [
        'producer', 'production_manager',
    ]), ['writer', 'playwright', 'screenwriter', 'script_consultant'], false, false);

    return $edges;
}

/**
 * @return array{version:int,edges:array<string,array{can_start:bool,require_project:bool,enabled:bool}>,customized:bool,overrides:array<string,array{enabled:bool,require_project:bool}>}
 */
function casting_message_access_defaults_payload(): array
{
    return [
        'version'    => CASTING_MSG_ACCESS_VERSION,
        'edges'      => casting_message_access_build_default_edges(),
        'overrides'  => [],
        'customized' => false,
    ];
}

/**
 * @param array<string, array{enabled?:bool,require_project?:bool,can_start?:bool}> $overrides
 * @return array<string, array{enabled:bool,require_project:bool}>
 */
function casting_message_access_normalize_overrides(array $overrides): array
{
    $out = [];
    $labels = casting_activity_labels();
    foreach ($overrides as $key => $row) {
        if (!is_string($key) || !str_contains($key, '|') || !is_array($row)) {
            continue;
        }
        $parts = explode('|', $key, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $from = sanitize_key($parts[0]);
        $to = sanitize_key($parts[1]);
        if ($from === '' || $to === '' || $from === $to || !isset($labels[$from]) || !isset($labels[$to])) {
            continue;
        }
        $enabled = array_key_exists('enabled', $row) ? !empty($row['enabled']) : !empty($row['can_start']);
        $out[casting_message_access_edge_key($from, $to)] = [
            'enabled'         => $enabled,
            'require_project' => !empty($row['require_project']),
        ];
    }

    return $out;
}

/**
 * اعمال override روی ماتریس پیش‌فرض
 *
 * @param array<string, array{can_start:bool,require_project:bool,enabled:bool}> $defaults
 * @param array<string, array{enabled:bool,require_project:bool}> $overrides
 * @return array<string, array{can_start:bool,require_project:bool,enabled:bool}>
 */
function casting_message_access_apply_overrides(array $defaults, array $overrides): array
{
    $edges = $defaults;
    foreach ($overrides as $key => $row) {
        if (empty($row['enabled'])) {
            unset($edges[$key]);
            continue;
        }
        $edges[$key] = [
            'can_start'       => true,
            'require_project' => !empty($row['require_project']),
            'enabled'         => true,
        ];
    }

    return $edges;
}

/**
 * تبدیل ذخیرهٔ قدیمی (کل edges) به override
 *
 * @param array<string, mixed> $stored
 * @return array<string, array{enabled:bool,require_project:bool}>
 */
function casting_message_access_overrides_from_legacy_edges(array $stored): array
{
    $defaults = casting_message_access_build_default_edges();
    $legacy = [];
    foreach (($stored['edges'] ?? []) as $key => $row) {
        if (!is_string($key) || !is_array($row)) {
            continue;
        }
        if (isset($row['enabled']) && empty($row['enabled'])) {
            continue;
        }
        if (isset($row['can_start']) && empty($row['can_start'])) {
            continue;
        }
        $legacy[$key] = [
            'require_project' => !empty($row['require_project']),
        ];
    }

    $overrides = [];
    foreach ($defaults as $key => $row) {
        if (!isset($legacy[$key])) {
            // در ذخیرهٔ سفارشی نبود = عمداً خاموش شده
            if (!empty($stored['customized'])) {
                $overrides[$key] = ['enabled' => false, 'require_project' => false];
            }
            continue;
        }
        $req = !empty($legacy[$key]['require_project']);
        if ($req !== !empty($row['require_project'])) {
            $overrides[$key] = ['enabled' => true, 'require_project' => $req];
        }
    }
    foreach ($legacy as $key => $row) {
        if (isset($defaults[$key])) {
            continue;
        }
        $overrides[$key] = [
            'enabled'         => true,
            'require_project' => !empty($row['require_project']),
        ];
    }

    return casting_message_access_normalize_overrides($overrides);
}

/**
 * دادهٔ مؤثر (پیش‌فرض + override) برای UI و چک دسترسی
 *
 * @return array{version:int,edges:array<string,array{can_start:bool,require_project:bool,enabled:bool}>,customized:bool,overrides:array<string,array{enabled:bool,require_project:bool}>}
 */
function casting_message_access_get(): array
{
    $defaults = casting_message_access_defaults_payload();
    $stored = get_option(CASTING_MSG_ACCESS_OPTION, null);
    if (!is_array($stored)) {
        return $defaults;
    }

    $stored_version = (int) ($stored['version'] ?? 1);
    $overrides = [];

    if ($stored_version >= 3 && isset($stored['overrides']) && is_array($stored['overrides'])) {
        $overrides = casting_message_access_normalize_overrides($stored['overrides']);
    } elseif ($stored_version < 3 && !empty($stored['edges']) && is_array($stored['edges'])) {
        $overrides = casting_message_access_overrides_from_legacy_edges($stored);
        // مهاجرت یک‌باره به فرمت کوچک
        update_option(CASTING_MSG_ACCESS_OPTION, [
            'version'    => CASTING_MSG_ACCESS_VERSION,
            'overrides'  => $overrides,
            'customized' => $overrides !== [],
            'updated_at' => current_time('mysql'),
        ], false);
        wp_cache_delete(CASTING_MSG_ACCESS_OPTION, 'options');
    }

    $edges = casting_message_access_apply_overrides($defaults['edges'], $overrides);

    return [
        'version'    => CASTING_MSG_ACCESS_VERSION,
        'edges'      => $edges,
        'overrides'  => $overrides,
        'customized' => $overrides !== [],
    ];
}

/**
 * ذخیره فقط overrideها (کوچک و قابل‌اعتماد)
 *
 * @param array<string, array{enabled?:bool,require_project?:bool}> $overrides
 */
function casting_message_access_save_overrides(array $overrides): bool
{
    $overrides = casting_message_access_normalize_overrides($overrides);
    $data = [
        'version'    => CASTING_MSG_ACCESS_VERSION,
        'overrides'  => $overrides,
        'customized' => $overrides !== [],
        'updated_at' => current_time('mysql'),
    ];

    $ok = update_option(CASTING_MSG_ACCESS_OPTION, $data, false);
    wp_cache_delete(CASTING_MSG_ACCESS_OPTION, 'options');
    wp_cache_delete('alloptions', 'options');
    if ($ok) {
        return true;
    }
    $current = get_option(CASTING_MSG_ACCESS_OPTION, null);

    return is_array($current)
        && (int) ($current['version'] ?? 0) >= CASTING_MSG_ACCESS_VERSION
        && (($current['overrides'] ?? null) == $data['overrides']);
}

/**
 * @param array{edges?:array<string,array{can_start?:bool,require_project?:bool,enabled?:bool}>,overrides?:array<string,array{enabled?:bool,require_project?:bool}>,customized?:bool} $payload
 */
function casting_message_access_save(array $payload): bool
{
    if (isset($payload['overrides']) && is_array($payload['overrides'])) {
        return casting_message_access_save_overrides($payload['overrides']);
    }

    // سازگاری: اگر edges کامل آمد، به override تبدیل کن
    return casting_message_access_save_overrides(
        casting_message_access_overrides_from_legacy_edges([
            'edges'      => $payload['edges'] ?? [],
            'customized' => true,
        ])
    );
}

function casting_message_access_reset_to_defaults(): bool
{
    delete_option(CASTING_MSG_ACCESS_OPTION);
    wp_cache_delete(CASTING_MSG_ACCESS_OPTION, 'options');
    wp_cache_delete('alloptions', 'options');

    return true;
}

/**
 * @return array{can_start:bool,require_project:bool,enabled:bool}|null
 */
function casting_message_access_lookup_edge(string $from, string $to): ?array
{
    $data = casting_message_access_get();
    $key = casting_message_access_edge_key($from, $to);
    $row = $data['edges'][$key] ?? null;
    if (!is_array($row) || empty($row['enabled']) || empty($row['can_start'])) {
        return null;
    }

    return $row;
}

/**
 * رابطهٔ پروژه‌محور — گفتگوی یک‌طرفه از طرف بازیگر کافی نیست؛
 * باید طرف مقابل پیام داده باشد، یا پروژه/درخواست مشترک باشد.
 */
function casting_users_have_message_relationship(int $user_a, int $user_b): bool
{
    if ($user_a <= 0 || $user_b <= 0 || $user_a === $user_b) {
        return false;
    }

    if (!function_exists('casting_dm_user_has_sent_to')) {
        require_once __DIR__ . '/chat.php';
    }
    // طرف مقابل قبلاً به من پیام داده
    if (casting_dm_user_has_sent_to($user_b, $user_a)) {
        return true;
    }

    // بازیگر در میز کارگردان طرف مقابل
    if (!function_exists('casting_director_desk_ensure_tables')) {
        require_once __DIR__ . '/director-desk.php';
    }
    casting_director_desk_ensure_tables();
    global $wpdb;
    $rt = casting_director_role_talents_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $linked = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$rt}
         WHERE (director_id = %d AND talent_id = %d)
            OR (director_id = %d AND talent_id = %d)",
        $user_a,
        $user_b,
        $user_b,
        $user_a
    ));
    if ($linked > 0) {
        return true;
    }

    // درخواست / فراخوان بین دو نفر
    foreach ([$user_a, $user_b] as $owner_id) {
        $peer_id = $owner_id === $user_a ? $user_b : $user_a;
        foreach (['casting_requests', 'casting_sent_requests', 'casting_requests_archive'] as $meta_key) {
            $list = get_user_meta($owner_id, $meta_key, true);
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $req) {
                if (!is_array($req)) {
                    continue;
                }
                $eid = (int) ($req['employer_id'] ?? 0);
                $tid = (int) ($req['talent_id'] ?? 0);
                if (($eid === $owner_id && $tid === $peer_id) || ($eid === $peer_id && $tid === $owner_id)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * آیا تخصص from می‌تواند به تخصص to پیام جدید بدهد؟
 *
 * @return array{ok:bool,require_project:bool,error:string}
 */
function casting_message_access_specialty_allows(string $from_spec, string $to_spec): array
{
    $row = casting_message_access_lookup_edge($from_spec, $to_spec);
    if ($row === null || empty($row['enabled']) || empty($row['can_start'])) {
        return [
            'ok'              => false,
            'require_project' => false,
            'error'           => 'طبق قوانین سمت شغلی، امکان شروع گفتگو با این کاربر وجود ندارد.',
        ];
    }

    return [
        'ok'              => true,
        'require_project' => !empty($row['require_project']),
        'error'           => '',
    ];
}

/**
 * تخصص‌های کاربر برای ماتریس پیام (با fallback نقش پورتال)
 *
 * @return list<string>
 */
function casting_message_access_user_specs(int $user_id): array
{
    if (function_exists('casting_chat_specialty_keys')) {
        return casting_chat_specialty_keys($user_id);
    }

    $keys = casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true), $user_id);
    if ($keys !== []) {
        return $keys;
    }
    $role = casting_get_user_role($user_id);
    if ($role === 'director') {
        return ['director_cinema'];
    }
    if ($role === 'producer') {
        return ['producer'];
    }
    if ($role === 'talent') {
        return ['actor_cinema'];
    }

    return ['activity_none'];
}

/**
 * آیا بین دو تخصص لبهٔ روشن (can_start) وجود دارد؟ — بدون در نظر گرفتن محدودیت پروژه
 */
function casting_message_access_has_enabled_edge(int $from_id, int $to_id): bool
{
    $from_specs = casting_message_access_user_specs($from_id);
    $to_specs = casting_message_access_user_specs($to_id);
    if ($from_specs === [] || $to_specs === []) {
        return false;
    }
    foreach ($from_specs as $from_spec) {
        foreach ($to_specs as $to_spec) {
            $row = casting_message_access_lookup_edge($from_spec, $to_spec);
            if ($row !== null && !empty($row['enabled']) && !empty($row['can_start'])) {
                return true;
            }
        }
    }

    return false;
}

/**
 * بررسی کامل شروع گفتگو بر اساس تخصص‌های دو کاربر
 *
 * @return array{ok:bool,error:string}
 */
function casting_message_access_allows_start(int $from_id, int $to_id): array
{
    $from_specs = casting_message_access_user_specs($from_id);
    $to_specs = casting_message_access_user_specs($to_id);
    if ($from_specs === [] || $to_specs === []) {
        return ['ok' => false, 'error' => 'برای پیام‌رسانی باید نوع فعالیت در پروفایل مشخص باشد.'];
    }

    // منبع حقیقت = جدول ادمین (روشن/خاموش + فقط با پروژه)
    $need_relationship = false;
    $any_edge = false;
    foreach ($from_specs as $from_spec) {
        foreach ($to_specs as $to_spec) {
            $check = casting_message_access_specialty_allows($from_spec, $to_spec);
            if (!$check['ok']) {
                continue;
            }
            $any_edge = true;
            if (!empty($check['require_project'])) {
                $need_relationship = true;
                if (casting_users_have_message_relationship($from_id, $to_id)) {
                    return ['ok' => true, 'error' => ''];
                }
                continue;
            }

            // لبه آزاد (بدون نیاز به پروژه) — فوری مجاز
            return ['ok' => true, 'error' => ''];
        }
    }

    if ($any_edge && $need_relationship) {
        return [
            'ok'    => false,
            'error' => 'برای پیام به این کاربر باید عضو پروژه مشترک باشید، درخواست همکاری ثبت شده باشد، یا طرف مقابل اول پیام داده باشد.',
        ];
    }

    return ['ok' => false, 'error' => 'برای پاسخ به این پیام باید اشتراک ویژه خریداری کنید'];
}

/**
 * لیست گیرندگان مجاز برای یک تخصص (برای جدول ادمین)
 *
 * @return list<array{to:string,label:string,require_project:bool,enabled:bool}>
 */
function casting_message_access_targets_for(string $from_spec): array
{
    $from_spec = sanitize_key($from_spec);
    $data = casting_message_access_get();
    $labels = casting_activity_labels();
    $out = [];
    $prefix = $from_spec . '|';
    foreach ($data['edges'] as $key => $row) {
        if (!str_starts_with($key, $prefix) || empty($row['can_start'])) {
            continue;
        }
        $to = substr($key, strlen($prefix));
        if ($to === '' || !isset($labels[$to])) {
            continue;
        }
        $out[] = [
            'to'              => $to,
            'label'           => $labels[$to],
            'require_project' => !empty($row['require_project']),
            'enabled'         => !empty($row['enabled']),
        ];
    }
    usort($out, static fn ($a, $b) => strcmp($a['label'], $b['label']));

    return $out;
}

/**
 * آیا این لبه الان روشن است؟
 */
function casting_message_access_edge_is_on(array $edges, string $key): bool
{
    if (!isset($edges[$key]) || !is_array($edges[$key])) {
        return false;
    }
    $row = $edges[$key];
    if (isset($row['enabled']) && empty($row['enabled'])) {
        return false;
    }

    return !empty($row['can_start']);
}

/**
 * روشن/خاموش کردن یک رابطه from → to (اعمال فوری با override)
 *
 * @return array{ok:bool,error:string,enabled?:bool,require_project?:bool,message?:string}
 */
function casting_message_access_toggle_edge(string $from, string $to, string $field = 'enabled', ?bool $force = null): array
{
    $from = sanitize_key($from);
    $to = sanitize_key($to);
    $labels = casting_activity_labels();
    if ($from === '' || $to === '' || $from === $to || !isset($labels[$from]) || !isset($labels[$to])) {
        return ['ok' => false, 'error' => 'نقش فرستنده یا گیرنده نامعتبر است.'];
    }
    if (!in_array($field, ['enabled', 'require_project', 'can_start'], true)) {
        return ['ok' => false, 'error' => 'فیلد نامعتبر است.'];
    }

    $data = casting_message_access_get();
    $defaults = casting_message_access_build_default_edges();
    $overrides = $data['overrides'];
    $key = casting_message_access_edge_key($from, $to);
    $default_row = $defaults[$key] ?? null;
    $is_on = casting_message_access_edge_is_on($data['edges'], $key);
    $require_now = $is_on && !empty($data['edges'][$key]['require_project']);
    $to_label = $labels[$to];

    if ($field === 'require_project') {
        if (!$is_on) {
            return ['ok' => false, 'error' => 'اول دسترسی پیام را روشن کنید.', 'enabled' => false, 'require_project' => false];
        }
        $next_require = $force !== null ? $force : !$require_now;
        $default_req = $default_row !== null && !empty($default_row['require_project']);
        if ($default_row !== null && $next_require === $default_req) {
            // برگشت به پیش‌فرض → override لازم نیست
            unset($overrides[$key]);
        } else {
            $overrides[$key] = [
                'enabled'         => true,
                'require_project' => $next_require,
            ];
        }
        if (!casting_message_access_save_overrides($overrides)) {
            return ['ok' => false, 'error' => 'ذخیره ناموفق بود.'];
        }

        return [
            'ok'              => true,
            'error'           => '',
            'enabled'         => true,
            'require_project' => $next_require,
            'message'         => $next_require
                ? ('محدودیت پروژه برای «' . $to_label . '» فعال شد.')
                : ('محدودیت پروژه برای «' . $to_label . '» غیرفعال شد.'),
        ];
    }

    $next_on = $force !== null ? $force : !$is_on;
    if ($next_on) {
        $req = $require_now;
        if ($default_row !== null && !isset($overrides[$key])) {
            $req = !empty($default_row['require_project']);
        }
        $default_req = $default_row !== null && !empty($default_row['require_project']);
        if ($default_row !== null && $req === $default_req) {
            // روشن و مطابق پیش‌فرض
            unset($overrides[$key]);
        } else {
            $overrides[$key] = [
                'enabled'         => true,
                'require_project' => $req,
            ];
        }
    } else {
        // خاموش: اگر در پیش‌فرض هم نبود، override را پاک کن؛ وگرنه deny صریح
        if ($default_row === null) {
            unset($overrides[$key]);
        } else {
            $overrides[$key] = [
                'enabled'         => false,
                'require_project' => false,
            ];
        }
    }

    if (!casting_message_access_save_overrides($overrides)) {
        return ['ok' => false, 'error' => 'ذخیره ناموفق بود.'];
    }

    $fresh = casting_message_access_get();
    $fresh_on = casting_message_access_edge_is_on($fresh['edges'], $key);
    $fresh_req = $fresh_on && !empty($fresh['edges'][$key]['require_project']);

    return [
        'ok'              => true,
        'error'           => '',
        'enabled'         => $fresh_on,
        'require_project' => $fresh_req,
        'message'         => $fresh_on
            ? ('دسترسی به «' . $to_label . '» روشن شد.')
            : ('دسترسی به «' . $to_label . '» خاموش شد.'),
    ];
}

/**
 * ذخیره اهداف یک تخصص فرستنده (جایگزینی کامل لبه‌های خروجی)
 *
 * @param list<array{to:string,require_project?:bool,enabled?:bool}>|list<string> $targets
 */
function casting_message_access_save_from_specialty(string $from_spec, array $targets): bool
{
    $from_spec = sanitize_key($from_spec);
    if ($from_spec === '' || !isset(casting_activity_labels()[$from_spec])) {
        return false;
    }

    $defaults = casting_message_access_build_default_edges();
    $data = casting_message_access_get();
    $overrides = $data['overrides'];
    $prefix = $from_spec . '|';

    // پاک کردن overrideهای خروجی این تخصص
    foreach (array_keys($overrides) as $key) {
        if (str_starts_with($key, $prefix)) {
            unset($overrides[$key]);
        }
    }

    // همهٔ پیش‌فرض‌های خروجی این تخصص را خاموش فرض کن، بعد فقط انتخاب‌شده‌ها را روشن کن
    foreach (array_keys($defaults) as $key) {
        if (!str_starts_with($key, $prefix)) {
            continue;
        }
        $overrides[$key] = ['enabled' => false, 'require_project' => false];
    }

    $valid = casting_activity_labels();
    foreach ($targets as $item) {
        if (is_string($item)) {
            $to = sanitize_key($item);
            $require = false;
            $enabled = true;
        } elseif (is_array($item)) {
            $to = sanitize_key((string) ($item['to'] ?? ''));
            $require = !empty($item['require_project']);
            $enabled = array_key_exists('enabled', $item) ? !empty($item['enabled']) : true;
        } else {
            continue;
        }
        if ($to === '' || $to === $from_spec || !isset($valid[$to]) || !$enabled) {
            continue;
        }
        $key = casting_message_access_edge_key($from_spec, $to);
        $default_req = isset($defaults[$key]) && !empty($defaults[$key]['require_project']);
        if (isset($defaults[$key]) && $require === $default_req) {
            unset($overrides[$key]);
        } else {
            $overrides[$key] = [
                'enabled'         => true,
                'require_project' => $require,
            ];
        }
    }

    return casting_message_access_save_overrides($overrides);
}
