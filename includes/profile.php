<?php
declare(strict_types=1);

require_once __DIR__ . '/jalali.php';
require_once __DIR__ . '/activities.php';
require_once __DIR__ . '/membership-number.php';
require_once __DIR__ . '/referral.php';
require_once __DIR__ . '/locations.php';
require_once __DIR__ . '/works-catalog.php';

function casting_gender_labels(): array
{
    return [
        'female' => 'زن',
        'male'   => 'مرد',
    ];
}

/**
 * بازه‌های کرکره‌ای سن / قد / وزن (ثبت‌نام و جستجو)
 *
 * @return array<string, array{label:string,unit:string,min:int,max:int}>
 */
function casting_body_metric_defs(): array
{
    return [
        'age' => [
            'label' => 'سن',
            'unit'  => 'سال',
            'min'   => 3,
            'max'   => 75,
        ],
        'height' => [
            'label' => 'قد',
            'unit'  => 'سانتی‌متر',
            'min'   => 50,
            'max'   => 200,
        ],
        'weight' => [
            'label' => 'وزن',
            'unit'  => 'کیلوگرم',
            'min'   => 15,
            'max'   => 150,
        ],
    ];
}

function casting_body_metric_plus_value(string $kind): int
{
    $defs = casting_body_metric_defs();
    if (!isset($defs[$kind])) {
        return 0;
    }

    return (int) $defs[$kind]['max'] + 1;
}

/**
 * @return list<array{value:string,label:string}>
 */
function casting_body_metric_options(string $kind): array
{
    $defs = casting_body_metric_defs();
    if (!isset($defs[$kind])) {
        return [];
    }
    $def = $defs[$kind];
    $out = [];
    for ($i = (int) $def['min']; $i <= (int) $def['max']; $i++) {
        $out[] = [
            'value' => (string) $i,
            'label' => (string) $i,
        ];
    }
    $out[] = [
        'value' => (string) casting_body_metric_plus_value($kind),
        'label' => (string) $def['max'] . ' یا بالاتر',
    ];

    return $out;
}

function casting_body_metric_is_valid(string $kind, int $value): bool
{
    $defs = casting_body_metric_defs();
    if (!isset($defs[$kind]) || $value <= 0) {
        return false;
    }
    $min = (int) $defs[$kind]['min'];
    $plus = casting_body_metric_plus_value($kind);

    return $value >= $min && $value <= $plus;
}

/**
 * مقدار ذخیره‌شده را به value کرکره نگاشت می‌کند (مقادیر بالاتر از سقف → «یا بالاتر»)
 */
function casting_body_metric_select_value(string $kind, string $stored): string
{
    $stored = trim($stored);
    if ($stored === '' || !ctype_digit($stored)) {
        return '';
    }
    $value = (int) $stored;
    if (!casting_body_metric_is_valid($kind, $value) && $value > 0) {
        $defs = casting_body_metric_defs();
        if (isset($defs[$kind]) && $value > (int) $defs[$kind]['max']) {
            return (string) casting_body_metric_plus_value($kind);
        }

        return '';
    }

    return $value > 0 ? (string) $value : '';
}

function casting_format_body_metric_value(string $kind, string $stored): string
{
    $defs = casting_body_metric_defs();
    if (!isset($defs[$kind])) {
        return '';
    }
    $stored = trim($stored);
    if ($stored === '') {
        return '';
    }
    $value = (int) $stored;
    if ($value <= 0) {
        return '';
    }
    $max = (int) $defs[$kind]['max'];
    $unit = (string) $defs[$kind]['unit'];
    if ($value > $max) {
        return $max . ' یا بالاتر';
    }

    return $value . ' ' . $unit;
}

/**
 * کرکره انتخاب مقدار سن / قد / وزن
 */
function casting_render_body_metric_select(
    string $kind,
    string $name,
    string $id,
    string $selected,
    string $empty_label = 'انتخاب کنید',
    bool $required = false
): void {
    $defs = casting_body_metric_defs();
    if (!isset($defs[$kind])) {
        return;
    }
    $selected = casting_body_metric_select_value($kind, $selected);
    ?>
    <select id="<?= casting_e($id) ?>" name="<?= casting_e($name) ?>"<?= $required ? ' required' : '' ?>>
      <option value=""><?= casting_e($empty_label) ?></option>
      <?php foreach (casting_body_metric_options($kind) as $opt) : ?>
        <option value="<?= casting_e($opt['value']) ?>" <?= $selected === $opt['value'] ? 'selected' : '' ?>><?= casting_e($opt['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php
}

/**
 * بازه‌های سنی فیلتر جستجوی کارفرما
 *
 * @return array<string, array{label:string,min:?int,max:?int}>
 */
function casting_age_range_options(): array
{
    return [
        'under_10' => ['label' => 'زیر ۱۰ سال', 'min' => null, 'max' => 9],
        '10_15'    => ['label' => '۱۰ تا ۱۵', 'min' => 10, 'max' => 15],
        '15_20'    => ['label' => '۱۵ تا ۲۰', 'min' => 15, 'max' => 20],
        '20_25'    => ['label' => '۲۰ تا ۲۵', 'min' => 20, 'max' => 25],
        '30_35'    => ['label' => '۳۰ تا ۳۵', 'min' => 30, 'max' => 35],
        '35_40'    => ['label' => '۳۵ تا ۴۰', 'min' => 35, 'max' => 40],
        'over_40'  => ['label' => 'بالاتر از ۴۰ سال', 'min' => 41, 'max' => null],
    ];
}

function casting_look_labels(): array
{
    return [
        'fair'  => 'سفید / روشن',
        'olive' => 'سبزه',
        'dark'  => 'تیره',
    ];
}

/**
 * @return array<string, string>
 */
function casting_health_well_labels(): array
{
    return [
        'healthy'   => 'سالم',
        'unhealthy' => 'ناسالم',
    ];
}

function casting_resolve_health_well(string $well, string $detail): string
{
    $labels = casting_health_well_labels();
    if ($well !== '' && isset($labels[$well])) {
        return $well;
    }

    return $detail !== '' ? 'unhealthy' : 'healthy';
}

/**
 * @return array{well:string,detail:string}
 */
function casting_parse_health_post(array $post): array
{
    return [
        'well'   => sanitize_key((string) ($post['health_well'] ?? '')),
        'detail' => sanitize_textarea_field((string) ($post['health_status'] ?? '')),
    ];
}

function casting_validate_health_fields(array $health, bool $required = true): ?string
{
    $labels = casting_health_well_labels();
    $well = (string) ($health['well'] ?? '');
    $detail = (string) ($health['detail'] ?? '');

    if ($required && ($well === '' || !isset($labels[$well]))) {
        return 'وضعیت سلامت را انتخاب کنید (سالم یا ناسالم).';
    }
    if ($well !== '' && !isset($labels[$well])) {
        return 'وضعیت سلامت را درست انتخاب کنید.';
    }
    if ($well === 'unhealthy' && trim($detail) === '') {
        return 'برای وضعیت ناسالم، توضیح بنویسید.';
    }
    if (casting_strlen($detail) > 500) {
        return 'توضیح وضعیت سلامت حداکثر ۵۰۰ کاراکتر باشد.';
    }

    return null;
}

/**
 * @param array{well:string,detail:string} $health
 */
function casting_save_health_meta(int $user_id, array $health): void
{
    $labels = casting_health_well_labels();
    $well = sanitize_key((string) ($health['well'] ?? 'healthy'));
    if (!isset($labels[$well])) {
        $well = 'healthy';
    }

    update_user_meta($user_id, 'casting_health_well', $well);
    if ($well === 'healthy') {
        update_user_meta($user_id, 'casting_health_status', '');
        return;
    }

    update_user_meta($user_id, 'casting_health_status', sanitize_textarea_field((string) ($health['detail'] ?? '')));
}

function casting_format_health_display(string $well, string $detail = ''): string
{
    if ($well === 'healthy') {
        return casting_health_well_labels()['healthy'];
    }
    if ($well === 'unhealthy') {
        return $detail !== '' ? 'ناسالم — ' . $detail : casting_health_well_labels()['unhealthy'];
    }

    return $detail !== '' ? $detail : '—';
}

/**
 * @param string $well healthy|unhealthy|''
 */
function casting_render_health_fields(string $well = '', string $detail = '', bool $required = true): void
{
    $labels = casting_health_well_labels();
    $is_unhealthy = $well === 'unhealthy';
    ?>
  <fieldset class="field health-field-wrap" data-health-field>
    <legend>وضعیت سلامت<?= $required ? ' <span class="req-mark">*</span>' : '' ?></legend>
    <div class="role-grid role-grid-2">
      <?php foreach ($labels as $key => $label) : ?>
        <label class="role-option">
          <input type="radio" name="health_well" value="<?= casting_e($key) ?>" <?= $well === $key ? 'checked' : '' ?> <?= $required ? 'required' : '' ?> data-health-well>
          <span><?= casting_e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <div class="health-detail-wrap<?= $is_unhealthy ? ' is-active' : '' ?>" data-health-detail-wrap>
      <label for="health_status">توضیح وضعیت سلامت</label>
      <textarea
        id="health_status"
        name="health_status"
        rows="2"
        maxlength="500"
        placeholder="نوع محدودیت یا بیماری را بنویسید…"
        data-health-detail
        <?= $is_unhealthy ? '' : 'disabled' ?>
      ><?= casting_e($is_unhealthy ? $detail : '') ?></textarea>
    </div>
  </fieldset>
    <?php
}

/**
 * استان‌های ایران
 *
 * @return array<string, string>
 */
function casting_province_labels(): array
{
    return [
        'azarbaijan_east'  => 'آذربایجان شرقی',
        'azarbaijan_west'  => 'آذربایجان غربی',
        'ardabil'          => 'اردبیل',
        'isfahan'          => 'اصفهان',
        'alborz'           => 'البرز',
        'ilam'             => 'ایلام',
        'bushehr'          => 'بوشهر',
        'tehran'           => 'تهران',
        'chaharmahal'      => 'چهارمحال و بختیاری',
        'khorasan_south'   => 'خراسان جنوبی',
        'khorasan_razavi'  => 'خراسان رضوی',
        'khorasan_north'   => 'خراسان شمالی',
        'khuzestan'        => 'خوزستان',
        'zanjan'           => 'زنجان',
        'semnan'           => 'سمنان',
        'sistan'           => 'سیستان و بلوچستان',
        'fars'             => 'فارس',
        'qazvin'           => 'قزوین',
        'qom'              => 'قم',
        'kurdistan'        => 'کردستان',
        'kerman'           => 'کرمان',
        'kermanshah'       => 'کرمانشاه',
        'kohgiluyeh'       => 'کهگیلویه و بویراحمد',
        'golestan'         => 'گلستان',
        'gilan'            => 'گیلان',
        'lorestan'         => 'لرستان',
        'mazandaran'       => 'مازندران',
        'markazi'          => 'مرکزی',
        'hormozgan'        => 'هرمزگان',
        'hamadan'          => 'همدان',
        'yazd'             => 'یزد',
    ];
}

/**
 * @return array<string, string>
 */
function casting_yes_no_labels(): array
{
    return [
        'yes' => 'بله',
        'no'  => 'خیر',
    ];
}

/**
 * @return array<string, string>
 */
function casting_artistic_org_labels(): array
{
    return [
        'cinema_house'    => 'خانه سینما',
        'young_cinema'    => 'انجمن سینمای جوان',
        'theater_house'   => 'خانه تئاتر',
        'performing_arts' => 'انجمن هنرهای نمایشی',
        'art_credit_fund' => 'صندوق اعتبار هنر',
        'other'           => 'سایر',
    ];
}

/**
 * @return array{has:string,orgs:list<string>,other_items:list<string>}
 */
function casting_load_artistic_membership(int $user_id): array
{
    $has = sanitize_key((string) get_user_meta($user_id, 'casting_artistic_membership', true));
    $orgs = get_user_meta($user_id, 'casting_artistic_orgs', true);
    $other = get_user_meta($user_id, 'casting_artistic_other_items', true);
    $labels = casting_artistic_org_labels();

    if ($has === '') {
        $cinema = (string) get_user_meta($user_id, 'casting_cinema_house', true);
        $theater = (string) get_user_meta($user_id, 'casting_theater_house', true);
        if ($cinema === 'yes' || $theater === 'yes') {
            $has = 'yes';
            $orgs = [];
            if ($cinema === 'yes') {
                $orgs[] = 'cinema_house';
            }
            if ($theater === 'yes') {
                $orgs[] = 'theater_house';
            }
        } elseif ($cinema === 'no' && $theater === 'no') {
            $has = 'no';
            $orgs = [];
        }
    }

    $out_orgs = [];
    if (is_array($orgs)) {
        foreach ($orgs as $key) {
            $key = sanitize_key((string) $key);
            if (isset($labels[$key])) {
                $out_orgs[] = $key;
            }
        }
    }

    $out_other = [];
    if (is_array($other)) {
        foreach ($other as $item) {
            $item = sanitize_text_field(trim((string) $item));
            if ($item !== '') {
                $out_other[] = $item;
            }
        }
    }

    return [
        'has'          => in_array($has, ['yes', 'no'], true) ? $has : '',
        'orgs'         => array_values(array_unique($out_orgs)),
        'other_items'  => $out_other,
    ];
}

/**
 * @return array<int, array{org:string,other:string}>
 */
function casting_artistic_org_form_rows(array $orgs, array $other_items): array
{
    $rows = [];
    $other_i = 0;
    foreach ($orgs as $org) {
        if ($org === 'other') {
            $rows[] = ['org' => 'other', 'other' => $other_items[$other_i++] ?? ''];
            continue;
        }
        $rows[] = ['org' => $org, 'other' => ''];
    }
    if ($rows === []) {
        $rows[] = ['org' => '', 'other' => ''];
    }
    return $rows;
}

/**
 * @return array{has:string,orgs:list<string>,other_items:list<string>}
 */
function casting_parse_artistic_membership_post(array $post): array
{
    $has = sanitize_key((string) ($post['artistic_membership'] ?? ''));
    $labels = casting_artistic_org_labels();
    $orgs = [];
    $other_items = [];

    $items_raw = $post['artistic_org_items'] ?? null;
    if (is_array($items_raw)) {
        foreach ($items_raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $org = sanitize_key((string) ($item['org'] ?? ''));
            if ($org === '' || !isset($labels[$org])) {
                continue;
            }
            if ($org === 'other') {
                $text = sanitize_text_field(trim((string) ($item['other'] ?? '')));
                if ($text === '') {
                    continue;
                }
                if (!in_array('other', $orgs, true)) {
                    $orgs[] = 'other';
                }
                $other_items[] = $text;
                continue;
            }
            if (!in_array($org, $orgs, true)) {
                $orgs[] = $org;
            }
        }
    } else {
        $orgs_raw = $post['artistic_orgs'] ?? [];
        if (!is_array($orgs_raw)) {
            $orgs_raw = [];
        }
        foreach ($orgs_raw as $key) {
            $key = sanitize_key((string) $key);
            if (isset($labels[$key]) && !in_array($key, $orgs, true)) {
                $orgs[] = $key;
            }
        }
        $raw_other = $post['artistic_other_items'] ?? [];
        if (is_array($raw_other)) {
            foreach ($raw_other as $item) {
                $item = sanitize_text_field(trim((string) $item));
                if ($item !== '') {
                    $other_items[] = $item;
                }
            }
        }
    }

    return [
        'has'         => $has,
        'orgs'        => array_values(array_unique($orgs)),
        'other_items' => $other_items,
    ];
}

function casting_validate_artistic_membership(array $data): ?string
{
    $has = sanitize_key((string) ($data['has'] ?? ''));
    if ($has === '') {
        return null;
    }

    $yes_no = casting_yes_no_labels();
    if (!isset($yes_no[$has])) {
        return 'سابقه عضویت در تشکل‌های هنری را مشخص کنید.';
    }
    if ($data['has'] === 'no') {
        return null;
    }
    if (($data['orgs'] ?? []) === []) {
        return 'حداقل یک تشکل هنری انتخاب کنید.';
    }
    if (in_array('other', $data['orgs'], true) && ($data['other_items'] ?? []) === []) {
        return 'برای گزینه «سایر» نام تشکل را بنویسید.';
    }
    return null;
}

function casting_save_artistic_membership_meta(int $user_id, array $data): void
{
    $has = sanitize_key((string) ($data['has'] ?? ''));
    update_user_meta($user_id, 'casting_artistic_membership', $has);
    if ($has === 'yes') {
        update_user_meta($user_id, 'casting_artistic_orgs', $data['orgs'] ?? []);
        update_user_meta($user_id, 'casting_artistic_other_items', $data['other_items'] ?? []);
    } else {
        update_user_meta($user_id, 'casting_artistic_orgs', []);
        update_user_meta($user_id, 'casting_artistic_other_items', []);
    }
}

function casting_format_artistic_membership(array $data): string
{
    if (($data['has'] ?? '') === 'no') {
        return 'خیر';
    }
    if (($data['has'] ?? '') !== 'yes') {
        return '—';
    }
    $labels = casting_artistic_org_labels();
    $parts = [];
    foreach ($data['orgs'] ?? [] as $key) {
        if ($key === 'other') {
            continue;
        }
        $parts[] = $labels[$key] ?? $key;
    }
    foreach ($data['other_items'] ?? [] as $item) {
        $parts[] = $item;
    }
    return $parts !== [] ? implode('، ', $parts) : '—';
}

/**
 * @param list<string> $orgs
 * @param list<string> $other_items
 */
function casting_render_artistic_membership_fields(string $has = '', array $orgs = [], array $other_items = []): void
{
    $labels = casting_artistic_org_labels();
    $rows = casting_artistic_org_form_rows($orgs, $other_items);
    $show_orgs = $has === 'yes';
    ?>
  <fieldset class="field" data-artistic-membership>
    <legend>سابقه عضویت در تشکل‌های هنری</legend>
    <div class="role-grid role-grid-2">
      <?php foreach (casting_yes_no_labels() as $key => $label) : ?>
        <label class="role-option">
          <input type="radio" name="artistic_membership" value="<?= casting_e($key) ?>" <?= $has === $key ? 'checked' : '' ?> data-artistic-has>
          <span><?= casting_e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="artistic-orgs-panel" data-artistic-orgs-panel <?= $show_orgs ? '' : 'hidden' ?>>
      <p class="field-hint">تشکل‌هایی که عضو بوده‌اید را از فهرست انتخاب کنید. با + تشکل بعدی را اضافه کنید.</p>
      <div class="work-credits-list" data-artistic-org-list>
        <?php foreach ($rows as $i => $row) :
            $org = (string) ($row['org'] ?? '');
            $other = (string) ($row['other'] ?? '');
            $is_other = $org === 'other';
            ?>
          <div class="work-credit-row artistic-org-row<?= $is_other ? ' is-other' : '' ?>">
            <select name="artistic_org_items[<?= (int) $i ?>][org]" aria-label="تشکل هنری" data-artistic-org-select>
              <option value="">انتخاب تشکل…</option>
              <?php foreach ($labels as $key => $label) : ?>
                <option value="<?= casting_e($key) ?>" <?= $org === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="artistic_org_items[<?= (int) $i ?>][other]" value="<?= casting_e($is_other ? $other : '') ?>" placeholder="نام تشکل…" aria-label="نام تشکل دیگر" data-artistic-org-other<?= $is_other ? '' : ' disabled' ?>>
            <button type="button" class="btn-icon" data-remove-artistic-org aria-label="حذف">−</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-ghost btn-add-credit" data-add-artistic-org>+ افزودن تشکل بعدی</button>
      <template data-artistic-org-template>
        <div class="work-credit-row artistic-org-row">
          <select name="artistic_org_items[__i__][org]" aria-label="تشکل هنری" data-artistic-org-select>
            <option value="">انتخاب تشکل…</option>
            <?php foreach ($labels as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="artistic_org_items[__i__][other]" value="" placeholder="نام تشکل…" aria-label="نام تشکل دیگر" data-artistic-org-other disabled>
          <button type="button" class="btn-icon" data-remove-artistic-org aria-label="حذف">−</button>
        </div>
      </template>
    </div>
  </fieldset>
    <?php
}

/**
 * تخصص‌هایی که قد و وزن برایشان مهم است
 *
 * @param list<string> $activities
 */
function casting_activities_need_body_metrics(array $activities): bool
{
    return casting_activities_need_talent_fields($activities);
}

/**
 * @return array<string, string>
 */
function casting_skill_labels(): array
{
    return [
        'horse_riding'  => 'اسب‌سواری',
        'fencing'       => 'شمشیربازی',
        'pro_driving'   => 'رانندگی حرفه‌ای',
        'swimming'      => 'شنا',
        'music'         => 'موسیقی',
        'dance'         => 'رقص',
        'martial_arts'  => 'ورزش‌های رزمی',
        'other'         => 'سایر',
    ];
}

/**
 * @return array<string, string>
 */
function casting_motor_skill_labels(): array
{
    $keys = ['horse_riding', 'fencing', 'pro_driving', 'swimming', 'martial_arts'];
    return array_intersect_key(casting_skill_labels(), array_flip($keys));
}

/**
 * @return array<string, string>
 */
function casting_motor_skill_filter_labels(): array
{
    $labels = casting_motor_skill_labels();
    $labels['other'] = 'سایر';
    return $labels;
}

/**
 * @return array<string, string>
 */
function casting_artistic_skill_labels(): array
{
    $keys = ['music', 'dance'];
    return array_intersect_key(casting_skill_labels(), array_flip($keys));
}

/**
 * گزینه‌های فیلتر جستجو — «سایر» در انتهای لیست
 *
 * @return array<string, string>
 */
function casting_artistic_skill_filter_labels(): array
{
    return [
        'music' => 'موسیقی',
        'dance' => 'رقص',
        'other' => 'سایر',
    ];
}

function casting_search_filter_empty_label(): string
{
    return 'انتخاب کنید';
}

function casting_search_filter_none_label(): string
{
    return 'همه';
}

function casting_search_specialty_empty_label(bool $category_selected): string
{
    return $category_selected ? casting_search_filter_none_label() : 'اول تخصص هنری را انتخاب کنید';
}

/**
 * @return array<string, string>
 */
function casting_eye_color_labels(): array
{
    return [
        'black' => 'مشکی',
        'brown' => 'قهوه‌ای',
        'hazel' => 'عسلی',
        'green' => 'سبز',
        'blue'  => 'آبی',
        'gray'  => 'خاکستری',
    ];
}

/**
 * @return array<string, string>
 */
function casting_hair_color_labels(): array
{
    return [
        'black' => 'مشکی',
        'brown' => 'قهوه‌ای',
        'blond' => 'بلوند',
        'red'   => 'قرمز',
        'gray'  => 'خاکستری / سفید',
        'other' => 'سایر',
    ];
}

/**
 * @return array<string, string>
 */
function casting_accent_labels(): array
{
    return [
        'tehrani'  => 'تهرانی',
        'shirazi'  => 'شیرازی',
        'esfahani' => 'اصفهانی',
        'mashhadi' => 'مشهدی',
        'azeri'    => 'آذربایجانی',
        'kurdish'  => 'کردی',
        'lori'     => 'لری',
        'bandari'  => 'بندری',
        'other'    => 'سایر',
    ];
}

/**
 * @return array{ok:bool,error?:string}
 */
function casting_save_talent_trait_meta(int $user_id, array $data): array
{
    if (array_key_exists('eye_color', $data)) {
        $eye = sanitize_key((string) $data['eye_color']);
        if ($eye !== '' && !array_key_exists($eye, casting_eye_color_labels())) {
            return ['ok' => false, 'error' => 'رنگ چشم را درست انتخاب کنید.'];
        }
        if ($eye === '') {
            delete_user_meta($user_id, 'casting_eye_color');
        } else {
            update_user_meta($user_id, 'casting_eye_color', $eye);
        }
    }

    if (array_key_exists('hair_color', $data)) {
        $hair = sanitize_key((string) $data['hair_color']);
        if ($hair !== '' && !array_key_exists($hair, casting_hair_color_labels())) {
            return ['ok' => false, 'error' => 'رنگ مو را درست انتخاب کنید.'];
        }
        if ($hair === '') {
            delete_user_meta($user_id, 'casting_hair_color');
        } else {
            update_user_meta($user_id, 'casting_hair_color', $hair);
        }
    }

    if (array_key_exists('accent', $data)) {
        $accent = sanitize_key((string) $data['accent']);
        $accent_other = sanitize_text_field((string) ($data['accent_other'] ?? ''));
        if ($accent !== '' && !array_key_exists($accent, casting_accent_labels())) {
            return ['ok' => false, 'error' => 'لهجه را درست انتخاب کنید.'];
        }
        if ($accent === 'other') {
            if ($accent_other === '') {
                return ['ok' => false, 'error' => 'برای لهجه «سایر» بنویسید لهجه شما چیست.'];
            }
            if (casting_strlen($accent_other) > 80) {
                return ['ok' => false, 'error' => 'توضیح لهجه حداکثر ۸۰ کاراکتر باشد.'];
            }
            update_user_meta($user_id, 'casting_accent', 'other');
            update_user_meta($user_id, 'casting_accent_other', $accent_other);
        } elseif ($accent === '') {
            delete_user_meta($user_id, 'casting_accent');
            delete_user_meta($user_id, 'casting_accent_other');
        } else {
            update_user_meta($user_id, 'casting_accent', $accent);
            delete_user_meta($user_id, 'casting_accent_other');
        }
    }

    if (array_key_exists('apparent_age_range', $data)) {
        $apparent = sanitize_key((string) $data['apparent_age_range']);
        if ($apparent !== '' && !array_key_exists($apparent, casting_age_range_options())) {
            return ['ok' => false, 'error' => 'رده سنی ظاهری را درست انتخاب کنید.'];
        }
        if ($apparent === '') {
            delete_user_meta($user_id, 'casting_apparent_age_range');
        } else {
            update_user_meta($user_id, 'casting_apparent_age_range', $apparent);
        }
    }

    return ['ok' => true];
}

function casting_purge_actor_trait_meta(int $user_id): void
{
    delete_user_meta($user_id, 'casting_eye_color');
    delete_user_meta($user_id, 'casting_hair_color');
    delete_user_meta($user_id, 'casting_accent');
    delete_user_meta($user_id, 'casting_accent_other');
    delete_user_meta($user_id, 'casting_apparent_age_range');
    delete_user_meta($user_id, 'casting_health_well');
    delete_user_meta($user_id, 'casting_health_status');
    delete_user_meta($user_id, 'casting_availability');
    delete_user_meta($user_id, 'casting_skill_items');
    delete_user_meta($user_id, 'casting_skills');
    delete_user_meta($user_id, 'casting_skills_other');
}

function casting_purge_actor_portrait_meta(int $user_id): void
{
    foreach (array_keys(casting_all_portrait_slots()) as $slot) {
        $meta_key = casting_portrait_meta_key($slot);
        if ($meta_key !== '') {
            delete_user_meta($user_id, $meta_key);
        }
    }
    delete_user_meta($user_id, 'casting_photo_id');
}

function casting_purge_non_actor_profile_meta(int $user_id): void
{
    casting_purge_actor_trait_meta($user_id);
    if (!casting_user_can_upload_portraits($user_id)) {
        casting_purge_actor_portrait_meta($user_id);
    }
}

function casting_user_has_acting_profile(int $user_id): bool
{
    $activities = casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true), $user_id);

    return casting_activities_need_talent_fields($activities);
}

function casting_user_can_upload_portraits(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }

    return casting_get_user_role($user_id) !== '';
}

/**
 * بازیگر (یا «هیچ‌کدام») → سه شات کلوزاپ/مدیوم/لانگ؛ بقیه → یک عکس پروفایل
 */
function casting_user_uses_actor_portrait_set(int $user_id): bool
{
    return casting_user_has_acting_profile($user_id);
}

function casting_profile_shows_portraits(array $activities, int $user_id = 0): bool
{
    if ($user_id > 0) {
        return casting_user_can_upload_portraits($user_id);
    }

    return casting_activities_need_talent_fields($activities) || $activities !== [];
}

function casting_user_has_actor_only_profile_meta(int $user_id): bool
{
    $keys = [
        'casting_eye_color',
        'casting_hair_color',
        'casting_accent',
        'casting_accent_other',
        'casting_apparent_age_range',
        'casting_health_well',
        'casting_health_status',
        'casting_availability',
        'casting_skills',
        'casting_skills_other',
    ];
    foreach ($keys as $key) {
        if (get_user_meta($user_id, $key, true) !== '') {
            return true;
        }
    }

    $skill_items = get_user_meta($user_id, 'casting_skill_items', true);
    return is_array($skill_items) && $skill_items !== [];
}

function casting_format_accent_display(string $accent, string $accent_other = ''): string
{
    if ($accent === '') {
        return '';
    }
    if ($accent === 'other') {
        return $accent_other !== '' ? $accent_other : (casting_accent_labels()['other'] ?? 'سایر');
    }

    return casting_accent_labels()[$accent] ?? '';
}

/**
 * @param array<string, string> $values
 */
function casting_render_talent_trait_fields(array $values = []): void
{
    $eye_color = (string) ($values['eye_color'] ?? '');
    $hair_color = (string) ($values['hair_color'] ?? '');
    $accent = (string) ($values['accent'] ?? '');
    $accent_other = (string) ($values['accent_other'] ?? '');
    $apparent_age_range = (string) ($values['apparent_age_range'] ?? '');
    $is_accent_other = $accent === 'other';
    ?>
  <div class="form-grid">
    <div class="field">
      <label for="eye_color">رنگ چشم</label>
      <select id="eye_color" name="eye_color">
        <option value="">انتخاب کنید</option>
        <?php foreach (casting_eye_color_labels() as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $eye_color === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="hair_color">رنگ مو</label>
      <select id="hair_color" name="hair_color">
        <option value="">انتخاب کنید</option>
        <?php foreach (casting_hair_color_labels() as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $hair_color === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field accent-field-wrap" data-accent-field>
      <label for="accent">لهجه</label>
      <div class="trait-other-row accent-other-row<?= $is_accent_other ? ' is-other' : '' ?>">
        <select id="accent" name="accent" data-accent-select>
          <option value="">انتخاب کنید</option>
          <?php foreach (casting_accent_labels() as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>" <?= $accent === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <input
          type="text"
          id="accent_other"
          name="accent_other"
          value="<?= casting_e($is_accent_other ? $accent_other : '') ?>"
          placeholder="لهجه خود را بنویسید…"
          aria-label="توضیح لهجه سایر"
          data-accent-other
          <?= $is_accent_other ? '' : 'disabled' ?>
        >
      </div>
    </div>
    <div class="field">
      <label for="apparent_age_range">رده سنی ظاهری</label>
      <select id="apparent_age_range" name="apparent_age_range">
        <option value="">انتخاب کنید</option>
        <?php foreach (casting_age_range_options() as $key => $range) : ?>
          <option value="<?= casting_e($key) ?>" <?= $apparent_age_range === $key ? 'selected' : '' ?>><?= casting_e($range['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
    <?php
}

/**
 * @return array<string, string>
 */
function casting_language_level_labels(): array
{
    return [
        'basic'        => 'مقدماتی',
        'intermediate' => 'متوسط',
        'advanced'     => 'پیشرفته',
        'native'       => 'در حد زبان مادری',
    ];
}

/**
 * @return list<string>
 */
function casting_common_languages(): array
{
    return [
        'انگلیسی', 'عربی', 'فرانسوی', 'آلمانی', 'ترکی استانبولی', 'ترکی آذری',
        'اسپانیایی', 'ایتالیایی', 'روسی', 'چینی', 'ژاپنی', 'کره‌ای', 'کردی',
    ];
}

/**
 * @return array<string, string>
 */
function casting_availability_labels(): array
{
    return [
        'available' => 'آزاد',
        'busy'      => 'مشغول',
        'project'   => 'پروژه‌ای',
    ];
}

/**
 * @param mixed $raw
 * @return array<int, array{skill:string,note:string}>
 */
function casting_normalize_skill_items($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $labels = casting_skill_labels();
    $out = [];
    foreach ($raw as $item) {
        if (is_string($item) || is_numeric($item)) {
            $key = sanitize_key((string) $item);
            $note = '';
        } elseif (is_array($item)) {
            $key = sanitize_key((string) ($item['skill'] ?? $item['key'] ?? ''));
            $note = sanitize_text_field((string) ($item['note'] ?? ''));
        } else {
            continue;
        }
        if ($key === '' || !isset($labels[$key])) {
            continue;
        }
        if ($key !== 'other') {
            $note = '';
        }
        $out[] = ['skill' => $key, 'note' => $note];
    }
    return $out;
}

/**
 * @return array<int, array{skill:string,note:string}>
 */
function casting_parse_skill_items_post(array $post): array
{
    $raw = $post['skill_items'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    return casting_normalize_skill_items($raw);
}

/**
 * @param array<int, array{skill:string,note:string}>|list<string> $items
 * @return list<string>
 */
function casting_skill_item_keys($items): array
{
    $keys = [];
    foreach (casting_normalize_skill_items($items) as $row) {
        if (!in_array($row['skill'], $keys, true)) {
            $keys[] = $row['skill'];
        }
    }
    return $keys;
}

/**
 * @param mixed $raw
 * @return array<int, array{name:string,level:string}>
 */
function casting_normalize_language_items($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $levels = casting_language_level_labels();
    $out = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = sanitize_text_field((string) ($item['name'] ?? ''));
        $level = sanitize_key((string) ($item['level'] ?? ''));
        if ($name === '') {
            continue;
        }
        if ($level !== '' && !isset($levels[$level])) {
            $level = '';
        }
        $out[] = ['name' => $name, 'level' => $level];
    }
    return $out;
}

/**
 * @return array<int, array{name:string,level:string}>
 */
function casting_parse_language_items_post(array $post): array
{
    $raw = $post['language_items'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    return casting_normalize_language_items($raw);
}

/**
 * @param array<int, array{skill:string,note:string}>|list<string> $items
 * @param string $other سازگاری با داده قدیمی
 */
function casting_render_skill_fields($items = [], string $other = ''): void
{
    $rows = casting_normalize_skill_items($items);
    if ($rows === [] && $other !== '') {
        $rows[] = ['skill' => 'other', 'note' => $other];
    }
    if ($rows === []) {
        $rows[] = ['skill' => '', 'note' => ''];
    }
    $labels = casting_skill_labels();
    ?>
  <div class="field work-credits" data-skill-items>
    <span class="jalali-label">مهارت‌ها</span>
    <p class="field-hint">مهارت را انتخاب کنید. اگر «سایر» را بزنید، بنویسید چه هنری دارید. با + مهارت بعدی را اضافه کنید.</p>
    <div class="work-credits-list" data-skill-list>
      <?php foreach ($rows as $i => $row) :
          $skill = (string) ($row['skill'] ?? '');
          $note = (string) ($row['note'] ?? '');
          $is_other = $skill === 'other';
          ?>
        <div class="work-credit-row skill-row<?= $is_other ? ' is-other' : '' ?>">
          <select name="skill_items[<?= (int) $i ?>][skill]" aria-label="مهارت" data-skill-select>
            <option value="">انتخاب مهارت…</option>
            <?php foreach ($labels as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $skill === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="skill_items[<?= (int) $i ?>][note]" value="<?= casting_e($is_other ? $note : '') ?>" placeholder="چه هنری دارید؟" aria-label="توضیح سایر" data-skill-note<?= $is_other ? '' : ' disabled' ?>>
          <button type="button" class="btn-icon" data-remove-skill aria-label="حذف">−</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-ghost btn-add-credit" data-add-skill>+ افزودن مهارت بعدی</button>
    <template data-skill-template>
      <div class="work-credit-row skill-row">
        <select name="skill_items[__i__][skill]" aria-label="مهارت" data-skill-select>
          <option value="">انتخاب مهارت…</option>
          <?php foreach ($labels as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="skill_items[__i__][note]" value="" placeholder="—" aria-label="توضیح سایر" data-skill-note disabled>
        <button type="button" class="btn-icon" data-remove-skill aria-label="حذف">−</button>
      </div>
    </template>
  </div>
    <?php
}

/**
 * @param array<int, array{name:string,level:string}> $items
 */
function casting_render_language_fields(array $items = []): void
{
    if (!$items) {
        $items = [['name' => '', 'level' => '']];
    }
    $levels = casting_language_level_labels();
    $common = casting_common_languages();
    ?>
  <div class="field work-credits" data-language-items>
    <span class="jalali-label">زبان‌های خارجه</span>
    <p class="field-hint">زبان را بنویسید یا از پیشنهادها انتخاب کنید؛ سطح را هم مشخص کنید. با + زبان بعدی را اضافه کنید.</p>
    <div class="work-credits-list" data-language-list>
      <?php foreach ($items as $i => $item) : ?>
        <div class="work-credit-row education-row language-row">
          <input type="text" name="language_items[<?= (int) $i ?>][name]" list="casting-languages-list" value="<?= casting_e((string) ($item['name'] ?? '')) ?>" placeholder="مثلاً انگلیسی" aria-label="زبان">
          <select name="language_items[<?= (int) $i ?>][level]" aria-label="سطح زبان">
            <option value="">سطح…</option>
            <?php foreach ($levels as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= ($item['level'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn-icon" data-remove-language aria-label="حذف">−</button>
        </div>
      <?php endforeach; ?>
    </div>
    <datalist id="casting-languages-list">
      <?php foreach ($common as $lang) : ?>
        <option value="<?= casting_e($lang) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <button type="button" class="btn btn-ghost btn-add-credit" data-add-language>+ افزودن زبان بعدی</button>
    <template data-language-template>
      <div class="work-credit-row education-row language-row">
        <input type="text" name="language_items[__i__][name]" list="casting-languages-list" value="" placeholder="مثلاً انگلیسی" aria-label="زبان">
        <select name="language_items[__i__][level]" aria-label="سطح زبان">
          <option value="">سطح…</option>
          <?php foreach ($levels as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn-icon" data-remove-language aria-label="حذف">−</button>
      </div>
    </template>
  </div>
    <?php
}

/**
 * @param array<int, array{skill:string,note:string}>|list<string> $items
 */
function casting_format_skill_labels($items, string $other = ''): string
{
    $labels = casting_skill_labels();
    $rows = casting_normalize_skill_items($items);
    if ($rows === [] && $other !== '') {
        $rows[] = ['skill' => 'other', 'note' => $other];
    }
    $parts = [];
    foreach ($rows as $row) {
        $key = $row['skill'];
        if ($key === 'other') {
            $parts[] = $row['note'] !== '' ? $row['note'] : 'سایر';
            continue;
        }
        $parts[] = $labels[$key] ?? $key;
    }
    return implode('، ', $parts);
}

/**
 * نقش پورتال را از نوع فعالیت‌ها حدس می‌زند
 *
 * @param list<string> $activities
 */
function casting_infer_role_from_activities(array $activities): string
{
    $activities = casting_normalize_activities($activities);
    if (array_intersect($activities, [
        'producer', 'executive', 'production_manager', 'logistics_manager',
        'production_assistant', 'logistics_assistant', 'logistics_driver',
    ]) !== []) {
        return 'producer';
    }
    if (array_intersect($activities, [
        'director', 'director_theater', 'director_short_film', 'director_tv', 'director_cinema',
        'first_ad', 'second_ad', 'third_ad', 'scheduler', 'script_supervisor',
    ]) !== []) {
        return 'director';
    }
    return 'talent';
}

/**
 * نام شهر را تمیز می‌کند.
 */
function casting_normalize_city_name(string $city): string
{
    $city = sanitize_text_field($city);
    $city = preg_replace('/\s+/u', ' ', $city) ?? $city;
    return trim($city);
}

/**
 * شهرهای پیش‌فرض برای شروع لیست
 *
 * @return list<string>
 */
function casting_default_cities(): array
{
    return [
        'تهران', 'کرج', 'اصفهان', 'مشهد', 'شیراز', 'تبریز', 'اهواز', 'قم',
        'کرمانشاه', 'ارومیه', 'رشت', 'کرمان', 'یزد', 'همدان', 'اردبیل',
        'بندرعباس', 'بوشهر', 'زاهدان', 'ساری', 'قزوین', 'خرم‌آباد', 'سنندج',
        'گرگان', 'اراک', 'کاشان', 'اسلامشهر', 'پردیس', 'پرند',
    ];
}

/**
 * لیست یکتای شهرها (ذخیره‌شده + استفاده‌شده توسط کاربران)
 *
 * @return list<string>
 */
function casting_get_cities(): array
{
    $stored = get_option('casting_cities', []);
    if (!is_array($stored) || $stored === []) {
        $stored = casting_default_cities();
        update_option('casting_cities', $stored, false);
    }

    global $wpdb;
    $from_users = $wpdb->get_col(
        "SELECT DISTINCT meta_value FROM {$wpdb->usermeta}
         WHERE meta_key = 'casting_city' AND meta_value <> ''"
    );
    if (!is_array($from_users)) {
        $from_users = [];
    }

    $map = [];
    foreach (array_merge($stored, $from_users) as $raw) {
        $city = casting_normalize_city_name((string) $raw);
        if ($city === '') {
            continue;
        }
        $key = function_exists('mb_strtolower') ? mb_strtolower($city, 'UTF-8') : strtolower($city);
        if (!isset($map[$key])) {
            $map[$key] = $city;
        }
    }

    $cities = array_values($map);
    usort($cities, static function (string $a, string $b): int {
        $al = function_exists('mb_strtolower') ? mb_strtolower($a, 'UTF-8') : strtolower($a);
        $bl = function_exists('mb_strtolower') ? mb_strtolower($b, 'UTF-8') : strtolower($b);
        return $al <=> $bl;
    });

    return $cities;
}

/**
 * شهر جدید را به لیست مشترک اضافه می‌کند.
 */
function casting_remember_city(string $city): void
{
    $city = casting_normalize_city_name($city);
    if ($city === '') {
        return;
    }

    $stored = get_option('casting_cities', []);
    if (!is_array($stored) || $stored === []) {
        $stored = casting_default_cities();
    }

    $map = [];
    foreach (array_merge($stored, [$city]) as $raw) {
        $item = casting_normalize_city_name((string) $raw);
        if ($item === '') {
            continue;
        }
        $key = function_exists('mb_strtolower') ? mb_strtolower($item, 'UTF-8') : strtolower($item);
        if (!isset($map[$key])) {
            $map[$key] = $item;
        }
    }

    $cities = array_values($map);
    usort($cities, static function (string $a, string $b): int {
        $al = function_exists('mb_strtolower') ? mb_strtolower($a, 'UTF-8') : strtolower($a);
        $bl = function_exists('mb_strtolower') ? mb_strtolower($b, 'UTF-8') : strtolower($b);
        return $al <=> $bl;
    });

    update_option('casting_cities', $cities, false);
}

/**
 * فیلد شهر با پیشنهاد از لیست مشترک (قابل تایپ شهر جدید)
 */
function casting_render_city_field(string $id, string $name, string $value, bool $required = false, string $placeholder = 'شهر را انتخاب یا تایپ کنید'): void
{
    $list_id = $id . '-list';
    $cities = casting_get_cities();
    $req = $required ? ' required' : '';
    ?>
        <label for="<?= casting_e($id) ?>">شهر</label>
        <input id="<?= casting_e($id) ?>" name="<?= casting_e($name) ?>" type="text" list="<?= casting_e($list_id) ?>" value="<?= casting_e($value) ?>" placeholder="<?= casting_e($placeholder) ?>" autocomplete="address-level2"<?= $req ?>>
        <datalist id="<?= casting_e($list_id) ?>">
          <?php foreach ($cities as $city) : ?>
            <option value="<?= casting_e($city) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <p class="field-hint">از لیست انتخاب کنید یا شهر جدید بنویسید تا برای بقیه هم اضافه شود.</p>
    <?php
}

function casting_work_type_labels(): array
{
    return [
        'film'    => 'فیلم',
        'theater' => 'تئاتر',
    ];
}

/**
 * @param mixed $raw
 * @return array<int, array{type:string,title:string}>
 */
function casting_normalize_work_credits($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    $types = casting_work_type_labels();
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = sanitize_key((string) ($item['type'] ?? 'film'));
        if (!array_key_exists($type, $types)) {
            $type = 'film';
        }
        $title = sanitize_text_field((string) ($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $out[] = ['type' => $type, 'title' => $title];
    }
    return $out;
}

/**
 * @return array<int, array{type:string,title:string}>
 */
function casting_parse_work_credits_post(array $post): array
{
    $raw = $post['work_credits'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    return casting_normalize_work_credits($raw);
}

/**
 * @param mixed $raw
 * @return array<int, array{type:string,title:string}>
 */
function casting_normalize_artistic_works($raw): array
{
    return casting_normalize_work_credits($raw);
}

/**
 * @return array<int, array{type:string,title:string}>
 */
function casting_parse_artistic_works_post(array $post): array
{
    $raw = $post['artistic_works'] ?? [];
    if (!is_array($raw)) {
        return [];
    }

    return casting_normalize_artistic_works($raw);
}

/**
 * @param array<int, array{type:string,title:string}> $items
 */
function casting_render_work_list_fields(
    array $items,
    string $name_prefix,
    string $label,
    string $hint,
    string $placeholder,
    string $root_attr,
    string $list_attr,
    string $template_attr,
    string $add_attr,
    string $remove_attr
): void {
    if (!$items) {
        $items = [['type' => 'film', 'title' => '']];
    }
    $types = casting_work_type_labels();
    ?>
  <div class="field work-credits" <?= $root_attr ?>>
    <span class="jalali-label"><?= casting_e($label) ?></span>
    <p class="field-hint"><?= casting_e($hint) ?></p>
    <div class="work-credits-list" <?= $list_attr ?>>
      <?php foreach ($items as $i => $item) : ?>
        <div class="work-credit-row">
          <select name="<?= casting_e($name_prefix) ?>[<?= (int) $i ?>][type]" aria-label="نوع اثر">
            <?php foreach ($types as $key => $type_label) : ?>
              <option value="<?= casting_e($key) ?>" <?= ($item['type'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($type_label) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="<?= casting_e($name_prefix) ?>[<?= (int) $i ?>][title]" value="<?= casting_e((string) ($item['title'] ?? '')) ?>" placeholder="<?= casting_e($placeholder) ?>">
          <button type="button" class="btn-icon btn-remove-credit" <?= $remove_attr ?> aria-label="حذف">−</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-ghost btn-add-credit" <?= $add_attr ?>>+ افزودن اثر بعدی</button>
    <template <?= $template_attr ?>>
      <div class="work-credit-row">
        <select name="<?= casting_e($name_prefix) ?>[__i__][type]" aria-label="نوع اثر">
          <?php foreach ($types as $key => $type_label) : ?>
            <option value="<?= casting_e($key) ?>"><?= casting_e($type_label) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="<?= casting_e($name_prefix) ?>[__i__][title]" value="" placeholder="<?= casting_e($placeholder) ?>">
        <button type="button" class="btn-icon btn-remove-credit" <?= $remove_attr ?> aria-label="حذف">−</button>
      </div>
    </template>
  </div>
    <?php
}

function casting_render_work_credits_fields(array $credits = []): void
{
    casting_render_work_list_fields(
        $credits,
        'work_credits',
        'فیلم‌ها و تئاترهایی که بازی کرده‌اید',
        'برای هر اثر یک ردیف بنویسید؛ با + ردیف جدید اضافه کنید.',
        'نام فیلم یا تئاتر',
        'data-work-credits',
        'data-work-credits-list',
        'data-work-credit-template',
        'data-add-credit',
        'data-remove-credit'
    );
}

function casting_render_artistic_works_fields(array $works = []): void
{
    casting_render_work_list_fields(
        $works,
        'artistic_works',
        'آثار هنری',
        'فیلم‌ها یا نمایش‌هایی که کارگردانی / تهیه کرده‌اید؛ هر اثر در فهرست مشترک ذخیره می‌شود.',
        'نام اثر هنری',
        'data-artistic-works',
        'data-artistic-works-list',
        'data-artistic-work-template',
        'data-add-artistic-work',
        'data-remove-artistic-work'
    );
}

function casting_render_profile_work_sections(array $profile): void
{
    $activities = casting_normalize_activities($profile['activities'] ?? []);
    $enable_artistic = casting_activities_show_artistic_works($activities);
    $hide_acting = casting_profile_hides_talent_fields($profile['activities'] ?? []);
    ?>
    <div data-talent-profile-field<?= $hide_acting ? ' hidden' : '' ?>>
      <?php casting_render_work_credits_fields($profile['work_credits'] ?? []); ?>
    </div>
    <div data-director-profile-field<?= $enable_artistic ? '' : ' hidden' ?>>
      <?php casting_render_artistic_works_fields($profile['artistic_works'] ?? []); ?>
    </div>
    <?php
}

/**
 * @param array<string, mixed> $data
 */
function casting_save_user_work_meta(int $user_id, array $data, bool $skip_talent_profile = false): void
{
    $credits = casting_normalize_work_credits($data['work_credits'] ?? []);
    $artistic = casting_normalize_artistic_works($data['artistic_works'] ?? []);

    if ($skip_talent_profile) {
        update_user_meta($user_id, 'casting_work_credits', []);
        update_user_meta($user_id, 'casting_artistic_works', $artistic);
        casting_work_catalog_sync_user_works($user_id, [], $artistic);
        return;
    }

    update_user_meta($user_id, 'casting_work_credits', $credits);
    update_user_meta($user_id, 'casting_artistic_works', $artistic);
    casting_work_catalog_sync_user_works($user_id, $credits, $artistic);
}

function casting_education_degree_labels(): array
{
    return [
        'below_diploma' => 'زیر دیپلم',
        'diploma'       => 'دیپلم',
        'associate'     => 'فوق‌دیپلم',
        'bachelor'      => 'لیسانس',
        'master'        => 'فوق‌لیسانس',
        'doctorate'     => 'دکترا',
    ];
}

/**
 * @param mixed $raw
 * @return array<int, array{degree:string,university:string}>
 */
function casting_normalize_education_items($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    $degrees = casting_education_degree_labels();
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $degree = sanitize_key((string) ($item['degree'] ?? ''));
        if (!array_key_exists($degree, $degrees)) {
            continue;
        }
        $university = sanitize_text_field((string) ($item['university'] ?? ''));
        if ($university === '') {
            continue;
        }
        $out[] = ['degree' => $degree, 'university' => $university];
    }
    return $out;
}

/**
 * @return array<int, array{degree:string,university:string}>
 */
function casting_parse_education_items_post(array $post): array
{
    $raw = $post['education_items'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    return casting_normalize_education_items($raw);
}

function casting_render_education_fields(array $items = []): void
{
    if (!$items) {
        $items = [['degree' => 'bachelor', 'university' => '']];
    }
    $degrees = casting_education_degree_labels();
    ?>
  <div class="field work-credits" data-education-items>
    <span class="jalali-label">سابقه تحصیلی</span>
    <p class="field-hint">مقطع را انتخاب کنید و نام دانشگاه را بنویسید؛ با + مدرک بعدی را اضافه کنید.</p>
    <div class="work-credits-list" data-education-list>
      <?php foreach ($items as $i => $item) : ?>
        <div class="work-credit-row education-row">
          <select name="education_items[<?= (int) $i ?>][degree]" aria-label="مقطع تحصیلی">
            <?php foreach ($degrees as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= ($item['degree'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="education_items[<?= (int) $i ?>][university]" value="<?= casting_e((string) ($item['university'] ?? '')) ?>" placeholder="نام دانشگاه">
          <button type="button" class="btn-icon" data-remove-education aria-label="حذف">−</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-ghost btn-add-credit" data-add-education>+ افزودن مدرک بعدی</button>
    <template data-education-template>
      <div class="work-credit-row education-row">
        <select name="education_items[__i__][degree]" aria-label="مقطع تحصیلی">
          <?php foreach ($degrees as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="education_items[__i__][university]" value="" placeholder="نام دانشگاه">
        <button type="button" class="btn-icon" data-remove-education aria-label="حذف">−</button>
      </div>
    </template>
  </div>
    <?php
}


function casting_age_from_birthdate(string $birthdate): ?int
{
    $birth = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$birth || $birth->format('Y-m-d') !== $birthdate) {
        return null;
    }
    $today = new DateTime('today');
    if ($birth > $today) {
        return null;
    }
    return (int) $birth->diff($today)->y;
}

/**
 * سه شات بازیگری (الزامی برای مجموعه بازیگر)
 *
 * @return array<string, string>
 */
function casting_portrait_slots(): array
{
    return [
        'closeup' => 'کلوزاپ',
        'medium'  => 'مدیوم',
        'long'    => 'لانگ',
    ];
}

/**
 * همه اسلات‌های تصویری بازیگر: عکس پروفایل + سه شات
 *
 * @return array<string, string>
 */
function casting_all_portrait_slots(): array
{
    return [
        'profile' => 'عکس پروفایل',
    ] + casting_portrait_slots();
}

/**
 * @return array{width:int,height:int}
 */
function casting_portrait_display_dimensions(): array
{
    return ['width' => 360, 'height' => 480];
}

/**
 * @return array<string, string>
 */
function casting_portrait_slot_hints(): array
{
    return [
        'profile' => 'عکس اصلی نمایش در پنل',
        'closeup' => 'نمای نزدیک صورت',
        'medium'  => 'نیم‌تنه یا تا کمر',
        'long'    => 'تمام‌قد',
    ];
}

function casting_portrait_meta_key(string $slot): string
{
    return array_key_exists($slot, casting_all_portrait_slots()) ? 'casting_photo_' . $slot . '_id' : '';
}

/**
 * @param array<string, mixed> $portraits
 * @return array{id:int,url:string,full:string}
 */
function casting_portrait_shot(array $portraits, string $slot): array
{
    $empty = ['id' => 0, 'url' => '', 'full' => ''];
    $shot = $portraits[$slot] ?? $empty;
    if (!is_array($shot)) {
        return $empty;
    }

    return [
        'id'   => (int) ($shot['id'] ?? 0),
        'url'  => is_string($shot['url'] ?? null) ? (string) $shot['url'] : '',
        'full' => is_string($shot['full'] ?? null) ? (string) $shot['full'] : '',
    ];
}

/**
 * @return array{id:int,url:string,full:string}
 */
function casting_load_portrait(int $user_id, string $slot): array
{
    $empty = ['id' => 0, 'url' => '', 'full' => ''];
    $meta_key = casting_portrait_meta_key($slot);
    if ($meta_key === '') {
        return $empty;
    }

    $id = (int) get_user_meta($user_id, $meta_key, true);
    if ($id <= 0 && $slot === 'medium') {
        $id = (int) get_user_meta($user_id, 'casting_photo_id', true);
    }

    if ($id <= 0) {
        return $empty;
    }

    $url = wp_get_attachment_image_url($id, 'casting_portrait');
    if (!is_string($url) || $url === '') {
        $url = wp_get_attachment_image_url($id, 'medium');
    }
    if (!is_string($url) || $url === '') {
        $url = wp_get_attachment_image_url($id, 'thumbnail');
    }
    $full = wp_get_attachment_image_url($id, 'large');
    if (!is_string($full) || $full === '') {
        $full = wp_get_attachment_image_url($id, 'full');
    }

    return [
        'id'   => $id,
        'url'  => is_string($url) ? $url : '',
        'full' => is_string($full) ? $full : '',
    ];
}

/**
 * @return array<string, array{id:int,url:string,full:string}>
 */
function casting_load_all_portraits(int $user_id): array
{
    $out = [];
    foreach (casting_all_portrait_slots() as $slot => $label) {
        unset($label);
        $out[$slot] = casting_load_portrait($user_id, $slot);
    }
    return $out;
}

function casting_primary_portrait(array $portraits): array
{
    foreach (['profile', 'medium', 'closeup', 'long'] as $slot) {
        if (!empty($portraits[$slot]['id'])) {
            return $portraits[$slot];
        }
    }
    return ['id' => 0, 'url' => '', 'full' => ''];
}

/**
 * عکس کارت استعداد — اولویت با کلوزآپ برای چهره‌محوری
 *
 * @param array<string, mixed> $profile
 */
function casting_member_card_photo_url(int $user_id, array $profile = []): string
{
    if ($user_id <= 0) {
        return '';
    }
    foreach (['closeup', 'medium', 'profile', 'long'] as $slot) {
        $shot = casting_load_portrait($user_id, $slot);
        $url = trim((string) ($shot['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }
    }
    return trim((string) ($profile['photo_url'] ?? ''));
}

/**
 * @param array<string, array{id:int,url:string,full:string}> $portraits
 */
function casting_portraits_complete(array $portraits): bool
{
    foreach (casting_portrait_slots() as $slot => $label) {
        unset($label);
        if (empty($portraits[$slot]['id'])) {
            return false;
        }
    }
    return true;
}

/**
 * @param array<string, array{id:int,url:string,full:string}> $portraits
 */
function casting_render_portrait_upload_fields(array $portraits = [], bool $required = false, bool $require_primary_only = false): void
{
    $hints = casting_portrait_slot_hints();
    $dims = casting_portrait_display_dimensions();
    ?>
  <div class="portrait-upload-grid portrait-upload-grid--actor">
    <?php foreach (casting_all_portrait_slots() as $slot => $label) :
        $field = 'photo_' . $slot;
        $preview = $portraits[$slot]['url'] ?? '';
        $is_profile = $slot === 'profile';
        $slot_req = '';
        $has_preview = $preview !== '';
        if ($required && !$is_profile && !$has_preview) {
            $slot_req = ' required';
        }
        if (!$required && $require_primary_only && $slot === 'medium' && !$has_preview) {
            $slot_req = ' required';
        }
        ?>
      <div class="portrait-upload-card<?= $is_profile ? ' portrait-upload-card--profile' : '' ?>" data-file-preview-card>
        <div class="portrait-frame portrait-preview<?= $is_profile ? ' portrait-preview--profile' : '' ?>" data-file-preview-frame>
          <?php if ($preview !== '') : ?>
            <img
              src="<?= casting_e($preview) ?>"
              alt="<?= casting_e($label) ?>"
              width="<?= (int) $dims['width'] ?>"
              height="<?= (int) $dims['height'] ?>"
              decoding="async"
              data-file-preview-img
            >
          <?php else : ?>
            <div class="photo-placeholder portrait-frame-empty" data-file-preview-empty>بدون عکس</div>
          <?php endif; ?>
        </div>
        <div class="field">
          <label for="<?= casting_e($field) ?>"><?= casting_e($label) ?><?= $slot_req !== '' ? ' <span class="req-mark">*</span>' : '' ?></label>
          <input id="<?= casting_e($field) ?>" name="<?= casting_e($field) ?>" type="file" accept="image/jpeg,image/png,image/webp"<?= $slot_req ?> data-file-preview-input data-upload-kind="image" data-max-bytes="<?= (int) casting_upload_max_bytes('image') ?>">
          <p class="field-hint portrait-upload-hint">
            <span class="portrait-upload-hint-desc"><?= casting_e($hints[$slot] ?? '') ?></span>
            <span class="portrait-upload-hint-formats">JPG / PNG / WebP</span>
            <span class="portrait-upload-hint-size">حداکثر <?= casting_e(casting_upload_max_label_fa('image')) ?></span>
          </p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
    <?php
}

/**
 * یک عکس پروفایل (برای غیر‌بازیگر) — روی اسلات medium ذخیره می‌شود
 *
 * @param array<string, array{id:int,url:string,full:string}> $portraits
 */
function casting_render_single_profile_photo_field(array $portraits = [], bool $required = false, string $input_id = 'photo_medium'): void
{
    $preview = (string) (($portraits['medium']['url'] ?? '') ?: ($portraits['medium']['full'] ?? ''));
    $dims = casting_portrait_display_dimensions();
    $req = ($required && $preview === '') ? ' required' : '';
    ?>
  <div class="portrait-upload-grid portrait-upload-grid--single">
    <div class="portrait-upload-card" data-file-preview-card>
      <div class="portrait-frame portrait-preview" data-file-preview-frame>
        <?php if ($preview !== '') : ?>
          <img
            src="<?= casting_e($preview) ?>"
            alt="عکس پروفایل"
            width="<?= (int) $dims['width'] ?>"
            height="<?= (int) $dims['height'] ?>"
            decoding="async"
            data-file-preview-img
          >
        <?php else : ?>
          <div class="photo-placeholder portrait-frame-empty" data-file-preview-empty>بدون عکس</div>
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="<?= casting_e($input_id) ?>">عکس پروفایل<?= $required ? ' <span class="req-mark">*</span>' : '' ?></label>
        <input id="<?= casting_e($input_id) ?>" name="photo_medium" type="file" accept="image/jpeg,image/png,image/webp"<?= $req ?> data-profile-photo-single data-file-preview-input data-upload-kind="image" data-max-bytes="<?= (int) casting_upload_max_bytes('image') ?>">
        <p class="field-hint portrait-upload-hint">
          <span class="portrait-upload-hint-desc">یک عکس واضح از خودتان</span>
          <span class="portrait-upload-hint-formats">JPG / PNG / WebP</span>
          <span class="portrait-upload-hint-size">حداکثر <?= casting_e(casting_upload_max_label_fa('image')) ?></span>
        </p>
      </div>
    </div>
  </div>
    <?php
}

function casting_get_profile(int $user_id): array
{
    $portraits = casting_load_all_portraits($user_id);
    $primary = casting_primary_portrait($portraits);
    $photo_id = (int) $primary['id'];
    $video_id = (int) get_user_meta($user_id, 'casting_video_id', true);
    $photo_url = $primary['url'];
    $photo_full = $primary['full'];
    $video_url_file = $video_id > 0 ? wp_get_attachment_url($video_id) : '';
    $video_url_meta = (string) get_user_meta($user_id, 'casting_video_url', true);
    $look_meta = (string) get_user_meta($user_id, 'casting_look', true);
    if ($look_meta === 'gandoum') {
        $look_meta = 'olive';
    }

    if (function_exists('casting_sync_portal_owner_activities')) {
        casting_sync_portal_owner_activities($user_id);
    }
    $activities = casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true), $user_id);
    if (casting_profile_hides_talent_fields($activities, $user_id) && casting_user_has_actor_only_profile_meta($user_id)) {
        casting_purge_actor_trait_meta($user_id);
    }
    $wp_user = get_user_by('id', $user_id);

    return [
        'name'              => $wp_user instanceof WP_User ? (string) $wp_user->display_name : '',
        'username'          => $wp_user instanceof WP_User ? (string) $wp_user->user_login : '',
        'email'             => $wp_user instanceof WP_User ? (string) $wp_user->user_email : '',
        'birthdate'         => (string) get_user_meta($user_id, 'casting_birthdate', true),
        'age'               => (string) get_user_meta($user_id, 'casting_age', true),
        'gender'            => (string) get_user_meta($user_id, 'casting_gender', true),
        'mobile'            => (string) get_user_meta($user_id, 'casting_mobile', true),
        'mobile2'           => (string) get_user_meta($user_id, 'casting_mobile2', true),
        'phone'             => (string) get_user_meta($user_id, 'casting_phone', true),
        'province'          => (string) get_user_meta($user_id, 'casting_province', true),
        'city'              => (string) get_user_meta($user_id, 'casting_city', true),
        'residence'         => (string) get_user_meta($user_id, 'casting_residence', true),
        'height'            => (string) get_user_meta($user_id, 'casting_height', true),
        'weight'            => (string) get_user_meta($user_id, 'casting_weight', true),
        'health_well'       => casting_resolve_health_well(
            (string) get_user_meta($user_id, 'casting_health_well', true),
            (string) get_user_meta($user_id, 'casting_health_status', true)
        ),
        'health_status'     => (string) get_user_meta($user_id, 'casting_health_status', true),
        'experience'        => (string) get_user_meta($user_id, 'casting_experience', true),
        'artistic_membership' => casting_load_artistic_membership($user_id),
        'activity_license'  => (string) get_user_meta($user_id, 'casting_activity_license', true),
        'work_history'      => (string) get_user_meta($user_id, 'casting_work_history', true),
        'awards'            => (string) get_user_meta($user_id, 'casting_awards', true),
        'work_credits'      => casting_normalize_work_credits(get_user_meta($user_id, 'casting_work_credits', true)),
        'artistic_works'    => casting_normalize_artistic_works(get_user_meta($user_id, 'casting_artistic_works', true)),
        'education'         => (string) get_user_meta($user_id, 'casting_education', true),
        'education_items'   => casting_normalize_education_items(get_user_meta($user_id, 'casting_education_items', true)),
        'activities'        => $activities,
        'look'              => $look_meta,
        'eye_color'         => (string) get_user_meta($user_id, 'casting_eye_color', true),
        'hair_color'        => (string) get_user_meta($user_id, 'casting_hair_color', true),
        'accent'            => (string) get_user_meta($user_id, 'casting_accent', true),
        'accent_other'      => (string) get_user_meta($user_id, 'casting_accent_other', true),
        'apparent_age_range'=> (string) get_user_meta($user_id, 'casting_apparent_age_range', true),
        'skills'            => (string) get_user_meta($user_id, 'casting_skills', true),
        'skill_items'       => casting_normalize_skill_items(get_user_meta($user_id, 'casting_skill_items', true)),
        'skills_other'      => (string) get_user_meta($user_id, 'casting_skills_other', true),
        'language_items'    => casting_normalize_language_items(get_user_meta($user_id, 'casting_language_items', true)),
        'availability'      => (string) get_user_meta($user_id, 'casting_availability', true),
        'bio'               => (string) get_user_meta($user_id, 'casting_bio', true),
        'video_url'         => $video_url_meta,
        'portraits'         => $portraits,
        'photo_id'          => $photo_id,
        'video_id'          => $video_id,
        'photo_url'         => is_string($photo_url) ? $photo_url : '',
        'photo_full'        => is_string($photo_full) ? $photo_full : '',
        'video_file_url'    => is_string($video_url_file) ? $video_url_file : '',
        'visible'           => get_user_meta($user_id, 'casting_visible', true) !== '0',
        'membership_number' => casting_get_membership_number($user_id),
        'referral_code'     => casting_get_referral_code($user_id),
    ];
}

function casting_normalize_mobile(string $mobile): string
{
    $mobile = preg_replace('/\D+/', '', $mobile) ?? '';
    if (str_starts_with($mobile, '98') && strlen($mobile) === 12) {
        $mobile = '0' . substr($mobile, 2);
    }
    return $mobile;
}

/**
 * شماره موبایل دوم (اختیاری) — خالی مجاز است؛ در صورت پر بودن باید معتبر و متفاوت از موبایل اصلی باشد.
 *
 * @return array{ok:bool,error:string,mobile:string}
 */
function casting_normalize_optional_mobile2(string $raw, string $primary_mobile = ''): array
{
    $mobile2 = casting_normalize_mobile($raw);
    if ($mobile2 === '') {
        return ['ok' => true, 'error' => '', 'mobile' => ''];
    }
    if (!preg_match('/^09\d{9}$/', $mobile2)) {
        return ['ok' => false, 'error' => 'شماره موبایل دوم را درست وارد کنید (مثلاً ۰۹۱۲۱۲۳۴۵۶۷).', 'mobile' => ''];
    }
    $primary = casting_normalize_mobile($primary_mobile);
    if ($primary !== '' && $mobile2 === $primary) {
        return ['ok' => false, 'error' => 'شماره موبایل دوم باید با موبایل اصلی فرق داشته باشد.', 'mobile' => ''];
    }

    return ['ok' => true, 'error' => '', 'mobile' => $mobile2];
}

/**
 * دکمه و فیلد اختیاری موبایل دوم (ثبت‌نام / ویرایش پروفایل)
 */
function casting_render_optional_mobile2_field(string $mobile2 = '', bool $invalid = false): void
{
    $has = $mobile2 !== '';
    ?>
  <div class="mobile2-extra" data-mobile2-extra>
    <button
      type="button"
      class="btn btn-ghost btn-sm mobile2-add-btn"
      data-mobile2-add
      <?= $has ? 'hidden' : '' ?>
    >+ افزودن شماره موبایل دوم (اختیاری)</button>
    <div class="field mobile2-field<?= $invalid ? ' is-invalid' : '' ?>" data-mobile2-field<?= $has ? '' : ' hidden' ?>>
      <label for="mobile2">موبایل دوم <span class="meta">(اختیاری)</span></label>
      <div class="field-control field-control--mobile2">
        <input
          id="mobile2"
          name="mobile2"
          type="tel"
          inputmode="numeric"
          pattern="09[0-9]{9}"
          value="<?= casting_e($mobile2) ?>"
          placeholder="09121234567"
          autocomplete="tel-national"
          data-mobile2-input
          <?= $invalid ? ' aria-invalid="true"' : '' ?>
        >
        <button type="button" class="btn btn-ghost btn-sm" data-mobile2-remove title="حذف شماره دوم">حذف</button>
      </div>
      <p class="field-hint">اگر لازم دارید شماره جایگزین ثبت کنید؛ این فیلد اجباری نیست و برای دیگر اعضا نمایش داده نمی‌شود.</p>
    </div>
  </div>
    <?php
}

function casting_normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

/**
 * @return array{mobile:string,mobile2:string,phone:string}
 */
function casting_profile_contact_numbers(array $profile): array
{
    return [
        'mobile'  => trim((string) ($profile['mobile'] ?? '')),
        'mobile2' => trim((string) ($profile['mobile2'] ?? '')),
        'phone'   => trim((string) ($profile['phone'] ?? '')),
    ];
}

function casting_render_contact_number_items(array $profile): void
{
    $nums = casting_profile_contact_numbers($profile);
    if ($nums['mobile'] !== '') {
        echo '<li><strong>موبایل:</strong> <span dir="ltr">' . casting_e($nums['mobile']) . '</span></li>';
    }
    if ($nums['mobile2'] !== '') {
        echo '<li><strong>موبایل دوم:</strong> <span dir="ltr">' . casting_e($nums['mobile2']) . '</span></li>';
    }
    if ($nums['phone'] !== '') {
        echo '<li><strong>تلفن ثابت:</strong> <span dir="ltr">' . casting_e($nums['phone']) . '</span></li>';
    }
}

function casting_viewer_can_see_contact_numbers(int $viewer_id): bool
{
    if (!function_exists('casting_user_can_view_contact_numbers')) {
        $file = __DIR__ . '/admin-access.php';
        if (is_file($file)) {
            require_once $file;
        }
    }

    return function_exists('casting_user_can_view_contact_numbers')
        && casting_user_can_view_contact_numbers($viewer_id);
}

function casting_register_focus_for_error(string $error): string
{
    $map = [
        'نام کاربری'       => 'username',
        'تلفن ثابت'        => 'phone',
        'موبایل دوم'       => 'mobile2',
        'تاریخ تولد'       => 'birth_jd',
        'رنگ پوست'         => 'look',
        'ایمیل'            => 'email',
        'رمز عبور'         => 'password',
        'موبایل'           => 'mobile',
        'کد تأیید'         => 'otp_code',
        'جنسیت'            => 'gender',
        'استان'            => 'province',
        'شهر'              => 'city',
        'قد'               => 'height',
        'وزن'              => 'weight',
        'سلامت'            => 'health_well',
        'پروانه'           => 'activity_license',
        'سابقه'            => 'experience',
        'آمادگی'           => 'availability',
        'عکس'              => 'photo_medium',
        'مهارت'            => 'skill_items',
        'لهجه'             => 'accent',
        'تشکل'             => 'artistic_membership',
        'قوانین'           => 'rules_accepted',
        'نام'              => 'name',
        'فعالیت'           => 'activities',
    ];
    foreach ($map as $needle => $field) {
        if (str_contains($error, $needle)) {
            return $field;
        }
    }

    return '';
}

/**
 * بررسی فیلدهای ستاره‌دار ثبت‌نام — همه ایرادها را یکجا برمی‌گرداند
 *
 * @param array<string, mixed> $ctx
 * @return array{errors: list<string>, fields: list<string>}
 */
function casting_register_collect_required_issues(array $ctx): array
{
    $errors = [];
    $fields = [];
    $add = static function (string $field, string $message) use (&$errors, &$fields): void {
        if (!in_array($field, $fields, true)) {
            $fields[] = $field;
        }
        if (!in_array($message, $errors, true)) {
            $errors[] = $message;
        }
    };

    $activities = casting_normalize_activities($ctx['activities'] ?? []);
    if ($activities === []) {
        $add('activities', 'نوع فعالیت (ستاره‌دار) الزامی است.');
    }
    $skip_talent = !casting_activities_need_talent_fields($activities);
    $pending = casting_register_pending_media_get();

    $name = trim((string) ($ctx['name'] ?? ''));
    if ($name === '' || casting_strlen($name) < 2) {
        $add('name', 'نام و نام خانوادگی (ستاره‌دار) الزامی است.');
    }
    $username = trim((string) ($ctx['username'] ?? ''));
    if ($username === '' || strlen($username) < 3) {
        $add('username', 'نام کاربری (ستاره‌دار) الزامی است.');
    }
    $email = trim((string) ($ctx['email'] ?? ''));
    if ($email === '' || !is_email($email)) {
        $add('email', 'ایمیل (ستاره‌دار) الزامی است.');
    }
    $password = (string) ($ctx['password'] ?? '');
    $password2 = (string) ($ctx['password2'] ?? '');
    if (strlen($password) < 8) {
        $add('password', 'رمز عبور (ستاره‌دار) حداقل ۸ کاراکتر است.');
    }
    if ($password !== $password2) {
        $add('password2', 'تکرار رمز عبور با رمز یکسان نیست.');
    }
    $mobile = casting_normalize_mobile((string) ($ctx['mobile'] ?? ''));
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        $add('mobile', 'موبایل (ستاره‌دار) را درست وارد کنید.');
    }
    $birthdate = (string) ($ctx['birthdate'] ?? '');
    if ($birthdate === '' || casting_age_from_birthdate($birthdate) === null) {
        $add('birth_jd', 'تاریخ تولد شمسی (ستاره‌دار) الزامی است.');
    }
    $gender = sanitize_key((string) ($ctx['gender'] ?? ''));
    if (!array_key_exists($gender, casting_gender_labels())) {
        $add('gender', 'جنسیت (ستاره‌دار) را انتخاب کنید.');
    }
    $province = sanitize_key((string) ($ctx['province'] ?? ''));
    if (!array_key_exists($province, casting_province_labels())) {
        $add('province', 'استان (ستاره‌دار) را انتخاب کنید.');
    }
    $city = casting_normalize_city_name((string) ($ctx['city'] ?? ''));
    if ($province !== '' && !casting_is_valid_city_for_province($province, $city)) {
        $add('city', 'شهر (ستاره‌دار) را انتخاب کنید.');
    }
    $experience = (string) ($ctx['experience'] ?? '');
    if ($experience === '' || (int) $experience < 0 || (int) $experience > 60) {
        $add('experience', 'سابقه فعالیت (ستاره‌دار) الزامی است.');
    }
    $license = sanitize_key((string) ($ctx['activity_license'] ?? ''));
    if (!isset(casting_yes_no_labels()[$license])) {
        $add('activity_license', 'پروانه فعالیت (ستاره‌دار) را مشخص کنید.');
    }
    if (empty($ctx['rules_accepted'])) {
        $add('rules_accepted', 'تأیید قوانین (ستاره‌دار) الزامی است.');
    }

    if (!$skip_talent) {
        $look = sanitize_key((string) ($ctx['look'] ?? ''));
        if (!array_key_exists($look, casting_look_labels())) {
            $add('look', 'رنگ پوست (ستاره‌دار) الزامی است.');
        }
        $health_err = casting_validate_health_fields([
            'well'   => (string) ($ctx['health_well'] ?? ''),
            'detail' => (string) ($ctx['health_status'] ?? ''),
        ], true);
        if ($health_err !== null) {
            $add('health_well', $health_err);
        }
        $availability = sanitize_key((string) ($ctx['availability'] ?? ''));
        if (!array_key_exists($availability, casting_availability_labels())) {
            $add('availability', 'وضعیت آمادگی همکاری (ستاره‌دار) را انتخاب کنید.');
        }
        if (casting_activities_need_body_metrics($activities)) {
            if (trim((string) ($ctx['height'] ?? '')) === '') {
                $add('height', 'قد (ستاره‌دار) الزامی است.');
            }
            if (trim((string) ($ctx['weight'] ?? '')) === '') {
                $add('weight', 'وزن (ستاره‌دار) الزامی است.');
            }
        }
        foreach (casting_portrait_slots() as $slot => $label) {
            $has = !empty($_FILES['photo_' . $slot]['name']) || !empty($pending['portraits'][$slot]);
            if (!$has) {
                $add('photo_' . $slot, 'عکس «' . $label . '» (ستاره‌دار) الزامی است.');
            }
        }
    } else {
        $has = !empty($_FILES['photo_medium']['name']) || !empty($pending['portraits']['medium']);
        if (!$has) {
            $add('photo_medium_single', 'عکس پروفایل (ستاره‌دار) الزامی است.');
        }
    }

    return ['errors' => $errors, 'fields' => $fields];
}

function casting_save_registration_profile(int $user_id, array $data): array
{
    $birthdate = sanitize_text_field((string) ($data['birthdate'] ?? ''));
    $age = casting_age_from_birthdate($birthdate);
    if ($age === null) {
        return ['ok' => false, 'error' => 'تاریخ تولد معتبر نیست.'];
    }
    if ($age < 3 || $age > 120) {
        return ['ok' => false, 'error' => 'سن محاسبه‌شده باید حداقل ۳ سال باشد.'];
    }

    $gender = sanitize_key((string) ($data['gender'] ?? ''));
    if (!array_key_exists($gender, casting_gender_labels())) {
        return ['ok' => false, 'error' => 'جنسیت را انتخاب کنید.'];
    }

    $activities = casting_normalize_activities($data['activities'] ?? []);
    $skip_talent_profile = !casting_activities_need_talent_fields($activities);

    $look = sanitize_key((string) ($data['look'] ?? ''));
    if (!$skip_talent_profile && !array_key_exists($look, casting_look_labels())) {
        return ['ok' => false, 'error' => 'رنگ پوست را انتخاب کنید.'];
    }

    $mobile = casting_normalize_mobile((string) ($data['mobile'] ?? ''));
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['ok' => false, 'error' => 'شماره موبایل را درست وارد کنید (مثلاً ۰۹۱۲۱۲۳۴۵۶۷).'];
    }
    $mobile2_res = casting_normalize_optional_mobile2((string) ($data['mobile2'] ?? ''), $mobile);
    if (!$mobile2_res['ok']) {
        return ['ok' => false, 'error' => $mobile2_res['error']];
    }
    $mobile2 = $mobile2_res['mobile'];
    if ($mobile2 !== '' && function_exists('casting_mobile_is_taken') && casting_mobile_is_taken($mobile2, $user_id)) {
        return ['ok' => false, 'error' => 'شماره موبایل دوم قبلاً برای حساب دیگری ثبت شده است.'];
    }
    $phone = casting_normalize_phone((string) ($data['phone'] ?? ''));
    if ($phone !== '' && (strlen($phone) < 8 || strlen($phone) > 11)) {
        return ['ok' => false, 'error' => 'تلفن ثابت معتبر نیست.'];
    }

    $province = sanitize_key((string) ($data['province'] ?? ''));
    if (!array_key_exists($province, casting_province_labels())) {
        return ['ok' => false, 'error' => 'استان را انتخاب کنید.'];
    }

    $city = casting_normalize_city_name((string) ($data['city'] ?? ''));
    if (!casting_is_valid_city_for_province($province, $city)) {
        return ['ok' => false, 'error' => 'شهر را از فهرست همان استان انتخاب کنید.'];
    }

    $residence = sanitize_text_field((string) ($data['residence'] ?? ''));

    $experience = (int) ($data['experience'] ?? -1);
    if ($experience < 0 || $experience > 60) {
        return ['ok' => false, 'error' => 'سابقه فعالیت را درست وارد کنید (۰ تا ۶۰ سال).'];
    }

    $yes_no = casting_yes_no_labels();
    $activity_license = sanitize_key((string) ($data['activity_license'] ?? ''));
    if (!isset($yes_no[$activity_license])) {
        return ['ok' => false, 'error' => 'پروانه فعالیت را مشخص کنید.'];
    }

    $artistic = casting_parse_artistic_membership_post($data);
    $artistic_err = casting_validate_artistic_membership($artistic);
    if ($artistic_err !== null) {
        return ['ok' => false, 'error' => $artistic_err];
    }

    $work = sanitize_textarea_field((string) ($data['work_history'] ?? ''));
    $awards = sanitize_textarea_field((string) ($data['awards'] ?? ''));
    $education = sanitize_textarea_field((string) ($data['education'] ?? ''));
    $edu_items = casting_normalize_education_items($data['education_items'] ?? []);

    $height_raw = trim((string) ($data['height'] ?? ''));
    $weight_raw = trim((string) ($data['weight'] ?? ''));
    $need_body = !$skip_talent_profile && casting_activities_need_body_metrics($activities);
    if ($need_body && ($height_raw === '' || $weight_raw === '')) {
        return ['ok' => false, 'error' => 'برای بازیگری قد و وزن الزامی است.'];
    }
    $height = 0;
    $weight = 0;
    if ($height_raw !== '') {
        $height = (int) $height_raw;
        if (!casting_body_metric_is_valid('height', $height)) {
            return ['ok' => false, 'error' => 'قد را از فهرست انتخاب کنید (۵۰ تا ۲۰۰ سانتی‌متر یا بالاتر).'];
        }
    }
    if ($weight_raw !== '') {
        $weight = (int) $weight_raw;
        if (!casting_body_metric_is_valid('weight', $weight)) {
            return ['ok' => false, 'error' => 'وزن را از فهرست انتخاب کنید (۱۵ تا ۱۵۰ کیلوگرم یا بالاتر).'];
        }
    }

    $health = casting_parse_health_post($data);
    if (!$skip_talent_profile) {
        $health_err = casting_validate_health_fields($health, true);
        if ($health_err !== null) {
            return ['ok' => false, 'error' => $health_err];
        }
    } else {
        $health = ['well' => '', 'detail' => ''];
    }

    update_user_meta($user_id, 'casting_birthdate', $birthdate);
    update_user_meta($user_id, 'casting_age', (string) $age);
    update_user_meta($user_id, 'casting_gender', $gender);
    if (!$skip_talent_profile && $look !== '') {
        update_user_meta($user_id, 'casting_look', $look);
    }
    update_user_meta($user_id, 'casting_mobile', $mobile);
    update_user_meta($user_id, 'casting_mobile2', $mobile2);
    update_user_meta($user_id, 'casting_phone', $phone);
    update_user_meta($user_id, 'casting_province', $province);
    update_user_meta($user_id, 'casting_city', $city);
    if ($city !== casting_city_all_label()) {
        casting_remember_city($city);
    }
    update_user_meta($user_id, 'casting_residence', $residence);
    update_user_meta($user_id, 'casting_experience', (string) $experience);
    casting_save_artistic_membership_meta($user_id, $artistic);
    update_user_meta($user_id, 'casting_activity_license', $activity_license);
    if ($height > 0) {
        update_user_meta($user_id, 'casting_height', (string) $height);
    }
    if ($weight > 0) {
        update_user_meta($user_id, 'casting_weight', (string) $weight);
    }
    if (!$skip_talent_profile) {
        casting_save_health_meta($user_id, $health);
    }
    update_user_meta($user_id, 'casting_work_history', $work);
    update_user_meta($user_id, 'casting_awards', $awards);
    casting_save_user_work_meta($user_id, $data, $skip_talent_profile);
    update_user_meta($user_id, 'casting_education', $education);
    update_user_meta($user_id, 'casting_education_items', $edu_items);
    update_user_meta($user_id, 'casting_activities', $activities);

    $skill_items = casting_normalize_skill_items($data['skill_items'] ?? []);
    if (!$skip_talent_profile) {
        foreach ($skill_items as $row) {
            if ($row['skill'] === 'other' && $row['note'] === '') {
                return ['ok' => false, 'error' => 'برای مهارت «سایر» بنویسید چه هنری دارید.'];
            }
        }
    }
    $language_items = casting_normalize_language_items($data['language_items'] ?? []);
    $availability = sanitize_key((string) ($data['availability'] ?? ''));
    if (!$skip_talent_profile && !array_key_exists($availability, casting_availability_labels())) {
        return ['ok' => false, 'error' => 'وضعیت آمادگی برای همکاری را انتخاب کنید.'];
    }

    update_user_meta($user_id, 'casting_language_items', $language_items);
    if (!$skip_talent_profile) {
        update_user_meta($user_id, 'casting_skill_items', $skill_items);
        update_user_meta($user_id, 'casting_skills_other', '');
        update_user_meta($user_id, 'casting_skills', casting_format_skill_labels($skill_items));
        if ($availability !== '') {
            update_user_meta($user_id, 'casting_availability', $availability);
        }
    }
    update_user_meta($user_id, 'casting_visible', '1');

    if (!$skip_talent_profile) {
        $traits = casting_save_talent_trait_meta($user_id, $data);
        if (!$traits['ok']) {
            return $traits;
        }
    } else {
        casting_purge_non_actor_profile_meta($user_id);
    }

    return ['ok' => true, 'age' => $age];
}

/**
 * اعتبارسنجی فیلدهای اجباری هنگام ویرایش پروفایل (مثل ثبت‌نام، بدون رمز/نام‌کاربری/عکس)
 *
 * @param array<string, mixed> $data
 */
function casting_profile_edit_required_error(int $user_id, array $data): ?string
{
    $name = trim(sanitize_text_field((string) ($data['name'] ?? '')));
    if ($name === '' || casting_strlen($name) < 2) {
        return 'نام و نام خانوادگی الزامی است.';
    }

    $email = trim((string) ($data['email'] ?? ''));
    if ($email === '' || !is_email($email)) {
        return 'ایمیل الزامی است.';
    }

    $mobile = casting_normalize_mobile((string) ($data['mobile'] ?? ''));
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return 'موبایل الزامی است و باید معتبر باشد.';
    }

    $birthdate = sanitize_text_field((string) ($data['birthdate'] ?? ''));
    if ($birthdate === '' || casting_age_from_birthdate($birthdate) === null) {
        return 'تاریخ تولد شمسی الزامی است.';
    }

    $gender = sanitize_key((string) ($data['gender'] ?? ''));
    if (!array_key_exists($gender, casting_gender_labels())) {
        return 'جنسیت را انتخاب کنید.';
    }

    $province = sanitize_key((string) ($data['province'] ?? ''));
    if (!array_key_exists($province, casting_province_labels())) {
        return 'استان را انتخاب کنید.';
    }
    $city = casting_normalize_city_name((string) ($data['city'] ?? ''));
    if (!casting_is_valid_city_for_province($province, $city)) {
        return 'شهر را انتخاب کنید.';
    }

    if (!array_key_exists('experience', $data) || $data['experience'] === '' || (int) $data['experience'] < 0 || (int) $data['experience'] > 60) {
        return 'سابقه فعالیت الزامی است.';
    }

    $license = sanitize_key((string) ($data['activity_license'] ?? ''));
    if (!isset(casting_yes_no_labels()[$license])) {
        return 'پروانه فعالیت را مشخص کنید.';
    }

    $activities = casting_normalize_activities($data['activities'] ?? [], $user_id);
    if ($activities === []) {
        return 'حداقل یک نوع فعالیت انتخاب کنید.';
    }

    if (casting_activities_need_talent_fields($activities)) {
        $look = sanitize_key((string) ($data['look'] ?? ''));
        if (!array_key_exists($look, casting_look_labels())) {
            return 'رنگ پوست الزامی است.';
        }
        $health_err = casting_validate_health_fields([
            'well'   => (string) ($data['health_well'] ?? ''),
            'detail' => (string) ($data['health_status'] ?? ''),
        ], true);
        if ($health_err !== null) {
            return $health_err;
        }
        $availability = sanitize_key((string) ($data['availability'] ?? ''));
        if (!array_key_exists($availability, casting_availability_labels())) {
            return 'وضعیت آمادگی همکاری را انتخاب کنید.';
        }
        if (casting_activities_need_body_metrics($activities)) {
            if (trim((string) ($data['height'] ?? '')) === '') {
                return 'قد الزامی است.';
            }
            if (trim((string) ($data['weight'] ?? '')) === '') {
                return 'وزن الزامی است.';
            }
        }
    }

    return null;
}

function casting_save_profile(int $user_id, array $data): array
{
    if (!function_exists('casting_update_user_email')) {
        require_once __DIR__ . '/auth.php';
    }

    $required_err = casting_profile_edit_required_error($user_id, $data);
    if ($required_err !== null) {
        return ['ok' => false, 'error' => $required_err];
    }

    if (array_key_exists('name', $data)) {
        $name_result = casting_update_user_display_name($user_id, (string) $data['name']);
        if (!$name_result['ok']) {
            return $name_result;
        }
    }

    $birthdate = sanitize_text_field((string) ($data['birthdate'] ?? ''));
    $age = casting_age_from_birthdate($birthdate);
    if ($age === null || $age < 3 || $age > 120) {
        return ['ok' => false, 'error' => 'تاریخ تولد معتبر نیست.'];
    }
    update_user_meta($user_id, 'casting_birthdate', $birthdate);
    update_user_meta($user_id, 'casting_age', (string) $age);

    $gender = sanitize_key((string) ($data['gender'] ?? ''));
    if (!array_key_exists($gender, casting_gender_labels())) {
        return ['ok' => false, 'error' => 'جنسیت را انتخاب کنید.'];
    }
    update_user_meta($user_id, 'casting_gender', $gender);

    if (array_key_exists('email', $data)) {
        $email_result = casting_update_user_email($user_id, (string) $data['email']);
        if (!$email_result['ok']) {
            return $email_result;
        }
    }

    if (array_key_exists('mobile', $data)) {
        $mobile = casting_normalize_mobile((string) $data['mobile']);
        if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
            return ['ok' => false, 'error' => 'شماره موبایل را درست وارد کنید.'];
        }
        update_user_meta($user_id, 'casting_mobile', $mobile);
    }
    if (array_key_exists('mobile2', $data)) {
        $primary = casting_normalize_mobile((string) (
            array_key_exists('mobile', $data)
                ? $data['mobile']
                : get_user_meta($user_id, 'casting_mobile', true)
        ));
        $mobile2_res = casting_normalize_optional_mobile2((string) $data['mobile2'], $primary);
        if (!$mobile2_res['ok']) {
            return ['ok' => false, 'error' => $mobile2_res['error']];
        }
        $mobile2 = $mobile2_res['mobile'];
        if ($mobile2 !== '' && function_exists('casting_mobile_is_taken') && casting_mobile_is_taken($mobile2, $user_id)) {
            return ['ok' => false, 'error' => 'شماره موبایل دوم قبلاً برای حساب دیگری ثبت شده است.'];
        }
        update_user_meta($user_id, 'casting_mobile2', $mobile2);
    }
    if (array_key_exists('phone', $data)) {
        $phone = casting_normalize_phone((string) $data['phone']);
        if ($phone !== '' && (strlen($phone) < 8 || strlen($phone) > 11)) {
            return ['ok' => false, 'error' => 'تلفن ثابت معتبر نیست.'];
        }
        update_user_meta($user_id, 'casting_phone', $phone);
    }

    $province = sanitize_key((string) ($data['province'] ?? ''));
    if ($province !== '') {
        if (!array_key_exists($province, casting_province_labels())) {
            return ['ok' => false, 'error' => 'استان را درست انتخاب کنید.'];
        }
        update_user_meta($user_id, 'casting_province', $province);
    }

    $city = casting_normalize_city_name((string) ($data['city'] ?? ''));
    if ($city !== '') {
        $check_province = $province !== '' ? $province : (string) get_user_meta($user_id, 'casting_province', true);
        if (!casting_is_valid_city_for_province($check_province, $city)) {
            return ['ok' => false, 'error' => 'شهر را از فهرست همان استان انتخاب کنید.'];
        }
        update_user_meta($user_id, 'casting_city', $city);
        if ($city !== casting_city_all_label()) {
            casting_remember_city($city);
        }
    }


    if (isset($data['height']) && $data['height'] !== '') {
        $height = (int) $data['height'];
        if (!casting_body_metric_is_valid('height', $height)) {
            return ['ok' => false, 'error' => 'قد را از فهرست انتخاب کنید (۵۰ تا ۲۰۰ سانتی‌متر یا بالاتر).'];
        }
        update_user_meta($user_id, 'casting_height', (string) $height);
    }

    if (isset($data['weight']) && $data['weight'] !== '') {
        $weight = (int) $data['weight'];
        if (!casting_body_metric_is_valid('weight', $weight)) {
            return ['ok' => false, 'error' => 'وزن را از فهرست انتخاب کنید (۱۵ تا ۱۵۰ کیلوگرم یا بالاتر).'];
        }
        update_user_meta($user_id, 'casting_weight', (string) $weight);
    }

    $activities_for_traits = isset($data['activities'])
        ? casting_normalize_activities($data['activities'], $user_id)
        : casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true), $user_id);
    $is_actor_profile = casting_activities_need_talent_fields($activities_for_traits);

    if ($is_actor_profile && (array_key_exists('health_well', $data) || array_key_exists('health_status', $data))) {
        $health = casting_parse_health_post($data);
        $health_err = casting_validate_health_fields($health, false);
        if ($health_err !== null) {
            return ['ok' => false, 'error' => $health_err];
        }
        if (($health['well'] ?? '') !== '') {
            casting_save_health_meta($user_id, $health);
        }
    }

    if (isset($data['experience']) && $data['experience'] !== '') {
        $experience = max(0, min(60, (int) $data['experience']));
        update_user_meta($user_id, 'casting_experience', (string) $experience);
    }

    $yes_no = casting_yes_no_labels();
    if (array_key_exists('activity_license', $data)) {
        $val = sanitize_key((string) $data['activity_license']);
        if ($val !== '' && !isset($yes_no[$val])) {
            return ['ok' => false, 'error' => 'گزینه بله/خیر را درست انتخاب کنید.'];
        }
        if ($val !== '') {
            update_user_meta($user_id, 'casting_activity_license', $val);
        }
    }

    if (array_key_exists('artistic_membership', $data) || array_key_exists('artistic_orgs', $data)) {
        $artistic = casting_parse_artistic_membership_post($data);
        $artistic_err = casting_validate_artistic_membership($artistic);
        if ($artistic_err !== null) {
            return ['ok' => false, 'error' => $artistic_err];
        }
        casting_save_artistic_membership_meta($user_id, $artistic);
    }

    $look = sanitize_key((string) ($data['look'] ?? ''));
    if ($look !== '' && !array_key_exists($look, casting_look_labels())) {
        return ['ok' => false, 'error' => 'رنگ پوست را درست انتخاب کنید.'];
    }
    if ($look !== '') {
        update_user_meta($user_id, 'casting_look', $look);
    }

    if ($is_actor_profile) {
        $traits = casting_save_talent_trait_meta($user_id, $data);
        if (!$traits['ok']) {
            return $traits;
        }
    } else {
        casting_purge_non_actor_profile_meta($user_id);
    }

    if ($is_actor_profile) {
        if (array_key_exists('skill_items', $data)) {
            $skill_items = casting_normalize_skill_items($data['skill_items']);
            foreach ($skill_items as $row) {
                if ($row['skill'] === 'other' && $row['note'] === '') {
                    return ['ok' => false, 'error' => 'برای مهارت «سایر» بنویسید چه هنری دارید.'];
                }
            }
            update_user_meta($user_id, 'casting_skill_items', $skill_items);
            update_user_meta($user_id, 'casting_skills_other', '');
            update_user_meta($user_id, 'casting_skills', casting_format_skill_labels($skill_items));
        } elseif (array_key_exists('skills', $data)) {
            update_user_meta($user_id, 'casting_skills', sanitize_text_field((string) $data['skills']));
        }

        if (array_key_exists('availability', $data)) {
            $availability = sanitize_key((string) $data['availability']);
            if ($availability !== '' && !array_key_exists($availability, casting_availability_labels())) {
                return ['ok' => false, 'error' => 'وضعیت آمادگی برای همکاری را درست انتخاب کنید.'];
            }
            if ($availability !== '') {
                update_user_meta($user_id, 'casting_availability', $availability);
            }
        }
    }

    if (array_key_exists('language_items', $data)) {
        update_user_meta($user_id, 'casting_language_items', casting_normalize_language_items($data['language_items']));
    }

    update_user_meta($user_id, 'casting_bio', sanitize_textarea_field((string) ($data['bio'] ?? '')));
    update_user_meta($user_id, 'casting_work_history', sanitize_textarea_field((string) ($data['work_history'] ?? '')));
    if (array_key_exists('awards', $data)) {
        update_user_meta($user_id, 'casting_awards', sanitize_textarea_field((string) $data['awards']));
    }
    $skip_talent_profile = false;
    if (array_key_exists('activities', $data)) {
        $skip_talent_profile = !casting_activities_need_talent_fields(casting_normalize_activities($data['activities'], $user_id));
    } elseif (!casting_activities_need_talent_fields(casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true), $user_id))) {
        $skip_talent_profile = true;
    }
    casting_save_user_work_meta($user_id, $data, $skip_talent_profile);
    update_user_meta($user_id, 'casting_education', sanitize_textarea_field((string) ($data['education'] ?? '')));
    update_user_meta($user_id, 'casting_education_items', casting_normalize_education_items($data['education_items'] ?? []));

    if (array_key_exists('activities', $data)) {
        $activities = casting_normalize_activities($data['activities'], $user_id);
        if ($activities === []) {
            return ['ok' => false, 'error' => 'حداقل یک نوع فعالیت انتخاب کنید.'];
        }
        if (function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id) && !in_array('it', $activities, true)) {
            $activities[] = 'it';
        }
        if (casting_activities_need_body_metrics($activities)) {
            $h = (string) get_user_meta($user_id, 'casting_height', true);
            $w = (string) get_user_meta($user_id, 'casting_weight', true);
            if (isset($data['height']) && $data['height'] !== '') {
                $h = (string) $data['height'];
            }
            if (isset($data['weight']) && $data['weight'] !== '') {
                $w = (string) $data['weight'];
            }
            if ($h === '' || $w === '') {
                return ['ok' => false, 'error' => 'برای بازیگری قد و وزن الزامی است.'];
            }
        }
        update_user_meta($user_id, 'casting_activities', $activities);
        $new_role = casting_infer_role_from_activities($activities);
        if (casting_valid_role($new_role)) {
            update_user_meta($user_id, 'casting_role', $new_role);
        }
    }

    $video_url = esc_url_raw((string) ($data['video_url'] ?? ''));
    if ($video_url !== '' && !filter_var($video_url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'error' => 'لینک ویدیو معتبر نیست.'];
    }
    update_user_meta($user_id, 'casting_video_url', $video_url);
    update_user_meta($user_id, 'casting_visible', !empty($data['visible']) ? '1' : '0');

    return ['ok' => true];
}

function casting_require_media_includes(): void
{
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
}

/**
 * سقف حجم آپلود بر حسب بایت — image | video | audio
 */
function casting_upload_max_bytes(string $kind = 'image'): int
{
    if ($kind === 'video') {
        return 40 * 1024 * 1024;
    }
    if ($kind === 'audio') {
        return 25 * 1024 * 1024;
    }

    return 5 * 1024 * 1024;
}

function casting_upload_max_label_fa(string $kind = 'image'): string
{
    if ($kind === 'video') {
        return '۴۰ مگابایت';
    }
    if ($kind === 'audio') {
        return '۲۵ مگابایت';
    }

    return '۵ مگابایت';
}

function casting_upload_kind_label_fa(string $kind = 'image'): string
{
    if ($kind === 'video') {
        return 'ویدیو';
    }
    if ($kind === 'audio') {
        return 'فایل صوتی';
    }

    return 'عکس';
}

/** پیام یکدست وقتی حجم از سقف اپ بیشتر است */
function casting_upload_too_large_message(string $kind = 'image'): string
{
    return 'حجم ' . casting_upload_kind_label_fa($kind)
        . ' بالاتر از حد مجاز است. حداکثر حجم مجاز '
        . casting_upload_max_label_fa($kind) . ' است.';
}

/**
 * وقتی PHP کل POST را به‌خاطر post_max_size دور می‌ریزد
 */
function casting_upload_post_too_large(): bool
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        return false;
    }
    $content_length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($content_length <= 0) {
        return false;
    }
    $post_max = function_exists('wp_convert_hr_to_bytes')
        ? (int) wp_convert_hr_to_bytes((string) ini_get('post_max_size'))
        : 0;
    if ($post_max > 0 && $content_length > $post_max) {
        return true;
    }
    if (empty($_POST) && empty($_FILES) && $content_length > (1024 * 1024)) {
        return true;
    }

    return false;
}

function casting_upload_post_too_large_message(): string
{
    return 'حجم فایل بالاتر از حد مجاز است. حداکثر حجم مجاز برای عکس '
        . casting_upload_max_label_fa('image')
        . ' و برای ویدیو '
        . casting_upload_max_label_fa('video')
        . ' است.';
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_uploaded_file_within_limit(array $file, string $kind = 'image'): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => casting_upload_too_large_message($kind)];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => casting_upload_php_error_message($error, $kind)];
    }
    if ((int) ($file['size'] ?? 0) > casting_upload_max_bytes($kind)) {
        return ['ok' => false, 'error' => casting_upload_too_large_message($kind)];
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * پیام خطای آپلود PHP برای کاربر
 */
function casting_upload_php_error_message(int $error_code, string $kind = 'image'): string
{
    if ($error_code === UPLOAD_ERR_INI_SIZE || $error_code === UPLOAD_ERR_FORM_SIZE) {
        return casting_upload_too_large_message($kind === 'video' || $kind === 'audio' ? $kind : 'image');
    }
    if ($error_code === UPLOAD_ERR_PARTIAL) {
        return 'آپلود ناقص بود. دوباره تلاش کنید.';
    }
    if ($error_code === UPLOAD_ERR_NO_FILE) {
        return 'فایلی انتخاب نشده است.';
    }
    if ($error_code === UPLOAD_ERR_NO_TMP_DIR) {
        return 'پوشه موقت سرور در دسترس نیست.';
    }
    if ($error_code === UPLOAD_ERR_CANT_WRITE) {
        return 'نوشتن فایل روی سرور ناموفق بود.';
    }
    if ($error_code === UPLOAD_ERR_EXTENSION) {
        return 'افزونه سرور جلوی آپلود را گرفت.';
    }

    return 'آپلود فایل ناموفق بود.';
}

/**
 * نرمال‌سازی MIME برای مرورگرهایی که type خالی / octet-stream می‌فرستند
 *
 * @param array<string, mixed> $file
 * @return array{ok:bool,error:string,type:string}
 */
function casting_normalize_uploaded_file_type(array &$file, string $kind = 'image'): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        $msg_kind = $kind === 'video' || $kind === 'audio' ? $kind : 'image';

        return ['ok' => false, 'error' => casting_upload_php_error_message($error, $msg_kind), 'type' => ''];
    }

    $ftype = strtolower(trim((string) ($file['type'] ?? '')));
    $name = (string) ($file['name'] ?? '');
    $tmp = (string) ($file['tmp_name'] ?? '');

    if ($ftype === '' || $ftype === 'application/octet-stream' || $ftype === 'binary/octet-stream') {
        if (function_exists('wp_check_filetype_and_ext') && $tmp !== '' && is_uploaded_file($tmp)) {
            $check = wp_check_filetype_and_ext($tmp, $name);
            if (!empty($check['type'])) {
                $ftype = strtolower((string) $check['type']);
                $file['type'] = $ftype;
            }
        }
        if ($ftype === '' || $ftype === 'application/octet-stream' || $ftype === 'binary/octet-stream') {
            $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            $map = $kind === 'video'
                ? ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime']
                : ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
            if (isset($map[$ext])) {
                $ftype = $map[$ext];
                $file['type'] = $ftype;
            }
        }
    }

    return ['ok' => true, 'error' => '', 'type' => $ftype];
}

/**
 * آپلود با دسترسی موقت — برای ثبت‌نام (هنوز لاگین نیست) و نقش subscriber
 *
 * @return int|\WP_Error
 */
function casting_media_handle_upload_as_user(string $field, int $user_id)
{
    casting_require_media_includes();
    casting_enable_user_upload_dir($user_id);

    $prev_user = get_current_user_id();
    $grant = static function (array $allcaps, $caps, $args, $user) use ($user_id): array {
        unset($caps, $args);
        if ($user instanceof WP_User && (int) $user->ID === $user_id) {
            $allcaps['upload_files'] = true;
        }

        return $allcaps;
    };
    add_filter('user_has_cap', $grant, 20, 4);
    wp_set_current_user($user_id);

    $attachment_id = media_handle_upload($field, 0);

    remove_filter('user_has_cap', $grant, 20);
    wp_set_current_user($prev_user);
    casting_disable_user_upload_dir();

    return $attachment_id;
}

/**
 * پیش‌نویس فیلدهای ثبت‌نام در نشست (بدون رمز عبور)
 *
 * @return array<string, mixed>
 */
function casting_register_draft_get(): array
{
    $raw = $_SESSION['casting_reg_draft'] ?? null;
    return is_array($raw) ? $raw : [];
}

/**
 * @param array<string, mixed> $post
 */
function casting_register_draft_save(array $post): void
{
    $skip = [
        'password' => true,
        'password2' => true,
        '_wpnonce' => true,
        '_wp_http_referer' => true,
        'otp_code' => true,
        'otp_action' => true,
        'casting_submit' => true,
    ];
    $draft = [];
    foreach ($post as $key => $value) {
        $key = (string) $key;
        if (isset($skip[$key]) || str_starts_with($key, 'photo_') || $key === 'video') {
            continue;
        }
        if (is_array($value) || is_scalar($value)) {
            $draft[$key] = $value;
        }
    }
    $_SESSION['casting_reg_draft'] = $draft;
}

function casting_register_draft_clear(): void
{
    unset($_SESSION['casting_reg_draft']);
}

/**
 * گرفتن آپلودهای ثبت‌نام در نشست؛ در صورت حجم/خطای فایل، پیام برمی‌گردد
 *
 * @return string متن خطا یا رشته خالی
 */
function casting_register_pending_capture_uploads(): string
{
    $paths = casting_register_pending_paths();
    if ($paths === null) {
        return '';
    }
    $pending = casting_register_pending_media_get();
    $persist = static function (array $pending): void {
        $_SESSION['casting_reg_pending_media'] = [
            'portraits' => $pending['portraits'],
            'video'     => $pending['video'],
        ];
    };
    $slot_fields = [
        'closeup' => 'photo_closeup',
        'medium'  => 'photo_medium',
        'long'    => 'photo_long',
        'profile' => 'photo_profile',
    ];
    foreach ($slot_fields as $slot => $field) {
        if (empty($_FILES[$field]['name'])) {
            continue;
        }
        $file = &$_FILES[$field];
        $size_check = casting_uploaded_file_within_limit($file, 'image');
        if (!$size_check['ok']) {
            $persist($pending);

            return $size_check['error'];
        }
        $norm = casting_normalize_uploaded_file_type($file, 'image');
        if (!$norm['ok']) {
            $persist($pending);

            return $norm['error'];
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }
        $dest_name = $slot . '.' . $ext;
        $dest = $paths['dir'] . '/' . $dest_name;
        if (!empty($pending['portraits'][$slot]['path']) && is_file($pending['portraits'][$slot]['path'])) {
            @unlink($pending['portraits'][$slot]['path']);
        }
        if (!@move_uploaded_file((string) $file['tmp_name'], $dest)) {
            continue;
        }
        $pending['portraits'][$slot] = [
            'id'   => 0,
            'url'  => $paths['url'] . '/' . rawurlencode($dest_name),
            'full' => $paths['url'] . '/' . rawurlencode($dest_name),
            'path' => $dest,
            'name' => (string) $file['name'],
            'type' => (string) ($norm['type'] ?? 'image/jpeg'),
        ];
        $_FILES[$field]['name'] = '';
        $_FILES[$field]['tmp_name'] = '';
        $_FILES[$field]['error'] = UPLOAD_ERR_NO_FILE;
    }

    if (!empty($_FILES['video']['name'])) {
        $file = &$_FILES['video'];
        $size_check = casting_uploaded_file_within_limit($file, 'video');
        if (!$size_check['ok']) {
            $persist($pending);

            return $size_check['error'];
        }
        $norm = casting_normalize_uploaded_file_type($file, 'video');
        if (!$norm['ok']) {
            $persist($pending);

            return $norm['error'];
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'webm', 'mov'], true)) {
            $ext = 'mp4';
        }
        $dest_name = 'intro.' . $ext;
        $dest = $paths['dir'] . '/' . $dest_name;
        if (!empty($pending['video']['path']) && is_file($pending['video']['path'])) {
            @unlink($pending['video']['path']);
        }
        if (@move_uploaded_file((string) $file['tmp_name'], $dest)) {
            $pending['video'] = [
                'url'  => $paths['url'] . '/' . rawurlencode($dest_name),
                'path' => $dest,
                'name' => (string) $file['name'],
                'type' => (string) ($norm['type'] ?? 'video/mp4'),
            ];
            $_FILES['video']['name'] = '';
            $_FILES['video']['tmp_name'] = '';
            $_FILES['video']['error'] = UPLOAD_ERR_NO_FILE;
        }
    }

    $persist($pending);

    return '';
}

/**
 * نگهداری موقت عکس/ویدیو ثبت‌نام در نشست — تا با خطای فرم پاک نشوند
 *
 * @return array{portraits: array<string, array{url:string,full:string,id:int,path:string,name:string,type:string}>, video:?array{url:string,path:string,name:string,type:string}}
 */
function casting_register_pending_media_get(): array
{
    $raw = $_SESSION['casting_reg_pending_media'] ?? null;
    if (!is_array($raw)) {
        return ['portraits' => [], 'video' => null];
    }
    $portraits = [];
    foreach ((array) ($raw['portraits'] ?? []) as $slot => $item) {
        if (!is_array($item) || empty($item['path']) || !is_file((string) $item['path'])) {
            continue;
        }
        $portraits[(string) $slot] = [
            'id'   => 0,
            'url'  => (string) ($item['url'] ?? ''),
            'full' => (string) ($item['url'] ?? ''),
            'path' => (string) $item['path'],
            'name' => (string) ($item['name'] ?? 'photo.jpg'),
            'type' => (string) ($item['type'] ?? 'image/jpeg'),
        ];
    }
    $video = null;
    if (is_array($raw['video'] ?? null) && !empty($raw['video']['path']) && is_file((string) $raw['video']['path'])) {
        $video = [
            'url'  => (string) ($raw['video']['url'] ?? ''),
            'path' => (string) $raw['video']['path'],
            'name' => (string) ($raw['video']['name'] ?? 'video.mp4'),
            'type' => (string) ($raw['video']['type'] ?? 'video/mp4'),
        ];
    }

    return ['portraits' => $portraits, 'video' => $video];
}

function casting_register_pending_token(): string
{
    if (empty($_SESSION['casting_reg_pending_token']) || !is_string($_SESSION['casting_reg_pending_token'])) {
        $_SESSION['casting_reg_pending_token'] = wp_generate_password(24, false, false);
    }

    return (string) $_SESSION['casting_reg_pending_token'];
}

/**
 * @return array{basedir:string,baseurl:string,dir:string,url:string}|null
 */
function casting_register_pending_paths(): ?array
{
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        return null;
    }
    $token = preg_replace('/[^a-zA-Z0-9_-]/', '', casting_register_pending_token()) ?? '';
    if ($token === '') {
        return null;
    }
    $subdir = '/casting/_pending/' . $token;
    $dir = (string) $uploads['basedir'] . $subdir;
    if (!is_dir($dir) && !wp_mkdir_p($dir)) {
        return null;
    }

    return [
        'basedir' => (string) $uploads['basedir'],
        'baseurl' => (string) $uploads['baseurl'],
        'dir'     => $dir,
        'url'     => (string) $uploads['baseurl'] . $subdir,
    ];
}

function casting_register_pending_clear(): void
{
    $pending = casting_register_pending_media_get();
    foreach ($pending['portraits'] as $item) {
        if (!empty($item['path']) && is_file($item['path'])) {
            @unlink($item['path']);
        }
    }
    if (!empty($pending['video']['path']) && is_file($pending['video']['path'])) {
        @unlink($pending['video']['path']);
    }
    $paths = casting_register_pending_paths();
    if ($paths !== null && is_dir($paths['dir'])) {
        @rmdir($paths['dir']);
    }
    unset($_SESSION['casting_reg_pending_media'], $_SESSION['casting_reg_pending_token']);
}

/**
 * فایل pending را به پیوست کاربر تبدیل می‌کند.
 *
 * @param array{path:string,name:string,type:string} $item
 * @return array{ok:bool,error:string,attachment_id?:int}
 */
function casting_register_pending_attach_to_user(array $item, int $user_id): array
{
    $path = (string) ($item['path'] ?? '');
    if ($path === '' || !is_file($path) || $user_id <= 0) {
        return ['ok' => false, 'error' => 'فایل موقت پیدا نشد.'];
    }
    casting_require_media_includes();
    casting_enable_user_upload_dir($user_id);
    $binary = file_get_contents($path);
    if ($binary === false) {
        casting_disable_user_upload_dir();

        return ['ok' => false, 'error' => 'خواندن فایل موقت ناموفق بود.'];
    }
    $filename = sanitize_file_name((string) ($item['name'] ?? basename($path)));
    if ($filename === '') {
        $filename = basename($path);
    }
    $bits = wp_upload_bits($filename, null, $binary);
    casting_disable_user_upload_dir();
    if (!empty($bits['error'])) {
        return ['ok' => false, 'error' => (string) $bits['error']];
    }
    $filetype = wp_check_filetype((string) $bits['file']);
    $attachment = [
        'post_mime_type' => (string) ($filetype['type'] ?: ($item['type'] ?? 'application/octet-stream')),
        'post_title'     => preg_replace('/\.[^.]+$/', '', $filename) ?? $filename,
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_author'    => $user_id,
    ];
    $attach_id = wp_insert_attachment($attachment, (string) $bits['file']);
    if (is_wp_error($attach_id) || !$attach_id) {
        return ['ok' => false, 'error' => 'ذخیره فایل ناموفق بود.'];
    }
    $meta = wp_generate_attachment_metadata((int) $attach_id, (string) $bits['file']);
    if (is_array($meta)) {
        wp_update_attachment_metadata((int) $attach_id, $meta);
    }

    return ['ok' => true, 'error' => '', 'attachment_id' => (int) $attach_id];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_register_apply_pending_media(int $user_id, bool $require_all = false, bool $require_one = false): array
{
    $pending = casting_register_pending_media_get();
    $labels = casting_all_portrait_slots();

    foreach ($labels as $slot => $label) {
        unset($label);
        $field = 'photo_' . $slot;
        if (!empty($_FILES[$field]['name'])) {
            $result = casting_handle_portrait_upload($user_id, $slot);
            if (!$result['ok']) {
                return ['ok' => false, 'error' => $result['error']];
            }
            continue;
        }
        if (empty($pending['portraits'][$slot])) {
            continue;
        }
        $attached = casting_register_pending_attach_to_user($pending['portraits'][$slot], $user_id);
        if (!$attached['ok']) {
            return $attached;
        }
        $meta_key = casting_portrait_meta_key($slot);
        $old = (int) get_user_meta($user_id, $meta_key, true);
        update_user_meta($user_id, $meta_key, (int) $attached['attachment_id']);
        if ($slot === 'medium' || $slot === 'profile') {
            update_user_meta($user_id, 'casting_photo_id', (int) $attached['attachment_id']);
        }
        if ($old > 0 && $old !== (int) $attached['attachment_id']) {
            wp_delete_attachment($old, true);
        }
    }

    if (!empty($_FILES['video']['name'])) {
        $video = casting_handle_video_upload($user_id);
        if (!$video['ok']) {
            return ['ok' => false, 'error' => $video['error']];
        }
    } elseif (!empty($pending['video'])) {
        $attached = casting_register_pending_attach_to_user($pending['video'], $user_id);
        if (!$attached['ok']) {
            return $attached;
        }
        $old = (int) get_user_meta($user_id, 'casting_video_id', true);
        update_user_meta($user_id, 'casting_video_id', (int) $attached['attachment_id']);
        if ($old > 0 && $old !== (int) $attached['attachment_id']) {
            wp_delete_attachment($old, true);
        }
    }

    $portraits = casting_load_all_portraits($user_id);
    if ($require_all && !casting_portraits_complete($portraits)) {
        return ['ok' => false, 'error' => 'هر سه عکس (کلوزاپ، مدیوم، لانگ) الزامی است.'];
    }
    if ($require_one && empty(casting_primary_portrait($portraits)['id'])) {
        return ['ok' => false, 'error' => 'عکس پروفایل الزامی است.'];
    }

    casting_register_pending_clear();

    return ['ok' => true, 'error' => ''];
}

/**
 * پوشه آپلود اختصاصی هر کاربر: uploads/casting/{username}/
 */
function casting_user_upload_subdir(int $user_id): string
{
    $user = get_user_by('id', $user_id);
    $username = $user ? sanitize_file_name((string) $user->user_login) : '';
    if ($username === '') {
        $username = 'user-' . $user_id;
    }
    return '/casting/' . $username;
}

function casting_enable_user_upload_dir(int $user_id): void
{
    $subdir = casting_user_upload_subdir($user_id);
    $GLOBALS['casting_upload_subdir'] = $subdir;

    add_filter('upload_dir', 'casting_filter_upload_dir');
}

function casting_disable_user_upload_dir(): void
{
    remove_filter('upload_dir', 'casting_filter_upload_dir');
    unset($GLOBALS['casting_upload_subdir']);
}

/**
 * @param array<string, mixed> $uploads
 * @return array<string, mixed>
 */
function casting_filter_upload_dir(array $uploads): array
{
    $subdir = (string) ($GLOBALS['casting_upload_subdir'] ?? '');
    if ($subdir === '') {
        return $uploads;
    }

    $uploads['subdir'] = $subdir;
    $uploads['path'] = $uploads['basedir'] . $subdir;
    $uploads['url'] = $uploads['baseurl'] . $subdir;

    if (!empty($uploads['error'])) {
        return $uploads;
    }

    if (!is_dir($uploads['path'])) {
        wp_mkdir_p($uploads['path']);
    }

    return $uploads;
}

function casting_handle_portrait_upload(int $user_id, string $slot): array
{
    $meta_key = casting_portrait_meta_key($slot);
    if ($meta_key === '') {
        return ['ok' => false, 'error' => 'نوع عکس نامعتبر است.'];
    }

    $field = 'photo_' . $slot;
    if (empty($_FILES[$field]['name'])) {
        return ['ok' => true, 'skipped' => true];
    }

    $file = &$_FILES[$field];
    $norm = casting_normalize_uploaded_file_type($file, 'image');
    if (!$norm['ok']) {
        return ['ok' => false, 'error' => $norm['error']];
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $ftype = (string) ($norm['type'] ?? '');
    if (!in_array($ftype, $allowed, true)) {
        return ['ok' => false, 'error' => 'فقط عکس JPG، PNG یا WebP مجاز است.'];
    }
    $size_check = casting_uploaded_file_within_limit($file, 'image');
    if (!$size_check['ok']) {
        return ['ok' => false, 'error' => $size_check['error']];
    }

    $attachment_id = casting_media_handle_upload_as_user($field, $user_id);

    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'آپلود عکس ناموفق بود: ' . $attachment_id->get_error_message()];
    }

    $old = (int) get_user_meta($user_id, $meta_key, true);
    update_user_meta($user_id, $meta_key, (int) $attachment_id);
    if ($slot === 'medium' || $slot === 'profile') {
        update_user_meta($user_id, 'casting_photo_id', (int) $attachment_id);
    }
    if ($old > 0 && $old !== (int) $attachment_id) {
        wp_delete_attachment($old, true);
    }

    return ['ok' => true, 'attachment_id' => (int) $attachment_id];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_handle_portrait_uploads(int $user_id, bool $require_all = false, bool $require_one = false): array
{
    if (!casting_user_can_upload_portraits($user_id)) {
        foreach (array_keys(casting_all_portrait_slots()) as $slot) {
            if (!empty($_FILES['photo_' . $slot]['name'])) {
                return ['ok' => false, 'error' => 'بارگذاری عکس پروفایل برای این حساب مجاز نیست.'];
            }
        }
        if ($require_all || $require_one) {
            return ['ok' => false, 'error' => 'بارگذاری عکس پروفایل برای این حساب مجاز نیست.'];
        }

        return ['ok' => true, 'error' => ''];
    }

    $labels = casting_all_portrait_slots();
    $required_slots = casting_portrait_slots();

    if ($require_all) {
        foreach ($required_slots as $slot => $label) {
            $field = 'photo_' . $slot;
            if (empty($_FILES[$field]['name'])) {
                $existing = casting_load_portrait($user_id, $slot);
                if ($existing['id'] <= 0) {
                    return ['ok' => false, 'error' => 'عکس «' . $label . '» را آپلود کنید.'];
                }
            }
        }
    }

    foreach ($labels as $slot => $label) {
        unset($label);
        $result = casting_handle_portrait_upload($user_id, $slot);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error']];
        }
    }

    $portraits = casting_load_all_portraits($user_id);
    if ($require_all && !casting_portraits_complete($portraits)) {
        return ['ok' => false, 'error' => 'هر سه عکس (کلوزاپ، مدیوم، لانگ) الزامی است.'];
    }
    if ($require_one && empty(casting_primary_portrait($portraits)['id'])) {
        return ['ok' => false, 'error' => 'حداقل یک عکس پروفایل آپلود کنید.'];
    }

    return ['ok' => true, 'error' => ''];
}

function casting_handle_photo_upload(int $user_id): array
{
    return casting_handle_portrait_upload($user_id, 'medium');
}

function casting_handle_video_upload(int $user_id): array
{
    if (empty($_FILES['video']['name'])) {
        return ['ok' => true, 'skipped' => true];
    }

    $file = &$_FILES['video'];
    $norm = casting_normalize_uploaded_file_type($file, 'video');
    if (!$norm['ok']) {
        return ['ok' => false, 'error' => $norm['error']];
    }

    $allowed = ['video/mp4', 'video/webm', 'video/quicktime'];
    $ftype = (string) ($norm['type'] ?? '');
    $name = strtolower((string) ($file['name'] ?? ''));
    $ext_ok = preg_match('/\.(mp4|webm|mov)$/', $name) === 1;

    if (!in_array($ftype, $allowed, true) && !$ext_ok) {
        return ['ok' => false, 'error' => 'فقط ویدیو MP4، WebM یا MOV مجاز است.'];
    }
    $size_check = casting_uploaded_file_within_limit($file, 'video');
    if (!$size_check['ok']) {
        return ['ok' => false, 'error' => $size_check['error']];
    }

    $attachment_id = casting_media_handle_upload_as_user('video', $user_id);

    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'آپلود ویدیو ناموفق بود: ' . $attachment_id->get_error_message()];
    }

    $old = (int) get_user_meta($user_id, 'casting_video_id', true);
    update_user_meta($user_id, 'casting_video_id', (int) $attachment_id);
    if ($old > 0 && $old !== (int) $attachment_id) {
        wp_delete_attachment($old, true);
    }

    return ['ok' => true, 'attachment_id' => (int) $attachment_id];
}

/**
 * @return array{users: WP_User[], total: int}
 */
function casting_query_talents(array $filters = [], int $page = 1, int $per_page = 12): array
{
    $meta_query = [
        'relation' => 'AND',
        [
            'key'   => 'casting_role',
            'value' => 'talent',
        ],
        [
            'relation' => 'OR',
            [
                'key'     => 'casting_visible',
                'value'   => '1',
                'compare' => '=',
            ],
            [
                'key'     => 'casting_visible',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ];

    if (!empty($filters['gender']) && array_key_exists($filters['gender'], casting_gender_labels())) {
        $meta_query[] = [
            'key'   => 'casting_gender',
            'value' => sanitize_key($filters['gender']),
        ];
    }

    $city_filter = casting_city_search_filter_value((string) ($filters['city'] ?? ''));
    if ($city_filter !== '') {
        $meta_query[] = [
            'key'     => 'casting_city',
            'value'   => $city_filter,
            'compare' => 'LIKE',
        ];
    }

    $age_min = $filters['age_min'] ?? '';
    $age_max = $filters['age_max'] ?? '';
    $age_range = isset($filters['age_range']) ? (string) $filters['age_range'] : '';
    if ($age_range !== '' && array_key_exists($age_range, casting_age_range_options())) {
        $range = casting_age_range_options()[$age_range];
        $age_min = $range['min'] !== null ? (string) $range['min'] : '';
        $age_max = $range['max'] !== null ? (string) $range['max'] : '';
    }

    if ($age_min !== '' && $age_min !== null) {
        $meta_query[] = [
            'key'     => 'casting_age',
            'value'   => (int) $age_min,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }

    if ($age_max !== '' && $age_max !== null) {
        $meta_query[] = [
            'key'     => 'casting_age',
            'value'   => (int) $age_max,
            'type'    => 'NUMERIC',
            'compare' => '<=',
        ];
    }

    if (!empty($filters['look']) && array_key_exists($filters['look'], casting_look_labels())) {
        $meta_query[] = [
            'key'   => 'casting_look',
            'value' => sanitize_key($filters['look']),
        ];
    } elseif (!empty($filters['look'])) {
        $meta_query[] = [
            'key'     => 'casting_look',
            'value'   => sanitize_text_field($filters['look']),
            'compare' => 'LIKE',
        ];
    }

    $page = max(1, $page);
    $per_page = max(1, $per_page);
    $args = [
        'number'      => $per_page,
        'offset'      => ($page - 1) * $per_page,
        'orderby'     => 'registered',
        'order'       => 'DESC',
        'meta_query'  => $meta_query,
        'count_total' => true,
    ];

    if (!empty($filters['q'])) {
        $args['search'] = '*' . esc_attr(sanitize_text_field($filters['q'])) . '*';
        $args['search_columns'] = ['display_name', 'user_email'];
    }
    if (function_exists('casting_user_query_exclude_hidden_profiles')) {
        $args = casting_user_query_exclude_hidden_profiles($args);
    }

    $query = new WP_User_Query($args);
    $users = $query->get_results();
    if (!is_array($users)) {
        $users = [];
    }

    usort($users, static function (WP_User $a, WP_User $b): int {
        if (!function_exists('casting_user_is_premium')) {
            return 0;
        }
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

function casting_profile_complete(array $profile): bool
{
    if (($profile['age'] ?? '') === '' || ($profile['gender'] ?? '') === '') {
        return false;
    }
    if (casting_profile_hides_talent_fields($profile['activities'] ?? [])) {
        return !empty(casting_primary_portrait($profile['portraits'] ?? [])['id']);
    }

    return casting_portraits_complete($profile['portraits'] ?? []);
}

/**
 * کلید تخصص → دستهٔ تخصص هنری
 *
 * @return array<string, string>
 */
function casting_activity_specialty_category_map(): array
{
    if (!function_exists('casting_activity_categories')) {
        $file = __DIR__ . '/activities.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
    $map = [];
    if (!function_exists('casting_activity_categories')) {
        return $map;
    }
    foreach (casting_activity_categories() as $cat_key => $cat) {
        if (!is_array($cat)) {
            continue;
        }
        foreach (array_keys($cat['items'] ?? []) as $specialty) {
            $map[(string) $specialty] = (string) $cat_key;
        }
    }

    return $map;
}

/**
 * آمار صفحهٔ اصلی بر اساس تخصص هنری (زنده با ثبت‌نام)
 *
 * @return array{
 *   total:int,
 *   talents:int,
 *   employers:int,
 *   tiles:list<array{key:string,label:string,count:int}>
 * }
 */
function casting_member_counts(): array
{
    if (!function_exists('casting_normalize_activities')) {
        $file = __DIR__ . '/activities.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
    if (!function_exists('casting_user_is_suspended')) {
        $file = __DIR__ . '/admin-access.php';
        if (is_file($file)) {
            require_once $file;
        }
    }

    $query = new WP_User_Query([
        'number'     => 5000,
        'fields'     => 'ID',
        'meta_query' => [
            [
                'key'     => 'casting_role',
                'value'   => ['talent', 'director', 'producer'],
                'compare' => 'IN',
            ],
        ],
    ]);
    $ids = $query->get_results();
    if (!is_array($ids)) {
        $ids = [];
    }

    $categories = function_exists('casting_activity_categories') ? casting_activity_categories() : [];
    $specialty_map = casting_activity_specialty_category_map();

    // برچسب‌های دوستانه برای کشف استعداد در صفحهٔ اول
    $tile_labels = [
        'acting'     => 'بازیگری',
        'directing'  => 'کارگردانی',
        'production' => 'تهیه و تولید',
        'writing'    => 'نویسندگی',
        'camera'     => 'فیلمبرداری',
        'sound'      => 'صدا',
        'post'       => 'تدوین',
        'art'        => 'طراحی هنری',
        'lighting'   => 'نور',
        'music'      => 'موسیقی',
        'promo'      => 'رسانه',
        'set_crew'   => 'عوامل صحنه',
        'other'      => 'سایر تخصص‌ها',
        'discovery'  => 'کشف استعداد',
    ];

    $by_cat = [];
    foreach (array_keys($tile_labels) as $key) {
        $by_cat[$key] = 0;
    }

    $total = 0;
    $talents = 0;
    $employers = 0;

    foreach ($ids as $raw_id) {
        $user_id = (int) $raw_id;
        if ($user_id <= 0) {
            continue;
        }
        if (function_exists('casting_user_is_suspended') && casting_user_is_suspended($user_id)) {
            continue;
        }

        $role = casting_get_user_role($user_id);
        if ($role === '') {
            continue;
        }

        $activities = function_exists('casting_normalize_activities')
            ? casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true), $user_id)
            : [];
        $activities = array_values(array_filter(array_map('strval', is_array($activities) ? $activities : [])));

        // حساب فنی/IT عمومی در آمار صفحهٔ اول نیاید
        $public_activities = array_values(array_filter(
            $activities,
            static fn(string $key): bool => $key !== 'it'
        ));
        if ($public_activities === [] && in_array('it', $activities, true)) {
            continue;
        }

        $total++;
        if ($role === 'talent') {
            $talents++;
        } elseif (in_array($role, ['director', 'producer'], true)) {
            $employers++;
        }

        $matched_cats = [];
        $is_discovery = $public_activities === []
            || $public_activities === ['activity_none']
            || (function_exists('casting_activities_has_none') && casting_activities_has_none($public_activities) && count($public_activities) === 1);

        if ($is_discovery) {
            $matched_cats['discovery'] = true;
        } else {
            foreach ($public_activities as $specialty) {
                if ($specialty === 'activity_none') {
                    continue;
                }
                $cat = (string) ($specialty_map[$specialty] ?? '');
                if ($cat === '' || $cat === 'none' || !isset($by_cat[$cat])) {
                    continue;
                }
                $matched_cats[$cat] = true;
            }
            if ($matched_cats === []) {
                $matched_cats['discovery'] = true;
            }
        }

        foreach (array_keys($matched_cats) as $cat_key) {
            $by_cat[$cat_key] = (int) ($by_cat[$cat_key] ?? 0) + 1;
        }
    }

    // ترتیب نمایش: دسته‌های اصلی اول، بعد بقیهٔ دارای عضو، بعد کشف استعداد
    $preferred = ['acting', 'directing', 'production', 'writing', 'camera', 'sound', 'post', 'art', 'music'];
    $tiles = [];
    $seen = [];
    foreach ($preferred as $key) {
        $count = (int) ($by_cat[$key] ?? 0);
        if ($count <= 0) {
            continue;
        }
        $tiles[] = [
            'key'   => $key,
            'label' => (string) ($tile_labels[$key] ?? ($categories[$key]['label'] ?? $key)),
            'count' => $count,
        ];
        $seen[$key] = true;
    }
    foreach ($by_cat as $key => $count) {
        if ($key === 'discovery' || isset($seen[$key]) || (int) $count <= 0) {
            continue;
        }
        $tiles[] = [
            'key'   => (string) $key,
            'label' => (string) ($tile_labels[$key] ?? ($categories[$key]['label'] ?? $key)),
            'count' => (int) $count,
        ];
    }
    $tiles[] = [
        'key'   => 'discovery',
        'label' => (string) $tile_labels['discovery'],
        'count' => (int) ($by_cat['discovery'] ?? 0),
    ];
    $tiles[] = [
        'key'   => 'total',
        'label' => 'اعضای پورتال',
        'count' => $total,
    ];

    return [
        'talents'   => $talents,
        'employers' => $employers,
        'total'     => $total,
        'tiles'     => $tiles,
    ];
}

/**
 * کاشی‌های آمار تخصص (صفحهٔ عمومی و پنل مدیران اصلی)
 *
 * @param array{tiles?:list<array{key?:string,label?:string,count?:int}>}|null $counts
 */
function casting_render_member_count_tiles(?array $counts = null, string $extra_class = ''): void
{
    if ($counts === null) {
        $counts = casting_member_counts();
    }
    $tiles = $counts['tiles'] ?? [];
    if (!is_array($tiles) || $tiles === []) {
        return;
    }
    $class = 'home-stats';
    if ($extra_class !== '') {
        $class .= ' ' . $extra_class;
    }
    ?>
    <div class="<?= casting_e($class) ?>" aria-label="آمار تخصص‌های هنری">
      <?php foreach ($tiles as $tile) : ?>
        <?php
        $key = (string) ($tile['key'] ?? '');
        $mod = '';
        if ($key === 'total') {
            $mod = ' stat-item--total';
        } elseif ($key === 'discovery') {
            $mod = ' stat-item--discovery';
        }
        ?>
        <div class="stat-item<?= $mod ?>">
          <strong><?= (int) ($tile['count'] ?? 0) ?></strong>
          <span><?= casting_e((string) ($tile['label'] ?? '')) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
}

function casting_touch_last_active(int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    $prev = (string) get_user_meta($user_id, 'casting_last_active', true);
    $now = time();
    if ($prev !== '' && ($now - (int) strtotime($prev)) < 300) {
        return;
    }
    update_user_meta($user_id, 'casting_last_active', current_time('mysql'));
}

/** آنلاین اگر در ۱۵ دقیقه اخیر فعالیت داشته باشد. */
function casting_member_is_online(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    $last = (string) get_user_meta($user_id, 'casting_last_active', true);
    if ($last === '') {
        return false;
    }
    $ts = strtotime($last);

    return $ts !== false && (time() - $ts) <= 15 * MINUTE_IN_SECONDS;
}

/**
 * چراغ وضعیت آنلاین/آفلاین روی عکس پروفایل (مثل اینستاگرام).
 *
 * @param 'sm'|'md'|'lg' $size
 */
function casting_render_presence_dot(int $user_id, string $size = 'md'): void
{
    if ($user_id <= 0) {
        return;
    }
    $online = casting_member_is_online($user_id);
    $label = $online ? 'آنلاین' : 'آفلاین';
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $mod = $online ? 'presence-dot--online' : 'presence-dot--offline';
    ?>
    <span class="presence-dot presence-dot--<?= casting_e($size) ?> <?= casting_e($mod) ?>" title="<?= casting_e($label) ?>" aria-label="<?= casting_e($label) ?>"></span>
    <?php
}

function casting_require_casting_user(): WP_User
{
    $user = casting_current_user();
    if (!$user) {
        if (empty($_SESSION['casting_flash'])) {
            casting_set_flash('error', 'برای گفتگو ابتدا وارد شوید.');
        }
        casting_redirect('login.php');
    }
    $role = casting_get_user_role((int) $user->ID);
    if ($role === '' && !(function_exists('casting_user_can_use_member_portal') && casting_user_can_use_member_portal((int) $user->ID))) {
        casting_set_flash('error', 'فقط اعضای ۷ رخ می‌توانند گفتگو کنند.');
        casting_redirect('index.php');
    }
    if (!function_exists('casting_user_is_suspended')) {
        require_once __DIR__ . '/admin-access.php';
    }
    if (casting_user_is_suspended((int) $user->ID)) {
        casting_set_flash('error', 'حساب شما معلق شده است. برای پیگیری با پشتیبانی تماس بگیرید.');
        casting_redirect('logout.php');
    }
    casting_touch_last_active((int) $user->ID);
    if (!function_exists('casting_follow_default_admins')) {
        require_once __DIR__ . '/follows.php';
    }
    // همگام‌سازی فالو اجباری مدیران + فالو خود کاربر
    if (function_exists('casting_follows_bootstrap_maintenance')) {
        casting_follows_bootstrap_maintenance();
    }
    casting_follow_default_admins((int) $user->ID);

    if (!function_exists('casting_get_referral_code')) {
        require_once __DIR__ . '/referral.php';
    }
    // فقط کد خود کاربر — بک‌فیل دسته‌جمعی سنگین را اینجا اجرا نکن
    casting_get_referral_code((int) $user->ID);

    return $user;
}
