<?php
declare(strict_types=1);

/** جلوگیری از تداخل با includes/panel.php */
if (function_exists('casting_query_members')) {
    return;
}

/**
 * @return array<string, string>
 */
function casting_last_active_filter_options(): array
{
    return [
        '7'  => '۷ روز اخیر',
        '30' => '۳۰ روز اخیر',
        '90' => '۹۰ روز اخیر',
    ];
}

/**
 * @return array<string, string>
 */
function casting_parse_member_search_filters(array $input): array
{
    return [
        'q'                   => (string) ($input['q'] ?? ''),
        'role'                => (string) ($input['role'] ?? ''),
        'activity_category'   => (string) ($input['activity_category'] ?? ''),
        'activity_specialty'  => (string) ($input['activity_specialty'] ?? ''),
        'province'            => (string) ($input['province'] ?? ''),
        'city'                => (string) ($input['city'] ?? ''),
        'experience_min'      => (string) ($input['experience_min'] ?? ''),
        'experience_max'      => (string) ($input['experience_max'] ?? ''),
        'identity_verified'   => (string) ($input['identity_verified'] ?? ''),
        'has_portfolio'       => (string) ($input['has_portfolio'] ?? ''),
        'has_video'           => (string) ($input['has_video'] ?? ''),
        'resume_verified'     => (string) ($input['resume_verified'] ?? ''),
        'availability'        => (string) ($input['availability'] ?? ''),
        'last_active'         => (string) ($input['last_active'] ?? ''),
        'cooperation_score_min' => (string) ($input['cooperation_score_min'] ?? ''),
        'language'            => (string) ($input['language'] ?? ''),
        'language_level'      => (string) ($input['language_level'] ?? ''),
        'accent'              => (string) ($input['accent'] ?? ''),
        'skill'               => (string) ($input['skill'] ?? ''),
        'motor_skill'         => (string) ($input['motor_skill'] ?? ''),
        'artistic_skill'      => (string) ($input['artistic_skill'] ?? ''),
        'education_degree'    => (string) ($input['education_degree'] ?? ''),
        'artistic_has'        => (string) ($input['artistic_has'] ?? ''),
        'artistic_org'        => (string) ($input['artistic_org'] ?? ''),
        'activity_license'    => (string) ($input['activity_license'] ?? ''),
        'premium'             => (string) ($input['premium'] ?? ''),
        'gender'              => (string) ($input['gender'] ?? ''),
        'apparent_age_range'  => (string) ($input['apparent_age_range'] ?? ''),
        'birth_jy'            => (string) ($input['birth_jy'] ?? ''),
        'birth_jm'            => (string) ($input['birth_jm'] ?? ''),
        'birth_jd'            => (string) ($input['birth_jd'] ?? ''),
        'age_range'           => (string) ($input['age_range'] ?? ''),
        'height_min'          => (string) ($input['height_min'] ?? ''),
        'height_max'          => (string) ($input['height_max'] ?? ''),
        'weight_min'          => (string) ($input['weight_min'] ?? ''),
        'weight_max'          => (string) ($input['weight_max'] ?? ''),
        'eye_color'           => (string) ($input['eye_color'] ?? ''),
        'hair_color'          => (string) ($input['hair_color'] ?? ''),
        'look'                => (string) ($input['look'] ?? ''),
        'health_status'       => (string) ($input['health_status'] ?? ''),
    ];
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_yes_no_search_filter(array &$meta_query, string $key, string $value): void
{
    $value = sanitize_key($value);
    if ($value === '' || !array_key_exists($value, casting_yes_no_labels())) {
        return;
    }
    $meta_query[] = [
        'key'   => $key,
        'value' => $value,
    ];
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_birthdate_filters(array &$meta_query, array $filters): void
{
    $jy = (int) ($filters['birth_jy'] ?? 0);
    $jm = (int) ($filters['birth_jm'] ?? 0);
    $jd = (int) ($filters['birth_jd'] ?? 0);

    if ($jy >= 1300 && $jy <= 1500 && $jm >= 1 && $jm <= 12 && $jd >= 1 && $jd <= casting_jalali_month_days($jy, $jm)) {
        $birthdate = casting_birthdate_from_jalali_post([
            'birth_jy' => $jy,
            'birth_jm' => $jm,
            'birth_jd' => $jd,
        ]);
        if ($birthdate !== null) {
            $meta_query[] = [
                'key'   => 'casting_birthdate',
                'value' => $birthdate,
            ];
            return;
        }
    }

    if ($jy >= 1300 && $jy <= 1500) {
        [$gy1, $gm1, $gd1] = casting_jalali_to_gregorian($jy, 1, 1);
        $last_day = casting_jalali_month_days($jy, 12);
        [$gy2, $gm2, $gd2] = casting_jalali_to_gregorian($jy, 12, $last_day);
        $meta_query[] = [
            'key'     => 'casting_birthdate',
            'value'   => [
                sprintf('%04d-%02d-%02d', $gy1, $gm1, $gd1),
                sprintf('%04d-%02d-%02d', $gy2, $gm2, $gd2),
            ],
            'compare' => 'BETWEEN',
            'type'    => 'DATE',
        ];
        return;
    }

    $age_range = (string) ($filters['age_range'] ?? '');
    if ($age_range === '' || !array_key_exists($age_range, casting_age_range_options())) {
        return;
    }

    $range = casting_age_range_options()[$age_range];
    if ($range['min'] !== null) {
        $meta_query[] = [
            'key'     => 'casting_age',
            'value'   => (int) $range['min'],
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }
    if ($range['max'] !== null) {
        $meta_query[] = [
            'key'     => 'casting_age',
            'value'   => (int) $range['max'],
            'type'    => 'NUMERIC',
            'compare' => '<=',
        ];
    }
}

/**
 * @param list<array<string, mixed>> $meta_query
 */
function casting_apply_member_search_meta_query(array &$meta_query, array $filters): void
{
    if (!empty($filters['role']) && casting_valid_role((string) $filters['role'])) {
        $meta_query[] = [
            'key'   => 'casting_role',
            'value' => sanitize_key((string) $filters['role']),
        ];
    }

    $activity_specialty = sanitize_key((string) ($filters['activity_specialty'] ?? ''));
    $activity_category = sanitize_key((string) ($filters['activity_category'] ?? ''));
    $activity_labels = casting_activity_labels();
    $activity_categories = casting_activity_categories();

    if ($activity_specialty !== '' && isset($activity_labels[$activity_specialty])) {
        $meta_query[] = [
            'key'     => 'casting_activities',
            'value'   => '"' . $activity_specialty . '"',
            'compare' => 'LIKE',
        ];
    } elseif ($activity_category !== '' && isset($activity_categories[$activity_category])) {
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

    casting_apply_yes_no_search_filter($meta_query, 'casting_identity_verified', (string) ($filters['identity_verified'] ?? ''));
    casting_apply_yes_no_search_filter($meta_query, 'casting_has_portfolio', (string) ($filters['has_portfolio'] ?? ''));
    casting_apply_yes_no_search_filter($meta_query, 'casting_has_video', (string) ($filters['has_video'] ?? ''));
    casting_apply_yes_no_search_filter($meta_query, 'casting_resume_verified', (string) ($filters['resume_verified'] ?? ''));

    $license = sanitize_key((string) ($filters['activity_license'] ?? ''));
    if ($license !== '' && array_key_exists($license, casting_yes_no_labels())) {
        $meta_query[] = [
            'key'   => 'casting_activity_license',
            'value' => $license,
        ];
    }

    if (!empty($filters['availability']) && array_key_exists($filters['availability'], casting_availability_labels())) {
        $meta_query[] = [
            'key'   => 'casting_availability',
            'value' => sanitize_key((string) $filters['availability']),
        ];
    }

    $last_active = sanitize_key((string) ($filters['last_active'] ?? ''));
    $last_opts = casting_last_active_filter_options();
    if ($last_active !== '' && isset($last_opts[$last_active])) {
        $days = (int) $last_active;
        $since = wp_date('Y-m-d H:i:s', strtotime('-' . $days . ' days', (int) current_time('timestamp')));
        $meta_query[] = [
            'key'     => 'casting_last_active',
            'value'   => $since,
            'compare' => '>=',
            'type'    => 'DATETIME',
        ];
    }

    $score_min = (int) ($filters['cooperation_score_min'] ?? 0);
    if ($score_min >= 1 && $score_min <= 5) {
        $meta_query[] = [
            'key'     => 'casting_cooperation_score',
            'value'   => $score_min,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }

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

    $accent = sanitize_key((string) ($filters['accent'] ?? ''));
    if ($accent !== '' && array_key_exists($accent, casting_accent_labels())) {
        $meta_query[] = [
            'key'   => 'casting_accent',
            'value' => $accent,
        ];
    }

    $skill = sanitize_key((string) ($filters['skill'] ?? ''));
    if ($skill !== '' && isset(casting_skill_labels()[$skill])) {
        $meta_query[] = [
            'key'     => 'casting_skill_items',
            'value'   => '"' . $skill . '"',
            'compare' => 'LIKE',
        ];
    }

    $motor = sanitize_key((string) ($filters['motor_skill'] ?? ''));
    if ($motor !== '' && isset(casting_motor_skill_labels()[$motor])) {
        $meta_query[] = [
            'key'     => 'casting_skill_items',
            'value'   => '"' . $motor . '"',
            'compare' => 'LIKE',
        ];
    }

    $art_skill = sanitize_key((string) ($filters['artistic_skill'] ?? ''));
    if ($art_skill !== '' && isset(casting_artistic_skill_labels()[$art_skill])) {
        $meta_query[] = [
            'key'     => 'casting_skill_items',
            'value'   => '"' . $art_skill . '"',
            'compare' => 'LIKE',
        ];
    }

    $degree = sanitize_key((string) ($filters['education_degree'] ?? ''));
    if ($degree !== '' && array_key_exists($degree, casting_education_degree_labels())) {
        $meta_query[] = [
            'key'     => 'casting_education_items',
            'value'   => '"' . $degree . '"',
            'compare' => 'LIKE',
        ];
    }

    $artistic_has = sanitize_key((string) ($filters['artistic_has'] ?? ''));
    if ($artistic_has !== '' && array_key_exists($artistic_has, casting_yes_no_labels())) {
        $meta_query[] = [
            'key'   => 'casting_artistic_membership',
            'value' => $artistic_has,
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

    if (sanitize_key((string) ($filters['premium'] ?? '')) === 'yes') {
        $meta_query[] = [
            'key'     => 'casting_premium_until',
            'value'   => (string) current_time('mysql'),
            'compare' => '>=',
            'type'    => 'DATETIME',
        ];
    }

    if (!empty($filters['gender']) && array_key_exists($filters['gender'], casting_gender_labels())) {
        $meta_query[] = [
            'key'   => 'casting_gender',
            'value' => sanitize_key((string) $filters['gender']),
        ];
    }

    $apparent = sanitize_key((string) ($filters['apparent_age_range'] ?? ''));
    if ($apparent !== '' && array_key_exists($apparent, casting_age_range_options())) {
        $meta_query[] = [
            'key'   => 'casting_apparent_age_range',
            'value' => $apparent,
        ];
    }

    casting_apply_member_birthdate_filters($meta_query, $filters);

    $height_min = (int) ($filters['height_min'] ?? 0);
    $height_max = (int) ($filters['height_max'] ?? 0);
    if ($height_min >= 80 && $height_min <= 230) {
        $meta_query[] = [
            'key'     => 'casting_height',
            'value'   => $height_min,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }
    if ($height_max >= 80 && $height_max <= 230) {
        $meta_query[] = [
            'key'     => 'casting_height',
            'value'   => $height_max,
            'type'    => 'NUMERIC',
            'compare' => '<=',
        ];
    }

    $weight_min = (int) ($filters['weight_min'] ?? 0);
    $weight_max = (int) ($filters['weight_max'] ?? 0);
    if ($weight_min >= 20 && $weight_min <= 250) {
        $meta_query[] = [
            'key'     => 'casting_weight',
            'value'   => $weight_min,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }
    if ($weight_max >= 20 && $weight_max <= 250) {
        $meta_query[] = [
            'key'     => 'casting_weight',
            'value'   => $weight_max,
            'type'    => 'NUMERIC',
            'compare' => '<=',
        ];
    }

    if (!empty($filters['eye_color']) && array_key_exists($filters['eye_color'], casting_eye_color_labels())) {
        $meta_query[] = [
            'key'   => 'casting_eye_color',
            'value' => sanitize_key((string) $filters['eye_color']),
        ];
    }

    if (!empty($filters['hair_color']) && array_key_exists($filters['hair_color'], casting_hair_color_labels())) {
        $meta_query[] = [
            'key'   => 'casting_hair_color',
            'value' => sanitize_key((string) $filters['hair_color']),
        ];
    }

    if (!empty($filters['look']) && array_key_exists($filters['look'], casting_look_labels())) {
        $meta_query[] = [
            'key'   => 'casting_look',
            'value' => sanitize_key((string) $filters['look']),
        ];
    }

    $health = sanitize_text_field((string) ($filters['health_status'] ?? ''));
    if ($health !== '') {
        $meta_query[] = [
            'key'     => 'casting_health_status',
            'value'   => $health,
            'compare' => 'LIKE',
        ];
    }
}

/**
 * @param array<string, string> $filters
 * @param list<string> $keys
 */
function casting_search_has_active_filters(array $filters, array $keys): bool
{
    foreach ($keys as $key) {
        if (($filters[$key] ?? '') !== '') {
            return true;
        }
    }
    return false;
}

/**
 * فیلترهای پیشرفته — فقط داخل بخش جمع‌شونده (صفحه اصلی ۳ فیلد ساده دارد).
 *
 * @param array<string, string> $filters
 */
function casting_render_member_search_advanced(array $filters, string $page = 'search-users.php'): void
{
    $more_keys = [
        'province',
        'experience_min', 'experience_max', 'identity_verified', 'activity_license', 'has_portfolio',
        'has_video', 'resume_verified', 'availability', 'last_active', 'cooperation_score_min', 'premium',
        'language', 'language_level', 'accent', 'skill', 'education_degree', 'artistic_has', 'artistic_org',
        'gender', 'apparent_age_range', 'age_range',
        'height_min', 'height_max', 'weight_min', 'weight_max', 'eye_color', 'hair_color', 'look',
        'motor_skill', 'artistic_skill', 'health_status',
    ];
    if (!casting_search_has_active_filters($filters, $more_keys)) {
        $open = false;
    } else {
        $open = true;
    }

    $yes_no = casting_yes_no_labels();
    $genders = casting_gender_labels();
    $looks = casting_look_labels();
    $eyes = casting_eye_color_labels();
    $hairs = casting_hair_color_labels();
    $accents = casting_accent_labels();
    $age_ranges = casting_age_range_options();
    $artistic_orgs = casting_artistic_org_labels();
    $skills = casting_skill_labels();
    $motor_skills = casting_motor_skill_labels();
    $artistic_skills = casting_artistic_skill_labels();
    $availability_labels = casting_availability_labels();
    $language_levels = casting_language_level_labels();
    $education_degrees = casting_education_degree_labels();
    $last_active_opts = casting_last_active_filter_options();
    $languages = casting_common_languages();
    $provinces = casting_province_labels();
    ?>
  <details class="filter-details" <?= $open ? 'open' : '' ?>>
    <summary>فیلترهای بیشتر</summary>
    <form class="filter-details-form" method="get" action="<?= casting_e($page) ?>">
      <input type="hidden" name="q" value="<?= casting_e($filters['q']) ?>">
      <input type="hidden" name="activity_category" value="<?= casting_e($filters['activity_category']) ?>">
      <input type="hidden" name="city" value="<?= casting_e($filters['city']) ?>">
      <div class="filter-details-body">
        <?php casting_render_member_search_advanced_fields($filters, [
            'yes_no' => $yes_no,
            'genders' => $genders,
            'looks' => $looks,
            'eyes' => $eyes,
            'hairs' => $hairs,
            'accents' => $accents,
            'age_ranges' => $age_ranges,
            'artistic_orgs' => $artistic_orgs,
            'skills' => $skills,
            'motor_skills' => $motor_skills,
            'artistic_skills' => $artistic_skills,
            'availability_labels' => $availability_labels,
            'language_levels' => $language_levels,
            'education_degrees' => $education_degrees,
            'last_active_opts' => $last_active_opts,
            'languages' => $languages,
            'provinces' => $provinces,
        ]); ?>
        <div class="filter-actions">
          <button class="btn btn-primary" type="submit">اعمال فیلترها</button>
          <a class="btn btn-ghost" href="<?= casting_e($page) ?>">پاک کردن همه</a>
        </div>
      </div>
    </form>
  </details>
    <?php
}

/**
 * @param array<string, string> $filters
 * @param array<string, mixed> $labels
 */
function casting_render_member_search_advanced_fields(array $filters, array $labels): void
{
    extract($labels, EXTR_SKIP);
    ?>
        <div class="filter-bar filter-bar-wide">
          <div class="field">
            <label for="province">استان</label>
            <select id="province" name="province">
              <option value="">همه</option>
              <?php foreach ($provinces as $key => $label) : ?>
                <option value="<?= casting_e($key) ?>" <?= $filters['province'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <div class="field">
          <label for="experience_min">سابقه از (سال)</label>
          <input id="experience_min" name="experience_min" type="number" min="0" max="60" value="<?= casting_e($filters['experience_min']) ?>">
        </div>
        <div class="field">
          <label for="experience_max">سابقه تا (سال)</label>
          <input id="experience_max" name="experience_max" type="number" min="0" max="60" value="<?= casting_e($filters['experience_max']) ?>">
        </div>
        <div class="field">
          <label for="identity_verified">احراز هویت</label>
          <select id="identity_verified" name="identity_verified">
            <option value="">همه</option>
            <?php foreach ($yes_no as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['identity_verified'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="activity_license">پروانه فعالیت</label>
          <select id="activity_license" name="activity_license">
            <option value="">همه</option>
            <?php foreach ($yes_no as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['activity_license'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="has_portfolio">دارای نمونه‌کار</label>
          <select id="has_portfolio" name="has_portfolio">
            <option value="">همه</option>
            <?php foreach ($yes_no as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['has_portfolio'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="has_video">دارای ویدئوی معرفی</label>
          <select id="has_video" name="has_video">
            <option value="">همه</option>
            <?php foreach ($yes_no as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['has_video'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="resume_verified">رزومه تأییدشده</label>
          <select id="resume_verified" name="resume_verified">
            <option value="">همه</option>
            <?php foreach ($yes_no as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['resume_verified'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="availability">نوع همکاری</label>
          <select id="availability" name="availability">
            <option value="">همه</option>
            <?php foreach ($availability_labels as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['availability'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="last_active">آخرین فعالیت</label>
          <select id="last_active" name="last_active">
            <option value="">همه</option>
            <?php foreach ($last_active_opts as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['last_active'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="cooperation_score_min">امتیاز همکاری</label>
          <select id="cooperation_score_min" name="cooperation_score_min">
            <option value="">همه</option>
            <?php for ($s = 5; $s >= 1; $s--) : ?>
              <option value="<?= $s ?>" <?= $filters['cooperation_score_min'] === (string) $s ? 'selected' : '' ?>><?= $s ?>+</option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="field">
          <label for="premium">عضویت ویژه</label>
          <select id="premium" name="premium">
            <option value="">همه</option>
            <option value="yes" <?= $filters['premium'] === 'yes' ? 'selected' : '' ?>>فقط ویژه</option>
          </select>
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
            <option value="">همه</option>
            <?php foreach ($language_levels as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['language_level'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="accent">لهجه</label>
          <select id="accent" name="accent">
            <option value="">همه</option>
            <?php foreach ($accents as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['accent'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="skill">مهارت</label>
          <select id="skill" name="skill">
            <option value="">همه</option>
            <?php foreach ($skills as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['skill'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="education_degree">تحصیلات</label>
          <select id="education_degree" name="education_degree">
            <option value="">همه</option>
            <?php foreach ($education_degrees as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['education_degree'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="artistic_has">عضویت صنفی</label>
          <select id="artistic_has" name="artistic_has">
            <option value="">همه</option>
            <?php foreach ($yes_no as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['artistic_has'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="artistic_org">نام تشکل</label>
          <select id="artistic_org" name="artistic_org">
            <option value="">همه</option>
            <?php foreach ($artistic_orgs as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['artistic_org'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        </div>

        <p class="filter-section-title filter-section-span">فیلترهای بازیگران</p>
        <div class="filter-bar filter-bar-wide">
        <div class="field">
          <label for="gender">جنسیت</label>
          <select id="gender" name="gender">
            <option value="">همه</option>
            <?php foreach ($genders as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['gender'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="apparent_age_range">رده سنی ظاهری</label>
          <select id="apparent_age_range" name="apparent_age_range">
            <option value="">همه</option>
            <?php foreach ($age_ranges as $key => $range) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['apparent_age_range'] === $key ? 'selected' : '' ?>><?= casting_e($range['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="age_range">بازه سنی</label>
          <select id="age_range" name="age_range">
            <option value="">همه</option>
            <?php foreach ($age_ranges as $key => $range) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['age_range'] === $key ? 'selected' : '' ?>><?= casting_e($range['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-pair-row">
          <div class="field">
            <label for="height_min">قد از</label>
            <input id="height_min" name="height_min" type="number" min="80" max="230" value="<?= casting_e($filters['height_min']) ?>" placeholder="cm">
          </div>
          <div class="field">
            <label for="height_max">قد تا</label>
            <input id="height_max" name="height_max" type="number" min="80" max="230" value="<?= casting_e($filters['height_max']) ?>" placeholder="cm">
          </div>
        </div>
        <div class="filter-pair-row">
          <div class="field">
            <label for="weight_min">وزن از</label>
            <input id="weight_min" name="weight_min" type="number" min="20" max="250" value="<?= casting_e($filters['weight_min']) ?>" placeholder="kg">
          </div>
          <div class="field">
            <label for="weight_max">وزن تا</label>
            <input id="weight_max" name="weight_max" type="number" min="20" max="250" value="<?= casting_e($filters['weight_max']) ?>" placeholder="kg">
          </div>
        </div>
        <div class="field">
          <label for="eye_color">رنگ چشم</label>
          <select id="eye_color" name="eye_color">
            <option value="">همه</option>
            <?php foreach ($eyes as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['eye_color'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="hair_color">رنگ مو</label>
          <select id="hair_color" name="hair_color">
            <option value="">همه</option>
            <?php foreach ($hairs as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['hair_color'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="look">رنگ پوست</label>
          <select id="look" name="look">
            <option value="">همه</option>
            <?php foreach ($looks as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['look'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="motor_skill">مهارت حرکتی</label>
          <select id="motor_skill" name="motor_skill">
            <option value="">همه</option>
            <?php foreach ($motor_skills as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['motor_skill'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="artistic_skill">مهارت هنری</label>
          <select id="artistic_skill" name="artistic_skill">
            <option value="">همه</option>
            <?php foreach ($artistic_skills as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $filters['artistic_skill'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="health_status">وضعیت سلامت</label>
          <input id="health_status" name="health_status" type="search" value="<?= casting_e($filters['health_status']) ?>" placeholder="کلمه کلیدی…">
        </div>
        </div>
    <?php
}
