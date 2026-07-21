<?php
declare(strict_types=1);

const CASTING_WORKS_CATALOG_OPTION = 'casting_works_catalog';

/**
 * @return array<string, string>
 */
function casting_work_contribution_role_labels(): array
{
    return [
        'director' => 'کارگردان',
        'actor'    => 'بازیگر',
        'producer' => 'تهیه‌کننده',
    ];
}

function casting_work_catalog_normalize_key(string $type, string $title): string
{
    $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
    if (function_exists('mb_strtolower')) {
        $title = mb_strtolower($title, 'UTF-8');
    } else {
        $title = strtolower($title);
    }

    return sanitize_key($type) . '|' . $title;
}

/**
 * @return array<string, array<string, mixed>>
 */
function casting_work_catalog_get(): array
{
    $raw = get_option(CASTING_WORKS_CATALOG_OPTION, []);
    return is_array($raw) ? $raw : [];
}

/**
 * @param array<string, array<string, mixed>> $catalog
 */
function casting_work_catalog_save(array $catalog): void
{
    update_option(CASTING_WORKS_CATALOG_OPTION, $catalog, false);
}

function casting_work_catalog_contribution_role_for_user(int $user_id): string
{
    $role = casting_get_user_role($user_id);
    if ($role === 'producer') {
        return 'producer';
    }
    if ($role === 'director') {
        return 'director';
    }

    return 'actor';
}

/**
 * @param array<int, array{type:string,title:string}> $acting_credits
 * @param array<int, array{type:string,title:string}> $artistic_works
 */
function casting_work_catalog_sync_user_works(int $user_id, array $acting_credits, array $artistic_works): void
{
    $catalog = casting_work_catalog_get();
    foreach ($catalog as $key => $entry) {
        if (!is_array($entry)) {
            unset($catalog[$key]);
            continue;
        }
        $contributors = is_array($entry['contributors'] ?? null) ? $entry['contributors'] : [];
        $contributors = array_values(array_filter(
            $contributors,
            static fn($row): bool => is_array($row) && (int) ($row['user_id'] ?? 0) !== $user_id
        ));
        if ($contributors === []) {
            unset($catalog[$key]);
            continue;
        }
        $catalog[$key]['contributors'] = $contributors;
    }

    $user = get_userdata($user_id);
    $display = $user ? (string) $user->display_name : '';
    $login = $user ? (string) $user->user_login : '';

    $add_works = static function (array $works, string $role) use (&$catalog, $user_id, $display, $login): void {
        foreach ($works as $work) {
            if (!is_array($work)) {
                continue;
            }
            $type = sanitize_key((string) ($work['type'] ?? 'film'));
            $title = sanitize_text_field((string) ($work['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $key = casting_work_catalog_normalize_key($type, $title);
            if (!isset($catalog[$key]) || !is_array($catalog[$key])) {
                $catalog[$key] = [
                    'id'           => 'w_' . substr(md5($key), 0, 12),
                    'type'         => $type,
                    'title'        => $title,
                    'key'          => $key,
                    'contributors' => [],
                    'updated_at'   => gmdate('c'),
                ];
            }
            $catalog[$key]['title'] = $title;
            $catalog[$key]['type'] = $type;
            $catalog[$key]['updated_at'] = gmdate('c');
            $catalog[$key]['contributors'][] = [
                'user_id' => $user_id,
                'role'    => $role,
                'name'    => $display,
                'login'   => $login,
            ];
        }
    };

    $add_works($acting_credits, 'actor');
    $add_works($artistic_works, casting_work_catalog_contribution_role_for_user($user_id));

    casting_work_catalog_save($catalog);
}

/**
 * @return array<int, int>
 */
function casting_work_catalog_find_user_ids(string $query, string $role = ''): array
{
    $query = trim(sanitize_text_field($query));
    if ($query === '') {
        return [];
    }

    $needle = function_exists('mb_strtolower') ? mb_strtolower($query, 'UTF-8') : strtolower($query);
    $ids = [];

    foreach (casting_work_catalog_get() as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $title = (string) ($entry['title'] ?? '');
        if ($title === '') {
            continue;
        }
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
        if (!str_contains($haystack, $needle)) {
            continue;
        }
        foreach ($entry['contributors'] ?? [] as $contributor) {
            if (!is_array($contributor)) {
                continue;
            }
            if ($role !== '' && sanitize_key((string) ($contributor['role'] ?? '')) !== sanitize_key($role)) {
                continue;
            }
            $uid = (int) ($contributor['user_id'] ?? 0);
            if ($uid > 0) {
                $ids[] = $uid;
            }
        }
    }

    return array_values(array_unique($ids));
}

/**
 * @return array<int, string>
 */
function casting_work_catalog_title_suggestions(string $query, int $limit = 12): array
{
    $query = trim(sanitize_text_field($query));
    if ($query === '') {
        return [];
    }

    $needle = function_exists('mb_strtolower') ? mb_strtolower($query, 'UTF-8') : strtolower($query);
    $titles = [];

    foreach (casting_work_catalog_get() as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $title = (string) ($entry['title'] ?? '');
        if ($title === '') {
            continue;
        }
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
        if (!str_contains($haystack, $needle)) {
            continue;
        }
        $titles[$title] = $title;
        if (count($titles) >= $limit) {
            break;
        }
    }

    return array_values($titles);
}
