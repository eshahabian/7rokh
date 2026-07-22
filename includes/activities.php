<?php
declare(strict_types=1);

/**
 * دسته‌بندی نوع فعالیت و تخصص‌های هر دسته
 *
 * @return array<string, array{label:string, items:array<string,string>}>
 */
function casting_activity_categories(): array
{
    return [
        'acting' => [
            'label' => 'بازیگران',
            'items' => [
                'actor_cinema'  => 'بازیگر سینما',
                'actor_theater' => 'بازیگر تئاتر',
                'actor_tv'      => 'بازیگر تلویزیون',
                'actor_youth'   => 'بازیگر کودک و نوجوان',
                'extra'         => 'هنرور (اکسترا)',
                'stunt'         => 'بدلکار (استانت)',
            ],
        ],
        'directing' => [
            'label' => 'کارگردانی',
            'items' => [
                'director_theater'    => 'کارگردان تئاتر',
                'director_short_film' => 'کارگردان فیلم کوتاه',
                'director_tv'         => 'کارگردان تلویزیونی',
                'director_cinema'     => 'کارگردان سینما',
                'first_ad'            => 'دستیار اول کارگردان',
                'second_ad'           => 'دستیار دوم کارگردان',
                'third_ad'            => 'دستیار سوم کارگردان',
                'scheduler'           => 'برنامه‌ریز',
                'script_supervisor'   => 'منشی صحنه',
            ],
        ],
        'production' => [
            'label' => 'تهیه و تولید',
            'items' => [
                'producer'             => 'تهیه‌کننده',
                'production_manager'   => 'مدیر تولید',
                'production_assistant' => 'دستیار تولید',
                'executive'            => 'مجری طرح',
                'logistics_manager'    => 'مدیر تدارکات',
                'logistics_assistant'  => 'دستیار تدارکات',
                'logistics_driver'     => 'راننده تدارکات',
            ],
        ],
        'writing' => [
            'label' => 'نویسندگی',
            'items' => [
                'writer'            => 'نویسنده',
                'playwright'        => 'نمایشنامه‌نویس',
                'screenwriter'      => 'فیلمنامه‌نویس',
                'script_consultant' => 'مشاور فیلمنامه',
                'researcher'        => 'پژوهشگر',
            ],
        ],
        'camera' => [
            'label' => 'فیلمبرداری و تصویر',
            'items' => [
                'dop'                   => 'مدیر فیلمبرداری',
                'cameraman'             => 'فیلمبردار',
                'camera_assistant'      => 'دستیار فیلمبردار',
                'camera_first_assistant'  => 'دستیار اول فیلم برداری',
                'camera_second_assistant' => 'دستیار دوم فیلم برداری',
                'camera_third_assistant'  => 'دستیار سوم فیلم برداری',
                'camera_technical_crew'   => 'نیروی فنی',
                'videographer'          => 'تصویربردار',
                'crane_op'              => 'اپراتور کرین',
                'steadicam_op'          => 'اپراتور استدی‌کم',
                'gimbal_op'             => 'اپراتور گیمبال',
                'drone_op'              => 'اپراتور پهپاد',
            ],
        ],
        'sound' => [
            'label' => 'صدا',
            'items' => [
                'sound_mixer'  => 'مدیر صدابرداری',
                'boom_op'      => 'بوم‌من',
                'sound_editor' => 'صداگذار',
            ],
        ],
        'post' => [
            'label' => 'تدوین و پس‌تولید',
            'items' => [
                'editor'   => 'دستیار تدوین‌گر',
                'colorist' => 'اصلاح رنگ (کالرست)',
                'vfx'      => 'جلوه‌های ویژه بصری (VFX)',
                'motion'   => 'موشن گرافیست',
                'animator' => 'انیماتور',
            ],
        ],
        'art' => [
            'label' => 'طراح هنری',
            'items' => [
                'set_designer'     => 'طراح صحنه',
                'art_assistant'    => 'دستیار هنری',
                'costume_designer' => 'طراح لباس',
                'makeup_designer'  => 'طراح گریم',
                'makeup_artist'    => 'گریمور',
            ],
        ],
        'lighting' => [
            'label' => 'نور',
            'items' => [
                'lighting_designer' => 'طراح نور',
                'gaffer'            => 'نورپرداز',
            ],
        ],
        'music' => [
            'label' => 'موسیقی',
            'items' => [
                'composer' => 'آهنگساز',
                'musician' => 'نوازنده',
                'singer'   => 'خواننده',
            ],
        ],
        'promo' => [
            'label' => 'رسانه و تبلیغات',
            'items' => [
                'still_photographer' => 'عکاس صحنه',
                'poster_designer'    => 'طراح پوستر',
                'graphic_designer'   => 'گرافیست',
                'promo_manager'      => 'تبلیغات',
                'pr_manager'         => 'روابط عمومی',
                'media_journalist'   => 'خبرنگار',
                'media_consultant'   => 'مشاور رسانه‌ای',
            ],
        ],
        'set_crew' => [
            'label' => 'عوامل صحنه',
            'items' => [
                'stage_manager'   => 'مدیر صحنه',
                'stage_assistant' => 'دستیار صحنه',
                'set_deco'        => 'ساخت دکور',
                'props'           => 'مسئول وسایل صحنه',
            ],
        ],
        'other' => [
            'label' => 'سایر تخصص‌ها',
            'items' => [
                'casting_director' => 'مدیر انتخاب بازیگر (Casting Director)',
                'location_manager' => 'مدیر لوکیشن',
                'sfx'              => 'مسئول جلوه‌های ویژه میدانی (SFX)',
                'acting_coach'     => 'مربی بازیگری',
                'art_consultant'   => 'مشاور هنری',
            ],
        ],
    ];
}

/**
 * تخصص‌های مخفی — فقط برای مدیر اصلی (eshahabian)
 *
 * @return array<string, string>
 */
function casting_hidden_activity_labels(): array
{
    return [
        'it' => 'IT',
    ];
}

/**
 * @return list<string>
 */
function casting_hidden_activity_keys(): array
{
    return array_keys(casting_hidden_activity_labels());
}

/**
 * @return array<string, array{label:string, items:array<string,string>}>
 */
function casting_activity_categories_for_user(?int $user_id = null): array
{
    $categories = casting_activity_categories();
    if ($user_id !== null && $user_id > 0 && casting_user_is_portal_owner($user_id)) {
        $categories['it_internal'] = [
            'label' => 'فناوری',
            'items' => casting_hidden_activity_labels(),
        ];
    }

    return $categories;
}

/**
 * @return array<string, string> key => label برای همه تخصص‌ها
 */
function casting_activity_labels(): array
{
    $all = [];
    foreach (casting_activity_categories() as $cat) {
        foreach ($cat['items'] as $key => $label) {
            $all[$key] = $label;
        }
    }
    return $all;
}

/**
 * @return array<string, string>
 */
function casting_activity_labels_for_user(int $user_id): array
{
    $all = casting_activity_labels();
    if (casting_user_is_portal_owner($user_id)) {
        return array_merge($all, casting_hidden_activity_labels());
    }

    return $all;
}

/**
 * کلید تخصص → کلید دسته
 */
function casting_activity_category_for_specialty(string $specialty): string
{
    if (isset(casting_hidden_activity_labels()[$specialty])) {
        return 'it_internal';
    }
    foreach (casting_activity_categories() as $cat_key => $cat) {
        if (isset($cat['items'][$specialty])) {
            return $cat_key;
        }
    }
    return '';
}

/**
 * @param list<string> $keys
 * @param int $profile_user_id
 * @param int $viewer_id
 * @return list<string>
 */
function casting_filter_activities_for_viewer(array $keys, int $profile_user_id, int $viewer_id): array
{
    $hidden = casting_hidden_activity_keys();
    if ($hidden === []) {
        return $keys;
    }
    $show_hidden = casting_user_is_portal_owner($profile_user_id) && casting_user_is_portal_owner($viewer_id);
    if ($show_hidden) {
        return $keys;
    }

    return array_values(array_filter($keys, static function (string $key) use ($hidden): bool {
        return !in_array($key, $hidden, true);
    }));
}

function casting_sync_portal_owner_activities(int $user_id): void
{
    if (!casting_user_is_portal_owner($user_id)) {
        return;
    }
    $raw = get_user_meta($user_id, 'casting_activities', true);
    $activities = casting_normalize_activities($raw, $user_id);
    if (in_array('it', $activities, true)) {
        return;
    }
    $activities[] = 'it';
    update_user_meta($user_id, 'casting_activities', $activities);
}

function casting_user_profile_chip_label(int $user_id, int $viewer_id = 0): string
{
    if ($viewer_id <= 0) {
        $viewer_id = $user_id;
    }
    if (casting_user_is_portal_owner($user_id) && casting_user_is_portal_owner($viewer_id)) {
        if (function_exists('casting_sync_portal_owner_activities')) {
            casting_sync_portal_owner_activities($user_id);
        }

        return casting_hidden_activity_labels()['it'] ?? 'IT';
    }

    return casting_role_label(casting_get_user_role($user_id));
}

/**
 * @param mixed $raw
 * @return list<string>
 */
function casting_normalize_activities($raw, int $user_id = 0): array
{
    if (!is_array($raw)) {
        return [];
    }
    $labels = $user_id > 0 ? casting_activity_labels_for_user($user_id) : casting_activity_labels();
    $out = [];
    foreach ($raw as $item) {
        if (is_array($item)) {
            $key = sanitize_key((string) ($item['specialty'] ?? $item['activity'] ?? ''));
        } else {
            $key = sanitize_key((string) $item);
        }
        if ($key !== '' && isset($labels[$key]) && !in_array($key, $out, true)) {
            $out[] = $key;
        }
    }
    return $out;
}

/**
 * @param list<string> $keys
 * @return array<int, array{category:string,specialty:string}>
 */
function casting_activities_to_rows(array $keys, int $user_id = 0): array
{
    $keys = casting_normalize_activities($keys, $user_id);
    $rows = [];
    foreach ($keys as $key) {
        $cat = casting_activity_category_for_specialty($key);
        if ($cat !== '') {
            $rows[] = ['category' => $cat, 'specialty' => $key];
        }
    }
    if ($rows === []) {
        $rows[] = ['category' => '', 'specialty' => ''];
    }
    return $rows;
}

/**
 * @return list<string>
 */
function casting_parse_activities_post(array $post, int $user_id = 0): array
{
    $raw = $post['activity_items'] ?? $post['activities'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $categories = $user_id > 0
        ? casting_activity_categories_for_user($user_id)
        : casting_activity_categories();
    $labels = $user_id > 0 ? casting_activity_labels_for_user($user_id) : casting_activity_labels();
    $out = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            $key = sanitize_key((string) $item);
            if ($key !== '' && isset($labels[$key]) && !in_array($key, $out, true)) {
                $out[] = $key;
            }
            continue;
        }
        $cat = sanitize_key((string) ($item['category'] ?? ''));
        $specialty = sanitize_key((string) ($item['specialty'] ?? ''));
        if ($cat === '' || $specialty === '' || !isset($categories[$cat]['items'][$specialty])) {
            continue;
        }
        if (!in_array($specialty, $out, true)) {
            $out[] = $specialty;
        }
    }
    return $out;
}

/**
 * @return list<string>
 */
function casting_directing_activity_keys(): array
{
    return array_keys(casting_activity_categories()['directing']['items']);
}

/**
 * فقط تخصص‌های کارگردانی (بدون ترکیب با بازیگری و …)
 *
 * @param list<string> $activities
 */
function casting_activities_are_directing_only(array $activities): bool
{
    $activities = casting_normalize_activities($activities);
    if ($activities === []) {
        return false;
    }
    $directing = casting_directing_activity_keys();
    foreach ($activities as $activity) {
        if (!in_array($activity, $directing, true)) {
            return false;
        }
    }
    return true;
}

/**
 * کارگردان / تهیه‌کننده — نمایش بخش «آثار هنری»
 *
 * @param list<string> $activities
 */
function casting_activities_show_artistic_works(array $activities): bool
{
    $activities = casting_normalize_activities($activities);
    foreach ($activities as $activity) {
        if (casting_activity_category_for_specialty($activity) === 'directing') {
            return true;
        }
    }

    return false;
}

/**
 * آیا حداقل یک تخصص از دسته «بازیگران» انتخاب شده؟
 *
 * @param list<string> $activities
 */
function casting_activities_has_acting(array $activities): bool
{
    $activities = casting_normalize_activities($activities);
    foreach ($activities as $activity) {
        if (casting_activity_category_for_specialty($activity) === 'acting') {
            return true;
        }
    }

    return false;
}

/**
 * فرم و نمایش پروفایل — فیلدهای مخصوص بازیگر فقط برای دسته بازیگران
 *
 * @param list<string>|mixed $activities
 */
function casting_profile_hides_talent_fields($activities, int $user_id = 0): bool
{
    unset($user_id);

    return !casting_activities_has_acting(is_array($activities) ? $activities : []);
}

/**
 * @param list<string> $selected
 */
function casting_render_activity_fields(array $selected = [], bool $required = true, int $user_id = 0): void
{
    $rows = casting_activities_to_rows($selected, $user_id);
    $categories = $user_id > 0
        ? casting_activity_categories_for_user($user_id)
        : casting_activity_categories();
    $map = [];
    foreach ($categories as $cat_key => $cat) {
        $map[$cat_key] = $cat['items'];
    }
    $map_json = wp_json_encode($map, JSON_UNESCAPED_UNICODE);
    ?>
  <div class="field work-credits activity-fields" data-activity-items<?= $required ? ' data-activities-required' : '' ?> data-activity-map="<?= casting_e((string) $map_json) ?>">
    <span class="jalali-label">نوع فعالیت<?= $required ? ' <span class="req-mark">*</span>' : '' ?></span>
    <p class="field-hint">اول تخصص هنری را انتخاب کنید (مثلاً کارگردانی)، بعد تخصص همان رشته را انتخاب کنید. با + مورد بعدی را اضافه کنید.</p>
    <div class="work-credits-list" data-activity-list>
      <?php foreach ($rows as $i => $row) :
          $cat_key = (string) ($row['category'] ?? '');
          $specialty = (string) ($row['specialty'] ?? '');
          $subs = ($cat_key !== '' && isset($categories[$cat_key])) ? $categories[$cat_key]['items'] : [];
          ?>
        <div class="work-credit-row activity-row">
          <select name="activity_items[<?= (int) $i ?>][category]" aria-label="تخصص هنری" data-activity-category>
            <option value="">انتخاب تخصص هنری…</option>
            <?php foreach ($categories as $key => $cat) : ?>
              <option value="<?= casting_e($key) ?>" <?= $cat_key === $key ? 'selected' : '' ?>><?= casting_e($cat['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="activity_items[<?= (int) $i ?>][specialty]" aria-label="تخصص" data-activity-specialty <?= $cat_key === '' ? 'disabled' : '' ?>>
            <option value=""><?= $cat_key === '' ? 'اول تخصص هنری را انتخاب کنید' : 'انتخاب تخصص…' ?></option>
            <?php foreach ($subs as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $specialty === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn-icon" data-remove-activity aria-label="حذف">−</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-ghost btn-add-credit" data-add-activity>+ افزودن تخصص بعدی</button>
    <template data-activity-template>
      <div class="work-credit-row activity-row">
        <select name="activity_items[__i__][category]" aria-label="تخصص هنری" data-activity-category>
          <option value="">انتخاب تخصص هنری…</option>
          <?php foreach ($categories as $key => $cat) : ?>
            <option value="<?= casting_e($key) ?>"><?= casting_e($cat['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="activity_items[__i__][specialty]" aria-label="تخصص" data-activity-specialty disabled>
          <option value="">اول تخصص هنری را انتخاب کنید</option>
        </select>
        <button type="button" class="btn-icon" data-remove-activity aria-label="حذف">−</button>
      </div>
    </template>
  </div>
    <?php
}

/**
 * @param list<string> $keys
 * @return list<array{category:string, items:list<string>}>
 */
function casting_group_activities_for_display(array $keys, int $profile_user_id = 0, int $viewer_id = 0): array
{
    $keys = casting_normalize_activities($keys, $profile_user_id);
    if ($profile_user_id > 0 && $viewer_id > 0) {
        $keys = casting_filter_activities_for_viewer($keys, $profile_user_id, $viewer_id);
    } elseif ($profile_user_id > 0) {
        $keys = casting_filter_activities_for_viewer($keys, $profile_user_id, 0);
    }
    if ($keys === []) {
        return [];
    }
    $grouped = [];
    $categories = casting_activity_categories();
    if ($profile_user_id > 0 && casting_user_is_portal_owner($profile_user_id) && casting_user_is_portal_owner($viewer_id)) {
        $categories['it_internal'] = [
            'label' => 'فناوری',
            'items' => casting_hidden_activity_labels(),
        ];
    }
    foreach ($categories as $cat_key => $cat) {
        $labels = [];
        foreach ($cat['items'] as $key => $label) {
            if (in_array($key, $keys, true)) {
                $labels[] = $label;
            }
        }
        if ($labels !== []) {
            $grouped[] = [
                'category' => $cat['label'],
                'items'    => $labels,
            ];
        }
    }
    return $grouped;
}
