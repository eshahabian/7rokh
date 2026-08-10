<?php
declare(strict_types=1);

/**
 * ذخیرهٔ جستجوی اعضا در حساب کاربر (بدون اعلان پیامک)
 */

function casting_saved_searches_meta_key(): string
{
    return 'casting_saved_searches';
}

function casting_saved_searches_max(): int
{
    return 12;
}

/**
 * @return list<string>
 */
function casting_saved_search_filter_keys(): array
{
    return [
        'q',
        'activity_category',
        'activity_specialty',
        'gender',
        'look',
        'age_range',
        'height_range',
        'weight_range',
        'health_well',
        'province',
        'city',
        'artistic_org',
        'availability',
        'experience',
        'language',
        'language_level',
        'education_degree',
        'has_video',
        'has_portfolio',
        'eye_color',
        'hair_color',
        'accent',
        'apparent_age_range',
        'motor_skill',
        'artistic_skill',
    ];
}

/**
 * @param array<string, mixed> $filters
 * @return array<string, string>
 */
function casting_saved_search_normalize_filters(array $filters): array
{
    $parsed = casting_parse_member_search_filters($filters);
    $out = [];
    foreach (casting_saved_search_filter_keys() as $key) {
        $value = trim((string) ($parsed[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        if ($key === 'city' && function_exists('casting_city_all_label') && $value === casting_city_all_label()) {
            continue;
        }
        $out[$key] = $value;
    }

    return $out;
}

/**
 * @param array<string, mixed> $row
 */
function casting_saved_search_normalize_row($row): ?array
{
    if (!is_array($row)) {
        return null;
    }
    $id = sanitize_key((string) ($row['id'] ?? ''));
    $name = trim(sanitize_text_field((string) ($row['name'] ?? '')));
    $filters = casting_saved_search_normalize_filters(is_array($row['filters'] ?? null) ? $row['filters'] : []);
    if ($id === '' || $filters === []) {
        return null;
    }
    if ($name === '') {
        $name = casting_saved_search_auto_name($filters);
    }

    return [
        'id'         => $id,
        'name'       => casting_strlen($name) > 60 ? rtrim(function_exists('mb_substr') ? mb_substr($name, 0, 60) : substr($name, 0, 60)) . '…' : $name,
        'filters'    => $filters,
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

/**
 * @return list<array{id:string,name:string,filters:array<string,string>,created_at:string}>
 */
function casting_saved_searches_list(int $user_id): array
{
    if ($user_id <= 0) {
        return [];
    }
    $raw = get_user_meta($user_id, casting_saved_searches_meta_key(), true);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $row) {
        $item = casting_saved_search_normalize_row($row);
        if ($item !== null) {
            $out[] = $item;
        }
    }

    return $out;
}

/**
 * @param list<array{id:string,name:string,filters:array<string,string>,created_at:string}> $items
 */
function casting_saved_searches_store(int $user_id, array $items): void
{
    if ($user_id <= 0) {
        return;
    }
    $items = array_slice(array_values($items), 0, casting_saved_searches_max());
    update_user_meta($user_id, casting_saved_searches_meta_key(), $items);
}

/**
 * @param array<string, string> $filters
 */
function casting_saved_search_auto_name(array $filters): string
{
    $parts = [];
    if (!empty($filters['activity_specialty']) && function_exists('casting_activity_labels')) {
        $labels = casting_activity_labels();
        $key = sanitize_key((string) $filters['activity_specialty']);
        if (isset($labels[$key])) {
            $parts[] = (string) $labels[$key];
        }
    } elseif (!empty($filters['activity_category']) && function_exists('casting_activity_categories')) {
        $cats = casting_activity_categories();
        $key = sanitize_key((string) $filters['activity_category']);
        if (isset($cats[$key]['label'])) {
            $parts[] = (string) $cats[$key]['label'];
        }
    }
    if (!empty($filters['gender']) && function_exists('casting_gender_labels')) {
        $g = casting_gender_labels();
        $key = sanitize_key((string) $filters['gender']);
        if (isset($g[$key])) {
            $parts[] = (string) $g[$key];
        }
    }
    if (!empty($filters['province']) && function_exists('casting_province_labels')) {
        $p = casting_province_labels();
        $key = sanitize_key((string) $filters['province']);
        if (isset($p[$key])) {
            $parts[] = (string) $p[$key];
        }
    }
    if (!empty($filters['city']) && (!function_exists('casting_city_all_label') || $filters['city'] !== casting_city_all_label())) {
        $parts[] = (string) $filters['city'];
    }
    if (!empty($filters['age_range'])) {
        $parts[] = 'سن ' . (string) $filters['age_range'];
    }
    if (($filters['has_video'] ?? '') === 'yes') {
        $parts[] = 'دارای ویدیو';
    }
    if (!empty($filters['q'])) {
        $parts[] = '«' . (string) $filters['q'] . '»';
    }
    if ($parts === []) {
        return 'جستجوی ذخیره‌شده';
    }

    return implode(' · ', array_slice($parts, 0, 4));
}

/**
 * @param array<string, string> $filters
 */
function casting_saved_search_url(array $filters, string $saved_id = ''): string
{
    $query = casting_saved_search_normalize_filters($filters);
    if ($saved_id !== '') {
        $query['saved'] = sanitize_key($saved_id);
    }

    return 'search-users.php' . ($query !== [] ? '?' . http_build_query($query) : '');
}

/**
 * @param array<string, mixed> $filters
 * @return array{ok:bool,error:string,item?:array{id:string,name:string,filters:array<string,string>,created_at:string}}
 */
function casting_saved_search_save(int $user_id, string $name, array $filters): array
{
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'ورود لازم است.'];
    }
    $normalized = casting_saved_search_normalize_filters($filters);
    if ($normalized === []) {
        return ['ok' => false, 'error' => 'اول حداقل یک فیلتر انتخاب کنید.'];
    }
    $name = trim(sanitize_text_field($name));
    if ($name === '') {
        $name = casting_saved_search_auto_name($normalized);
    }
    if (casting_strlen($name) > 60) {
        $name = rtrim(function_exists('mb_substr') ? mb_substr($name, 0, 60) : substr($name, 0, 60));
    }

    $items = casting_saved_searches_list($user_id);
    foreach ($items as $i => $item) {
        if (($item['filters'] ?? []) === $normalized) {
            $items[$i]['name'] = $name;
            casting_saved_searches_store($user_id, $items);

            return ['ok' => true, 'error' => '', 'item' => $items[$i]];
        }
    }

    if (count($items) >= casting_saved_searches_max()) {
        return ['ok' => false, 'error' => 'حداکثر ' . casting_saved_searches_max() . ' جستجو می‌توانید ذخیره کنید. یکی را حذف کنید.'];
    }

    $item = [
        'id'         => 's' . substr(md5(uniqid((string) $user_id, true)), 0, 12),
        'name'       => $name,
        'filters'    => $normalized,
        'created_at' => gmdate('c'),
    ];
    array_unshift($items, $item);
    casting_saved_searches_store($user_id, $items);

    return ['ok' => true, 'error' => '', 'item' => $item];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_saved_search_delete(int $user_id, string $id): array
{
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'ورود لازم است.'];
    }
    $id = sanitize_key($id);
    if ($id === '') {
        return ['ok' => false, 'error' => 'جستجو پیدا نشد.'];
    }
    $items = casting_saved_searches_list($user_id);
    $next = [];
    $found = false;
    foreach ($items as $item) {
        if (($item['id'] ?? '') === $id) {
            $found = true;
            continue;
        }
        $next[] = $item;
    }
    if (!$found) {
        return ['ok' => false, 'error' => 'جستجو پیدا نشد.'];
    }
    casting_saved_searches_store($user_id, $next);

    return ['ok' => true, 'error' => ''];
}

/**
 * @param array<string, string> $current_filters
 */
function casting_render_saved_searches_bar(int $user_id, array $current_filters = [], string $active_id = ''): void
{
    $items = casting_saved_searches_list($user_id);
    $can_save = casting_member_search_filters_active($current_filters);
    $active_id = sanitize_key($active_id);
    ?>
  <div class="saved-searches" data-saved-searches>
    <div class="saved-searches-head">
      <span class="saved-searches-title">جستجوهای ذخیره‌شده</span>
      <button type="button" class="btn btn-ghost btn-sm" data-saved-search-open<?= $can_save ? '' : ' disabled' ?> title="<?= $can_save ? 'ذخیره فیلترهای فعلی' : 'اول یک فیلتر انتخاب کنید' ?>">ذخیره این جستجو</button>
    </div>

    <div class="saved-search-compose" data-saved-search-compose hidden>
      <label class="sr-only" for="saved-search-name">نام جستجو</label>
      <input id="saved-search-name" type="text" maxlength="60" placeholder="مثلاً بازیگر زن تهران ۲۵–۳۵" data-saved-search-name>
      <div class="cta-row">
        <button type="button" class="btn btn-primary btn-sm" data-saved-search-save>ذخیره در حساب</button>
        <button type="button" class="btn btn-ghost btn-sm" data-saved-search-cancel>انصراف</button>
      </div>
      <p class="field-hint">فقط در حساب شما ذخیره می‌شود؛ فعلاً پیامک یا اعلان ندارد.</p>
    </div>

    <div class="saved-searches-list" data-saved-searches-list>
      <?php if ($items === []) : ?>
        <p class="saved-searches-empty meta" data-saved-searches-empty>هنوز جستجویی ذخیره نکرده‌اید.</p>
      <?php else : ?>
        <?php foreach ($items as $item) :
            $href = casting_saved_search_url($item['filters'], (string) $item['id']);
            $is_active = $active_id !== '' && $active_id === (string) $item['id'];
            ?>
          <div class="saved-search-chip<?= $is_active ? ' is-active' : '' ?>" data-saved-search-item="<?= casting_e((string) $item['id']) ?>">
            <a class="saved-search-chip-link" href="<?= casting_e($href) ?>"><?= casting_e((string) $item['name']) ?></a>
            <button type="button" class="saved-search-chip-delete" data-saved-search-delete="<?= casting_e((string) $item['id']) ?>" aria-label="حذف <?= casting_e((string) $item['name']) ?>">×</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
    <?php
}

/**
 * @return list<array{id:string,name:string,href:string}>
 */
function casting_saved_searches_public_list(int $user_id): array
{
    $out = [];
    foreach (casting_saved_searches_list($user_id) as $item) {
        $out[] = [
            'id'   => (string) $item['id'],
            'name' => (string) $item['name'],
            'href' => casting_saved_search_url($item['filters'], (string) $item['id']),
        ];
    }

    return $out;
}
