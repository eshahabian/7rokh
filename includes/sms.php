<?php
declare(strict_types=1);

/**
 * ارسال پیامک از طریق WebOne (rest.payamakapi.ir)
 *
 * @return array{ok:bool,error:string,code?:int}
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
    return 'https://rest.payamakapi.ir/api/v1/';
}

/**
 * @return array{ok:bool,error:string,code?:int,raw?:mixed}
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
    $result_code = (int) ($data['resultCode'] ?? $data['ResultCode'] ?? -1);
    if ($succeeded && $result_code === 0) {
        return ['ok' => true, 'error' => '', 'code' => 0, 'raw' => $data];
    }

    return [
        'ok'    => false,
        'error' => casting_sms_error_message($result_code),
        'code'  => $result_code,
        'raw'   => $data,
    ];
}

function casting_sms_error_message(int $code): string
{
    $map = [
        0  => 'ارسال با موفقیت انجام شد.',
        1  => 'کلید API یا اطلاعات ورود نامعتبر است.',
        2  => 'حساب پیامک مسدود شده است.',
        3  => 'محدودیت ارسال روزانه.',
        4  => 'شماره فرستنده نامعتبر است.',
        5  => 'تعداد گیرندگان بیش از حد مجاز است.',
        6  => 'خط فرستنده غیرفعال است.',
        7  => 'متن پیامک شامل کلمات فیلترشده است.',
        8  => 'اعتبار پنل پیامک کافی نیست.',
        9  => 'سامانه پیامک در حال به‌روزرسانی است.',
        10 => 'وب‌سرویس پیامک غیرفعال است.',
        15 => 'ارسال تکراری؛ کمی بعد دوباره تلاش کنید.',
        16 => 'شماره گیرنده نامعتبر است.',
        17 => 'خط OTP در پنل فعال نیست. از پشتیبانی WebOne بخواهید خط OTP را فعال کنند.',
        18 => 'با این شماره فقط ارسال تکی مجاز است.',
        19 => 'متن با الگوی تعریف‌شده در پنل مطابقت ندارد.',
        21 => 'IP سرور برای وب‌سرویس مجاز نیست؛ IP را در پنل WebOne ثبت کنید.',
        22 => 'احراز هویت پنل (کارت ملی) تکمیل نشده است.',
    ];

    return $map[$code] ?? ('ارسال پیامک ناموفق بود (کد ' . $code . ').');
}

/**
 * ارسال کد OTP از خط SmartOTP وب‌وان
 *
 * @return array{ok:bool,error:string}
 */
function casting_sms_send_otp(string $mobile, string $message): array
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

    $result = casting_sms_request('SMS/SmartOTP', [
        'ToNumber' => $mobile,
        'Content'  => $message,
    ]);

    return ['ok' => $result['ok'], 'error' => $result['error']];
}

/**
 * ارسال پیامک متنی (مثلاً لینک بازیابی رمز)
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
        'From'      => $from,
        'ToNumbers' => [$mobile],
        'Content'   => $message,
    ]);

    return ['ok' => $result['ok'], 'error' => $result['error']];
}
