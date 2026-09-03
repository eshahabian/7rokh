<?php
declare(strict_types=1);

const CASTING_PROFILE_SMS_META_SENT_AT = 'casting_profile_sms_reminder_at';
const CASTING_PROFILE_SMS_META_REF = 'casting_profile_sms_reminder_ref';

function casting_profile_completion_sms_is_enabled(): bool
{
    if (defined('CASTING_PROFILE_SMS_REMINDER_ENABLED') && !CASTING_PROFILE_SMS_REMINDER_ENABLED) {
        return false;
    }
    if (!function_exists('casting_sms_is_configured')) {
        require_once __DIR__ . '/sms.php';
    }

    return casting_sms_is_configured();
}

function casting_profile_completion_sms_cooldown_days(): int
{
    $days = defined('CASTING_PROFILE_SMS_REMINDER_COOLDOWN_DAYS')
        ? (int) CASTING_PROFILE_SMS_REMINDER_COOLDOWN_DAYS
        : 7;

    return max(1, min(90, $days));
}

function casting_profile_completion_sms_threshold_percent(): int
{
    $threshold = defined('CASTING_PROFILE_SMS_REMINDER_THRESHOLD')
        ? (int) CASTING_PROFILE_SMS_REMINDER_THRESHOLD
        : 75;

    return max(1, min(99, $threshold));
}

/**
 * @return list<string>
 */
function casting_profile_completion_sms_always_send_logins(): array
{
    $logins = ['ardvan', 'eshahabian'];
    if (defined('CASTING_PROFILE_SMS_REMINDER_ALWAYS_SEND_LOGINS') && is_array(CASTING_PROFILE_SMS_REMINDER_ALWAYS_SEND_LOGINS)) {
        $logins = CASTING_PROFILE_SMS_REMINDER_ALWAYS_SEND_LOGINS;
    }
    $out = [];
    foreach ($logins as $login) {
        $login = strtolower(trim((string) $login));
        if ($login !== '') {
            $out[] = $login;
        }
    }

    return array_values(array_unique($out));
}

function casting_profile_completion_sms_is_always_send_user(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }

    return in_array(strtolower((string) $user->user_login), casting_profile_completion_sms_always_send_logins(), true);
}

/**
 * @return list<int>
 */
function casting_profile_completion_sms_always_send_user_ids(): array
{
    $ids = [];
    foreach (casting_profile_completion_sms_always_send_logins() as $login) {
        $user = get_user_by('login', $login);
        if ($user instanceof WP_User) {
            $ids[] = (int) $user->ID;
        }
    }

    return array_values(array_unique($ids));
}

function casting_profile_completion_sms_link(): string
{
    if (defined('CASTING_PROFILE_SMS_REMINDER_LINK') && trim((string) CASTING_PROFILE_SMS_REMINDER_LINK) !== '') {
        return trim((string) CASTING_PROFILE_SMS_REMINDER_LINK);
    }
    if (function_exists('casting_portal_public_origin')) {
        $origin = casting_portal_public_origin();
    } elseif (defined('CASTING_MAIN_SITE_URL')) {
        $origin = rtrim((string) CASTING_MAIN_SITE_URL, '/');
    } else {
        $origin = 'https://7rokh.com';
    }
    $host = (string) (parse_url($origin, PHP_URL_HOST) ?: '7rokh.com');

    return $host . '/casting-portal';
}

function casting_profile_completion_sms_message(): string
{
    return casting_profile_completion_sms_message_body(true);
}

function casting_profile_completion_sms_message_body(bool $with_link = true): string
{
    $body = "پروفایل شما در «۷رخ» هنوز کامل نشده!\n"
        . "با تکمیل پروفایل، شانس دیده‌شدن در پروژه‌های سینما و تئاتر را بیشتر کنید.";
    if ($with_link) {
        $body .= "\n" . casting_profile_completion_sms_link();
    }

    return $body;
}

function casting_profile_completion_sms_pattern_id(): string
{
    return defined('CASTING_PROFILE_SMS_REMINDER_PATTERN_ID')
        ? trim((string) CASTING_PROFILE_SMS_REMINDER_PATTERN_ID)
        : '';
}

/**
 * @return array{ok:bool,error:string,ref_id:string,code:int,variant:string}
 */
function casting_profile_completion_sms_deliver(string $mobile): array
{
    if (!function_exists('casting_sms_send_text')) {
        require_once __DIR__ . '/sms.php';
    }

    $pattern_id = casting_profile_completion_sms_pattern_id();
    if ($pattern_id !== '') {
        $pattern = casting_sms_send_pattern_fixed($mobile, $pattern_id);
        if (!empty($pattern['ok'])) {
            return [
                'ok'      => true,
                'error'   => '',
                'ref_id'  => (string) ($pattern['ref_id'] ?? ''),
                'code'    => (int) ($pattern['code'] ?? 0),
                'variant' => 'pattern',
            ];
        }
    }

    $with_link = !(defined('CASTING_PROFILE_SMS_REMINDER_INCLUDE_LINK') && !CASTING_PROFILE_SMS_REMINDER_INCLUDE_LINK);
    $message = casting_profile_completion_sms_message_body($with_link);
    $result = casting_sms_send_text($mobile, $message);
    $code = (int) ($result['code'] ?? 0);
    if (!empty($result['ok'])) {
        return [
            'ok'      => true,
            'error'   => '',
            'ref_id'  => (string) ($result['ref_id'] ?? ''),
            'code'    => $code,
            'variant' => $with_link ? 'text_with_link' : 'text',
        ];
    }

    // لینک در پیامک تبلیغاتی اغلب فیلتر می‌شود (کد ۷)
    if ($with_link && ($code === 7 || $code === 23)) {
        $retry = casting_sms_send_text($mobile, casting_profile_completion_sms_message_body(false));
        if (!empty($retry['ok'])) {
            return [
                'ok'      => true,
                'error'   => '',
                'ref_id'  => (string) ($retry['ref_id'] ?? ''),
                'code'    => (int) ($retry['code'] ?? 0),
                'variant' => 'text_no_link',
            ];
        }
        $result = $retry;
        $code = (int) ($result['code'] ?? 0);
    }

    return [
        'ok'      => false,
        'error'   => (string) ($result['error'] ?? 'ارسال پیامک ناموفق بود.'),
        'ref_id'  => (string) ($result['ref_id'] ?? ''),
        'code'    => $code,
        'variant' => 'failed',
    ];
}

function casting_profile_completion_sms_user_mobile(int $user_id): string
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile', true));
    if ($mobile !== '' && preg_match('/^09\d{9}$/', $mobile)) {
        return $mobile;
    }
    $mobile2 = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile2', true));
    if ($mobile2 !== '' && preg_match('/^09\d{9}$/', $mobile2)) {
        return $mobile2;
    }

    return '';
}

function casting_profile_completion_sms_last_sent_at(int $user_id): int
{
    return max(0, (int) get_user_meta($user_id, CASTING_PROFILE_SMS_META_SENT_AT, true));
}

/**
 * @return array{eligible:bool,reason:string,percent:int,mobile:string}
 */
function casting_profile_completion_sms_eligibility(int $user_id, bool $ignore_cooldown = false): array
{
    $empty = ['eligible' => false, 'reason' => '', 'percent' => 0, 'mobile' => ''];
    if ($user_id <= 0) {
        return array_merge($empty, ['reason' => 'invalid_user']);
    }
    if (!casting_profile_completion_sms_is_enabled()) {
        return array_merge($empty, ['reason' => 'sms_disabled']);
    }
    if (!function_exists('casting_get_user_role')) {
        require_once __DIR__ . '/auth.php';
    }
    if (casting_get_user_role($user_id) === '') {
        return array_merge($empty, ['reason' => 'not_portal_member']);
    }
    if (function_exists('casting_user_is_suspended') && casting_user_is_suspended($user_id)) {
        return array_merge($empty, ['reason' => 'suspended']);
    }
    if (!function_exists('casting_get_profile')) {
        require_once __DIR__ . '/profile.php';
    }
    if (!function_exists('casting_profile_completion_percent')) {
        require_once __DIR__ . '/panel-profile.php';
    }
    $profile = casting_get_profile($user_id);
    $percent = casting_profile_completion_percent($profile, $user_id);
    $always_send = casting_profile_completion_sms_is_always_send_user($user_id);
    if (!$always_send && $percent >= casting_profile_completion_sms_threshold_percent()) {
        return array_merge($empty, ['reason' => 'profile_complete', 'percent' => $percent]);
    }
    $mobile = casting_profile_completion_sms_user_mobile($user_id);
    if ($mobile === '') {
        return array_merge($empty, ['reason' => 'no_mobile', 'percent' => $percent]);
    }
    if (!$ignore_cooldown) {
        $last = casting_profile_completion_sms_last_sent_at($user_id);
        $cooldown = casting_profile_completion_sms_cooldown_days() * DAY_IN_SECONDS;
        if ($last > 0 && (time() - $last) < $cooldown) {
            return [
                'eligible' => false,
                'reason'   => 'cooldown',
                'percent'  => $percent,
                'mobile'   => $mobile,
            ];
        }
    }

    return [
        'eligible' => true,
        'reason'   => 'ok',
        'percent'  => $percent,
        'mobile'   => $mobile,
    ];
}

/**
 * @return array{ok:bool,error:string,skipped:bool,reason:string,ref_id:string,percent:int}
 */
function casting_profile_completion_sms_send_to_user(int $user_id, bool $ignore_cooldown = false): array
{
    $check = casting_profile_completion_sms_eligibility($user_id, $ignore_cooldown);
    if (!$check['eligible']) {
        return [
            'ok'      => false,
            'error'   => '',
            'skipped' => true,
            'reason'  => (string) $check['reason'],
            'ref_id'  => '',
            'percent' => (int) $check['percent'],
        ];
    }
    if (!function_exists('casting_sms_send_text')) {
        require_once __DIR__ . '/sms.php';
    }
    $result = casting_profile_completion_sms_deliver((string) $check['mobile']);
    if (empty($result['ok'])) {
        return [
            'ok'      => false,
            'error'   => (string) ($result['error'] ?? 'ارسال پیامک ناموفق بود.'),
            'skipped' => false,
            'reason'  => 'send_failed',
            'ref_id'  => (string) ($result['ref_id'] ?? ''),
            'percent' => (int) $check['percent'],
        ];
    }
    update_user_meta($user_id, CASTING_PROFILE_SMS_META_SENT_AT, time());
    $ref_id = (string) ($result['ref_id'] ?? '');
    if ($ref_id !== '') {
        update_user_meta($user_id, CASTING_PROFILE_SMS_META_REF, $ref_id);
    }

    return [
        'ok'      => true,
        'error'   => '',
        'skipped' => false,
        'reason'  => 'sent',
        'ref_id'  => $ref_id,
        'percent' => (int) $check['percent'],
    ];
}

function casting_profile_completion_sms_maybe_send_on_login(int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    try {
        casting_profile_completion_sms_send_to_user($user_id, false);
    } catch (Throwable $e) {
        if (function_exists('error_log')) {
            error_log('[casting-profile-sms-login] user=' . $user_id . ' ' . $e->getMessage());
        }
    }
}

/**
 * ارسال به کاربران همیشگی (ardvan، eshahabian) — بدون شرط درصد پروفایل.
 *
 * @return array{sent:int,skipped:int,failed:int,items:array<int, array{id:int,name:string,mobile:string,percent:int,status:string,error:string}>,errors:array<int,string>}
 */
function casting_profile_completion_sms_send_always_users(bool $dry_run = false): array
{
    $sent = 0;
    $skipped = 0;
    $failed = 0;
    $items = [];
    $errors = [];

    foreach (casting_profile_completion_sms_always_send_user_ids() as $user_id) {
        $check = casting_profile_completion_sms_eligibility($user_id, false);
        if (!$check['eligible']) {
            $skipped++;
            continue;
        }
        $user = get_user_by('id', $user_id);
        $row = [
            'id'      => $user_id,
            'name'    => $user ? (string) $user->display_name : ('#' . $user_id),
            'mobile'  => (string) $check['mobile'],
            'percent' => (int) $check['percent'],
            'status'  => $dry_run ? 'would_send_always' : 'sent_always',
            'error'   => '',
        ];
        if ($dry_run) {
            $items[] = $row;
            $sent++;
            continue;
        }
        $result = casting_profile_completion_sms_send_to_user($user_id, false);
        if (!empty($result['ok'])) {
            $items[] = $row;
            $sent++;
        } elseif (!empty($result['skipped'])) {
            $skipped++;
        } else {
            $failed++;
            $row['status'] = 'failed';
            $row['error'] = (string) ($result['error'] ?? 'ناموفق');
            $items[] = $row;
            $errors[] = 'کاربر #' . $user_id . ': ' . $row['error'];
        }
    }

    return [
        'sent'    => $sent,
        'skipped' => $skipped,
        'failed'  => $failed,
        'items'   => $items,
        'errors'  => $errors,
    ];
}

/**
 * @return array{
 *   ok:bool,
 *   dry_run:bool,
 *   sent:int,
 *   scanned:int,
 *   skipped:int,
 *   failed:int,
 *   next_page:int,
 *   done:bool,
 *   items:array<int, array{id:int,name:string,mobile:string,percent:int,status:string,error:string}>,
 *   errors:array<int, string>
 * }
 */
function casting_profile_completion_sms_run_batch(int $send_limit = 30, bool $dry_run = false, int $start_page = 1, bool $include_always = true): array
{
    $send_limit = max(1, min(200, $send_limit));
    $scan_per_page = 50;
    $page = max(1, $start_page);
    $sent = 0;
    $scanned = 0;
    $skipped = 0;
    $failed = 0;
    $items = [];
    $errors = [];
    $done = false;

    if (!casting_profile_completion_sms_is_enabled()) {
        return [
            'ok'        => false,
            'dry_run'   => $dry_run,
            'sent'      => 0,
            'scanned'   => 0,
            'skipped'   => 0,
            'failed'    => 0,
            'next_page' => $page,
            'done'      => true,
            'items'     => [],
            'errors'    => ['پیامک در پورتال فعال یا پیکربندی نشده است.'],
        ];
    }

    if ($include_always && $start_page <= 1) {
        $always = casting_profile_completion_sms_send_always_users($dry_run);
        $sent += (int) $always['sent'];
        $skipped += (int) $always['skipped'];
        $failed += (int) $always['failed'];
        $items = array_merge($items, $always['items']);
        $errors = array_merge($errors, $always['errors']);
    }

    while ($sent < $send_limit) {
        $query = new WP_User_Query([
            'meta_key'     => 'casting_role',
            'meta_compare' => 'EXISTS',
            'number'       => $scan_per_page,
            'offset'       => ($page - 1) * $scan_per_page,
            'orderby'      => 'ID',
            'order'        => 'ASC',
            'fields'       => 'all',
        ]);
        $users = $query->get_results();
        if ($users === []) {
            $done = true;
            break;
        }

        foreach ($users as $user) {
            $user_id = (int) $user->ID;
            $scanned++;
            $check = casting_profile_completion_sms_eligibility($user_id, false);
            if (!$check['eligible']) {
                $skipped++;
                continue;
            }

            $row = [
                'id'      => $user_id,
                'name'    => (string) $user->display_name,
                'mobile'  => (string) $check['mobile'],
                'percent' => (int) $check['percent'],
                'status'  => $dry_run ? 'would_send' : 'sent',
                'error'   => '',
            ];

            if ($dry_run) {
                $items[] = $row;
                $sent++;
            } else {
                $result = casting_profile_completion_sms_send_to_user($user_id, false);
                if (!empty($result['ok'])) {
                    $items[] = $row;
                    $sent++;
                } elseif (!empty($result['skipped'])) {
                    $skipped++;
                } else {
                    $failed++;
                    $row['status'] = 'failed';
                    $row['error'] = (string) ($result['error'] ?? 'ناموفق');
                    $items[] = $row;
                    $errors[] = 'کاربر #' . $user_id . ': ' . $row['error'];
                }
            }

            if ($sent >= $send_limit) {
                break;
            }
        }

        if (count($users) < $scan_per_page) {
            $done = true;
            break;
        }
        if ($sent >= $send_limit) {
            $page++;
            break;
        }
        $page++;
    }

    return [
        'ok'        => true,
        'dry_run'   => $dry_run,
        'sent'      => $sent,
        'scanned'   => $scanned,
        'skipped'   => $skipped,
        'failed'    => $failed,
        'next_page' => $page,
        'done'      => $done,
        'items'     => $items,
        'errors'    => $errors,
    ];
}

function casting_profile_completion_sms_cron_key(): string
{
    return defined('CASTING_PROFILE_SMS_CRON_KEY') ? trim((string) CASTING_PROFILE_SMS_CRON_KEY) : '';
}

function casting_profile_completion_sms_cron_url(): string
{
    $key = casting_profile_completion_sms_cron_key();
    if ($key === '') {
        return '';
    }
    if (function_exists('casting_portal_public_origin')) {
        $origin = casting_portal_public_origin();
    } elseif (defined('CASTING_MAIN_SITE_URL')) {
        $origin = rtrim((string) CASTING_MAIN_SITE_URL, '/');
    } else {
        $origin = 'https://7rokh.com';
    }

    return $origin . '/casting-portal/profile-completion-sms-cron.php?key=' . rawurlencode($key) . '&all=1';
}

/**
 * ارسال به همه واجد شرایط — چند صفحه پشت‌سرهم تا تمام شود یا به سقف برسد.
 *
 * @return array{ok:bool,dry_run:bool,sent:int,scanned:int,skipped:int,failed:int,done:bool,items:array,errors:array<int,string>,pages:int}
 */
function casting_profile_completion_sms_run_all(bool $dry_run = false, int $per_page = 50, int $max_pages = 40): array
{
    $per_page = max(10, min(200, $per_page));
    $max_pages = max(1, min(100, $max_pages));
    $total = [
        'ok'      => true,
        'dry_run' => $dry_run,
        'sent'    => 0,
        'scanned' => 0,
        'skipped' => 0,
        'failed'  => 0,
        'done'    => false,
        'items'   => [],
        'errors'  => [],
        'pages'   => 0,
    ];

    $page = 1;
    while ($page <= $max_pages) {
        $batch = casting_profile_completion_sms_run_batch($per_page, $dry_run, $page, $page === 1);
        if (empty($batch['ok'])) {
            $total['ok'] = false;
            $total['errors'] = array_merge($total['errors'], $batch['errors'] ?? ['اجرای دسته‌ای ناموفق بود.']);
            break;
        }
        $total['sent'] += (int) ($batch['sent'] ?? 0);
        $total['scanned'] += (int) ($batch['scanned'] ?? 0);
        $total['skipped'] += (int) ($batch['skipped'] ?? 0);
        $total['failed'] += (int) ($batch['failed'] ?? 0);
        $total['items'] = array_merge($total['items'], $batch['items'] ?? []);
        $total['errors'] = array_merge($total['errors'], $batch['errors'] ?? []);
        $total['pages'] = $page;
        if (!empty($batch['done'])) {
            $total['done'] = true;
            break;
        }
        $next = (int) ($batch['next_page'] ?? 0);
        $page = $next > $page ? $next : ($page + 1);
    }

    return $total;
}

/**
 * @return array{ok:bool,error:string,ref_id:string,code:int,variant:string}
 */
function casting_profile_completion_sms_send_test(string $mobile, bool $with_link = true): array
{
    if (!casting_profile_completion_sms_is_enabled()) {
        return ['ok' => false, 'error' => 'پیامک در پورتال فعال یا پیکربندی نشده است.', 'ref_id' => '', 'code' => -1, 'variant' => 'disabled'];
    }
    if (!function_exists('casting_sms_send_text')) {
        require_once __DIR__ . '/sms.php';
    }

    $pattern_id = casting_profile_completion_sms_pattern_id();
    if ($pattern_id !== '') {
        return casting_profile_completion_sms_deliver($mobile);
    }

    $message = casting_profile_completion_sms_message_body($with_link);
    $result = casting_sms_send_text($mobile, $message);
    $code = (int) ($result['code'] ?? 0);
    if (!empty($result['ok'])) {
        return [
            'ok'      => true,
            'error'   => '',
            'ref_id'  => (string) ($result['ref_id'] ?? ''),
            'code'    => $code,
            'variant' => $with_link ? 'text_with_link' : 'text_no_link',
        ];
    }

    return [
        'ok'      => false,
        'error'   => (string) ($result['error'] ?? 'ارسال ناموفق بود.'),
        'ref_id'  => (string) ($result['ref_id'] ?? ''),
        'code'    => $code,
        'variant' => 'failed',
    ];
}
