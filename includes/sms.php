<?php
declare(strict_types=1);

/**
 * ارسال پیامک از طریق WebOne SMS
 *
 * پنل: https://webone-sms.ir
 * Base API (مستند RestDocument v1.4): https://api.payamakapi.ir/api/v1/
 * هدر الزامی: X-API-KEY
 */

/**
 * @return array{
 *   plugin:string,
 *   plugins:list<string>,
 *   gateway:string,
 *   username:string,
 *   password:string,
 *   from:string,
 *   api_key:string,
 *   pattern_id:string,
 *   template:string,
 *   option_keys:list<string>
 * }
 */
function casting_sms_wp_plugin_config(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $out = [
        'plugin'      => '',
        'plugins'     => [],
        'gateway'     => '',
        'username'    => '',
        'password'    => '',
        'from'        => '',
        'api_key'     => '',
        'pattern_id'  => '',
        'template'    => '',
        'option_keys' => [],
    ];
    if (!function_exists('get_option')) {
        $cached = $out;

        return $cached;
    }

    $plugin_dirs = [];
    $plugin_root = defined('WP_PLUGIN_DIR') ? (string) WP_PLUGIN_DIR : '';
    if ($plugin_root !== '' && is_dir($plugin_root)) {
        $scan = @scandir($plugin_root);
        if (is_array($scan)) {
            foreach ($scan as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                if (!preg_match('/sms|payamak|webone|digits|otino|ippanel|kavenegar|melipayamak|faraz/i', $name)) {
                    continue;
                }
                $plugin_dirs[] = $name;
            }
        }
    }
    $active = (array) get_option('active_plugins', []);
    foreach ($active as $file) {
        $file = (string) $file;
        if (!preg_match('/sms|payamak|webone|digits|otino|ippanel|kavenegar|melipayamak|faraz/i', $file)) {
            continue;
        }
        $slug = explode('/', $file)[0] ?? $file;
        if ($slug !== '' && !in_array($slug, $plugin_dirs, true)) {
            $plugin_dirs[] = $slug;
        }
    }
    $out['plugins'] = $plugin_dirs;
    if ($plugin_dirs !== []) {
        $out['plugin'] = $plugin_dirs[0];
    }

    $candidates = [];
    $known = [
        'wpsms_settings',
        'wps_settings',
        'pwoosms_settings',
        'pwsms_settings',
        'sms_gateway',
        'sms_gateway_username',
        'sms_gateway_password',
        'sms_gateway_sender',
        'sms_gateway_name',
        'woocommerce_pwoosms_settings',
        'digit_api',
        'digit_settings',
        'digits_settings',
    ];
    if (isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb']) && method_exists($GLOBALS['wpdb'], 'get_col')) {
        global $wpdb;
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s LIMIT 80",
                '%' . $wpdb->esc_like('sms') . '%',
                '%' . $wpdb->esc_like('wpsms') . '%',
                '%' . $wpdb->esc_like('pwoo') . '%',
                '%' . $wpdb->esc_like('webone') . '%',
                '%' . $wpdb->esc_like('payamak') . '%'
            )
        );
        if (is_array($rows)) {
            foreach ($rows as $name) {
                $known[] = (string) $name;
            }
        }
    }
    $known = array_values(array_unique($known));

    $pick = static function (array $bag, array $keys): string {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $bag)) {
                continue;
            }
            $val = $bag[$key];
            if (is_scalar($val)) {
                $val = trim((string) $val);
                if ($val !== '') {
                    return $val;
                }
            }
        }

        return '';
    };
    $walk = static function ($data, array &$found) use (&$walk, $pick): void {
        if (!is_array($data)) {
            return;
        }
        $found['username'] = $found['username'] !== '' ? $found['username'] : $pick($data, [
            'username', 'userName', 'user_name', 'gateway_username', 'sms_gateway_username', 'ws_username',
        ]);
        $found['password'] = $found['password'] !== '' ? $found['password'] : $pick($data, [
            'password', 'passwd', 'gateway_password', 'sms_gateway_password', 'ws_password',
        ]);
        $found['from'] = $found['from'] !== '' ? $found['from'] : $pick($data, [
            'from', 'sender', 'sender_id', 'gateway_sender_id', 'sms_gateway_sender', 'fromNumber', 'from_number', 'senderNumber',
        ]);
        $found['api_key'] = $found['api_key'] !== '' ? $found['api_key'] : $pick($data, [
            'api_key', 'apiKey', 'apikey', 'gateway_key', 'has_key', 'token', 'key', 'X-API-KEY',
        ]);
        $found['gateway'] = $found['gateway'] !== '' ? $found['gateway'] : $pick($data, [
            'gateway', 'gateway_name', 'sms_gateway', 'webservice',
        ]);
        $found['pattern_id'] = $found['pattern_id'] !== '' ? $found['pattern_id'] : $pick($data, [
            'pattern_id', 'PatternId', 'patternid', 'otp_pattern_id', 'patternId',
        ]);
        $found['template'] = $found['template'] !== '' ? $found['template'] : $pick($data, [
            'template', 'otp_template', 'pattern', 'pattern_text', 'otp_pattern', 'message_pattern',
        ]);
        foreach ($data as $value) {
            if (is_array($value)) {
                $walk($value, $found);
            }
        }
    };

    $found = ['username' => '', 'password' => '', 'from' => '', 'api_key' => '', 'gateway' => '', 'pattern_id' => '', 'template' => ''];
    foreach ($known as $option_name) {
        $value = get_option($option_name);
        if ($value === false || $value === null || $value === '') {
            continue;
        }
        $out['option_keys'][] = $option_name;
        if (is_string($value) && !is_array($value)) {
            $lower = strtolower($option_name);
            $trim = trim($value);
            if ($trim === '') {
                continue;
            }
            if (str_contains($lower, 'username') || str_contains($lower, 'user_name')) {
                $found['username'] = $found['username'] !== '' ? $found['username'] : $trim;
            } elseif (str_contains($lower, 'password') || str_contains($lower, 'passwd')) {
                $found['password'] = $found['password'] !== '' ? $found['password'] : $trim;
            } elseif (str_contains($lower, 'sender') || str_contains($lower, 'from')) {
                $found['from'] = $found['from'] !== '' ? $found['from'] : $trim;
            } elseif (str_contains($lower, 'key') || str_contains($lower, 'token')) {
                $found['api_key'] = $found['api_key'] !== '' ? $found['api_key'] : $trim;
            } elseif (str_contains($lower, 'pattern_id') || str_contains($lower, 'patternid')) {
                $found['pattern_id'] = $found['pattern_id'] !== '' ? $found['pattern_id'] : $trim;
            } elseif (str_contains($lower, 'template') || (str_contains($lower, 'pattern') && !str_contains($lower, 'gateway'))) {
                $found['template'] = $found['template'] !== '' ? $found['template'] : $trim;
            }
            continue;
        }
        if (is_array($value)) {
            $walk($value, $found);
        }
    }

    if ($found['api_key'] === '' && $found['username'] !== '' && preg_match('/^[0-9a-f-]{8,}\.[0-9a-f-]{8,}/i', $found['username'])) {
        $found['api_key'] = $found['username'];
    }

    $out['username'] = $found['username'];
    $out['password'] = $found['password'];
    $out['from'] = $found['from'];
    $out['api_key'] = $found['api_key'];
    $out['gateway'] = $found['gateway'];
    $out['pattern_id'] = $found['pattern_id'];
    $out['template'] = $found['template'];
    $cached = $out;

    return $cached;
}

function casting_sms_mask_secret(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $len = function_exists('mb_strlen') ? (int) mb_strlen($value, 'UTF-8') : strlen($value);
    if ($len <= 4) {
        return str_repeat('•', $len);
    }
    $head = function_exists('mb_substr') ? (string) mb_substr($value, 0, 2, 'UTF-8') : substr($value, 0, 2);
    $tail = function_exists('mb_substr') ? (string) mb_substr($value, -2, null, 'UTF-8') : substr($value, -2);

    return $head . str_repeat('•', min(12, $len - 4)) . $tail;
}

function casting_sms_api_key(): string
{
    $key = defined('CASTING_SMS_API_KEY') ? trim((string) CASTING_SMS_API_KEY) : '';
    if ($key !== '') {
        return $key;
    }

    return trim((string) (casting_sms_wp_plugin_config()['api_key'] ?? ''));
}

function casting_sms_username(): string
{
    $user = defined('CASTING_SMS_USERNAME') ? trim((string) CASTING_SMS_USERNAME) : '';
    if ($user !== '') {
        return $user;
    }

    return trim((string) (casting_sms_wp_plugin_config()['username'] ?? ''));
}

function casting_sms_password(): string
{
    $pass = defined('CASTING_SMS_PASSWORD') ? trim((string) CASTING_SMS_PASSWORD) : '';
    if ($pass !== '') {
        return $pass;
    }

    return trim((string) (casting_sms_wp_plugin_config()['password'] ?? ''));
}

function casting_sms_is_configured(): bool
{
    if (defined('CASTING_SMS_ENABLED') && !CASTING_SMS_ENABLED) {
        return false;
    }
    if (casting_sms_api_key() !== '') {
        return true;
    }

    return casting_sms_http_is_configured();
}

function casting_sms_http_is_configured(): bool
{
    return casting_sms_username() !== '' && casting_sms_password() !== '';
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

/**
 * فرستنده SmartOTP — RestDocument: Auto یا شماره خط.
 * بدون خط OTP اختصاصی باید Auto باشد تا خط خدماتی سیستم انتخاب شود.
 */
function casting_sms_otp_sender(): string
{
    $raw = defined('CASTING_SMS_OTP_SENDER') ? trim((string) CASTING_SMS_OTP_SENDER) : '';
    if ($raw === '' || preg_match('/^09\d{9}$/', $raw)) {
        return 'Auto';
    }

    return $raw;
}

/**
 * خط From برای POST /SMS/Send (پیامک متنی و ارسال با PatternId).
 * Auto و موبایل شخصی ۰۹ اینجا مجاز نیست.
 */
function casting_sms_line_number(): string
{
    $from = defined('CASTING_SMS_FROM') ? trim((string) CASTING_SMS_FROM) : '';
    if ($from === '' || strcasecmp($from, 'Auto') === 0 || preg_match('/^09\d{9}$/', $from)) {
        $from = trim((string) (casting_sms_wp_plugin_config()['from'] ?? ''));
    }
    if ($from !== '' && strcasecmp($from, 'Auto') !== 0 && !preg_match('/^09\d{9}$/', $from)) {
        return $from;
    }

    return '9998624065';
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

        $api_key = casting_sms_api_key();
        $url = casting_sms_api_base() . ltrim($endpoint, '/');

        $response = wp_remote_post($url, [
            'timeout'     => 25,
            'redirection' => 0,
            'headers'     => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'X-API-KEY'    => $api_key,
            ],
            'body'        => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'data_format' => 'body',
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

        $api_key = casting_sms_api_key();
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
    $id = defined('CASTING_SMS_OTP_PATTERN_ID') ? trim((string) CASTING_SMS_OTP_PATTERN_ID) : '';
    if ($id !== '') {
        return $id;
    }

    return trim((string) (casting_sms_wp_plugin_config()['pattern_id'] ?? ''));
}

/** الگوی Otino/WebOne: یک بخش ثابت + یک متغیر {x} — پیش‌فرض همان مثال پنل */
function casting_sms_otp_template(): string
{
    $tpl = defined('CASTING_SMS_OTP_TEMPLATE') ? trim((string) CASTING_SMS_OTP_TEMPLATE) : '';
    if ($tpl !== '') {
        return $tpl;
    }
    $plugin_tpl = trim((string) (casting_sms_wp_plugin_config()['template'] ?? ''));
    if ($plugin_tpl !== '') {
        return $plugin_tpl;
    }

    return 'کد ورود شما {x}';
}

/**
 * نام متغیر داخل متن الگو ({x}) — فقط برای ساخت Content در SmartOTP.
 * کلید JSON وب‌سرویس این نیست؛ آن همیشه ParameterValue است.
 */
function casting_sms_otp_var_name(): string
{
    $tpl = casting_sms_otp_template();
    if (preg_match('/\{([A-Za-z][A-Za-z0-9_]*)\}/', $tpl, $m)) {
        return $m[1];
    }

    return 'x';
}

/**
 * RestDocument v1.4: PatternParameterData فقط همین کلید را دارد.
 *
 * @return array{ParameterValue:string}
 */
function casting_sms_pattern_parameter_data(string $otp_code): array
{
    return ['ParameterValue' => $otp_code];
}

function casting_sms_otp_pattern_param(): string
{
    return 'ParameterValue';
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

/** pattern = با PatternId — text = همان POST /SMS/Send که پیامک متنی کار می‌کند */
function casting_sms_otp_method(): string
{
    return casting_sms_otp_pattern_id() !== '' ? 'pattern' : 'text';
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
    $from = casting_sms_line_number();
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
        'username' => casting_sms_username(),
        'password' => casting_sms_password(),
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

    $last = ['ok' => false, 'error' => 'ارسال کد تأیید ناموفق بود.', 'code' => -1];

    // روش ۱ RestDocument: POST /SMS/Send با PatternId — بدون Content
    $pattern_id = casting_sms_otp_pattern_id();
    if ($pattern_id !== '' && $otp_code !== '') {
        $pattern = casting_sms_send_otp_pattern($mobile, $otp_code, $pattern_id);
        if (!empty($pattern['ok'])) {
            return $pattern;
        }
        $last = $pattern;
    }

    // روش ۲: همان مسیر پیامک متنی که الان کار می‌کند — POST /SMS/Send
    // بدون PatternId نباید SmartOTP زد؛ Content باید عین الگوی پنل باشد و کد ۱۹ می‌دهد.
    $text = casting_sms_send_text($mobile, $message);
    if (!empty($text['ok'])) {
        return $text;
    }
    $last = $text;

    if (casting_sms_http_is_configured()) {
        $http = casting_sms_send_http_get($mobile, $message);
        if (!empty($http['ok'])) {
            return $http;
        }
        $last = $http;
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
    $from = casting_sms_line_number();
    if ($from === '') {
        return ['ok' => false, 'error' => 'برای ارسال با الگو، CASTING_SMS_FROM لازم است.', 'code' => 3];
    }
    $content = trim($content);
    if ($content === '') {
        return ['ok' => false, 'error' => 'متن پیامک خالی است.'];
    }

    // ارسال تکی RestDocument: فقط From + ToNumber + Content
    $result = casting_sms_request('SMS/Send', [
        'From'     => $from,
        'ToNumber' => $mobile,
        'Content'  => $content,
    ]);
    $code = (int) ($result['code'] ?? 0);

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
    $from = casting_sms_line_number();
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
        'PatternId'            => (string) $pattern_id,
        'PatternParameterData' => casting_sms_pattern_parameter_data($otp_code),
    ]);

    return [
        'ok'     => $result['ok'],
        'error'  => $result['error'],
        'ref_id' => (string) ($result['ref_id'] ?? ''),
        'code'   => (int) ($result['code'] ?? 0),
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
    $otp_code = preg_replace('/\D+/', '', $otp_code) ?? '';
    if ($otp_code === '' && preg_match('/\d{4,8}/', $message, $m)) {
        $otp_code = $m[0];
    }
    $contents = casting_sms_otp_content_candidates($message, $otp_code);
    if ($contents === []) {
        return ['ok' => false, 'error' => 'متن پیامک خالی است.', 'code' => -1];
    }

    $preferred = casting_sms_otp_sender();
    $senders = [$preferred];
    if (strcasecmp($preferred, 'Auto') !== 0) {
        $senders[] = 'Auto';
    }

    $last = ['ok' => false, 'error' => 'ارسال OTP ناموفق بود.', 'code' => -1];
    foreach ($contents as $content) {
        foreach ($senders as $otp_sender) {
            $result = casting_sms_request('SMS/SmartOTP', [
                'OTPSender' => $otp_sender,
                'ToNumber'  => $mobile,
                'Content'   => $content,
            ]);
            $code = (int) ($result['code'] ?? 0);
            if (!empty($result['ok'])) {
                return [
                    'ok'     => true,
                    'error'  => '',
                    'ref_id' => (string) ($result['ref_id'] ?? ''),
                    'code'   => 0,
                ];
            }
            $last = [
                'ok'     => false,
                'error'  => $result['error'],
                'ref_id' => (string) ($result['ref_id'] ?? ''),
                'code'   => $code,
            ];
            // ۳ = فرستنده نامعتبر، ۱۷ = خط OTP نیست — فرستنده بعدی
            if ($code !== 3 && $code !== 17 && $code !== 19) {
                return $last;
            }
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

    $from = casting_sms_line_number();
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
        'code'   => (int) ($result['code'] ?? 0),
    ];
}
