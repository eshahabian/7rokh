<?php
declare(strict_types=1);

require_once __DIR__ . '/jalali.php';

function casting_gender_labels(): array
{
    return [
        'female' => 'زن',
        'male'   => 'مرد',
    ];
}

function casting_look_labels(): array
{
    return [
        'gandoum' => 'گندم‌گون',
        'fair'    => 'سفید',
    ];
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

    return [
        'birthdate'       => (string) get_user_meta($user_id, 'casting_birthdate', true),
        'age'             => (string) get_user_meta($user_id, 'casting_age', true),
        'gender'          => (string) get_user_meta($user_id, 'casting_gender', true),
        'city'            => (string) get_user_meta($user_id, 'casting_city', true),
        'residence'       => (string) get_user_meta($user_id, 'casting_residence', true),
        'height'          => (string) get_user_meta($user_id, 'casting_height', true),
        'experience'      => (string) get_user_meta($user_id, 'casting_experience', true),
        'work_history'    => (string) get_user_meta($user_id, 'casting_work_history', true),
        'work_credits'    => casting_normalize_work_credits(get_user_meta($user_id, 'casting_work_credits', true)),
        'education'       => (string) get_user_meta($user_id, 'casting_education', true),
        'education_items' => casting_normalize_education_items(get_user_meta($user_id, 'casting_education_items', true)),
        'look'            => (string) get_user_meta($user_id, 'casting_look', true),
        'skills'          => (string) get_user_meta($user_id, 'casting_skills', true),
        'bio'             => (string) get_user_meta($user_id, 'casting_bio', true),
        'video_url'       => $video_url_meta,
        'photo_id'        => $photo_id,
        'video_id'        => $video_id,
        'photo_url'       => is_string($photo_url) ? $photo_url : '',
        'photo_full'      => is_string($photo_full) ? $photo_full : '',
        'video_file_url'  => is_string($video_url_file) ? $video_url_file : '',
        'visible'         => get_user_meta($user_id, 'casting_visible', true) !== '0',
    ];
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
        return ['ok' => false, 'error' => 'جنسیت را انتخاب کنید (زن یا مرد).'];
    }

    $look = sanitize_key((string) ($data['look'] ?? ''));
    if (!array_key_exists($look, casting_look_labels())) {
        return ['ok' => false, 'error' => 'چهره را انتخاب کنید (گندم‌گون یا سفید).'];
    }

    $city = sanitize_text_field((string) ($data['city'] ?? ''));
    if ($city === '') {
        return ['ok' => false, 'error' => 'شهر را وارد کنید.'];
    }

    $residence = sanitize_text_field((string) ($data['residence'] ?? ''));
    if ($residence === '') {
        return ['ok' => false, 'error' => 'محل سکونت را وارد کنید.'];
    }

    $experience = (int) ($data['experience'] ?? -1);
    if ($experience < 0 || $experience > 60) {
        return ['ok' => false, 'error' => 'تعداد سال سابقه را درست وارد کنید (۰ تا ۶۰).'];
    }

    $work = sanitize_textarea_field((string) ($data['work_history'] ?? ''));
    $education = sanitize_textarea_field((string) ($data['education'] ?? ''));
    $credits = casting_normalize_work_credits($data['work_credits'] ?? []);
    $edu_items = casting_normalize_education_items($data['education_items'] ?? []);

    update_user_meta($user_id, 'casting_birthdate', $birthdate);
    update_user_meta($user_id, 'casting_age', (string) $age);
    update_user_meta($user_id, 'casting_gender', $gender);
    update_user_meta($user_id, 'casting_look', $look);
    update_user_meta($user_id, 'casting_city', $city);
    update_user_meta($user_id, 'casting_residence', $residence);
    update_user_meta($user_id, 'casting_experience', (string) $experience);
    update_user_meta($user_id, 'casting_work_history', $work);
    update_user_meta($user_id, 'casting_work_credits', $credits);
    update_user_meta($user_id, 'casting_education', $education);
    update_user_meta($user_id, 'casting_education_items', $edu_items);
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

    $city = sanitize_text_field((string) ($data['city'] ?? ''));
    if ($city !== '') {
        update_user_meta($user_id, 'casting_city', $city);
    }

    $residence = sanitize_text_field((string) ($data['residence'] ?? ''));
    update_user_meta($user_id, 'casting_residence', $residence);

    if (isset($data['height']) && $data['height'] !== '') {
        $height = (int) $data['height'];
        if ($height < 80 || $height > 230) {
            return ['ok' => false, 'error' => 'قد باید بین ۸۰ تا ۲۳۰ سانتی‌متر باشد.'];
        }
        update_user_meta($user_id, 'casting_height', (string) $height);
    }

    if (isset($data['experience']) && $data['experience'] !== '') {
        $experience = max(0, min(60, (int) $data['experience']));
        update_user_meta($user_id, 'casting_experience', (string) $experience);
    }

    $look = sanitize_key((string) ($data['look'] ?? ''));
    if ($look !== '' && !array_key_exists($look, casting_look_labels())) {
        return ['ok' => false, 'error' => 'چهره را درست انتخاب کنید.'];
    }
    if ($look !== '') {
        update_user_meta($user_id, 'casting_look', $look);
    }
    update_user_meta($user_id, 'casting_skills', sanitize_text_field((string) ($data['skills'] ?? '')));
    update_user_meta($user_id, 'casting_bio', sanitize_textarea_field((string) ($data['bio'] ?? '')));
    update_user_meta($user_id, 'casting_work_history', sanitize_textarea_field((string) ($data['work_history'] ?? '')));
    update_user_meta($user_id, 'casting_work_credits', casting_normalize_work_credits($data['work_credits'] ?? []));
    update_user_meta($user_id, 'casting_education', sanitize_textarea_field((string) ($data['education'] ?? '')));
    update_user_meta($user_id, 'casting_education_items', casting_normalize_education_items($data['education_items'] ?? []));

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

    if (isset($filters['age_min']) && $filters['age_min'] !== '' && $filters['age_min'] !== null) {
        $meta_query[] = [
            'key'     => 'casting_age',
            'value'   => (int) $filters['age_min'],
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ];
    }

    if (isset($filters['age_max']) && $filters['age_max'] !== '' && $filters['age_max'] !== null) {
        $meta_query[] = [
            'key'     => 'casting_age',
            'value'   => (int) $filters['age_max'],
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

    return [
        'users' => is_array($users) ? $users : [],
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
        casting_set_flash('error', 'برای ورود به تالار گفتگو ابتدا وارد شوید.');
        casting_redirect('login-talent.php');
    }
    $role = casting_get_user_role((int) $user->ID);
    if ($role === '') {
        casting_set_flash('error', 'فقط اعضای هفت رخ می‌توانند وارد تالار شوند.');
        casting_redirect('index.php');
    }
    return $user;
}
