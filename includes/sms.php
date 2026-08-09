<?php
declare(strict_types=1);

/**
 * ارسال پیامک از طریق WebOne SMS
 *
 * پنل: https://webone-sms.ir
 * Base API (مستند RestDocument v1.4): https://api.payamakapi.ir/api/v1/
 * هدر الزامی: X-API-KEY
 */

function casting_sms_is_configured(): bool
{
    if (defined('CASTING_SMS_ENABLED') && !CASTING_SMS_ENABLED) {
        return false;
    }
    $key = defined('CASTING_SMS_API_KEY') ? trim((string) CASTING_SMS_API_KEY) : '';

    return $key !== '';
}

function casting_sms_api_base(): string
{
    $base = defined('CASTING_SMS_API_BASE') ? trim((string) CASTING_SMS_API_BASE) : '';
    if ($base === '') {
        // مستند رسمی WebOne RestDocument v1.4
        $base = 'https://api.payamakapi.ir/api/v1/';
    }
    if ($base !== '' && substr($base, -1) !== '/') {
        $base .= '/';
    }

    return $base;
}

function casting_sms_otp_sender(): string
{
    $sender = defined('CASTING_SMS_OTP_SENDER') ? trim((string) CASTING_SMS_OTP_SENDER) : '';

    return $sender;
}

/**
 * true فقط وقتی مقدار واقعاً موفق باشد (نه رشتهٔ غیرخالی تصادفی)
 */
function casting_sms_is_truthy_success(mixed $value): bool
{
    if ($value === true || $value === 1 || $value === '1') {
        return true;
    }
    if (is_string($value) && strtolower(trim($value)) === 'true') {
        return true;
    }

    return false;
}

/**
 * آخرین پاسخ API برای دیباگ ادمین (بدون کلید)
 *
 * @param array<string,mixed>|null $payload
 */
function casting_sms_remember_last(array $payload): void
{
    if (!function_exists('set_transient')) {
        return;
    }
    set_transient('casting_sms_last_debug', $payload, 30 * MINUTE_IN_SECONDS);
}

/**
 * @return array<string,mixed>|null
 */
function casting_sms_last_debug(): ?array
{
    if (!function_exists('get_transient')) {
        return null;
    }
    $data = get_transient('casting_sms_last_debug');

    return is_array($data) ? $data : null;
}

/**
 * @return array{ok:bool,error:string,code?:int,raw?:mixed,ref_id?:string,http?:int}
 */
function casting_sms_request(string $endpoint, array $body): array
{
    if (!casting_sms_is_configured()) {
        return ['ok' => false, 'error' => 'ارسال پیامک پیکربندی نشده است. کلید API را در config.local.php بگذارید.'];
    }

    $api_key = trim((string) CASTING_SMS_API_KEY);
    $url = casting_sms_api_base() . ltrim($endpoint, '/');

    $response = wp_remote_post($url, [
        'timeout' => 25,
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept'       => 'application/json',
            'X-API-KEY'    => $api_key,
        ],
        'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);

    if (is_wp_error($response)) {
        $out = ['ok' => false, 'error' => 'ارتباط با پنل پیامک برقرار نشد: ' . $response->get_error_message()];
        casting_sms_remember_last([
            'at'       => current_time('mysql'),
            'url'      => $url,
            'endpoint' => $endpoint,
            'request'  => $body,
            'error'    => $out['error'],
        ]);

        return $out;
    }

    $http = (int) wp_remote_retrieve_response_code($response);
    $raw_body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($raw_body, true);

    $debug = [
        'at'       => current_time('mysql'),
        'url'      => $url,
        'endpoint' => $endpoint,
        'request'  => $body,
        'http'     => $http,
        'body'     => is_array($data) ? $data : mb_substr($raw_body, 0, 800),
    ];

    if ($http < 200 || $http >= 300) {
        $out = [
            'ok'    => false,
            'error' => 'پاسخ نامعتبر از پنل پیامک (HTTP ' . $http . ').',
            'http'  => $http,
            'raw'   => is_array($data) ? $data : $raw_body,
        ];
        $debug['ok'] = false;
        $debug['parsed_error'] = $out['error'];
        casting_sms_remember_last($debug);

        return $out;
    }

    if (!is_array($data)) {
        $out = [
            'ok'    => false,
            'error' => 'پاسخ JSON نامعتبر از پنل پیامک (HTTP ' . $http . ').',
            'http'  => $http,
            'raw'   => $raw_body,
        ];
        $debug['ok'] = false;
        $debug['parsed_error'] = $out['error'];
        casting_sms_remember_last($debug);

        return $out;
    }

    $succeeded_raw = $data['succeeded'] ?? $data['Succeeded'] ?? null;
    $succeeded = casting_sms_is_truthy_success($succeeded_raw);
    $has_code = array_key_exists('resultCode', $data) || array_key_exists('ResultCode', $data);
    $result_code = $has_code
        ? (int) ($data['resultCode'] ?? $data['ResultCode'])
        : null;
    $ref_id = trim((string) ($data['refId'] ?? $data['RefId'] ?? ''));

    // موفق فقط وقتی succeeded=true و (اگر کد آمده) resultCode=0
    $ok = $succeeded && ($result_code === null || $result_code === 0);
    // اگر کد خطا غیرصفر باشد حتی با succeeded عجیب، ناموفق
    if ($result_code !== null && $result_code !== 0) {
        $ok = false;
    }

    if ($ok) {
        $out = [
            'ok'     => true,
            'error'  => '',
            'code'   => 0,
            'raw'    => $data,
            'ref_id' => $ref_id,
            'http'   => $http,
        ];
        $debug['ok'] = true;
        $debug['ref_id'] = $ref_id;
        casting_sms_remember_last($debug);

        return $out;
    }

    $code = $result_code ?? -1;
    $out = [
        'ok'     => false,
        'error'  => casting_sms_error_message($code),
        'code'   => $code,
        'raw'    => $data,
        'ref_id' => $ref_id,
        'http'   => $http,
    ];
    $debug['ok'] = false;
    $debug['parsed_error'] = $out['error'];
    casting_sms_remember_last($debug);

    return $out;
}

/**
 * @return array{ok:bool,error:string,credit?:float}
 */
function casting_sms_get_credit(): array
{
    if (!casting_sms_is_configured()) {
        return ['ok' => false, 'error' => 'کلید API تنظیم نشده است.'];
    }

    $api_key = trim((string) CASTING_SMS_API_KEY);
    $url = casting_sms_api_base() . 'SMS/GetCredit';
    $response = wp_remote_get($url, [
        'timeout' => 20,
        'headers' => [
            'Accept'    => 'application/json',
            'X-API-KEY' => $api_key,
        ],
    ]);

    if (is_wp_error($response)) {
        return ['ok' => false, 'error' => 'ارتباط با پنل پیامک برقرار نشد: ' . $response->get_error_message()];
    }

    $http = (int) wp_remote_retrieve_response_code($response);
    $raw_body = trim((string) wp_remote_retrieve_body($response));
    if ($http < 200 || $http >= 300) {
        return ['ok' => false, 'error' => 'خواندن اعتبار ناموفق بود (HTTP ' . $http . ').'];
    }

    $data = json_decode($raw_body, true);
    if (is_array($data)) {
        $credit = $data['credit'] ?? $data['Credit'] ?? $data['result'] ?? null;
        if (is_numeric($credit)) {
            return ['ok' => true, 'error' => '', 'credit' => (float) $credit];
        }
    }
    if (is_numeric($raw_body)) {
        return ['ok' => true, 'error' => '', 'credit' => (float) $raw_body];
    }

    return ['ok' => false, 'error' => 'پاسخ اعتبار نامعتبر بود.'];
}

function casting_sms_error_message(int $code): string
{
    // کدها مطابق پاسخ واقعی rest.payamakapi.ir / درایور WebOne
    $map = [
        0  => 'ارسال با موفقیت انجام شد.',
        1  => 'کلید API یا نام کاربری/رمز نامعتبر است.',
        2  => 'حساب پیامک مسدود شده است.',
        3  => 'محدودیت ارسال روزانه.',
        4  => 'شماره فرستنده نامعتبر است.',
        5  => 'تعداد گیرندگان بیش از حد مجاز است (حداکثر ۱۰۰).',
        6  => 'خط فرستنده غیرفعال است.',
        7  => 'متن پیامک شامل کلمات فیلترشده است.',
        8  => 'اعتبار پنل کافی نیست.',
        9  => 'سامانه پیامک در حال به‌روزرسانی است.',
        10 => 'وب‌سرویس پیامک غیرفعال است.',
        12 => 'تعداد شماره و متن در ارسال متناظر باید یکسان باشد.',
        13 => 'حداکثر ۵۰۰ شماره در ارسال متناظر مجاز است.',
        14 => 'تعرفه کاربر مشخص نشده است.',
        15 => 'ارسال تکراری؛ کمی بعد دوباره تلاش کنید.',
        16 => 'شماره موبایل گیرنده یافت نشد / نامعتبر است.',
        17 => 'خط OTP برای کاربر یافت نشد. در پنل WebOne خط OTP را فعال کنید.',
        18 => 'با این خط فقط ارسال تکی مجاز است.',
        19 => 'متن با الگوی تعریف‌شده در پنل مطابقت ندارد.',
        21 => 'IP سرور برای وب‌سرویس مجاز نیست؛ IP را در پنل ثبت کنید.',
        22 => 'احراز هویت پنل (کارت ملی) تکمیل نشده است.',
    ];

    return $map[$code] ?? ('ارسال پیامک ناموفق بود (کد ' . $code . ').');
}

/**
 * @return array{ok:bool,error:string,ref_id?:string}
 */
function casting_sms_send_otp(string $mobile, string $message, string $otp_code = ''): array
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile);
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['ok' => false, 'error' => 'شماره موبایل نامعتبر است.'];
    }

    $message = trim($message);
    if ($message === '') {
        return ['ok' => false, 'error' => 'متن پیامک خالی است.'];
    }

    $pattern_id = defined('CASTING_SMS_OTP_PATTERN_ID') ? trim((string) CASTING_SMS_OTP_PATTERN_ID) : '';
    if ($pattern_id !== '' && $otp_code !== '') {
        $from = defined('CASTING_SMS_FROM') ? trim((string) CASTING_SMS_FROM) : '';
        if ($from === '') {
            return ['ok' => false, 'error' => 'برای ارسال با الگو، CASTING_SMS_FROM لازم است.'];
        }
        $param_key = defined('CASTING_SMS_OTP_PATTERN_PARAM')
            ? trim((string) CASTING_SMS_OTP_PATTERN_PARAM)
            : 'ParameterValue';
        if ($param_key === '') {
            $param_key = 'ParameterValue';
        }
        $result = casting_sms_request('SMS/Send', [
            'From'                 => $from,
            'ToNumbers'            => [$mobile],
            'PatternId'            => $pattern_id,
            'PatternParameterData' => [
                $param_key => $otp_code,
            ],
        ]);

        return [
            'ok'     => $result['ok'],
            'error'  => $result['error'],
            'ref_id' => (string) ($result['ref_id'] ?? ''),
        ];
    }

    // SmartOTP — طبق RestDocument v1.4: ToNumber + Content + OTPSender (Auto یا شماره خط)
    $payload = [
        'ToNumber' => $mobile,
        'Content'  => $message,
    ];
    $otp_sender = casting_sms_otp_sender();
    if ($otp_sender === '') {
        $otp_sender = 'Auto';
    }
    $payload['OTPSender'] = $otp_sender;

    $result = casting_sms_request('SMS/SmartOTP', $payload);
    // اگر SmartOTP در دسترس نبود، با پیامک متنی همان کد را بفرست
    if (!$result['ok'] && (int) ($result['http'] ?? 0) === 404) {
        $result = casting_sms_send_text($mobile, $message);
    }

    return [
        'ok'     => $result['ok'],
        'error'  => $result['error'],
        'ref_id' => (string) ($result['ref_id'] ?? ''),
    ];
}

/**
 * @return array{ok:bool,error:string,ref_id?:string}
 */
function casting_sms_send_text(string $mobile, string $message): array
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile);
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['ok' => false, 'error' => 'شماره موبایل نامعتبر است.'];
    }

    $from = defined('CASTING_SMS_FROM') ? trim((string) CASTING_SMS_FROM) : '';
    if ($from === '') {
        return ['ok' => false, 'error' => 'شماره فرستنده پیامک (CASTING_SMS_FROM) در config.local.php تنظیم نشده است.'];
    }

    $message = trim($message);
    if ($message === '') {
        return ['ok' => false, 'error' => 'متن پیامک خالی است.'];
    }

    // ارسال تکی طبق مستند: ToNumber — گروهی: ToNumbers
    $result = casting_sms_request('SMS/Send', [
        'From'     => $from,
        'ToNumber' => $mobile,
        'Content'  => $message,
    ]);

    return [
        'ok'     => $result['ok'],
        'error'  => $result['error'],
        'ref_id' => (string) ($result['ref_id'] ?? ''),
    ];
}
