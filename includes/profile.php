<?php
declare(strict_types=1);

require_once __DIR__ . '/jalali.php';
require_once __DIR__ . '/activities.php';
require_once __DIR__ . '/membership-number.php';
require_once __DIR__ . '/locations.php';
require_once __DIR__ . '/works-catalog.php';

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
    $rows = casting_artistic_org_form_rows($orgs, $other_items);
    $show_orgs = $has === 'yes';
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
    return casting_activities_has_acting($activities);
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

function casting_search_specialty_empty_label(bool $category_selected): string
{
    return $category_selected ? casting_search_filter_empty_label() : 'اول تخصص هنری را انتخاب کنید';
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
        'closeup' => 'نمای نزدیک صورت',
        'medium'  => 'نیم‌تنه یا تا کمر',
        'long'    => 'تمام‌قد',
    ];
}

function casting_portrait_meta_key(string $slot): string
{
    return array_key_exists($slot, casting_portrait_slots()) ? 'casting_photo_' . $slot . '_id' : '';
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
    foreach (casting_portrait_slots() as $slot => $label) {
        unset($label);
        $out[$slot] = casting_load_portrait($user_id, $slot);
    }
    return $out;
}

function casting_primary_portrait(array $portraits): array
{
    foreach (['medium', 'closeup', 'long'] as $slot) {
        if (!empty($portraits[$slot]['id'])) {
            return $portraits[$slot];
        }
    }
    return ['id' => 0, 'url' => '', 'full' => ''];
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
function casting_render_portrait_upload_fields(array $portraits = [], bool $required = false): void
{
    $req = $required ? ' required' : '';
    $hints = casting_portrait_slot_hints();
    $dims = casting_portrait_display_dimensions();
    ?>
  <div class="portrait-upload-grid">
    <?php foreach (casting_portrait_slots() as $slot => $label) :
        $field = 'photo_' . $slot;
        $preview = $portraits[$slot]['url'] ?? '';
        ?>
      <div class="portrait-upload-card">
        <div class="portrait-frame portrait-preview">
          <?php if ($preview !== '') : ?>
            <img
              src="<?= casting_e($preview) ?>"
              alt="<?= casting_e($label) ?>"
              width="<?= (int) $dims['width'] ?>"
              height="<?= (int) $dims['height'] ?>"
              decoding="async"
            >
          <?php else : ?>
            <div class="photo-placeholder portrait-frame-empty">بدون عکس</div>
          <?php endif; ?>
        </div>
        <div class="field">
          <label for="<?= casting_e($field) ?>"><?= casting_e($label) ?></label>
          <input id="<?= casting_e($field) ?>" name="<?= casting_e($field) ?>" type="file" accept="image/jpeg,image/png,image/webp"<?= $req ?>>
          <p class="field-hint"><?= casting_e($hints[$slot] ?? '') ?> · JPG / PNG / WebP — حداکثر ۵ مگابایت</p>
        </div>
      </div>
    <?php endforeach; ?>
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
    if (casting_profile_hides_talent_fields($activities, $user_id)) {
        $has_actor_traits = get_user_meta($user_id, 'casting_eye_color', true) !== ''
            || get_user_meta($user_id, 'casting_hair_color', true) !== ''
            || get_user_meta($user_id, 'casting_accent', true) !== ''
            || get_user_meta($user_id, 'casting_accent_other', true) !== ''
            || get_user_meta($user_id, 'casting_apparent_age_range', true) !== '';
        if ($has_actor_traits) {
            casting_purge_actor_trait_meta($user_id);
        }
    }
    $wp_user = get_user_by('id', $user_id);

    return [
        'email'             => $wp_user instanceof WP_User ? (string) $wp_user->user_email : '',
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
        'health_well'       => casting_resolve_health_well(
            (string) get_user_meta($user_id, 'casting_health_well', true),
            (string) get_user_meta($user_id, 'casting_health_status', true)
        ),
        'health_status'     => (string) get_user_meta($user_id, 'casting_health_status', true),
        'experience'        => (string) get_user_meta($user_id, 'casting_experience', true),
        'artistic_membership' => casting_load_artistic_membership($user_id),
        'activity_license'  => (string) get_user_meta($user_id, 'casting_activity_license', true),
        'work_history'      => (string) get_user_meta($user_id, 'casting_work_history', true),
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

    $activities = casting_normalize_activities($data['activities'] ?? []);
    if ($activities === []) {
        return ['ok' => false, 'error' => 'حداقل یک تخصص از نوع فعالیت انتخاب کنید.'];
    }
    $skip_talent_profile = !casting_activities_has_acting($activities);

    $look = sanitize_key((string) ($data['look'] ?? ''));
    if (!$skip_talent_profile && !array_key_exists($look, casting_look_labels())) {
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
    $education = sanitize_textarea_field((string) ($data['education'] ?? ''));
    $edu_items = casting_normalize_education_items($data['education_items'] ?? []);

    $height_raw = trim((string) ($data['height'] ?? ''));
    $weight_raw = trim((string) ($data['weight'] ?? ''));
    $need_body = !$skip_talent_profile && casting_activities_need_body_metrics($activities);
    if ($need_body && ($height_raw === '' || $weight_raw === '')) {
        return ['ok' => false, 'error' => 'برای بازیگران قد و وزن الزامی است.'];
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
    if (!$skip_talent_profile) {
        casting_save_health_meta($user_id, $health);
    }
    update_user_meta($user_id, 'casting_work_history', $work);
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

    update_user_meta($user_id, 'casting_skill_items', $skill_items);
    update_user_meta($user_id, 'casting_skills_other', '');
    update_user_meta($user_id, 'casting_skills', casting_format_skill_labels($skill_items));
    update_user_meta($user_id, 'casting_language_items', $language_items);
    if (!$skip_talent_profile && $availability !== '') {
        update_user_meta($user_id, 'casting_availability', $availability);
    }
    update_user_meta($user_id, 'casting_visible', '1');

    if (!$skip_talent_profile) {
        $traits = casting_save_talent_trait_meta($user_id, $data);
        if (!$traits['ok']) {
            return $traits;
        }
    } else {
        casting_purge_actor_trait_meta($user_id);
    }

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

    if (array_key_exists('email', $data)) {
        if (!function_exists('casting_update_user_email')) {
            require_once __DIR__ . '/auth.php';
        }
        $email_result = casting_update_user_email($user_id, (string) $data['email']);
        if (!$email_result['ok']) {
            return $email_result;
        }
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

    if (array_key_exists('health_well', $data) || array_key_exists('health_status', $data)) {
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

    $activities_for_traits = isset($data['activities'])
        ? casting_normalize_activities($data['activities'], $user_id)
        : casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true), $user_id);
    if (casting_activities_has_acting($activities_for_traits)) {
        $traits = casting_save_talent_trait_meta($user_id, $data);
        if (!$traits['ok']) {
            return $traits;
        }
    } else {
        casting_purge_actor_trait_meta($user_id);
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
    $skip_talent_profile = false;
    if (array_key_exists('activities', $data)) {
        $skip_talent_profile = !casting_activities_has_acting(casting_normalize_activities($data['activities'], $user_id));
    } elseif (!casting_activities_has_acting(casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true), $user_id))) {
        $skip_talent_profile = true;
    }
    casting_save_user_work_meta($user_id, $data, $skip_talent_profile);
    update_user_meta($user_id, 'casting_education', sanitize_textarea_field((string) ($data['education'] ?? '')));
    update_user_meta($user_id, 'casting_education_items', casting_normalize_education_items($data['education_items'] ?? []));

    if (array_key_exists('activities', $data)) {
        $activities = casting_normalize_activities($data['activities'], $user_id);
        if (function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id) && !in_array('it', $activities, true)) {
            $activities[] = 'it';
        }
        if ($activities === []) {
            return ['ok' => false, 'error' => 'حداقل یک تخصص از نوع فعالیت انتخاب کنید.'];
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
                return ['ok' => false, 'error' => 'برای بازیگران قد و وزن الزامی است.'];
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

    casting_require_media_includes();

    $file = $_FILES[$field];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $ftype = (string) ($file['type'] ?? '');
    if (!in_array($ftype, $allowed, true)) {
        return ['ok' => false, 'error' => 'فقط عکس JPG، PNG یا WebP مجاز است.'];
    }
    if ((int) $file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'حجم عکس حداکثر ۵ مگابایت باشد.'];
    }

    casting_enable_user_upload_dir($user_id);
    $attachment_id = media_handle_upload($field, 0);
    casting_disable_user_upload_dir();

    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'آپلود عکس ناموفق بود: ' . $attachment_id->get_error_message()];
    }

    $old = (int) get_user_meta($user_id, $meta_key, true);
    update_user_meta($user_id, $meta_key, (int) $attachment_id);
    if ($slot === 'medium') {
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
function casting_handle_portrait_uploads(int $user_id, bool $require_all = false): array
{
    $labels = casting_portrait_slots();

    if ($require_all) {
        foreach ($labels as $slot => $label) {
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

    if ($require_all && !casting_portraits_complete(casting_load_all_portraits($user_id))) {
        return ['ok' => false, 'error' => 'هر سه عکس (کلوزاپ، مدیوم، لانگ) الزامی است.'];
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
        && casting_portraits_complete($profile['portraits'] ?? []);
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
    if (!function_exists('casting_user_is_suspended')) {
        require_once __DIR__ . '/admin-access.php';
    }
    if (casting_user_is_suspended((int) $user->ID)) {
        casting_set_flash('error', 'حساب شما معلق شده است. برای پیگیری با پشتیبانی تماس بگیرید.');
        casting_redirect('logout.php');
    }
    return $user;
}
