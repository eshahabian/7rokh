<?php
declare(strict_types=1);

require_once __DIR__ . '/activities.php';
require_once __DIR__ . '/blocks.php';
require_once __DIR__ . '/message-access.php';

/**
 * @return list<string>
 */
function casting_user_specialty_keys(int $user_id): array
{
    return casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true), $user_id);
}

/**
 * تخصص‌های مؤثر برای قوانین چت — اگر نوع فعالیت خالی باشد از نقش پورتال استفاده می‌شود.
 *
 * @return list<string>
 */
function casting_chat_specialty_keys(int $user_id): array
{
    $keys = casting_user_specialty_keys($user_id);
    if ($keys !== []) {
        return $keys;
    }

    $role = casting_get_user_role($user_id);
    if ($role === 'director') {
        // بدون تخصص ذخیره‌شده: همهٔ انواع کارگردان را برای تطبیق ماتریس در نظر بگیر
        return casting_message_access_director_keys();
    }
    if ($role === 'producer') {
        return ['producer'];
    }
    if ($role === 'talent') {
        return casting_message_access_actor_keys();
    }

    return ['activity_none'];
}

function casting_user_has_specialty(int $user_id, string $specialty): bool
{
    return in_array($specialty, casting_chat_specialty_keys($user_id), true);
}

/**
 * @param list<string> $needles
 */
function casting_user_has_any_specialty(int $user_id, array $needles): bool
{
    if ($needles === []) {
        return false;
    }

    return array_intersect(casting_chat_specialty_keys($user_id), $needles) !== [];
}

function casting_user_is_actor(int $user_id): bool
{
    return casting_activities_has_acting(casting_chat_specialty_keys($user_id));
}

/**
 * @return list<string>
 */
function casting_director_specialty_keys(): array
{
    return casting_message_access_director_keys();
}

function casting_user_is_director(int $user_id): bool
{
    return casting_user_has_any_specialty($user_id, casting_director_specialty_keys());
}

function casting_user_is_film_director(int $user_id): bool
{
    $keys = casting_chat_specialty_keys($user_id);
    if (array_intersect($keys, ['director_cinema', 'director_short_film']) !== []) {
        return true;
    }
    if (array_intersect($keys, ['director_theater', 'director_tv']) !== []) {
        return false;
    }

    return casting_get_user_role($user_id) === 'director';
}

function casting_user_is_producer_for_chat(int $user_id): bool
{
    if (casting_user_has_specialty($user_id, 'producer')) {
        return true;
    }

    return casting_get_user_role($user_id) === 'producer';
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_can_start_chat(int $from_id, int $to_id): array
{
    if ($from_id <= 0 || $to_id <= 0) {
        return ['ok' => false, 'error' => 'کاربر معتبر نیست.'];
    }
    if ($from_id === $to_id) {
        return ['ok' => false, 'error' => 'نمی‌توانید با خودتان چت کنید.'];
    }

    if (casting_users_block_each_other($from_id, $to_id)) {
        return ['ok' => false, 'error' => 'به‌دلیل بلاک، امکان گفتگو وجود ندارد.'];
    }

    // فقط مالک پورتال (eshahabian) از قوانین سمتی معاف است
    if (casting_user_is_portal_owner($from_id)) {
        $to_role = casting_get_user_role($to_id);
        if ($to_role !== '') {
            return ['ok' => true, 'error' => ''];
        }
    }

    $from_role = casting_get_user_role($from_id);
    $to_role = casting_get_user_role($to_id);
    if ($from_role === '' || $to_role === '') {
        return ['ok' => false, 'error' => 'فقط اعضای ۷ رخ می‌توانند چت کنند.'];
    }

    return casting_message_access_allows_start($from_id, $to_id);
}

/**
 * پاسخ به گفتگوی موجود همیشه مجاز است؛ شروع گفتگو تابع قوانین سلسله‌مراتبی است.
 *
 * @return array{ok:bool,error:string}
 */
function casting_can_users_chat(int $from_id, int $to_id): array
{
    if (!function_exists('casting_dm_has_conversation')) {
        require_once __DIR__ . '/chat.php';
    }

    if (casting_user_is_portal_owner($from_id)) {
        if (casting_users_block_each_other($from_id, $to_id)) {
            return ['ok' => false, 'error' => 'به‌دلیل بلاک، امکان گفتگو وجود ندارد.'];
        }
        if ($from_id <= 0 || $to_id <= 0 || $from_id === $to_id) {
            return ['ok' => false, 'error' => 'کاربر معتبر نیست.'];
        }
        if (casting_get_user_role($to_id) !== '') {
            return ['ok' => true, 'error' => ''];
        }
    }

    if (casting_dm_has_conversation($from_id, $to_id)) {
        if (casting_users_block_each_other($from_id, $to_id)) {
            return ['ok' => false, 'error' => 'به‌دلیل بلاک، امکان گفتگو وجود ندارد.'];
        }
        if ($from_id <= 0 || $to_id <= 0 || $from_id === $to_id) {
            return ['ok' => false, 'error' => 'کاربر معتبر نیست.'];
        }

        return ['ok' => true, 'error' => ''];
    }

    return casting_can_start_chat($from_id, $to_id);
}

/**
 * دیدن مخاطب / دکمه پیام / باز کردن صفحه چت.
 * اگر در جدول دسترسی لبه روشن باشد دیده می‌شود؛ «فقط با پروژه» فقط ارسال را محدود می‌کند.
 *
 * @return array{ok:bool,error:string}
 */
function casting_can_user_open_dm(int $from_id, int $to_id): array
{
    if ($from_id <= 0 || $to_id <= 0) {
        return ['ok' => false, 'error' => 'کاربر معتبر نیست.'];
    }
    if ($from_id === $to_id) {
        return ['ok' => false, 'error' => 'نمی‌توانید با خودتان چت کنید.'];
    }
    if (casting_users_block_each_other($from_id, $to_id)) {
        return ['ok' => false, 'error' => 'به‌دلیل بلاک، امکان گفتگو وجود ندارد.'];
    }
    if (casting_user_is_portal_owner($from_id) && casting_get_user_role($to_id) !== '') {
        return ['ok' => true, 'error' => ''];
    }
    if (casting_get_user_role($from_id) === '' || casting_get_user_role($to_id) === '') {
        return ['ok' => false, 'error' => 'فقط اعضای ۷ رخ می‌توانند چت کنند.'];
    }

    if (!function_exists('casting_dm_has_conversation')) {
        require_once __DIR__ . '/chat.php';
    }
    if (casting_dm_has_conversation($from_id, $to_id)) {
        return ['ok' => true, 'error' => ''];
    }

    if (casting_message_access_has_enabled_edge($from_id, $to_id)) {
        return ['ok' => true, 'error' => ''];
    }

    return casting_can_start_chat($from_id, $to_id);
}
