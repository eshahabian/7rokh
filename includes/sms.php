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
    if ($key !== '') {
        return true;
    }

    return casting_sms_http_is_configured();
}

function casting_sms_http_is_configured(): bool
{
    $user = defined('CASTING_SMS_USERNAME') ? trim((string) CASTING_SMS_USERNAME) : '';
    $pass = defined('CASTING_SMS_PASSWORD') ? trim((string) CASTING_SMS_PASSWORD) : '';

    return $user !== '' && $pass !== '';
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
function casting_sms_is_truthy_success($value): bool
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
    try {
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
        $raw_clip = function_exists('mb_substr')
            ? (string) mb_substr($raw_body, 0, 800, 'UTF-8')
            : substr($raw_body, 0, 800);

        $debug = [
            'at'       => current_time('mysql'),
            'url'      => $url,
            'endpoint' => $endpoint,
            'request'  => $body,
            'http'     => $http,
            'body'     => is_array($data) ? $data : $raw_clip,
        ];

        if ($http < 200 || $http >= 300) {
            $err = 'پاسخ نامعتبر از پنل پیامک (HTTP ' . $http . ').';
            if (is_array($data)) {
                $api_msg = trim((string) ($data['message'] ?? $data['Message'] ?? ''));
                $client_ip = trim((string) ($data['clientIp'] ?? $data['ClientIp'] ?? ''));
                if ($api_msg !== '' && stripos($api_msg, 'IP') !== false) {
                    $err = 'IP سرور برای وب‌سرویس مجاز نیست'
                        . ($client_ip !== '' ? (' (IP: ' . $client_ip . ')') : '')
                        . '. در پنل WebOne: تنظیمات → آی‌پی‌های مجاز REST این IP را ثبت کنید.';
                } elseif ($api_msg !== '') {
                    $err = $api_msg . ' (HTTP ' . $http . ')';
                }
            }
            $out = [
                'ok'    => false,
                'error' => $err,
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
    } catch (Throwable $e) {
        $out = ['ok' => false, 'error' => 'خطای داخلی پیامک: ' . $e->getMessage()];
        casting_sms_remember_last([
            'at'       => function_exists('current_time') ? current_time('mysql') : date('c'),
            'endpoint' => $endpoint,
            'request'  => $body,
            'error'    => $out['error'],
        ]);

        return $out;
    }
}

/**
 * @return array{ok:bool,error:string,credit?:float}
 */
function casting_sms_get_credit(): array
{
    try {
        if (!casting_sms_is_configured()) {
            return ['ok' => false, 'error' => 'کلید API تنظیم نشده است.'];
        }

        $api_key = trim((string) CASTING_SMS_API_KEY);
        $url = casting_sms_api_base() . 'SMS/GetCredit';
        $response = wp_remote_get($url, [
            'timeout' => 12,
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
            $data = json_decode($raw_body, true);
            $api_msg = is_array($data) ? trim((string) ($data['message'] ?? $data['Message'] ?? '')) : '';
            if ($api_msg !== '') {
                return ['ok' => false, 'error' => $api_msg . ' (HTTP ' . $http . ')'];
            }

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
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'خطا در خواندن اعتبار: ' . $e->getMessage()];
    }
}

function casting_sms_error_message(int $code): string
{
    // کدها مطابق RestDocument v1.4 (WebOne)
    $map = [
        0  => 'ارسال با موفقیت انجام شد.',
        1  => 'کلید API یا نام کاربری/رمز نامعتبر است.',
        2  => 'حساب پیامک مسدود شده است.',
        3  => 'شماره فرستنده نامعتبر است.',
        4  => 'محدودیت ارسال روزانه.',
        5  => 'تعداد گیرندگان بیش از حد مجاز است (حداکثر ۱۰۰).',
        6  => 'خط فرستنده غیرفعال است.',
        7  => 'متن پیامک شامل کلمات فیلترشده است.',
        8  => 'اعتبار پنل کافی نیست (حداقل حدود ۵۰ هزار تومان).',
        9  => 'سامانه پیامک در حال به‌روزرسانی است.',
        10 => 'وب‌سرویس پیامک غیرفعال است.',
        12 => 'تعداد شماره و متن در ارسال متناظر باید یکسان باشد.',
        13 => 'حداکثر ۵۰۰ شماره در ارسال متناظر مجاز است.',
        14 => 'تعرفه کاربر مشخص نشده است.',
        15 => 'ارسال تکراری؛ کمی بعد دوباره تلاش کنید.',
        16 => 'شماره موبایل گیرنده یافت نشد / نامعتبر است.',
        17 => 'خط OTP برای این حساب یافت نشد. در پنل WebOne خط OTP را فعال کنید یا PatternId الگو را بگذارید.',
        18 => 'با این خط فقط ارسال تکی مجاز است.',
        19 => 'متن با الگوی پنل یکی نیست. بخش ثابت باید عین الگو باشد و فقط مقدار داخل {x} عوض شود.',
        21 => 'IP سرور برای وب‌سرویس مجاز نیست؛ IP را در پنل ثبت کنید.',
        22 => 'احراز هویت پنل (کارت ملی) تکمیل نشده است.',
        23 => 'بخش متغیر الگو نباید لینک یا IP باشد؛ فقط کد عددی بفرستید.',
    ];

    return $map[$code] ?? ('ارسال پیامک ناموفق بود (کد ' . $code . ').');
}

function casting_sms_otp_pattern_id(): string
{
    return defined('CASTING_SMS_OTP_PATTERN_ID') ? trim((string) CASTING_SMS_OTP_PATTERN_ID) : '';
}

/** الگوی Otino/WebOne: یک بخش ثابت + یک متغیر {x} — پیش‌فرض همان مثال پنل */
function casting_sms_otp_template(): string
{
    $tpl = defined('CASTING_SMS_OTP_TEMPLATE') ? trim((string) CASTING_SMS_OTP_TEMPLATE) : '';

    return $tpl !== '' ? $tpl : 'کد ورود شما {x}';
}

/** نام متغیر داخل {} — فقط حروف انگلیسی: x, otp, p, u */
function casting_sms_otp_var_name(): string
{
    $param = defined('CASTING_SMS_OTP_PATTERN_PARAM')
        ? trim((string) CASTING_SMS_OTP_PATTERN_PARAM)
        : '';
    if ($param !== '' && strcasecmp($param, 'ParameterValue') !== 0) {
        return $param;
    }
    $tpl = casting_sms_otp_template();
    if (preg_match('/\{([A-Za-z][A-Za-z0-9_]*)\}/', $tpl, $m)) {
        return $m[1];
    }

    return 'x';
}

function casting_sms_otp_pattern_param(): string
{
    return casting_sms_otp_var_name();
}

/** متن نهایی: بخش ثابت الگو + جایگزینی {x} با کد (بدون لینک/IP) */
function casting_sms_otp_text(string $code): string
{
    $code = preg_replace('/\D+/', '', $code) ?? '';
    $tpl = casting_sms_otp_template();
    $var = preg_quote(casting_sms_otp_var_name(), '/');
    $rendered = preg_replace('/\{' . $var . '\}/i', $code, $tpl) ?? $tpl;
    $rendered = str_replace(['{code}', '{CODE}', '%code%'], $code, $rendered);
    $rendered = preg_replace('/\{[A-Za-z][A-Za-z0-9_]*\}/', $code, $rendered) ?? $rendered;

    return trim($rendered);
}

/** pattern = با PatternId — send = SMS/Send با متن مطابق الگو (تشخیص خودکار {x}) */
function casting_sms_otp_method(): string
{
    return casting_sms_otp_pattern_id() !== '' ? 'pattern' : 'send';
}

/**
 * @return list<string>
 */
function casting_sms_otp_content_candidates(string $message, string $otp_code): array
{
    $code = preg_replace('/\D+/', '', $otp_code) ?? '';
    if ($code === '' && preg_match('/\d{4,8}/', $message, $m)) {
        $code = $m[0];
    }
    if ($code !== '') {
        return [casting_sms_otp_text($code)];
    }
    $message = trim($message);

    return $message !== '' ? [$message] : [];
}

/**
 * ارسال ساده طبق مستند HTTP GET پنل:
 * https://webone-sms.ir/SMSInOutBox/SendSms?username=&password=&from=&to=&text=
 *
 * @return array{ok:bool,error:string,ref_id?:string,http?:int,code?:int}
 */
function casting_sms_send_http_get(string $mobile, string $message): array
{
    if (!casting_sms_http_is_configured()) {
        return ['ok' => false, 'error' => 'برای ارسال HTTP، CASTING_SMS_USERNAME و CASTING_SMS_PASSWORD را در config.local.php بگذارید.'];
    }
    $from = defined('CASTING_SMS_FROM') ? trim((string) CASTING_SMS_FROM) : '';
    if ($from === '') {
        return ['ok' => false, 'error' => 'شماره فرستنده (CASTING_SMS_FROM) تنظیم نشده است.'];
    }
    $message = trim($message);
    if ($message === '') {
        return ['ok' => false, 'error' => 'متن پیامک خالی است.'];
    }

    $url_base = defined('CASTING_SMS_HTTP_SEND_URL') ? trim((string) CASTING_SMS_HTTP_SEND_URL) : '';
    if ($url_base === '') {
        $url_base = 'https://webone-sms.ir/SMSInOutBox/SendSms';
    }
    $query = [
        'username' => trim((string) CASTING_SMS_USERNAME),
        'password' => trim((string) CASTING_SMS_PASSWORD),
        'from'     => $from,
        'to'       => $mobile,
        'text'     => $message,
    ];
    $url = $url_base . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $debug_url = $url_base . '?' . http_build_query(array_merge($query, ['password' => '***']), '', '&', PHP_QUERY_RFC3986);

    $response = wp_remote_get($url, [
        'timeout' => 25,
        'headers' => [
            'Accept' => 'text/plain, application/json, */*',
        ],
    ]);
    if (is_wp_error($response)) {
        $out = ['ok' => false, 'error' => 'ارتباط با پنل پیامک برقرار نشد: ' . $response->get_error_message()];
        casting_sms_remember_last([
            'at'       => function_exists('current_time') ? current_time('mysql') : date('c'),
            'url'      => $debug_url,
            'endpoint' => 'HTTP-GET',
            'request'  => ['from' => $from, 'to' => $mobile, 'text' => $message],
            'error'    => $out['error'],
        ]);

        return $out;
    }

    $http = (int) wp_remote_retrieve_response_code($response);
    $raw_body = trim((string) wp_remote_retrieve_body($response));
    $raw_clip = function_exists('mb_substr')
        ? (string) mb_substr($raw_body, 0, 800, 'UTF-8')
        : substr($raw_body, 0, 800);
    $data = json_decode($raw_body, true);
    $debug = [
        'at'       => function_exists('current_time') ? current_time('mysql') : date('c'),
        'url'      => $debug_url,
        'endpoint' => 'HTTP-GET',
        'request'  => ['from' => $from, 'to' => $mobile, 'text' => $message],
        'http'     => $http,
        'body'     => is_array($data) ? $data : $raw_clip,
    ];

    if ($http < 200 || $http >= 300) {
        $out = ['ok' => false, 'error' => 'پاسخ نامعتبر از پنل پیامک (HTTP ' . $http . ').', 'http' => $http];
        $debug['ok'] = false;
        $debug['parsed_error'] = $out['error'];
        casting_sms_remember_last($debug);

        return $out;
    }

    if (is_array($data)) {
        $succeeded = casting_sms_is_truthy_success($data['succeeded'] ?? $data['Succeeded'] ?? $data['success'] ?? null);
        $result_code = isset($data['resultCode']) || isset($data['ResultCode'])
            ? (int) ($data['resultCode'] ?? $data['ResultCode'])
            : 0;
        $ref_id = trim((string) ($data['refId'] ?? $data['RefId'] ?? $data['id'] ?? ''));
        if ($succeeded && $result_code === 0) {
            $debug['ok'] = true;
            $debug['ref_id'] = $ref_id;
            casting_sms_remember_last($debug);

            return ['ok' => true, 'error' => '', 'ref_id' => $ref_id, 'http' => $http, 'code' => 0];
        }
        $code = $result_code !== 0 ? $result_code : 19;
        $out = ['ok' => false, 'error' => casting_sms_error_message($code), 'code' => $code, 'http' => $http];
        $debug['ok'] = false;
        $debug['parsed_error'] = $out['error'];
        casting_sms_remember_last($debug);

        return $out;
    }

    if (is_numeric($raw_body) && (float) $raw_body > 0) {
        $ref_id = (string) $raw_body;
        $debug['ok'] = true;
        $debug['ref_id'] = $ref_id;
        casting_sms_remember_last($debug);

        return ['ok' => true, 'error' => '', 'ref_id' => $ref_id, 'http' => $http, 'code' => 0];
    }

    $as_int = is_numeric($raw_body) ? (int) $raw_body : -1;
    $out = [
        'ok'    => false,
        'error' => $as_int >= 0 ? casting_sms_error_message($as_int) : ('ارسال HTTP ناموفق بود: ' . $raw_clip),
        'code'  => $as_int >= 0 ? $as_int : -1,
        'http'  => $http,
    ];
    $debug['ok'] = false;
    $debug['parsed_error'] = $out['error'];
    casting_sms_remember_last($debug);

    return $out;
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
    $otp_code = preg_replace('/\D+/', '', $otp_code) ?? '';
    if ($otp_code === '' && preg_match('/\d{4,8}/', $message, $m)) {
        $otp_code = $m[0];
    }
    if ($message === '' && $otp_code !== '') {
        $message = $otp_code;
    }
    if ($message === '') {
        return ['ok' => false, 'error' => 'متن پیامک خالی است.'];
    }

    // روش ۱: اگر کد الگو در تنظیمات باشد (Otino / RestDocument)
    $pattern_id = casting_sms_otp_pattern_id();
    if ($pattern_id !== '' && $otp_code !== '') {
        return casting_sms_send_otp_pattern($mobile, $otp_code, $pattern_id);
    }

    // روش ۲: ارسال متن کامل مطابق الگو تا پنل خودش {x} را تشخیص دهد
    $matched = $otp_code !== '' ? casting_sms_otp_text($otp_code) : $message;
    $result = casting_sms_send_otp_matched_text($mobile, $matched, $otp_code);
    $code = (int) ($result['code'] ?? 0);
    if (!empty($result['ok']) || ($code !== 19 && $code !== 0)) {
        return $result;
    }

    // روش ۳: SmartOTP فقط با رقم‌های کد (اگر الگوی پنل فقط {x} باشد)
    if ($otp_code !== '') {
        $otp_sender = casting_sms_otp_sender();
        if ($otp_sender === '') {
            $otp_sender = 'Auto';
        }
        $smart = casting_sms_request('SMS/SmartOTP', [
            'OTPSender' => $otp_sender,
            'ToNumber'  => $mobile,
            'Content'   => $otp_code,
        ]);
        if (!empty($smart['ok'])) {
            return [
                'ok'     => true,
                'error'  => '',
                'ref_id' => (string) ($smart['ref_id'] ?? ''),
            ];
        }
        $smart_code = (int) ($smart['code'] ?? 0);
        if ($smart_code !== 19) {
            return [
                'ok'     => false,
                'error'  => $smart['error'],
                'ref_id' => (string) ($smart['ref_id'] ?? ''),
                'code'   => $smart_code,
            ];
        }
        $result = [
            'ok'     => false,
            'error'  => $smart['error'],
            'ref_id' => (string) ($smart['ref_id'] ?? ''),
            'code'   => 19,
        ];
        $code = 19;
    }

    if ($code !== 19 || !casting_sms_http_is_configured()) {
        return $result;
    }

    $last = $result;
    foreach (casting_sms_otp_content_candidates($matched, $otp_code) as $content) {
        $last = casting_sms_send_http_get($mobile, $content);
        if (!empty($last['ok'])) {
            return $last;
        }
        if ((int) ($last['code'] ?? 0) !== 19) {
            return $last;
        }
    }

    return $last;
}

/**
 * ارسال OTP با متن منطبق بر الگو — POST /SMS/Send
 * پنل بخش ثابت را با الگو مقایسه می‌کند و مقدار {x} را از متن برمی‌دارد.
 *
 * @return array{ok:bool,error:string,ref_id?:string,code?:int}
 */
function casting_sms_send_otp_matched_text(string $mobile, string $content, string $otp_code = ''): array
{
    $from = defined('CASTING_SMS_FROM') ? trim((string) CASTING_SMS_FROM) : '';
    if ($from === '') {
        return ['ok' => false, 'error' => 'برای ارسال با الگو، CASTING_SMS_FROM لازم است.', 'code' => 3];
    }
    $content = trim($content);
    if ($content === '') {
        return ['ok' => false, 'error' => 'متن پیامک خالی است.'];
    }

    $payload = [
        'From'     => $from,
        'ToNumber' => $mobile,
        'Content'  => $content,
    ];
    if ($otp_code !== '') {
        $payload['PatternParameterData'] = [
            casting_sms_otp_var_name() => $otp_code,
        ];
    }

    $result = casting_sms_request('SMS/Send', $payload);
    $code = (int) ($result['code'] ?? 0);
    if (!$result['ok'] && $code === 19 && isset($payload['PatternParameterData'])) {
        unset($payload['PatternParameterData']);
        $result = casting_sms_request('SMS/Send', $payload);
        $code = (int) ($result['code'] ?? 0);
    }

    return [
        'ok'     => $result['ok'],
        'error'  => $result['error'],
        'ref_id' => (string) ($result['ref_id'] ?? ''),
        'code'   => $code,
    ];
}

/**
 * ارسال کد OTP با شناسه پترن — RestDocument v1.4
 *
 * POST {base}/SMS/Send
 * {
 *   "From": "1000...",
 *   "ToNumber": "0912...",
 *   "PatternId": "12345",
 *   "PatternParameterData": { "ParameterValue": "556587" }
 * }
 *
 * @return array{ok:bool,error:string,ref_id?:string}
 */
function casting_sms_send_otp_pattern(string $mobile, string $otp_code, string $pattern_id = ''): array
{
    $from = defined('CASTING_SMS_FROM') ? trim((string) CASTING_SMS_FROM) : '';
    if ($from === '') {
        return ['ok' => false, 'error' => 'برای ارسال OTP با الگو، CASTING_SMS_FROM لازم است.'];
    }
    if ($pattern_id === '') {
        $pattern_id = casting_sms_otp_pattern_id();
    }
    if ($pattern_id === '') {
        return ['ok' => false, 'error' => 'CASTING_SMS_OTP_PATTERN_ID خالی است.'];
    }

    $result = casting_sms_request('SMS/Send', [
        'From'                 => $from,
        'ToNumber'             => $mobile,
        'PatternId'            => $pattern_id,
        'PatternParameterData' => [
            casting_sms_otp_var_name() => $otp_code,
        ],
    ]);

    return [
        'ok'     => $result['ok'],
        'error'  => $result['error'],
        'ref_id' => (string) ($result['ref_id'] ?? ''),
    ];
}

/**
 * ارسال هوشمند کد OTP — RestDocument v1.4
 *
 * POST {base}/SMS/SmartOTP
 * OTPSender: Auto یا شماره خط | ToNumber | Content
 *
 * @return array{ok:bool,error:string,ref_id?:string}
 */
function casting_sms_send_smart_otp(string $mobile, string $message, string $otp_code = ''): array
{
    $otp_sender = casting_sms_otp_sender();
    if ($otp_sender === '') {
        $otp_sender = 'Auto';
    }
    $otp_code = preg_replace('/\D+/', '', $otp_code) ?? '';
    if ($otp_code === '' && preg_match('/\d{4,8}/', $message, $m)) {
        $otp_code = $m[0];
    }

    $last = ['ok' => false, 'error' => 'ارسال OTP ناموفق بود.', 'code' => -1];
    foreach (casting_sms_otp_content_candidates($message, $otp_code) as $content) {
        $payload = [
            'OTPSender' => $otp_sender,
            'ToNumber'  => $mobile,
            'Content'   => $content,
        ];
        $result = casting_sms_request('SMS/SmartOTP', $payload);
        $code = (int) ($result['code'] ?? 0);

        if (!$result['ok'] && $code === 3 && strcasecmp($otp_sender, 'Auto') !== 0) {
            $result = casting_sms_request('SMS/SmartOTP', [
                'OTPSender' => 'Auto',
                'ToNumber'  => $mobile,
                'Content'   => $content,
            ]);
            $code = (int) ($result['code'] ?? 0);
        }

        if ($result['ok']) {
            return [
                'ok'     => true,
                'error'  => '',
                'ref_id' => (string) ($result['ref_id'] ?? ''),
            ];
        }
        $last = [
            'ok'     => false,
            'error'  => $result['error'],
            'ref_id' => (string) ($result['ref_id'] ?? ''),
            'code'   => $code,
        ];
        if ($code !== 19) {
            break;
        }
    }

    return $last;
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
