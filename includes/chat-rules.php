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
        return ['director_cinema'];
    }
    if ($role === 'producer') {
        return ['producer'];
    }
    if ($role === 'talent') {
        return ['actor_cinema'];
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

    // همه اعضا می‌توانند به مدیران رسمی (eshahabian / ardavan) پیام بدهند
    if (casting_user_is_public_support_contact($to_id)) {
        return ['ok' => true, 'error' => ''];
    }

    $from_role = casting_get_user_role($from_id);
    $to_role = casting_get_user_role($to_id);
    if ($from_role === '' || $to_role === '') {
        return ['ok' => false, 'error' => 'فقط اعضای ۷ رخ می‌توانند چت کنند.'];
    }

    if (!function_exists('casting_dm_thread_is_closed')) {
        require_once __DIR__ . '/chat.php';
    }

    // گفتگوی بسته‌شده: فقط کسی که حق بازگشایی دارد
    if (casting_dm_thread_is_closed($from_id, $to_id)) {
        if (casting_dm_can_reopen_thread($from_id, $to_id)) {
            return ['ok' => true, 'error' => ''];
        }

        return [
            'ok'    => false,
            'error' => 'این گفتگو بسته شده است. فقط طرف مقابل می‌تواند با پیام جدید دوباره آن را باز کند.',
        ];
    }

    // طرف مقابل قبلاً پیام داده → اجازه پاسخ (تا وقتی نبسته باشد)
    if (casting_dm_user_has_sent_to($to_id, $from_id)) {
        return ['ok' => true, 'error' => ''];
    }

    return casting_message_access_allows_start($from_id, $to_id);
}

/**
 * ارسال/ادامه گفتگو — جدول دسترسی + محدودیت پروژه/رابطه.
 *
 * @return array{ok:bool,error:string}
 */
function casting_can_users_chat(int $from_id, int $to_id): array
{
    return casting_can_start_chat($from_id, $to_id);
}

/**
 * دیدن در لیست مخاطب / دکمه پیام / باز کردن صفحه چت.
 * اگر «دسترسی پیام» روشن باشد کافی است؛ محدودیت پروژه فقط هنگام ارسال اعمال می‌شود.
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
    if (!function_exists('casting_user_is_super_admin')) {
        require_once __DIR__ . '/admin-access.php';
    }
    if ((casting_user_is_portal_owner($from_id) || casting_user_is_super_admin($from_id))
        && casting_get_user_role($to_id) !== '') {
        return ['ok' => true, 'error' => ''];
    }
    // مدیران رسمی برای همه قابل پیام هستند
    if (casting_user_is_public_support_contact($to_id) && casting_get_user_role($from_id) !== '') {
        return ['ok' => true, 'error' => ''];
    }
    if (casting_get_user_role($from_id) === '' || casting_get_user_role($to_id) === '') {
        return ['ok' => false, 'error' => 'فقط اعضای ۷ رخ می‌توانند چت کنند.'];
    }

    if (casting_message_access_has_enabled_edge($from_id, $to_id)) {
        return ['ok' => true, 'error' => ''];
    }

    return [
        'ok'    => false,
        'error' => 'طبق جدول دسترسی پیام‌رسان، امکان ارسال پیام نیست.',
    ];
}

/**
 * مدیران رسمی سایت که همه اعضا می‌توانند به آن‌ها پیام بدهند
 * (همان حساب‌های صفحه رسمی / فالو الزامی)
 */
function casting_user_is_public_support_contact(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (casting_user_is_portal_owner($user_id)) {
        return true;
    }
    if (function_exists('casting_dm_is_support_peer') && casting_dm_is_support_peer($user_id)) {
        return true;
    }
    if (!function_exists('casting_follow_target_is_required')) {
        $follows = __DIR__ . '/follows.php';
        if (is_file($follows)) {
            require_once $follows;
        }
    }
    if (function_exists('casting_follow_target_is_required')) {
        return casting_follow_target_is_required($user_id);
    }

    return false;
}

/**
 * دکمه پیام روی کارت — اگر دسترسی نباشد غیرفعال نشان داده می‌شود (مخفی نمی‌شود)
 */
function casting_render_member_message_button(int $viewer_id, int $target_id, string $display_name = ''): void
{
    if ($viewer_id <= 0 || $target_id <= 0 || $viewer_id === $target_id) {
        return;
    }
    $allow = casting_can_user_open_dm($viewer_id, $target_id);
    $label = 'پیام';
    $aria = $display_name !== '' ? ('پیام به ' . $display_name) : 'پیام';
    if (!empty($allow['ok'])) {
        ?>
<a class="btn btn-ghost btn-sm member-card-msg" href="chat.php?with=<?= (int) $target_id ?>" title="<?= casting_e($aria) ?>" aria-label="<?= casting_e($aria) ?>"><?= casting_e($label) ?></a>
        <?php
        return;
    }
    $reason = trim((string) ($allow['error'] ?? ''));
    if ($reason === '') {
        $reason = 'طبق جدول دسترسی پیام‌رسان، امکان ارسال پیام نیست.';
    }
    ?>
<button type="button" class="btn btn-ghost btn-sm member-card-msg is-disabled" disabled title="<?= casting_e($reason) ?>" aria-label="<?= casting_e($reason) ?>"><?= casting_e($label) ?></button>
    <?php
}

/**
 * بخش «پیام به این کاربر» روی پروفایل کامل
 *
 * @param array{ok?:bool,error?:string} $allow
 */
function casting_render_profile_message_cta(int $viewer_id, int $target_id, string $display_name = '', array $allow = []): void
{
    if ($viewer_id <= 0 || $target_id <= 0 || $viewer_id === $target_id) {
        return;
    }
    if ($allow === []) {
        $allow = casting_can_user_open_dm($viewer_id, $target_id);
    }
    $ok = !empty($allow['ok']);
    $reason = trim((string) ($allow['error'] ?? ''));
    if ($reason === '') {
        $reason = 'طبق جدول دسترسی پیام‌رسان، امکان ارسال پیام نیست.';
    }
    $aria = $display_name !== '' ? ('پیام به ' . $display_name) : 'پیام به این کاربر';
    ?>
<section class="profile-message-section" aria-labelledby="profile-message-heading">
  <h3 id="profile-message-heading" class="profile-message-title">پیام به این کاربر</h3>
  <p class="meta">اگر بعد از دیدن پروفایل خواستید گفتگو کنید، از اینجا پیام بدهید.</p>
  <?php if ($ok) : ?>
    <a class="btn btn-primary" href="chat.php?with=<?= (int) $target_id ?>" aria-label="<?= casting_e($aria) ?>">ارسال پیام</a>
  <?php else : ?>
    <button type="button" class="btn btn-primary is-disabled" disabled title="<?= casting_e($reason) ?>" aria-label="<?= casting_e($reason) ?>">ارسال پیام</button>
    <p class="field-hint"><?= casting_e($reason) ?></p>
  <?php endif; ?>
</section>
    <?php
}
