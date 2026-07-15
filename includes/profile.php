<?php
declare(strict_types=1);

require_once __DIR__ . '/jalali.php';
require_once __DIR__ . '/activities.php';
require_once __DIR__ . '/locations.php';

function casting_gender_labels(): array
{
    return [
        'female' => 'زن',
        'male'   => 'مرد',
        'other'  => 'سایر',
    ];
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
 * @return array{has:string,orgs:list<string>,other_items:list<string>}
 */
function casting_parse_artistic_membership_post(array $post): array
{
    $has = sanitize_key((string) ($post['artistic_membership'] ?? ''));
    $orgs_raw = $post['artistic_orgs'] ?? [];
    if (!is_array($orgs_raw)) {
        $orgs_raw = [];
    }
    $labels = casting_artistic_org_labels();
    $orgs = [];
    foreach ($orgs_raw as $key) {
        $key = sanitize_key((string) $key);
        if (isset($labels[$key])) {
            $orgs[] = $key;
        }
    }

    $other_items = [];
    $raw_other = $post['artistic_other_items'] ?? [];
    if (is_array($raw_other)) {
        foreach ($raw_other as $item) {
            $item = sanitize_text_field(trim((string) $item));
            if ($item !== '') {
                $other_items[] = $item;
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
    $yes_no = casting_yes_no_labels();
    if (!isset($yes_no[$data['has'] ?? ''])) {
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
    if ($other_items === [] && in_array('other', $orgs, true)) {
        $other_items = [''];
    }
    $show_orgs = $has === 'yes';
    $show_other = $show_orgs && in_array('other', $orgs, true);
    ?>
  <fieldset class="field" data-artistic-membership>
    <legend>سابقه عضویت در تشکل‌های هنری</legend>
    <div class="role-grid role-grid-2">
      <?php foreach (casting_yes_no_labels() as $key => $label) : ?>
        <label class="role-option">
          <input type="radio" name="artistic_membership" value="<?= casting_e($key) ?>" <?= $has === $key ? 'checked' : '' ?> required data-artistic-has>
          <span><?= casting_e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="artistic-orgs-panel" data-artistic-orgs-panel <?= $show_orgs ? '' : 'hidden' ?>>
      <p class="field-hint">تشکل‌هایی که عضو بوده‌اید را انتخاب کنید:</p>
      <div class="artistic-org-checks">
        <?php foreach ($labels as $key => $label) : ?>
          <label class="check-inline">
            <input type="checkbox" name="artistic_orgs[]" value="<?= casting_e($key) ?>" <?= in_array($key, $orgs, true) ? 'checked' : '' ?> <?= $key === 'other' ? 'data-artistic-other-toggle' : '' ?>>
            <span><?= casting_e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="field artistic-other-panel" data-artistic-other-panel <?= $show_other ? '' : 'hidden' ?>>
        <span class="jalali-label">نام تشکل‌های دیگر</span>
        <p class="field-hint">برای «سایر» نام تشکل را بنویسید. با + مورد بعدی را اضافه کنید.</p>
        <div class="work-credits-list" data-artistic-other-list>
          <?php foreach ($other_items as $i => $item) : ?>
            <div class="work-credit-row artistic-other-row">
              <input type="text" name="artistic_other_items[<?= (int) $i ?>]" value="<?= casting_e($item) ?>" placeholder="نام تشکل…" aria-label="تشکل دیگر">
              <button type="button" class="btn-icon" data-remove-artistic-other aria-label="حذف">−</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-add-credit" data-add-artistic-other>+ افزودن مورد دیگر</button>
        <template data-artistic-other-template>
          <div class="work-credit-row artistic-other-row">
            <input type="text" name="artistic_other_items[__i__]" value="" placeholder="نام تشکل…" aria-label="تشکل دیگر">
            <button type="button" class="btn-icon" data-remove-artistic-other aria-label="حذف">−</button>
          </div>
        </template>
      </div>
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
    $need = [
        'actor_cinema', 'actor_theater', 'actor_tv', 'actor_youth',
        'extra', 'stunt', 'host',
    ];
    return count(array_intersect(casting_normalize_activities($activities), $need)) > 0;
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
    if (array_intersect($activities, ['producer', 'executive', 'production_manager', 'logistics_manager']) !== []) {
        return 'producer';
    }
    if (array_intersect($activities, ['director', 'first_ad', 'scheduler', 'script_supervisor']) !== []) {
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

function casting_render_work_credits_fields(array $credits = []): void
{
    if (!$credits) {
        $credits = [['type' => 'film', 'title' => '']];
    }
    $types = casting_work_type_labels();
    ?>
  <div class="field work-credits" data-work-credits>
    <span class="jalali-label">فیلم‌ها و تئاترهایی که بازی کرده‌اید</span>
    <p class="field-hint">برای هر اثر یک ردیف بنویسید؛ با + ردیف جدید اضافه کنید.</p>
    <div class="work-credits-list" data-work-credits-list>
      <?php foreach ($credits as $i => $credit) : ?>
        <div class="work-credit-row">
          <select name="work_credits[<?= (int) $i ?>][type]" aria-label="نوع اثر">
            <?php foreach ($types as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= ($credit['type'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="work_credits[<?= (int) $i ?>][title]" value="<?= casting_e((string) ($credit['title'] ?? '')) ?>" placeholder="نام فیلم یا تئاتر">
          <button type="button" class="btn-icon btn-remove-credit" data-remove-credit aria-label="حذف">−</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-ghost btn-add-credit" data-add-credit>+ افزودن اثر بعدی</button>
    <template data-work-credit-template>
      <div class="work-credit-row">
        <select name="work_credits[__i__][type]" aria-label="نوع اثر">
          <?php foreach ($types as $key => $label) : ?>
            <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="work_credits[__i__][title]" value="" placeholder="نام فیلم یا تئاتر">
        <button type="button" class="btn-icon btn-remove-credit" data-remove-credit aria-label="حذف">−</button>
      </div>
    </template>
  </div>
    <?php
}

function casting_education_degree_labels(): array
{
    return [
        'associate' => 'فوق‌دیپلم',
        'bachelor'  => 'لیسانس',
        'master'    => 'فوق‌لیسانس',
        'doctorate' => 'دکترا',
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

function casting_get_profile(int $user_id): array
{
    $photo_id = (int) get_user_meta($user_id, 'casting_photo_id', true);
    $video_id = (int) get_user_meta($user_id, 'casting_video_id', true);
    $photo_url = $photo_id > 0 ? wp_get_attachment_image_url($photo_id, 'medium') : '';
    $photo_full = $photo_id > 0 ? wp_get_attachment_image_url($photo_id, 'large') : '';
    $video_url_file = $video_id > 0 ? wp_get_attachment_url($video_id) : '';
    $video_url_meta = (string) get_user_meta($user_id, 'casting_video_url', true);
    $look_meta = (string) get_user_meta($user_id, 'casting_look', true);
    if ($look_meta === 'gandoum') {
        $look_meta = 'olive';
    }

    return [
        'birthdate'         => (string) get_user_meta($user_id, 'casting_birthdate', true),
        'age'               => (string) get_user_meta($user_id, 'casting_age', true),
        'gender'            => (string) get_user_meta($user_id, 'casting_gender', true),
        'mobile'            => (string) get_user_meta($user_id, 'casting_mobile', true),
        'phone'             => (string) get_user_meta($user_id, 'casting_phone', true),
        'province'          => (string) get_user_meta($user_id, 'casting_province', true),
        'city'              => (string) get_user_meta($user_id, 'casting_city', true),
        'residence'         => (string) get_user_meta($user_id, 'casting_residence', true),
        'height'            => (string) get_user_meta($user_id, 'casting_height', true),
        'weight'            => (string) get_user_meta($user_id, 'casting_weight', true),
        'health_status'     => (string) get_user_meta($user_id, 'casting_health_status', true),
        'experience'        => (string) get_user_meta($user_id, 'casting_experience', true),
        'artistic_membership' => casting_load_artistic_membership($user_id),
        'activity_license'  => (string) get_user_meta($user_id, 'casting_activity_license', true),
        'work_history'      => (string) get_user_meta($user_id, 'casting_work_history', true),
        'work_credits'      => casting_normalize_work_credits(get_user_meta($user_id, 'casting_work_credits', true)),
        'education'         => (string) get_user_meta($user_id, 'casting_education', true),
        'education_items'   => casting_normalize_education_items(get_user_meta($user_id, 'casting_education_items', true)),
        'activities'        => casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true)),
        'look'              => $look_meta,
        'skills'            => (string) get_user_meta($user_id, 'casting_skills', true),
        'skill_items'       => casting_normalize_skill_items(get_user_meta($user_id, 'casting_skill_items', true)),
        'skills_other'      => (string) get_user_meta($user_id, 'casting_skills_other', true),
        'language_items'    => casting_normalize_language_items(get_user_meta($user_id, 'casting_language_items', true)),
        'availability'      => (string) get_user_meta($user_id, 'casting_availability', true),
        'bio'               => (string) get_user_meta($user_id, 'casting_bio', true),
        'video_url'         => $video_url_meta,
        'photo_id'          => $photo_id,
        'video_id'          => $video_id,
        'photo_url'         => is_string($photo_url) ? $photo_url : '',
        'photo_full'        => is_string($photo_full) ? $photo_full : '',
        'video_file_url'    => is_string($video_url_file) ? $video_url_file : '',
        'visible'           => get_user_meta($user_id, 'casting_visible', true) !== '0',
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

function casting_normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function casting_save_registration_profile(int $user_id, array $data): array
{
    $birthdate = sanitize_text_field((string) ($data['birthdate'] ?? ''));
    $age = casting_age_from_birthdate($birthdate);
    if ($age === null) {
        return ['ok' => false, 'error' => 'تاریخ تولد معتبر نیست.'];
    }
    if ($age < 5 || $age > 100) {
        return ['ok' => false, 'error' => 'سن محاسبه‌شده باید بین ۵ تا ۱۰۰ باشد.'];
    }

    $gender = sanitize_key((string) ($data['gender'] ?? ''));
    if (!array_key_exists($gender, casting_gender_labels())) {
        return ['ok' => false, 'error' => 'جنسیت را انتخاب کنید.'];
    }

    $look = sanitize_key((string) ($data['look'] ?? ''));
    if (!array_key_exists($look, casting_look_labels())) {
        return ['ok' => false, 'error' => 'رنگ پوست را انتخاب کنید.'];
    }

    $mobile = casting_normalize_mobile((string) ($data['mobile'] ?? ''));
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['ok' => false, 'error' => 'شماره موبایل را درست وارد کنید (مثلاً ۰۹۱۲۱۲۳۴۵۶۷).'];
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
    $province_cities = casting_cities_for_province($province);
    if ($city === '' || !in_array($city, $province_cities, true)) {
        return ['ok' => false, 'error' => 'شهر را از فهرست همان استان انتخاب کنید.'];
    }

    $residence = '';

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
    $education = sanitize_textarea_field((string) ($data['education'] ?? ''));
    $credits = casting_normalize_work_credits($data['work_credits'] ?? []);
    $edu_items = casting_normalize_education_items($data['education_items'] ?? []);
    $activities = casting_normalize_activities($data['activities'] ?? []);
    if ($activities === []) {
        return ['ok' => false, 'error' => 'حداقل یک تخصص از بخش نوع فعالیت انتخاب کنید.'];
    }

    $height_raw = trim((string) ($data['height'] ?? ''));
    $weight_raw = trim((string) ($data['weight'] ?? ''));
    $need_body = casting_activities_need_body_metrics($activities);
    if ($need_body && ($height_raw === '' || $weight_raw === '')) {
        return ['ok' => false, 'error' => 'برای بازیگران و مدل‌ها قد و وزن الزامی است.'];
    }
    $height = 0;
    $weight = 0;
    if ($height_raw !== '') {
        $height = (int) $height_raw;
        if ($height < 80 || $height > 230) {
            return ['ok' => false, 'error' => 'قد باید بین ۸۰ تا ۲۳۰ سانتی‌متر باشد.'];
        }
    }
    if ($weight_raw !== '') {
        $weight = (int) $weight_raw;
        if ($weight < 20 || $weight > 250) {
            return ['ok' => false, 'error' => 'وزن باید بین ۲۰ تا ۲۵۰ کیلوگرم باشد.'];
        }
    }

    $health_status = sanitize_textarea_field((string) ($data['health_status'] ?? ''));
    if (casting_strlen($health_status) > 500) {
        return ['ok' => false, 'error' => 'وضعیت سلامت حداکثر ۵۰۰ کاراکتر باشد.'];
    }

    update_user_meta($user_id, 'casting_birthdate', $birthdate);
    update_user_meta($user_id, 'casting_age', (string) $age);
    update_user_meta($user_id, 'casting_gender', $gender);
    update_user_meta($user_id, 'casting_look', $look);
    update_user_meta($user_id, 'casting_mobile', $mobile);
    update_user_meta($user_id, 'casting_phone', $phone);
    update_user_meta($user_id, 'casting_province', $province);
    update_user_meta($user_id, 'casting_city', $city);
    casting_remember_city($city);
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
    update_user_meta($user_id, 'casting_health_status', $health_status);
    update_user_meta($user_id, 'casting_work_history', $work);
    update_user_meta($user_id, 'casting_work_credits', $credits);
    update_user_meta($user_id, 'casting_education', $education);
    update_user_meta($user_id, 'casting_education_items', $edu_items);
    update_user_meta($user_id, 'casting_activities', $activities);

    $skill_items = casting_normalize_skill_items($data['skill_items'] ?? []);
    foreach ($skill_items as $row) {
        if ($row['skill'] === 'other' && $row['note'] === '') {
            return ['ok' => false, 'error' => 'برای مهارت «سایر» بنویسید چه هنری دارید.'];
        }
    }
    $language_items = casting_normalize_language_items($data['language_items'] ?? []);
    $availability = sanitize_key((string) ($data['availability'] ?? ''));
    if (!array_key_exists($availability, casting_availability_labels())) {
        return ['ok' => false, 'error' => 'وضعیت آمادگی برای همکاری را انتخاب کنید.'];
    }

    update_user_meta($user_id, 'casting_skill_items', $skill_items);
    update_user_meta($user_id, 'casting_skills_other', '');
    update_user_meta($user_id, 'casting_skills', casting_format_skill_labels($skill_items));
    update_user_meta($user_id, 'casting_language_items', $language_items);
    update_user_meta($user_id, 'casting_availability', $availability);
    update_user_meta($user_id, 'casting_visible', '1');

    return ['ok' => true, 'age' => $age];
}

function casting_save_profile(int $user_id, array $data): array
{
    $birthdate = sanitize_text_field((string) ($data['birthdate'] ?? ''));
    if ($birthdate !== '') {
        $age = casting_age_from_birthdate($birthdate);
        if ($age === null || $age < 5 || $age > 100) {
            return ['ok' => false, 'error' => 'تاریخ تولد معتبر نیست.'];
        }
        update_user_meta($user_id, 'casting_birthdate', $birthdate);
        update_user_meta($user_id, 'casting_age', (string) $age);
    } else {
        $age = isset($data['age']) ? (int) $data['age'] : 0;
        if ($age < 5 || $age > 100) {
            return ['ok' => false, 'error' => 'سن باید بین ۵ تا ۱۰۰ باشد.'];
        }
        update_user_meta($user_id, 'casting_age', (string) $age);
    }

    $gender = sanitize_key((string) ($data['gender'] ?? ''));
    if ($gender !== '' && !array_key_exists($gender, casting_gender_labels())) {
        return ['ok' => false, 'error' => 'جنسیت را انتخاب کنید.'];
    }
    if ($gender !== '') {
        update_user_meta($user_id, 'casting_gender', $gender);
    }

    if (array_key_exists('mobile', $data)) {
        $mobile = casting_normalize_mobile((string) $data['mobile']);
        if ($mobile !== '' && !preg_match('/^09\d{9}$/', $mobile)) {
            return ['ok' => false, 'error' => 'شماره موبایل را درست وارد کنید.'];
        }
        if ($mobile !== '') {
            update_user_meta($user_id, 'casting_mobile', $mobile);
        }
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
        $allowed = casting_cities_for_province($check_province);
        if ($allowed !== [] && !in_array($city, $allowed, true)) {
            return ['ok' => false, 'error' => 'شهر را از فهرست همان استان انتخاب کنید.'];
        }
        update_user_meta($user_id, 'casting_city', $city);
        casting_remember_city($city);
    }


    if (isset($data['height']) && $data['height'] !== '') {
        $height = (int) $data['height'];
        if ($height < 80 || $height > 230) {
            return ['ok' => false, 'error' => 'قد باید بین ۸۰ تا ۲۳۰ سانتی‌متر باشد.'];
        }
        update_user_meta($user_id, 'casting_height', (string) $height);
    }

    if (isset($data['weight']) && $data['weight'] !== '') {
        $weight = (int) $data['weight'];
        if ($weight < 20 || $weight > 250) {
            return ['ok' => false, 'error' => 'وزن باید بین ۲۰ تا ۲۵۰ کیلوگرم باشد.'];
        }
        update_user_meta($user_id, 'casting_weight', (string) $weight);
    }

    if (array_key_exists('health_status', $data)) {
        $health_status = sanitize_textarea_field((string) $data['health_status']);
        if (casting_strlen($health_status) > 500) {
            return ['ok' => false, 'error' => 'وضعیت سلامت حداکثر ۵۰۰ کاراکتر باشد.'];
        }
        update_user_meta($user_id, 'casting_health_status', $health_status);
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

    if (array_key_exists('language_items', $data)) {
        update_user_meta($user_id, 'casting_language_items', casting_normalize_language_items($data['language_items']));
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

    update_user_meta($user_id, 'casting_bio', sanitize_textarea_field((string) ($data['bio'] ?? '')));
    update_user_meta($user_id, 'casting_work_history', sanitize_textarea_field((string) ($data['work_history'] ?? '')));
    update_user_meta($user_id, 'casting_work_credits', casting_normalize_work_credits($data['work_credits'] ?? []));
    update_user_meta($user_id, 'casting_education', sanitize_textarea_field((string) ($data['education'] ?? '')));
    update_user_meta($user_id, 'casting_education_items', casting_normalize_education_items($data['education_items'] ?? []));

    if (array_key_exists('activities', $data)) {
        $activities = casting_normalize_activities($data['activities']);
        if ($activities === []) {
            return ['ok' => false, 'error' => 'حداقل یک تخصص از بخش نوع فعالیت انتخاب کنید.'];
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
                return ['ok' => false, 'error' => 'برای بازیگران و مدل‌ها قد و وزن الزامی است.'];
            }
        }
        update_user_meta($user_id, 'casting_activities', $activities);
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

function casting_handle_photo_upload(int $user_id): array
{
    if (empty($_FILES['photo']['name'])) {
        return ['ok' => true, 'skipped' => true];
    }

    casting_require_media_includes();

    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $ftype = (string) ($file['type'] ?? '');
    if (!in_array($ftype, $allowed, true)) {
        return ['ok' => false, 'error' => 'فقط عکس JPG، PNG یا WebP مجاز است.'];
    }
    if ((int) $file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'حجم عکس حداکثر ۵ مگابایت باشد.'];
    }

    casting_enable_user_upload_dir($user_id);
    $attachment_id = media_handle_upload('photo', 0);
    casting_disable_user_upload_dir();

    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'آپلود عکس ناموفق بود: ' . $attachment_id->get_error_message()];
    }

    $old = (int) get_user_meta($user_id, 'casting_photo_id', true);
    update_user_meta($user_id, 'casting_photo_id', (int) $attachment_id);
    if ($old > 0 && $old !== (int) $attachment_id) {
        wp_delete_attachment($old, true);
    }

    return ['ok' => true, 'attachment_id' => (int) $attachment_id];
}

function casting_handle_video_upload(int $user_id): array
{
    if (empty($_FILES['video']['name'])) {
        return ['ok' => true, 'skipped' => true];
    }

    casting_require_media_includes();

    $file = $_FILES['video'];
    $allowed = ['video/mp4', 'video/webm', 'video/quicktime'];
    $ftype = (string) ($file['type'] ?? '');
    $name = strtolower((string) ($file['name'] ?? ''));
    $ext_ok = preg_match('/\.(mp4|webm|mov)$/', $name) === 1;

    if (!in_array($ftype, $allowed, true) && !$ext_ok) {
        return ['ok' => false, 'error' => 'فقط ویدیو MP4، WebM یا MOV مجاز است.'];
    }
    if ((int) $file['size'] > 40 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'حجم ویدیو حداکثر ۴۰ مگابایت باشد.'];
    }

    casting_enable_user_upload_dir($user_id);
    $attachment_id = media_handle_upload('video', 0);
    casting_disable_user_upload_dir();

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

    if (!empty($filters['city'])) {
        $meta_query[] = [
            'key'     => 'casting_city',
            'value'   => sanitize_text_field($filters['city']),
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
    $args = [
        'number'      => $per_page,
        'paged'       => $page,
        'orderby'     => 'registered',
        'order'       => 'DESC',
        'meta_query'  => $meta_query,
        'count_total' => true,
    ];

    if (!empty($filters['q'])) {
        $args['search'] = '*' . esc_attr(sanitize_text_field($filters['q'])) . '*';
        $args['search_columns'] = ['display_name', 'user_email'];
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
    return $profile['age'] !== ''
        && $profile['gender'] !== ''
        && $profile['photo_id'] > 0;
}

/**
 * @return array{talents:int,employers:int,total:int}
 */
function casting_member_counts(): array
{
    $talents = new WP_User_Query([
        'number'      => 1,
        'count_total' => true,
        'fields'      => 'ID',
        'meta_key'    => 'casting_role',
        'meta_value'  => 'talent',
    ]);

    $employers = new WP_User_Query([
        'number'      => 1,
        'count_total' => true,
        'fields'      => 'ID',
        'meta_query'  => [
            [
                'key'     => 'casting_role',
                'value'   => ['director', 'producer'],
                'compare' => 'IN',
            ],
        ],
    ]);

    $talent_n = (int) $talents->get_total();
    $employer_n = (int) $employers->get_total();

    return [
        'talents'   => $talent_n,
        'employers' => $employer_n,
        'total'     => $talent_n + $employer_n,
    ];
}

function casting_require_casting_user(): WP_User
{
    $user = casting_current_user();
    if (!$user) {
        casting_set_flash('error', 'برای گفتگو ابتدا وارد شوید.');
        casting_redirect('login.php');
    }
    $role = casting_get_user_role((int) $user->ID);
    if ($role === '') {
        casting_set_flash('error', 'فقط اعضای هفت رخ می‌توانند گفتگو کنند.');
        casting_redirect('index.php');
    }
    return $user;
}
