<?php
declare(strict_types=1);

/**
 * فیلدهای پروفایل هنرجو در user_meta
 */
function casting_profile_fields(): array
{
    return [
        'casting_age'         => 'age',
        'casting_gender'      => 'gender',
        'casting_city'        => 'city',
        'casting_height'      => 'height',
        'casting_experience'  => 'experience',
        'casting_look'        => 'look',
        'casting_skills'      => 'skills',
        'casting_bio'         => 'bio',
        'casting_video_url'   => 'video_url',
        'casting_photo_id'    => 'photo_id',
        'casting_visible'     => 'visible',
    ];
}

function casting_gender_labels(): array
{
    return [
        'female' => 'زن',
        'male'   => 'مرد',
        'other'  => 'سایر',
    ];
}

function casting_get_profile(int $user_id): array
{
    $photo_id = (int) get_user_meta($user_id, 'casting_photo_id', true);
    $photo_url = $photo_id > 0 ? wp_get_attachment_image_url($photo_id, 'medium') : '';
    $photo_full = $photo_id > 0 ? wp_get_attachment_image_url($photo_id, 'large') : '';

    return [
        'age'        => (string) get_user_meta($user_id, 'casting_age', true),
        'gender'     => (string) get_user_meta($user_id, 'casting_gender', true),
        'city'       => (string) get_user_meta($user_id, 'casting_city', true),
        'height'     => (string) get_user_meta($user_id, 'casting_height', true),
        'experience' => (string) get_user_meta($user_id, 'casting_experience', true),
        'look'       => (string) get_user_meta($user_id, 'casting_look', true),
        'skills'     => (string) get_user_meta($user_id, 'casting_skills', true),
        'bio'        => (string) get_user_meta($user_id, 'casting_bio', true),
        'video_url'  => (string) get_user_meta($user_id, 'casting_video_url', true),
        'photo_id'   => $photo_id,
        'photo_url'  => is_string($photo_url) ? $photo_url : '',
        'photo_full' => is_string($photo_full) ? $photo_full : '',
        'visible'    => get_user_meta($user_id, 'casting_visible', true) !== '0',
    ];
}

function casting_save_profile(int $user_id, array $data): array
{
    $age = isset($data['age']) ? (int) $data['age'] : 0;
    if ($age < 5 || $age > 100) {
        return ['ok' => false, 'error' => 'سن باید بین ۵ تا ۱۰۰ باشد.'];
    }

    $gender = sanitize_key((string) ($data['gender'] ?? ''));
    if (!array_key_exists($gender, casting_gender_labels())) {
        return ['ok' => false, 'error' => 'جنسیت را انتخاب کنید.'];
    }

    $city = sanitize_text_field((string) ($data['city'] ?? ''));
    if ($city === '') {
        return ['ok' => false, 'error' => 'شهر را وارد کنید.'];
    }

    $height = (int) ($data['height'] ?? 0);
    if ($height < 80 || $height > 230) {
        return ['ok' => false, 'error' => 'قد باید بین ۸۰ تا ۲۳۰ سانتی‌متر باشد.'];
    }

    $experience = max(0, min(60, (int) ($data['experience'] ?? 0)));
    $look = sanitize_text_field((string) ($data['look'] ?? ''));
    $skills = sanitize_text_field((string) ($data['skills'] ?? ''));
    $bio = sanitize_textarea_field((string) ($data['bio'] ?? ''));
    $video_url = esc_url_raw((string) ($data['video_url'] ?? ''));
    $visible = !empty($data['visible']) ? '1' : '0';

    if ($video_url !== '' && !filter_var($video_url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'error' => 'لینک ویدیو معتبر نیست.'];
    }

    update_user_meta($user_id, 'casting_age', (string) $age);
    update_user_meta($user_id, 'casting_gender', $gender);
    update_user_meta($user_id, 'casting_city', $city);
    update_user_meta($user_id, 'casting_height', (string) $height);
    update_user_meta($user_id, 'casting_experience', (string) $experience);
    update_user_meta($user_id, 'casting_look', $look);
    update_user_meta($user_id, 'casting_skills', $skills);
    update_user_meta($user_id, 'casting_bio', $bio);
    update_user_meta($user_id, 'casting_video_url', $video_url);
    update_user_meta($user_id, 'casting_visible', $visible);

    return ['ok' => true];
}

function casting_handle_photo_upload(int $user_id): array
{
    if (empty($_FILES['photo']['name'])) {
        return ['ok' => true, 'skipped' => true];
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $ftype = (string) ($file['type'] ?? '');
    if (!in_array($ftype, $allowed, true)) {
        return ['ok' => false, 'error' => 'فقط عکس JPG، PNG یا WebP مجاز است.'];
    }
    if ((int) $file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'حجم عکس حداکثر ۵ مگابایت باشد.'];
    }

    $attachment_id = media_handle_upload('photo', 0);
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

/**
 * جستجوی هنرجویان قابل‌نمایش با فیلتر
 *
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

    if (!empty($filters['look'])) {
        $meta_query[] = [
            'key'     => 'casting_look',
            'value'   => sanitize_text_field($filters['look']),
            'compare' => 'LIKE',
        ];
    }

    $page = max(1, $page);
    $args = [
        'number'     => $per_page,
        'paged'      => $page,
        'orderby'    => 'registered',
        'order'      => 'DESC',
        'meta_query' => $meta_query,
        'count_total'=> true,
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
        && $profile['city'] !== ''
        && $profile['photo_id'] > 0;
}
