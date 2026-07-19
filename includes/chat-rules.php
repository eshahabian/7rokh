<?php
declare(strict_types=1);

require_once __DIR__ . '/activities.php';
require_once __DIR__ . '/blocks.php';

/**
 * تخصص‌هایی که تهیه‌کننده اجازه چت با آن‌ها را دارد
 *
 * @return list<string>
 */
function casting_producer_chat_allow_specialties(): array
{
    return [
        'director',
        'director_theater',
        'director_short_film',
        'director_tv',
        'director_cinema',
        'first_ad',
        'second_ad',
        'third_ad',
        'executive',
        'production_manager',
        'logistics_manager',
    ];
}

/**
 * @return list<string>
 */
function casting_user_specialty_keys(int $user_id): array
{
    return casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true));
}

/**
 * دسته‌های صنفی کاربر (مثلاً acting، production)
 *
 * @return list<string>
 */
function casting_user_guild_keys(int $user_id): array
{
    $specialties = casting_user_specialty_keys($user_id);
    if ($specialties === []) {
        return [];
    }
    $guilds = [];
    foreach (casting_activity_categories() as $guild => $cat) {
        foreach (array_keys($cat['items']) as $key) {
            if (in_array($key, $specialties, true)) {
                $guilds[] = $guild;
                break;
            }
        }
    }
    return $guilds;
}

function casting_user_has_specialty(int $user_id, string $specialty): bool
{
    return in_array($specialty, casting_user_specialty_keys($user_id), true);
}

function casting_user_is_producer_for_chat(int $user_id): bool
{
    if (casting_user_has_specialty($user_id, 'producer')) {
        return true;
    }
    return casting_get_user_role($user_id) === 'producer';
}

/**
 * آیا کاربر B یکی از مخاطبان مجاز تهیه‌کننده است؟
 */
function casting_user_is_producer_chat_target(int $user_id): bool
{
    $allow = casting_producer_chat_allow_specialties();
    foreach (casting_user_specialty_keys($user_id) as $key) {
        if (in_array($key, $allow, true)) {
            return true;
        }
    }
    // نقش پورتال کارگردان هم معادل کارگردان تخصصی
    return casting_get_user_role($user_id) === 'director';
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_can_users_chat(int $from_id, int $to_id): array
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

    $from_role = casting_get_user_role($from_id);
    $to_role = casting_get_user_role($to_id);
    if ($from_role === '' || $to_role === '') {
        return ['ok' => false, 'error' => 'فقط اعضای هفت رخ می‌توانند چت کنند.'];
    }

    $from_specs = casting_user_specialty_keys($from_id);
    $to_specs = casting_user_specialty_keys($to_id);

    // استثنای تهیه‌کننده (دوطرفه)
    $producer_ok = (
        (casting_user_is_producer_for_chat($from_id) && casting_user_is_producer_chat_target($to_id))
        || (casting_user_is_producer_for_chat($to_id) && casting_user_is_producer_chat_target($from_id))
    );
    if ($producer_ok) {
        return ['ok' => true, 'error' => ''];
    }

    // تخصص یکسان → ممنوع
    if (array_intersect($from_specs, $to_specs) !== []) {
        return ['ok' => false, 'error' => 'هم‌صنف‌ها نمی‌توانند با یکدیگر چت کنند.'];
    }

    // دسته صنفی یکسان → ممنوع
    $from_guilds = casting_user_guild_keys($from_id);
    $to_guilds = casting_user_guild_keys($to_id);
    if ($from_guilds !== [] && $to_guilds !== [] && array_intersect($from_guilds, $to_guilds) !== []) {
        return ['ok' => false, 'error' => 'هم‌صنف‌ها نمی‌توانند با یکدیگر چت کنند.'];
    }

    // اگر هنوز تخصص ثبت نکرده‌اند، بر اساس نقش پورتال
    if ($from_guilds === [] || $to_guilds === []) {
        if ($from_role === $to_role) {
            return ['ok' => false, 'error' => 'هم‌صنف‌ها نمی‌توانند با یکدیگر چت کنند.'];
        }
        if (
            casting_is_employer_role($from_role)
            && casting_is_employer_role($to_role)
            && $from_role === $to_role
        ) {
            return ['ok' => false, 'error' => 'هم‌صنف‌ها نمی‌توانند با یکدیگر چت کنند.'];
        }
    }

    return ['ok' => true, 'error' => ''];
}
