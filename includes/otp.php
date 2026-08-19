<?php
declare(strict_types=1);

require_once __DIR__ . '/sms.php';

/**
 * آیا تأیید کد پیامک موبایل در ثبت‌نام فعال است؟
 * فلگ: CASTING_MOBILE_OTP_ENABLED در config.php / config.local.php
 */
function casting_mobile_otp_enabled(): bool
{
    return defined('CASTING_MOBILE_OTP_ENABLED') && CASTING_MOBILE_OTP_ENABLED;
}

/** مدت اعتبار کد (ثانیه) */
function casting_otp_ttl(): int
{
    return 5 * 60;
}

/** فاصله مجاز بین دو ارسال برای یک موبایل (ثانیه) */
function casting_otp_resend_gap(): int
{
    return 60;
}

/** حداکثر تلاش اشتباه برای یک کد */
function casting_otp_max_attempts(): int
{
    return 5;
}

/**
 * @return array{ok:bool,error:string,user_id?:int}
 */
function casting_find_user_by_mobile(string $mobile, int $exclude_user_id = 0): array
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile);
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['ok' => false, 'error' => 'شماره موبایل نامعتبر است.'];
    }

    $q = new WP_User_Query([
        'number'     => 5,
        'meta_query' => [
            [
                'key'   => 'casting_mobile',
                'value' => $mobile,
            ],
        ],
        'fields' => 'ID',
    ]);
    $ids = $q->get_results();
    if (!is_array($ids) || $ids === []) {
        return ['ok' => false, 'error' => 'کاربری با این موبایل پیدا نشد.'];
    }

    foreach ($ids as $id) {
        $uid = (int) $id;
        if ($uid <= 0 || ($exclude_user_id > 0 && $uid === $exclude_user_id)) {
            continue;
        }
        if (casting_get_user_role($uid) === '') {
            continue;
        }

        return ['ok' => true, 'error' => '', 'user_id' => $uid];
    }

    return ['ok' => false, 'error' => 'کاربری با این موبایل پیدا نشد.'];
}

/**
 * آیا این شماره به‌عنوان موبایل اصلی یا دومِ کاربر دیگری ثبت شده؟
 */
function casting_mobile_is_taken(string $mobile, int $exclude_user_id = 0): bool
{
    $found = casting_find_user_by_mobile($mobile, $exclude_user_id);
    if (!empty($found['ok'])) {
        return true;
    }

    $mobile = casting_normalize_mobile($mobile);
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return false;
    }

    $q = new WP_User_Query([
        'number'     => 5,
        'meta_query' => [
            [
                'key'   => 'casting_mobile2',
                'value' => $mobile,
            ],
        ],
        'fields' => 'ID',
    ]);
    $ids = $q->get_results();
    if (!is_array($ids)) {
        return false;
    }
    foreach ($ids as $id) {
        $uid = (int) $id;
        if ($uid <= 0 || ($exclude_user_id > 0 && $uid === $exclude_user_id)) {
            continue;
        }
        if (casting_get_user_role($uid) === '') {
            continue;
        }

        return true;
    }

    return false;
}

function casting_otp_storage_key(string $purpose, string $mobile): string
{
    return 'casting_otp_' . sanitize_key($purpose) . '_' . md5($mobile);
}

function casting_otp_cooldown_key(string $purpose, string $mobile): string
{
    return 'casting_otp_cd_' . sanitize_key($purpose) . '_' . md5($mobile);
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_otp_send(string $purpose, string $mobile): array
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile);
    $purpose = sanitize_key($purpose);
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['ok' => false, 'error' => 'شماره موبایل را درست وارد کنید (مثلاً ۰۹۱۲۱۲۳۴۵۶۷).'];
    }
    if ($purpose === '') {
        return ['ok' => false, 'error' => 'نوع درخواست نامعتبر است.'];
    }
    if (!casting_sms_is_configured()) {
        return ['ok' => false, 'error' => 'ارسال پیامک هنوز فعال نشده است.'];
    }

    $cd_key = casting_otp_cooldown_key($purpose, $mobile);
    if (get_transient($cd_key)) {
        return ['ok' => false, 'error' => 'کمی صبر کنید و دوباره کد را درخواست دهید.'];
    }

    $code = (string) random_int(100000, 999999);
    $hash = hash_hmac('sha256', $code, wp_salt('auth'));
    set_transient(casting_otp_storage_key($purpose, $mobile), [
        'hash'      => $hash,
        'attempts'  => 0,
        'created'   => time(),
        'mobile'    => $mobile,
        'purpose'   => $purpose,
    ], casting_otp_ttl());
    set_transient($cd_key, 1, casting_otp_resend_gap());

    $brand = casting_brand();
    $message = function_exists('casting_sms_otp_text')
        ? casting_sms_otp_text($code)
        : ('کد تأیید ' . $brand . ': ' . $code);
    $sms = casting_sms_send_otp($mobile, $message, $code);
    if (!$sms['ok']) {
        delete_transient(casting_otp_storage_key($purpose, $mobile));
        delete_transient($cd_key);
        $err = trim((string) ($sms['error'] ?? ''));
        $code_num = (int) ($sms['code'] ?? 0);
        if ($code_num === 19 || str_contains($err, 'الگوی پنل')) {
            $err = 'ارسال کد تأیید الان ممکن نیست. با رمز عبور وارد شوید یا کمی بعد دوباره تلاش کنید.';
        }

        return ['ok' => false, 'error' => $err !== '' ? $err : 'ارسال پیامک ناموفق بود.'];
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_otp_verify(string $purpose, string $mobile, string $code): array
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile);
    $purpose = sanitize_key($purpose);
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['ok' => false, 'error' => 'شماره موبایل نامعتبر است.'];
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        return ['ok' => false, 'error' => 'کد تأیید باید ۶ رقم باشد.'];
    }

    $key = casting_otp_storage_key($purpose, $mobile);
    $row = get_transient($key);
    if (!is_array($row) || empty($row['hash'])) {
        return ['ok' => false, 'error' => 'کد منقضی شده یا ارسال نشده است. دوباره درخواست دهید.'];
    }

    $attempts = (int) ($row['attempts'] ?? 0);
    if ($attempts >= casting_otp_max_attempts()) {
        delete_transient($key);

        return ['ok' => false, 'error' => 'تعداد تلاش بیش از حد بود. دوباره کد بگیرید.'];
    }

    $expect = (string) $row['hash'];
    $got = hash_hmac('sha256', $code, wp_salt('auth'));
    if (!hash_equals($expect, $got)) {
        $row['attempts'] = $attempts + 1;
        $remaining = casting_otp_ttl() - (time() - (int) ($row['created'] ?? time()));
        set_transient($key, $row, max(30, $remaining));

        return ['ok' => false, 'error' => 'کد تأیید نادرست است.'];
    }

    delete_transient($key);
    delete_transient(casting_otp_cooldown_key($purpose, $mobile));

    return ['ok' => true, 'error' => ''];
}

/**
 * علامت‌گذاری موبایل تأییدشده در نشست (برای ثبت‌نام)
 */
function casting_otp_mark_session_verified(string $purpose, string $mobile): void
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile);
    $_SESSION['casting_otp_ok'][$purpose] = [
        'mobile' => $mobile,
        'at'     => time(),
    ];
}

function casting_otp_session_is_verified(string $purpose, string $mobile, int $max_age = 1800): bool
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile);
    $row = $_SESSION['casting_otp_ok'][$purpose] ?? null;
    if (!is_array($row)) {
        return false;
    }
    $saved = casting_normalize_mobile((string) ($row['mobile'] ?? ''));
    $at = (int) ($row['at'] ?? 0);

    return $saved === $mobile && $at > 0 && (time() - $at) <= $max_age;
}

function casting_otp_clear_session(string $purpose): void
{
    if (isset($_SESSION['casting_otp_ok'][$purpose])) {
        unset($_SESSION['casting_otp_ok'][$purpose]);
    }
}

function casting_mark_mobile_verified(int $user_id, string $mobile): void
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile);
    update_user_meta($user_id, 'casting_mobile', $mobile);
    update_user_meta($user_id, 'casting_mobile_verified', '1');
    update_user_meta($user_id, 'casting_mobile_verified_at', current_time('mysql'));
}

function casting_user_mobile_is_verified(int $user_id): bool
{
    return (string) get_user_meta($user_id, 'casting_mobile_verified', true) === '1';
}
