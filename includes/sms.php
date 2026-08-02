<?php
declare(strict_types=1);

/**
 * ارسال پیامک از طریق WebOne SMS
 *
 * پنل: https://webone-sms.ir
 * Base API (مستند v1.4): https://api.payamakapi.ir/api/v1
 * هدر الزامی: X-API-KEY
 *
 * @see RestDocument.V1.4
 */

function casting_sms_is_configured(): bool
{
    if (defined('CASTING_SMS_ENABLED') && !CASTING_SMS_ENABLED) {
        return false;
    }
    $key = defined('CASTING_SMS_API_KEY') ? trim((string) CASTING_SMS_API_KEY) : '';

    return $key !== '';
}

/**
 * آدرس پایه وب‌سرویس — قابلOverride با CASTING_SMS_API_BASE
 */
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

/**
 * خط فرستنده OTP — طبق مستند: شماره خط یا "Auto"
 */
function casting_sms_otp_sender(): string
{
    $sender = defined('CASTING_SMS_OTP_SENDER') ? trim((string) CASTING_SMS_OTP_SENDER) : '';

    return $sender !== '' ? $sender : 'Auto';
}

/**
 * @return array{ok:bool,error:string,code?:int,raw?:mixed,ref_id?:string}
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
        return ['ok' => false, 'error' => 'ارتباط با پنل پیامک برقرار نشد: ' . $response->get_error_message()];
    }

    $http = (int) wp_remote_retrieve_response_code($response);
    $raw_body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($raw_body, true);
    if (!is_array($data)) {
        return [
            'ok'    => false,
            'error' => 'پاسخ نامعتبر از پنل پیامک (HTTP ' . $http . ').',
            'raw'   => $raw_body,
        ];
    }

    $succeeded = !empty($data['succeeded']) || !empty($data['Succeeded']);
    $result_code = array_key_exists('resultCode', $data)
        ? (int) $data['resultCode']
        : (array_key_exists('ResultCode', $data) ? (int) $data['ResultCode'] : null);
    $ref_id = (string) ($data['refId'] ?? $data['RefId'] ?? '');

    // موفق: Succeeded=true یا resultCode=0 (مستند v1.4)
    if ($succeeded || $result_code === 0) {
        return [
            'ok'     => true,
            'error'  => '',
            'code'   => 0,
            'raw'    => $data,
            'ref_id' => $ref_id,
        ];
    }

    $code = $result_code ?? -1;

    return [
        'ok'     => false,
        'error'  => casting_sms_error_message($code),
        'code'   => $code,
        'raw'    => $data,
        'ref_id' => $ref_id,
    ];
}

/**
 * مانده اعتبار (ریال) — GET SMS/GetCredit
 *
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

    return ['ok' => false, 'error' => 'پاسخ اعتبار نامعتبر بود.', 'raw' => $raw_body];
}

/**
 * کدهای خطا طبق RestDocument WebOne v1.4
 */
function casting_sms_error_message(int $code): string
{
    $map = [
        0  => 'ارسال با موفقیت انجام شد.',
        1  => 'کلید API یا نام کاربری/رمز نامعتبر است.',
        2  => 'حساب پیامک مسدود شده است.',
        3  => 'شماره فرستنده نامعتبر است.',
        4  => 'محدودیت ارسال روزانه.',
        5  => 'تعداد گیرندگان بیش از حد مجاز است (حداکثر ۱۰۰).',
        6  => 'خط فرستنده غیرفعال است.',
        7  => 'متن پیامک شامل کلمات فیلترشده است.',
        8  => 'اعتبار پنل کافی نیست (حداقل ۵۰ هزار تومان شارژ لازم است).',
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
 * ارسال کد OTP — ترجیح: SmartOTP؛ در صورت تنظیم PatternId از الگوی پنل
 *
 * @return array{ok:bool,error:string}
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
            'ToNumber'             => $mobile,
            'PatternId'            => $pattern_id,
            'PatternParameterData' => [
                $param_key => $otp_code,
            ],
        ]);

        return ['ok' => $result['ok'], 'error' => $result['error']];
    }

    // SmartOTP — مستند: OTPSender (یا Auto) + ToNumber + Content
    $result = casting_sms_request('SMS/SmartOTP', [
        'OTPSender' => casting_sms_otp_sender(),
        'ToNumber'  => $mobile,
        'Content'   => $message,
    ]);

    return ['ok' => $result['ok'], 'error' => $result['error']];
}

/**
 * ارسال پیامک متنی (مثلاً لینک بازیابی رمز)
 * مستند: From + ToNumber (تکی) یا ToNumbers (گروهی) + Content
 *
 * @return array{ok:bool,error:string}
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

    $result = casting_sms_request('SMS/Send', [
        'From'     => $from,
        'ToNumber' => $mobile,
        'Content'  => $message,
    ]);

    return ['ok' => $result['ok'], 'error' => $result['error']];
}
