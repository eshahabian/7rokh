<?php
declare(strict_types=1);

require_once __DIR__ . '/activities.php';
require_once __DIR__ . '/blocks.php';

/**
 * @return list<string>
 */
function casting_user_specialty_keys(int $user_id): array
{
    return casting_normalize_activities(get_user_meta($user_id, 'casting_activities', true));
}

function casting_user_has_specialty(int $user_id, string $specialty): bool
{
    return in_array($specialty, casting_user_specialty_keys($user_id), true);
}

/**
 * @param list<string> $needles
 */
function casting_user_has_any_specialty(int $user_id, array $needles): bool
{
    if ($needles === []) {
        return false;
    }
    return array_intersect(casting_user_specialty_keys($user_id), $needles) !== [];
}

function casting_user_is_actor(int $user_id): bool
{
    return casting_activities_has_acting(casting_user_specialty_keys($user_id));
}

/**
 * @return list<string>
 */
function casting_director_specialty_keys(): array
{
    return [
        'director_theater',
        'director_short_film',
        'director_tv',
        'director_cinema',
    ];
}

function casting_user_is_director(int $user_id): bool
{
    return casting_user_has_any_specialty($user_id, casting_director_specialty_keys());
}

function casting_user_is_film_director(int $user_id): bool
{
    return casting_user_has_any_specialty($user_id, ['director_cinema', 'director_short_film']);
}

function casting_user_is_producer_for_chat(int $user_id): bool
{
    if (casting_user_has_specialty($user_id, 'producer')) {
        return true;
    }

    return casting_get_user_role($user_id) === 'producer';
}

/**
 * مدیر هر بخش (برای شروع چت)
 *
 * @return array<string, list<string>>
 */
function casting_chat_guild_head_keys(): array
{
    return [
        'directing'  => casting_director_specialty_keys(),
        'writing'    => ['writer', 'playwright', 'screenwriter'],
        'production' => ['producer', 'production_manager', 'logistics_manager', 'executive'],
        'camera'     => ['dop'],
        'sound'      => ['sound_mixer'],
        'post'       => ['editor', 'colorist'],
        'music'      => ['composer'],
        'art'        => ['set_designer', 'costume_designer', 'makeup_designer'],
        'lighting'   => ['lighting_designer'],
        'set_crew'   => ['stage_manager'],
    ];
}

function casting_is_guild_head_specialty(string $specialty): bool
{
    foreach (casting_chat_guild_head_keys() as $heads) {
        if (in_array($specialty, $heads, true)) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<string>
 */
function casting_all_guild_head_specialty_keys(): array
{
    $keys = [];
    foreach (casting_chat_guild_head_keys() as $heads) {
        foreach ($heads as $head) {
            $keys[] = $head;
        }
    }

    return array_values(array_unique($keys));
}

/**
 * مخاطبان مجاز تهیه‌کننده (بدون بازیگر)
 *
 * @return list<string>
 */
function casting_producer_message_target_keys(): array
{
    return array_values(array_unique(array_merge(
        ['writer', 'playwright', 'screenwriter', 'script_consultant', 'researcher'],
        casting_director_specialty_keys(),
        ['first_ad', 'second_ad', 'third_ad', 'scheduler', 'script_supervisor'],
        ['production_manager', 'logistics_manager', 'executive', 'production_assistant', 'logistics_assistant', 'logistics_driver'],
        ['dop'],
        ['sound_mixer', 'sound_editor'],
        ['editor', 'colorist', 'vfx', 'motion', 'animator'],
        ['composer', 'musician', 'singer'],
        ['set_designer', 'costume_designer', 'makeup_designer', 'makeup_artist', 'art_assistant'],
        ['lighting_designer', 'gaffer'],
        ['stage_manager', 'stage_assistant', 'set_deco', 'props'],
        casting_all_guild_head_specialty_keys()
    )));
}

/**
 * @return list<string>
 */
function casting_director_allowed_directing_sub_keys(): array
{
    return ['first_ad', 'scheduler', 'script_supervisor'];
}

/**
 * محدودیت‌های کارگردان هنگام شروع چت
 */
function casting_director_start_block_reason(int $from_id, int $to_id): string
{
    if (!casting_user_is_director($from_id)) {
        return '';
    }

    foreach (casting_user_specialty_keys($to_id) as $target) {
        if (in_array($target, casting_director_specialty_keys(), true)) {
            return 'کارگردان نمی‌تواند به کارگردان دیگر پیام بدهد.';
        }
        if (in_array($target, ['second_ad', 'third_ad'], true)) {
            return 'کارگردان نمی‌تواند به دستیار دوم یا سوم کارگردان پیام بدهد.';
        }
        $guild = casting_activity_category_for_specialty($target);
        if ($guild === 'camera' && $target !== 'dop') {
            return 'کارگردان فقط می‌تواند به مدیر فیلمبرداری پیام بدهد، نه زیرمجموعه فیلمبرداری.';
        }
        if ($guild === 'sound' && $target !== 'sound_mixer') {
            return 'کارگردان فقط می‌تواند به مدیر صدابرداری پیام بدهد، نه دستیار صدا.';
        }
        if ($guild === 'directing' && !in_array($target, casting_director_allowed_directing_sub_keys(), true)) {
            return 'در بخش کارگردانی فقط دستیار اول، برنامه‌ریز و منشی صحنه مجازند.';
        }
    }

    return '';
}

/**
 * مدیر بخش → زیرمجموعه همان بخش
 */
function casting_section_head_allows_start(int $from_id, int $to_id): bool
{
    $from_specs = casting_user_specialty_keys($from_id);
    $to_specs = casting_user_specialty_keys($to_id);
    if ($from_specs === [] || $to_specs === []) {
        return false;
    }

    foreach ($from_specs as $from_spec) {
        if (!casting_is_guild_head_specialty($from_spec)) {
            continue;
        }
        $guild = casting_activity_category_for_specialty($from_spec);
        if ($guild === '') {
            continue;
        }
        foreach ($to_specs as $to_spec) {
            if (casting_activity_category_for_specialty($to_spec) !== $guild) {
                continue;
            }
            if (casting_is_guild_head_specialty($to_spec)) {
                continue;
            }

            return true;
        }
    }

    return false;
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

    if (casting_user_is_portal_owner($from_id)) {
        $to_role = casting_get_user_role($to_id);
        if ($to_role !== '') {
            return ['ok' => true, 'error' => ''];
        }
    }

    $from_role = casting_get_user_role($from_id);
    $to_role = casting_get_user_role($to_id);
    if ($from_role === '' || $to_role === '') {
        return ['ok' => false, 'error' => 'فقط اعضای هفت رخ می‌توانند چت کنند.'];
    }

    if (!function_exists('casting_user_is_premium')) {
        require_once __DIR__ . '/premium.php';
    }
    if (casting_user_is_premium($from_id)) {
        return ['ok' => true, 'error' => ''];
    }

    $to_is_actor = casting_user_is_actor($to_id);
    $producer_targets = casting_producer_message_target_keys();

    // تهیه‌کننده
    if (casting_user_is_producer_for_chat($from_id)) {
        if ($to_is_actor) {
            return ['ok' => false, 'error' => 'تهیه‌کننده نمی‌تواند به بازیگران پیام بدهد.'];
        }
        if (casting_user_has_any_specialty($to_id, $producer_targets)) {
            return ['ok' => true, 'error' => ''];
        }
    }

    // کارگردان سینما / فیلم کوتاه
    if (casting_user_is_film_director($from_id)) {
        $block = casting_director_start_block_reason($from_id, $to_id);
        if ($block !== '') {
            return ['ok' => false, 'error' => $block];
        }
        if ($to_is_actor || casting_user_has_any_specialty($to_id, $producer_targets)) {
            return ['ok' => true, 'error' => ''];
        }
    }

    // سایر کارگردان‌ها (تئاتر، تلویزیون)
    if (casting_user_is_director($from_id) && !casting_user_is_film_director($from_id)) {
        $block = casting_director_start_block_reason($from_id, $to_id);
        if ($block !== '') {
            return ['ok' => false, 'error' => $block];
        }
        if (!$to_is_actor && casting_user_has_any_specialty($to_id, $producer_targets)) {
            return ['ok' => true, 'error' => ''];
        }
    }

    // دستیار اول کارگردان → بازیگر
    if (casting_user_has_specialty($from_id, 'first_ad') && $to_is_actor) {
        return ['ok' => true, 'error' => ''];
    }

    // مدیر هر بخش → زیرمجموعه همان بخش
    if (casting_section_head_allows_start($from_id, $to_id)) {
        return ['ok' => true, 'error' => ''];
    }

    return ['ok' => false, 'error' => 'طبق قوانین پورتال، امکان شروع گفتگو با این کاربر وجود ندارد.'];
}

/**
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
