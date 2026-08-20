<?php
declare(strict_types=1);

/**
 * محدودیت نشست پورتال:
 * - خروج خودکار پس از ۱۵ دقیقه عدم فعالیت
 * - فقط یک نشست هم‌زمان برای هر کاربر
 * - ورود دوم از دسکتاپ: پیام تأیید (موبایل مستثنی است)
 */

function casting_session_idle_seconds(): int
{
    return 15 * 60;
}

function casting_session_meta_key(): string
{
    return 'casting_active_session';
}

function casting_request_is_mobile(): bool
{
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        return false;
    }

    return (bool) preg_match(
        '/android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini|mobile|windows phone|tablet/i',
        $ua
    );
}

/**
 * @return array{token:string,last_active:int,is_mobile:bool,ip:string}|null
 */
function casting_session_get_active(int $user_id): ?array
{
    if ($user_id <= 0) {
        return null;
    }
    $data = get_user_meta($user_id, casting_session_meta_key(), true);
    if (!is_array($data) || empty($data['token'])) {
        return null;
    }

    return [
        'token'       => (string) $data['token'],
        'last_active' => (int) ($data['last_active'] ?? 0),
        'is_mobile'   => !empty($data['is_mobile']),
        'ip'          => (string) ($data['ip'] ?? ''),
    ];
}

function casting_session_is_active_record(?array $data): bool
{
    if ($data === null || ($data['token'] ?? '') === '') {
        return false;
    }
    $last = (int) ($data['last_active'] ?? 0);
    if ($last <= 0) {
        return false;
    }

    return (time() - $last) <= casting_session_idle_seconds();
}

/** آیا کاربر نشست فعال روی دستگاه دیگری دارد؟ */
function casting_session_has_other_active(int $user_id): bool
{
    $data = casting_session_get_active($user_id);
    if (!casting_session_is_active_record($data)) {
        return false;
    }
    $mine = (string) ($_SESSION['casting_session_token'] ?? '');
    if ($mine !== '' && hash_equals((string) $data['token'], $mine)) {
        return false;
    }

    return true;
}

function casting_session_issue(int $user_id): string
{
    if ($user_id <= 0) {
        return '';
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = bin2hex(random_bytes(16));
    $now = time();
    update_user_meta($user_id, casting_session_meta_key(), [
        'token'       => $token,
        'last_active' => $now,
        'is_mobile'   => casting_request_is_mobile(),
        'ip'          => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);
    $_SESSION['casting_session_token'] = $token;
    $_SESSION['casting_last_active'] = $now;

    return $token;
}

function casting_session_touch(int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    $token = (string) ($_SESSION['casting_session_token'] ?? '');
    if ($token === '') {
        return;
    }
    $now = time();
    $_SESSION['casting_last_active'] = $now;
    $data = casting_session_get_active($user_id);
    if ($data === null || !hash_equals((string) $data['token'], $token)) {
        return;
    }
    $data['last_active'] = $now;
    update_user_meta($user_id, casting_session_meta_key(), $data);
}

function casting_session_clear(int $user_id = 0): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $uid = $user_id > 0 ? $user_id : max(0, (int) ($_SESSION['casting_portal_user_id'] ?? 0));
        $token = (string) ($_SESSION['casting_session_token'] ?? '');
        unset($_SESSION['casting_session_token'], $_SESSION['casting_last_active']);
        if ($uid > 0 && $token !== '') {
            $data = casting_session_get_active($uid);
            if ($data !== null && hash_equals((string) $data['token'], $token)) {
                delete_user_meta($uid, casting_session_meta_key());
            }
        }
    } elseif ($user_id > 0) {
        delete_user_meta($user_id, casting_session_meta_key());
    }
}

function casting_session_conflict_message(): string
{
    return 'حساب شما هم‌اکنون در دستگاه دیگری فعال است. با ادامهٔ ورود در این دستگاه، نشست قبلی قطع می‌شود.';
}

function casting_session_replaced_message(): string
{
    return 'نشست شما به‌دلیل ورود از دستگاه دیگر پایان یافت. لطفاً دوباره وارد شوید.';
}

function casting_session_idle_message(): string
{
    return 'به‌دلیل ۱۵ دقیقه عدم فعالیت، از حساب خارج شدید. لطفاً دوباره وارد شوید.';
}

/** بازگشت از درگاه بانک ممکن است بیشتر از مهلت بی‌فعالیتی طول بکشد. */
function casting_session_is_payment_return(): bool
{
    $script = strtolower(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    if (str_ends_with($script, '/checkout-callback.php')
        || str_ends_with($script, '/checkout-result.php')
        || str_ends_with($script, '/checkout-gateway.php')) {
        return true;
    }
    if (str_ends_with($script, '/cart.php')) {
        $ref = (string) ($_POST['RefId'] ?? $_GET['RefId'] ?? $_POST['refId'] ?? $_GET['refId'] ?? '');
        $res = (string) ($_POST['ResCode'] ?? $_GET['ResCode'] ?? $_POST['resCode'] ?? $_GET['resCode'] ?? '');
        $sale = (string) ($_POST['SaleOrderId'] ?? $_GET['SaleOrderId'] ?? '');

        return $ref !== '' && ($res !== '' || $sale !== '');
    }

    return false;
}

/**
 * بررسی اعتبار نشست فعلی — در صورت نامعتبر بودن، کاربر را خارج می‌کند.
 *
 * @return array{ok:bool,reason:string}
 */
function casting_session_validate_current(int $user_id): array
{
    if ($user_id <= 0) {
        return ['ok' => false, 'reason' => 'none'];
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return ['ok' => false, 'reason' => 'none'];
    }

    $local_token = (string) ($_SESSION['casting_session_token'] ?? '');
    $local_last = (int) ($_SESSION['casting_last_active'] ?? 0);
    $active = casting_session_get_active($user_id);
    $skip_idle = casting_session_is_payment_return();

    // نشست قدیمی بدون توکن: یک‌بار صادر کن تا قطع نشود
    if ($local_token === '' && !casting_session_is_active_record($active)) {
        casting_session_issue($user_id);

        return ['ok' => true, 'reason' => ''];
    }
    if ($local_token === '') {
        // دستگاه دیگر نشست فعال دارد
        return ['ok' => false, 'reason' => 'replaced'];
    }

    if (!$skip_idle && $local_last > 0 && (time() - $local_last) > casting_session_idle_seconds()) {
        return ['ok' => false, 'reason' => 'idle'];
    }

    if ($active === null || !hash_equals((string) $active['token'], $local_token)) {
        return ['ok' => false, 'reason' => 'replaced'];
    }

    if (!$skip_idle && !casting_session_is_active_record($active)) {
        return ['ok' => false, 'reason' => 'idle'];
    }

    casting_session_touch($user_id);

    return ['ok' => true, 'reason' => ''];
}

/**
 * قبل از لاگین: اگر نشست دیگری فعال است و این درخواست موبایل نیست، تأیید لازم است.
 *
 * @return array{ok:bool,need_confirm:bool,error:string}
 */
function casting_session_prepare_login(int $user_id, bool $force = false): array
{
    if ($user_id <= 0) {
        return ['ok' => false, 'need_confirm' => false, 'error' => 'کاربر نامعتبر است.'];
    }

    $has_other = casting_session_has_other_active($user_id);
    if (!$has_other) {
        return ['ok' => true, 'need_confirm' => false, 'error' => ''];
    }

    // موبایل: بدون پیام، نشست قبلی قطع و ورود انجام می‌شود
    if (casting_request_is_mobile()) {
        return ['ok' => true, 'need_confirm' => false, 'error' => ''];
    }

    if ($force) {
        return ['ok' => true, 'need_confirm' => false, 'error' => ''];
    }

    return [
        'ok'           => false,
        'need_confirm' => true,
        'error'        => casting_session_conflict_message(),
    ];
}
