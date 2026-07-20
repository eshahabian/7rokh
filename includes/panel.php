<?php
declare(strict_types=1);

require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/premium.php';
require_once __DIR__ . '/admin-access.php';
require_once __DIR__ . '/chat-rules.php';
require_once __DIR__ . '/layout.php';

/**
 * @return array<int, array{key:string,label:string,href:string,icon?:string}>
 */
function casting_panel_nav_items(): array
{
    return [
        ['key' => 'panel',      'label' => 'پنل کاربری',              'href' => 'panel.php'],
        ['key' => 'messages',   'label' => 'پیام کاربران',            'href' => 'chat.php'],
        ['key' => 'search',     'label' => 'جستجوی کاربران',          'href' => 'search-users.php'],
        ['key' => 'premium',    'label' => 'خرید و فعال‌سازی',        'href' => 'premium.php'],
        ['key' => 'receipt',    'label' => 'ثبت فیش کارت به کارت',    'href' => 'premium-receipt.php'],
        ['key' => 'newest',     'label' => 'جدیدترین کاربران',        'href' => 'newest-users.php'],
        ['key' => 'visitors',   'label' => 'بازدیدکنندگان پروفایل من', 'href' => 'profile-visitors.php'],
        ['key' => 'blocked',    'label' => 'بلاک‌شده‌های من',         'href' => 'blocked-by-me.php'],
        ['key' => 'photo',      'label' => 'ویرایش تصویر',            'href' => 'profile-photo.php'],
        ['key' => 'password',   'label' => 'تغییر رمز عبور',          'href' => 'change-password.php'],
        ['key' => 'transactions','label' => 'تراکنش‌های مالی',        'href' => 'transactions.php'],
        ['key' => 'cancel',     'label' => 'انصراف از عضویت',         'href' => 'cancel-membership.php'],
        ['key' => 'contact',    'label' => 'تماس با ما',              'href' => 'contact.php'],
        ['key' => 'faq',        'label' => 'سوالات متداول',           'href' => 'faq.php'],
        ['key' => 'rules',      'label' => 'قوانین',                  'href' => 'rules.php'],
        ['key' => 'logout',     'label' => 'خروج',                    'href' => 'logout.php'],
    ];
}

function casting_panel_profile_url(int $user_id): string
{
    $role = casting_get_user_role($user_id);
    if ($role === 'talent') {
        return 'member.php?id=' . $user_id;
    }
    return 'member.php?id=' . $user_id;
}

function casting_render_panel_sidebar(string $active): void
{
    $unread_peers = 0;
    $pending_receipts = 0;
    $unread_contacts = 0;
    $panel_premium_until = null;
    $user = casting_current_user();
    if ($user) {
        $user_id = (int) $user->ID;
        if (!function_exists('casting_dm_unread_peer_count')) {
            require_once __DIR__ . '/chat.php';
        }
        $unread_peers = casting_dm_unread_peer_count($user_id);
        if (!function_exists('casting_user_has_admin_permission')) {
            require_once __DIR__ . '/admin-access.php';
        }
        if (casting_user_has_admin_permission($user_id, 'approve_receipts')) {
            $pending_receipts = casting_admin_pending_receipt_count();
        }
        if (!function_exists('casting_contact_unread_count_for_user')) {
            require_once __DIR__ . '/contact-messages.php';
        }
        $unread_contacts = casting_contact_unread_count_for_user($user_id);
        if (casting_user_is_premium($user_id)) {
            $panel_premium_until = casting_premium_expire_timestamp($user_id);
        }
    }
    $admin_nav = $user ? casting_panel_admin_nav_items((int) $user->ID) : [];
    ?>
    <aside class="panel-sidebar" aria-label="منوی پنل کاربری">
      <p class="panel-sidebar-title">پنل کاربری</p>
      <nav class="panel-nav">
        <?php foreach (casting_panel_nav_items() as $item) : ?>
          <a class="panel-nav-link <?= $active === $item['key'] ? 'is-active' : '' ?>" href="<?= casting_e($item['href']) ?><?= $item['key'] === 'premium' && $unread_peers === 0 && $pending_receipts > 0 ? '#admin-receipts' : '' ?>">
            <span class="panel-nav-label"><?= casting_e($item['label']) ?></span>
            <?php if ($item['key'] === 'messages' && $unread_peers > 0) : ?>
              <span class="nav-badge" aria-label="<?= casting_e((string) $unread_peers) ?> پیام جدید"><?= (int) $unread_peers ?></span>
            <?php elseif ($item['key'] === 'panel' && $panel_premium_until !== null && $user) : ?>
              <span class="nav-premium-countdown" data-premium-until-ts="<?= (int) $panel_premium_until ?>" title="زمان باقی‌مانده حساب ویژه">
                <span data-premium-countdown><?= casting_e(casting_premium_countdown_nav_label((int) $user->ID)) ?></span>
              </span>
            <?php elseif ($item['key'] === 'premium' && $pending_receipts > 0) : ?>
              <span class="nav-badge" aria-label="<?= casting_e((string) $pending_receipts) ?> فیش در انتظار"><?= (int) $pending_receipts ?></span>
            <?php elseif ($item['key'] === 'contact' && $unread_contacts > 0) : ?>
              <span class="nav-badge" aria-label="<?= casting_e((string) $unread_contacts) ?> پیام جدید"><?= (int) $unread_contacts ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <?php if ($admin_nav) : ?>
        <p class="panel-sidebar-title panel-sidebar-title-admin">مدیریت</p>
        <nav class="panel-nav panel-nav-admin">
          <?php foreach ($admin_nav as $item) : ?>
            <a class="panel-nav-link panel-nav-link-admin <?= $active === $item['key'] ? 'is-active' : '' ?>" href="<?= casting_e($item['href']) ?>">
              <span class="panel-nav-label"><?= casting_e($item['label']) ?></span>
              <?php if ($item['key'] === 'admin-receipts' && $pending_receipts > 0) : ?>
                <span class="nav-badge" aria-label="<?= casting_e((string) $pending_receipts) ?> فیش در انتظار"><?= (int) $pending_receipts ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </aside>
    <?php
}

function casting_render_panel_start(string $title, string $active, string $body_class = 'page-panel'): void
{
    casting_render_head($title, $body_class);
    casting_render_header('panel');
    echo '<main class="wrap panel-shell">';
    casting_render_panel_sidebar($active);
    echo '<div class="panel-content">';
}

function casting_render_panel_end(): void
{
    echo '</div></main>';
    casting_render_footer();
}

/**
 * @return array<string, array{label:string,min:int,max:int}>
 */
function casting_search_height_range_options(): array
{
    $out = [];
    for ($min = 160; $min < 190; $min += 5) {
        $max = $min + 5;
        $key = $min . '_' . $max;
        $out[$key] = [
            'label' => $min . '-' . $max,
            'min'   => $min,
            'max'   => $max,
        ];
    }
    return $out;
}

/**
 * @return array<string, array{label:string,min:int,max:int}>
 */
function casting_search_weight_range_options(): array
{
    $out = [];
    for ($min = 50; $min < 110; $min += 10) {
        $max = $min + 10;
        $key = $min . '_' . $max;
        $out[$key] = [
            'label' => $min . '-' . $max,
            'min'   => $min,
            'max'   => $max,
        ];
    }
    return $out;
}

/**
 * @param array<string, array{label:string,min:int,max:int}> $options
 */
function casting_render_search_band_select(string $id, string $name, string $label, array $options, string $value): void
{
    ?>
    <div class="field">
      <label for="<?= casting_e($id) ?>"><?= casting_e($label) ?></label>
      <select id="<?= casting_e($id) ?>" name="<?= casting_e($name) ?>">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($options as $key => $range) : ?>
          <option value="<?= casting_e($key) ?>" <?= $value === $key ? 'selected' : '' ?>><?= casting_e($range['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php
}

/**
 * تبدیل اعداد فارسی/عربی به لاتین برای فیلتر محدوده
 */
function casting_normalize_search_digits(string $value): string
{
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $value = str_replace($persian, $latin, $value);
    $value = str_replace($arabic, $latin, $value);
    $value = preg_replace('/[\x{2013}\x{2014}\x{2212}]/u', '-', $value) ?? $value;

    return trim($value);
}

/**
 * @return array{min:?int,max:?int}
 */
function casting_parse_search_metric_range(string $raw, int $floor, int $ceil): array
{
    $raw = casting_normalize_search_digits($raw);
    if ($raw === '') {
        return ['min' => null, 'max' => null];
    }

    $min = null;
    $max = null;
    if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $raw, $matches) === 1) {
        $min = (int) $matches[1];
        $max = (int) $matches[2];
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }
    } elseif (preg_match('/^\d+$/', $raw) === 1) {
        $min = (int) $raw;
    } else {
        return ['min' => null, 'max' => null];
    }

    if ($min !== null && ($min < $floor || $min > $ceil)) {
        $min = null;
    }
    if ($max !== null && ($max < $floor || $max > $ceil)) {
        $max = null;
    }

    return ['min' => $min, 'max' => $max];
}

function casting_search_metric_range_from_input(array $input, string $range_key, string $min_key, string $max_key): string
{
    $value = casting_normalize_search_digits((string) ($input[$range_key] ?? ''));
    if ($value !== '') {
        return $value;
    }

    $min = casting_normalize_search_digits((string) ($input[$min_key] ?? ''));
    $max = casting_normalize_search_digits((string) ($input[$max_key] ?? ''));
    if ($min === '' && $max === '') {
        return '';
    }
    if ($min !== '' && $max !== '') {
        return $min . '-' . $max;
    }

    return $min !== '' ? $min : $max;
}

/**
 * یک گروه متریک (سن / قد / وزن) — بدون wrapper کل عرض
 *
 * @param array<string, string> $filters
 * @param array{prefix: string, label: string, unit: string, floor: int, ceil: int, range_key: string} $metric
 */
function casting_render_body_metric_group(array $filters, array $metric): void
{
    $parts = casting_parse_search_metric_range(
        (string) ($filters[$metric['range_key']] ?? ''),
        $metric['floor'],
        $metric['ceil']
    );
    $min_val = $parts['min'] !== null ? (string) $parts['min'] : '';
    $max_val = $parts['max'] !== null ? (string) $parts['max'] : '';
    ?>
    <div class="filter-metric-group">
      <div class="filter-metric-head">
        <span class="filter-metric-label"><?= casting_e($metric['label']) ?></span>
        <span class="filter-metric-unit"><?= casting_e($metric['unit']) ?></span>
      </div>
      <div class="filter-metric-range">
        <div class="field">
          <input
            id="<?= casting_e($metric['prefix']) ?>_min"
            name="<?= casting_e($metric['prefix']) ?>_min"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            value="<?= casting_e($min_val) ?>"
            placeholder="از"
            aria-label="<?= casting_e($metric['label']) ?> از"
          >
        </div>
        <div class="field">
          <input
            id="<?= casting_e($metric['prefix']) ?>_max"
            name="<?= casting_e($metric['prefix']) ?>_max"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            value="<?= casting_e($max_val) ?>"
            placeholder="تا"
            aria-label="<?= casting_e($metric['label']) ?> تا"
          >
        </div>
      </div>
    </div>
    <?php
}

/**
 * سن، قد و وزن — هر کدام دو فیلد «از» و «تا»
 *
 * @param array<string, string> $filters
 * @param list<string>|null $include
 */
function casting_render_body_metric_search_fields(array $filters, ?array $include = null): void
{
    $metrics = [
        ['prefix' => 'age', 'label' => 'سن', 'unit' => 'سال', 'floor' => 5, 'ceil' => 100, 'range_key' => 'age_range'],
        ['prefix' => 'height', 'label' => 'قد', 'unit' => 'سانتی‌متر', 'floor' => 80, 'ceil' => 230, 'range_key' => 'height_range'],
        ['prefix' => 'weight', 'label' => 'وزن', 'unit' => 'کیلو', 'floor' => 20, 'ceil' => 250, 'range_key' => 'weight_range'],
    ];
    if ($include !== null) {
        $allowed = array_flip($include);
        $metrics = array_values(array_filter($metrics, static fn(array $metric): bool => isset($allowed[$metric['prefix']])));
        usort(
            $metrics,
            static fn(array $a, array $b): int => ((int) array_search($a['prefix'], $include, true)) <=> ((int) array_search($b['prefix'], $include, true))
        );
    }
    if ($metrics === []) {
        return;
    }
    ?>
    <div class="filter-body-metrics" aria-label="فیلتر سن، قد و وزن">
      <?php foreach ($metrics as $metric) {
          casting_render_body_metric_group($filters, $metric);
      } ?>
    </div>
    <?php
}

/**
 * @param array<string, string> $filters
 */
function casting_render_health_search_field(array $filters): void
{
    $value = (string) ($filters['health_well'] ?? '');
    $options = [
        'healthy'   => 'بله',
        'unhealthy' => 'خیر',
    ];
    ?>
    <div class="field">
      <label for="health_well">سلامت</label>
      <select id="health_well" name="health_well">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($options as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $value === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_search_metric_range_filter(
    array &$meta_query,
    string $meta_key,
    string $filter_value,
    int $floor,
    int $ceil
): void {
    $parsed = casting_parse_search_metric_range($filter_value, $floor, $ceil);
    if ($parsed['min'] !== null) {
        $meta_query[] = [
            'key'     => $meta_key,
            'value'   => $parsed['min'],
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }
    if ($parsed['max'] !== null) {
        $meta_query[] = [
            'key'     => $meta_key,
            'value'   => $parsed['max'],
            'type'    => 'NUMERIC',
            'compare' => '<=',
        ];
    }
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_body_metric_search_filters(array &$meta_query, array $filters): void
{
    casting_apply_search_metric_range_filter($meta_query, 'casting_age', (string) ($filters['age_range'] ?? ''), 5, 100);
    casting_apply_search_metric_range_filter($meta_query, 'casting_height', (string) ($filters['height_range'] ?? ''), 80, 230);
    casting_apply_search_metric_range_filter($meta_query, 'casting_weight', (string) ($filters['weight_range'] ?? ''), 20, 250);
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_experience_filters(array &$meta_query, array $filters): void
{
    $exp_min = (int) ($filters['experience_min'] ?? 0);
    $exp_max = (int) ($filters['experience_max'] ?? 0);
    if ($exp_min >= 0 && $exp_min <= 60 && ($filters['experience_min'] ?? '') !== '') {
        $meta_query[] = [
            'key'     => 'casting_experience',
            'value'   => $exp_min,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }
    if ($exp_max >= 0 && $exp_max <= 60 && ($filters['experience_max'] ?? '') !== '') {
        $meta_query[] = [
            'key'     => 'casting_experience',
            'value'   => $exp_max,
            'type'    => 'NUMERIC',
            'compare' => '<=',
        ];
    }
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_language_filters(array &$meta_query, array $filters): void
{
    $language = sanitize_text_field((string) ($filters['language'] ?? ''));
    if ($language !== '') {
        $meta_query[] = [
            'key'     => 'casting_language_items',
            'value'   => $language,
            'compare' => 'LIKE',
        ];
    }

    $language_level = sanitize_key((string) ($filters['language_level'] ?? ''));
    if ($language_level !== '' && array_key_exists($language_level, casting_language_level_labels())) {
        $meta_query[] = [
            'key'     => 'casting_language_items',
            'value'   => '"' . $language_level . '"',
            'compare' => 'LIKE',
        ];
    }
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_education_filters(array &$meta_query, array $filters): void
{
    $degree = sanitize_key((string) ($filters['education_degree'] ?? ''));
    if ($degree !== '' && array_key_exists($degree, casting_education_degree_labels())) {
        $meta_query[] = [
            'key'     => 'casting_education_items',
            'value'   => '"' . $degree . '"',
            'compare' => 'LIKE',
        ];
    }
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_has_video_filter(array &$meta_query, string $value): void
{
    $value = sanitize_key($value);
    if ($value === '' || !array_key_exists($value, casting_yes_no_labels())) {
        return;
    }

    if ($value === 'yes') {
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => 'casting_video_id',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => 'casting_video_url',
                'value'   => '',
                'compare' => '!=',
            ],
        ];
        return;
    }

    $meta_query[] = [
        'relation' => 'AND',
        [
            'relation' => 'OR',
            ['key' => 'casting_video_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_video_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_video_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_video_url', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_video_url', 'value' => '', 'compare' => '='],
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_has_portfolio_filter(array &$meta_query, string $value): void
{
    $value = sanitize_key($value);
    if ($value === '' || !array_key_exists($value, casting_yes_no_labels())) {
        return;
    }

    $has_portfolio = [
        'relation' => 'OR',
        [
            'relation' => 'AND',
            ['key' => 'casting_work_credits', 'compare' => 'EXISTS'],
            ['key' => 'casting_work_credits', 'value' => 'a:0:{}', 'compare' => '!='],
        ],
        ['key' => 'casting_photo_closeup_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
        ['key' => 'casting_photo_medium_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
        ['key' => 'casting_photo_long_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
        ['key' => 'casting_photo_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
        [
            'key'     => 'casting_video_id',
            'value'   => 0,
            'compare' => '>',
            'type'    => 'NUMERIC',
        ],
        [
            'key'     => 'casting_video_url',
            'value'   => '',
            'compare' => '!=',
        ],
    ];

    if ($value === 'yes') {
        $meta_query[] = $has_portfolio;
        return;
    }

    $meta_query[] = [
        'relation' => 'AND',
        [
            'relation' => 'OR',
            ['key' => 'casting_work_credits', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_work_credits', 'value' => 'a:0:{}', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_photo_closeup_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_photo_closeup_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_photo_closeup_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_photo_medium_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_photo_medium_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_photo_medium_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_photo_long_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_photo_long_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_photo_long_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_photo_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_photo_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_photo_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_video_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_video_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'casting_video_id', 'value' => '', 'compare' => '='],
        ],
        [
            'relation' => 'OR',
            ['key' => 'casting_video_url', 'compare' => 'NOT EXISTS'],
            ['key' => 'casting_video_url', 'value' => '', 'compare' => '='],
        ],
    ];
}

/**
 * @param array<string, string> $filters
 */
function casting_render_member_search_phase1_fields(array $filters): void
{
    $yes_no = casting_yes_no_labels();
    $language_levels = casting_language_level_labels();
    $education_degrees = casting_education_degree_labels();
    $languages = casting_common_languages();
    ?>
    <div class="field">
      <label for="experience_min">سابقه از</label>
      <input id="experience_min" name="experience_min" type="number" min="0" max="60" value="<?= casting_e($filters['experience_min']) ?>" placeholder="سال">
    </div>
    <div class="field">
      <label for="experience_max">سابقه تا</label>
      <input id="experience_max" name="experience_max" type="number" min="0" max="60" value="<?= casting_e($filters['experience_max']) ?>" placeholder="سال">
    </div>
    <div class="field">
      <label for="language">زبان</label>
      <input id="language" name="language" type="search" list="casting-search-languages" value="<?= casting_e($filters['language']) ?>" placeholder="مثلاً انگلیسی">
      <datalist id="casting-search-languages">
        <?php foreach ($languages as $lang) : ?>
          <option value="<?= casting_e($lang) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>
    <div class="field">
      <label for="language_level">سطح زبان</label>
      <select id="language_level" name="language_level">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($language_levels as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['language_level'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="education_degree">تحصیلات</label>
      <select id="education_degree" name="education_degree">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($education_degrees as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['education_degree'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="has_video">ویدئو معرفی</label>
      <select id="has_video" name="has_video">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($yes_no as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['has_video'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="has_portfolio">نمونه‌کار</label>
      <select id="has_portfolio" name="has_portfolio">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($yes_no as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['has_portfolio'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_phase2_filters(array &$meta_query, array $filters): void
{
    $eye = sanitize_key((string) ($filters['eye_color'] ?? ''));
    if ($eye !== '' && array_key_exists($eye, casting_eye_color_labels())) {
        $meta_query[] = [
            'key'   => 'casting_eye_color',
            'value' => $eye,
        ];
    }

    $hair = sanitize_key((string) ($filters['hair_color'] ?? ''));
    if ($hair !== '' && array_key_exists($hair, casting_hair_color_labels())) {
        $meta_query[] = [
            'key'   => 'casting_hair_color',
            'value' => $hair,
        ];
    }

    $accent = sanitize_key((string) ($filters['accent'] ?? ''));
    if ($accent !== '' && array_key_exists($accent, casting_accent_labels())) {
        $meta_query[] = [
            'key'   => 'casting_accent',
            'value' => $accent,
        ];
    }

    $apparent = sanitize_key((string) ($filters['apparent_age_range'] ?? ''));
    if ($apparent !== '' && array_key_exists($apparent, casting_age_range_options())) {
        $meta_query[] = [
            'key'   => 'casting_apparent_age_range',
            'value' => $apparent,
        ];
    }

    $motor = sanitize_key((string) ($filters['motor_skill'] ?? ''));
    if ($motor !== '' && isset(casting_motor_skill_filter_labels()[$motor])) {
        $meta_query[] = [
            'key'     => 'casting_skill_items',
            'value'   => '"' . $motor . '"',
            'compare' => 'LIKE',
        ];
    }

    $art_skill = sanitize_key((string) ($filters['artistic_skill'] ?? ''));
    if ($art_skill !== '' && isset(casting_artistic_skill_filter_labels()[$art_skill])) {
        $meta_query[] = [
            'key'     => 'casting_skill_items',
            'value'   => '"' . $art_skill . '"',
            'compare' => 'LIKE',
        ];
    }
}

/**
 * رنگ چشم، رنگ مو، سن ظاهری، لهجه، همکاری — بلافاصله بعد از سلامت
 *
 * @param array<string, string> $filters
 */
function casting_render_member_search_after_health_fields(array $filters): void
{
    $eyes = casting_eye_color_labels();
    $hairs = casting_hair_color_labels();
    $accents = casting_accent_labels();
    $age_ranges = casting_age_range_options();
    $availability_labels = casting_availability_labels();
    ?>
    <div class="filter-appearance-cluster" aria-label="فیلتر ظاهری">
    <div class="field">
      <label for="eye_color">رنگ چشم</label>
      <select id="eye_color" name="eye_color">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($eyes as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['eye_color'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="hair_color">رنگ مو</label>
      <select id="hair_color" name="hair_color">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($hairs as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['hair_color'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="apparent_age_range">سن ظاهری</label>
      <select id="apparent_age_range" name="apparent_age_range">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($age_ranges as $key => $range) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['apparent_age_range'] === $key ? 'selected' : '' ?>><?= casting_e($range['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="accent">لهجه</label>
      <select id="accent" name="accent">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($accents as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['accent'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="availability">همکاری</label>
      <select id="availability" name="availability">
        <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
        <?php foreach ($availability_labels as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $filters['availability'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    </div>
    <?php
}

/**
 * @param array<string, string> $filters
 */
function casting_render_member_search_phase2_fields(array $filters): void
{
}

/**
 * تخصص هنری، تخصص، مهارت هنری، تشکل، مهارت حرکتی — یک ردیف کنار هم
 *
 * @param array<string, string> $filters
 */
function casting_render_member_search_talent_cluster(array $filters): void
{
    $categories = casting_activity_categories();
    $category = (string) ($filters['activity_category'] ?? '');
    $specialty = (string) ($filters['activity_specialty'] ?? '');
    $subs = ($category !== '' && isset($categories[$category])) ? $categories[$category]['items'] : [];
    $map = [];
    foreach ($categories as $cat_key => $cat) {
        $map[$cat_key] = $cat['items'];
    }
    $map_json = wp_json_encode($map, JSON_UNESCAPED_UNICODE);
    if (!is_string($map_json)) {
        $map_json = '{}';
    }

    $artistic_orgs = casting_artistic_org_labels();
    $motor_skills = casting_motor_skill_filter_labels();
    ?>
    <div class="filter-talent-cluster" data-activity-search data-activity-map="<?= casting_e($map_json) ?>">
      <div class="field">
        <label for="activity_category">تخصص هنری</label>
        <select id="activity_category" name="activity_category" data-activity-category>
          <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
          <?php foreach ($categories as $key => $cat) : ?>
            <option value="<?= casting_e($key) ?>" <?= $category === $key ? 'selected' : '' ?>><?= casting_e($cat['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="activity_specialty">تخصص</label>
        <select id="activity_specialty" name="activity_specialty" data-activity-specialty <?= $category === '' ? 'disabled' : '' ?>>
          <option value=""><?= casting_e(casting_search_specialty_empty_label($category !== '')) ?></option>
          <?php foreach ($subs as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $specialty === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="artistic_skill">مهارت هنری</label>
        <select id="artistic_skill" name="artistic_skill">
          <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
          <?php foreach (casting_artistic_skill_filter_labels() as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['artistic_skill'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="artistic_org">تشکل</label>
        <select id="artistic_org" name="artistic_org">
          <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
          <?php foreach ($artistic_orgs as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['artistic_org'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="motor_skill">مهارت حرکتی</label>
        <select id="motor_skill" name="motor_skill">
          <option value=""><?= casting_e(casting_search_filter_empty_label()) ?></option>
          <?php foreach ($motor_skills as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $filters['motor_skill'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php
}

/**
 * @return array<string, string>
 */
function casting_parse_member_search_filters(array $input): array
{
    return [
        'q'                  => (string) ($input['q'] ?? ''),
        'activity_category'  => (string) ($input['activity_category'] ?? ''),
        'activity_specialty' => (string) ($input['activity_specialty'] ?? ''),
        'gender'             => (string) ($input['gender'] ?? ''),
        'look'               => (string) ($input['look'] ?? ''),
        'age_range'          => casting_search_metric_range_from_input($input, 'age_range', 'age_min', 'age_max'),
        'height_range'       => casting_search_metric_range_from_input($input, 'height_range', 'height_min', 'height_max'),
        'weight_range'       => casting_search_metric_range_from_input($input, 'weight_range', 'weight_min', 'weight_max'),
        'health_well'        => sanitize_key((string) ($input['health_well'] ?? '')),
        'province'           => (string) ($input['province'] ?? ''),
        'city'               => (string) ($input['city'] ?? ''),
        'artistic_org'       => (string) ($input['artistic_org'] ?? ''),
        'availability'       => (string) ($input['availability'] ?? ''),
        'experience_min'     => (string) ($input['experience_min'] ?? ''),
        'experience_max'     => (string) ($input['experience_max'] ?? ''),
        'language'           => (string) ($input['language'] ?? ''),
        'language_level'     => (string) ($input['language_level'] ?? ''),
        'education_degree'   => (string) ($input['education_degree'] ?? ''),
        'has_video'          => (string) ($input['has_video'] ?? ''),
        'has_portfolio'      => (string) ($input['has_portfolio'] ?? ''),
        'eye_color'          => (string) ($input['eye_color'] ?? ''),
        'hair_color'         => (string) ($input['hair_color'] ?? ''),
        'accent'             => (string) ($input['accent'] ?? ''),
        'apparent_age_range' => (string) ($input['apparent_age_range'] ?? ''),
        'motor_skill'        => (string) ($input['motor_skill'] ?? ''),
        'artistic_skill'     => (string) ($input['artistic_skill'] ?? ''),
    ];
}

/**
 * @return array{users: WP_User[], total: int}
 */
function casting_query_members(int $exclude_id, array $filters = [], int $page = 1, int $per_page = 20): array
{
    $meta_query = [
        'relation' => 'AND',
        [
            'key'     => 'casting_role',
            'compare' => 'EXISTS',
        ],
    ];

    if (!empty($filters['activity_specialty'])) {
        $activity_specialty = sanitize_key((string) $filters['activity_specialty']);
        $activity_labels = casting_activity_labels();
        if (isset($activity_labels[$activity_specialty])) {
            $meta_query[] = [
                'key'     => 'casting_activities',
                'value'   => '"' . $activity_specialty . '"',
                'compare' => 'LIKE',
            ];
        }
    } elseif (!empty($filters['activity_category'])) {
        $activity_category = sanitize_key((string) $filters['activity_category']);
        $activity_categories = casting_activity_categories();
        if (isset($activity_categories[$activity_category])) {
            $activity_or = ['relation' => 'OR'];
            foreach (array_keys($activity_categories[$activity_category]['items']) as $spec_key) {
                $activity_or[] = [
                    'key'     => 'casting_activities',
                    'value'   => '"' . $spec_key . '"',
                    'compare' => 'LIKE',
                ];
            }
            if (count($activity_or) > 1) {
                $meta_query[] = $activity_or;
            }
        }
    }

    if (!empty($filters['gender']) && array_key_exists($filters['gender'], casting_gender_labels())) {
        $meta_query[] = [
            'key'   => 'casting_gender',
            'value' => sanitize_key((string) $filters['gender']),
        ];
    }

    if (!empty($filters['look']) && array_key_exists($filters['look'], casting_look_labels())) {
        $meta_query[] = [
            'key'   => 'casting_look',
            'value' => sanitize_key((string) $filters['look']),
        ];
    }

    casting_apply_body_metric_search_filters($meta_query, $filters);

    $health_well = sanitize_key((string) ($filters['health_well'] ?? ''));
    if ($health_well === 'healthy' || $health_well === 'unhealthy') {
        $meta_query[] = [
            'key'   => 'casting_health_well',
            'value' => $health_well,
        ];
    }

    $province = sanitize_key((string) ($filters['province'] ?? ''));
    if ($province !== '' && array_key_exists($province, casting_province_labels())) {
        $meta_query[] = [
            'key'   => 'casting_province',
            'value' => $province,
        ];
    }

    $city = casting_normalize_city_name((string) ($filters['city'] ?? ''));
    if ($city !== '') {
        $meta_query[] = [
            'key'     => 'casting_city',
            'value'   => $city,
            'compare' => 'LIKE',
        ];
    }

    $artistic_org = sanitize_key((string) ($filters['artistic_org'] ?? ''));
    $org_labels = casting_artistic_org_labels();
    if ($artistic_org !== '' && isset($org_labels[$artistic_org])) {
        $meta_query[] = [
            'key'     => 'casting_artistic_orgs',
            'value'   => '"' . $artistic_org . '"',
            'compare' => 'LIKE',
        ];
    }

    if (!empty($filters['availability']) && array_key_exists($filters['availability'], casting_availability_labels())) {
        $meta_query[] = [
            'key'   => 'casting_availability',
            'value' => sanitize_key((string) $filters['availability']),
        ];
    }

    casting_apply_member_experience_filters($meta_query, $filters);
    casting_apply_member_language_filters($meta_query, $filters);
    casting_apply_member_education_filters($meta_query, $filters);
    casting_apply_member_has_video_filter($meta_query, (string) ($filters['has_video'] ?? ''));
    casting_apply_member_has_portfolio_filter($meta_query, (string) ($filters['has_portfolio'] ?? ''));
    casting_apply_member_phase2_filters($meta_query, $filters);

    $page = max(1, $page);
    $per_page = max(1, $per_page);
    $args = [
        'number'      => $per_page,
        'offset'      => ($page - 1) * $per_page,
        'orderby'     => 'registered',
        'order'       => 'DESC',
        'meta_query'  => $meta_query,
        'count_total' => true,
        'exclude'     => [$exclude_id],
    ];

    $name_q = trim(sanitize_text_field((string) ($filters['q'] ?? '')));
    if ($name_q !== '') {
        $args['search'] = '*' . esc_attr($name_q) . '*';
        $args['search_columns'] = ['display_name', 'user_login'];
    }

    $query = new WP_User_Query($args);
    $users = $query->get_results();
    if (!is_array($users)) {
        $users = [];
    }

    usort($users, static function (WP_User $a, WP_User $b): int {
        $pa = casting_user_is_premium((int) $a->ID) ? 1 : 0;
        $pb = casting_user_is_premium((int) $b->ID) ? 1 : 0;
        if ($pa !== $pb) {
            return $pb <=> $pa;
        }
        return strcmp((string) $b->user_registered, (string) $a->user_registered);
    });

    return [
        'users' => $users,
        'total' => (int) $query->get_total(),
    ];
}

/**
 * @return array<int, WP_User>
 */
function casting_newest_members(int $limit = 30, int $exclude_id = 0): array
{
    $args = [
        'number'     => $limit,
        'orderby'    => 'registered',
        'order'      => 'DESC',
        'meta_query' => [
            [
                'key'     => 'casting_role',
                'compare' => 'EXISTS',
            ],
        ],
    ];
    if ($exclude_id > 0) {
        $args['exclude'] = [$exclude_id];
    }
    $query = new WP_User_Query($args);
    $users = $query->get_results();
    return is_array($users) ? $users : [];
}

function casting_render_member_card(WP_User $member, int $viewer_id): void
{
    $id = (int) $member->ID;
    $role = casting_get_user_role($id);
    $profile = casting_get_profile($id);
    $premium = casting_user_is_premium($id);
    $photo = $profile['photo_url'] !== '' ? $profile['photo_url'] : '';
    ?>
    <article class="member-card">
      <a class="member-card-photo" href="<?= casting_e(casting_panel_profile_url($id)) ?>">
        <?php if ($photo !== '') : ?>
          <img src="<?= casting_e($photo) ?>" alt="">
        <?php else : ?>
          <span class="photo-placeholder">بدون عکس</span>
        <?php endif; ?>
      </a>
      <div class="member-card-body">
        <h3><a href="<?= casting_e(casting_panel_profile_url($id)) ?>"><?= casting_e($member->display_name) ?></a></h3>
        <p class="meta">
          <?= casting_e(casting_role_label($role)) ?>
          <?php if ($premium) : ?><span class="chip chip-premium">ویژه</span><?php endif; ?>
          <?php if ($profile['city'] !== '') : ?> · <?= casting_e($profile['city']) ?><?php endif; ?>
        </p>
        <?php if ($viewer_id !== $id && casting_can_users_chat($viewer_id, $id)['ok']) : ?>
          <a class="btn btn-ghost btn-sm" href="chat.php?with=<?= $id ?>">پیام</a>
        <?php endif; ?>
      </div>
    </article>
    <?php
}

/**
 * @return array<int, array{id:int,name:string,login:string,role:string,photo_url:string,href:string}>
 */
function casting_search_members_by_name(string $q, int $exclude_id, int $limit = 12): array
{
    $q = trim(sanitize_text_field($q));
    if ($q === '' || casting_strlen($q) < 2) {
        return [];
    }

    $args = [
        'number'         => max(1, min(20, $limit)),
        'search'         => '*' . esc_attr($q) . '*',
        'search_columns' => ['display_name', 'user_login'],
        'orderby'        => 'display_name',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'casting_role',
                'compare' => 'EXISTS',
            ],
        ],
    ];
    if ($exclude_id > 0) {
        $args['exclude'] = [$exclude_id];
    }

    $query = new WP_User_Query($args);
    $users = $query->get_results();
    if (!is_array($users)) {
        return [];
    }

    $out = [];
    foreach ($users as $user) {
        $id = (int) $user->ID;
        $role = casting_get_user_role($id);
        if ($role === '') {
            continue;
        }
        $profile = casting_get_profile($id);
        $out[] = [
            'id'        => $id,
            'name'      => (string) $user->display_name,
            'login'     => (string) $user->user_login,
            'role'      => $role,
            'photo_url' => (string) ($profile['photo_url'] ?? ''),
            'href'      => casting_panel_profile_url($id),
        ];
    }
    return $out;
}

/**
 * @param list<WP_User> $members
 */
function casting_render_member_search_results(array $members, int $viewer_id, int $total, int $page, int $pages, array $filters): void
{
    ?>
  <p class="meta member-search-count"><?= (int) $total ?> کاربر · اعضای ویژه در اولویت نمایش</p>
  <?php if (!$members) : ?>
    <p class="empty-state">کاربری پیدا نشد.</p>
  <?php else : ?>
    <div class="member-grid">
      <?php foreach ($members as $member) : ?>
        <?php casting_render_member_card($member, $viewer_id); ?>
      <?php endforeach; ?>
    </div>
    <?php if ($pages > 1) : ?>
      <nav class="pager" aria-label="صفحه‌بندی">
        <?php for ($p = 1; $p <= $pages; $p++) : ?>
          <a class="pager-link <?= $p === $page ? 'is-active' : '' ?>" href="search-users.php?<?= casting_e(http_build_query(array_merge($filters, ['page' => $p]))) ?>"><?= $p ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
    <?php
}
