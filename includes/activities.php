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
                'director'            => 'کارگردان',
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
                'camera_first_assistant'  => 'دستیار یک',
                'camera_second_assistant' => 'دستیار دو',
                'camera_third_assistant'  => 'دستیار سه',
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
                'sound_recordist' => 'صدابردار',
                'sound_mixer'     => 'مدیر صدابرداری',
                'boom_op'         => 'بوم‌من',
                'sound_editor'    => 'صداگذار',
                'sound_designer'  => 'طراح صدا',
                'dubber'          => 'دوبلور',
                'voice_artist'    => 'گوینده',
            ],
        ],
        'post' => [
            'label' => 'تدوین و پس‌تولید',
            'items' => [
                'editor'      => 'تدوینگر',
                'colorist'    => 'اصلاح رنگ (کالرست)',
                'vfx'         => 'جلوه‌های ویژه بصری (VFX)',
                'motion'      => 'موشن گرافیست',
                'animator'    => 'انیماتور',
            ],
        ],
        'art' => [
            'label' => 'طراح هنری',
            'items' => [
                'set_designer'     => 'طراح صحنه',
                'decor_designer'   => 'طراح دکور',
                'art_director'     => 'مدیر هنری',
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
                'composer'   => 'آهنگساز',
                'arranger'   => 'تنظیم‌کننده',
                'musician'   => 'نوازنده',
                'singer'     => 'خواننده',
                'conductor'  => 'رهبر ارکستر',
            ],
        ],
        'promo' => [
            'label' => 'عکاسی و تبلیغات',
            'items' => [
                'still_photographer' => 'عکاس صحنه',
                'poster_designer'    => 'طراح پوستر',
                'graphic_designer'   => 'گرافیست',
                'promo_manager'      => 'مدیر تبلیغات',
                'pr_manager'         => 'مدیر روابط عمومی',
            ],
        ],
        'set_crew' => [
            'label' => 'عوامل صحنه',
            'items' => [
                'stage_manager'     => 'مدیر صحنه',
                'stage_assistant'   => 'دستیار صحنه',
                'wardrobe'          => 'مسئول لباس',
                'makeup_dept'       => 'مسئول گریم',
                'set_deco'          => 'مسئول دکور',
                'props'             => 'مسئول وسایل صحنه',
            ],
        ],
        'media' => [
            'label' => 'اجرا و رسانه',
            'items' => [
                'host'      => 'مجری',
                'announcer' => 'گوینده',
                'reporter'  => 'خبرنگار',
                'critic'    => 'منتقد سینما و تئاتر',
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
 * کلید تخصص → کلید دسته
 */
function casting_activity_category_for_specialty(string $specialty): string
{
    foreach (casting_activity_categories() as $cat_key => $cat) {
        if (isset($cat['items'][$specialty])) {
            return $cat_key;
        }
    }
    return '';
}

/**
 * @param mixed $raw
 * @return list<string>
 */
function casting_normalize_activities($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $labels = casting_activity_labels();
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
function casting_activities_to_rows(array $keys): array
{
    $keys = casting_normalize_activities($keys);
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
function casting_parse_activities_post(array $post): array
{
    $raw = $post['activity_items'] ?? $post['activities'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $categories = casting_activity_categories();
    $out = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            $key = sanitize_key((string) $item);
            if ($key !== '' && isset(casting_activity_labels()[$key]) && !in_array($key, $out, true)) {
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
        $category = casting_activity_category_for_specialty($activity);
        if (in_array($category, ['directing', 'production'], true)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $selected
 */
function casting_render_activity_fields(array $selected = [], bool $required = true): void
{
    $rows = casting_activities_to_rows($selected);
    $categories = casting_activity_categories();
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
function casting_group_activities_for_display(array $keys): array
{
    $keys = casting_normalize_activities($keys);
    if ($keys === []) {
        return [];
    }
    $grouped = [];
    foreach (casting_activity_categories() as $cat_key => $cat) {
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
