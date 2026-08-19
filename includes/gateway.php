<?php
declare(strict_types=1);

/**
 * درگاه پرداخت — sandbox داخلی یا به‌پرداخت ملت (SOAP)
 * مشخصات ترمینال فقط در config.local.php
 */

function casting_gateway_mode(): string
{
    $mode = '';
    if (function_exists('get_option')) {
        $from_opt = strtolower(trim((string) get_option('casting_gateway_mode', '')));
        if (in_array($from_opt, ['sandbox', 'live', 'off'], true)) {
            $mode = $from_opt;
        }
    }
    if ($mode === '' && defined('CASTING_GATEWAY_MODE')) {
        $raw = strtolower(trim((string) CASTING_GATEWAY_MODE));
        if (in_array($raw, ['sandbox', 'live', 'off'], true)) {
            $mode = $raw;
        }
    }
    if ($mode === '') {
        $mode = 'live';
    }
    if ($mode === 'off' && casting_behpardakht_has_credentials()) {
        return 'live';
    }

    return $mode;
}

function casting_behpardakht_setting(string $option_key, string $constant_name): string
{
    if (function_exists('get_option')) {
        $from_opt = trim((string) get_option($option_key, ''));
        if ($from_opt !== '') {
            return $from_opt;
        }
    }
    if (defined($constant_name)) {
        return trim((string) constant($constant_name));
    }

    return '';
}

function casting_behpardakht_terminal_id(): string
{
    return casting_behpardakht_setting('casting_behpardakht_terminal_id', 'CASTING_BEHPARDAKHT_TERMINAL_ID');
}

function casting_behpardakht_username(): string
{
    return casting_behpardakht_setting('casting_behpardakht_username', 'CASTING_BEHPARDAKHT_USERNAME');
}

function casting_behpardakht_password(): string
{
    return casting_behpardakht_setting('casting_behpardakht_password', 'CASTING_BEHPARDAKHT_PASSWORD');
}

function casting_behpardakht_has_credentials(): bool
{
    return casting_behpardakht_terminal_id() !== ''
        && casting_behpardakht_username() !== ''
        && casting_behpardakht_password() !== '';
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_gateway_store_credentials(string $terminal, string $username, string $password): array
{
    $terminal = trim($terminal);
    $username = trim($username);
    $password = trim($password);
    if ($terminal === '' || $username === '' || $password === '') {
        return ['ok' => false, 'error' => 'شماره پایانه، نام کاربری و رمز درگاه را کامل وارد کنید.'];
    }
    if (!function_exists('update_option')) {
        return ['ok' => false, 'error' => 'ذخیره در وردپرس ممکن نیست.'];
    }

    update_option('casting_gateway_mode', 'live', false);
    update_option('casting_behpardakht_terminal_id', $terminal, false);
    update_option('casting_behpardakht_username', $username, false);
    update_option('casting_behpardakht_password', $password, false);

    $path = function_exists('casting_local_config_path')
        ? casting_local_config_path()
        : (dirname(__DIR__) . '/config.local.php');
    if (is_string($path) && $path !== '' && (is_writable($path) || (!file_exists($path) && is_writable(dirname($path))))) {
        $pairs = [
            'CASTING_GATEWAY_MODE'           => 'live',
            'CASTING_BEHPARDAKHT_TERMINAL_ID' => $terminal,
            'CASTING_BEHPARDAKHT_USERNAME'   => $username,
            'CASTING_BEHPARDAKHT_PASSWORD'   => $password,
        ];
        $src = is_readable($path) ? (string) file_get_contents($path) : "<?php\n";
        if (!str_contains($src, '<?php')) {
            $src = "<?php\n" . $src;
        }
        $escape = static function (string $value): string {
            return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
        };
        foreach ($pairs as $name => $value) {
            $line = "define('" . $name . "', '" . $escape($value) . "');";
            $pattern = "/define\s*\(\s*['\"]" . preg_quote($name, '/') . "['\"]\s*,\s*['\"][^'\"]*['\"]\s*\)\s*;/";
            if (preg_match($pattern, $src)) {
                $src = preg_replace($pattern, $line, $src, 1) ?? $src;
            } else {
                $src = rtrim($src) . "\n" . $line . "\n";
            }
        }
        @file_put_contents($path, $src, LOCK_EX);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * شروع پرداخت سفارش
 *
 * @return array{ok:bool,error:string,redirect?:string}
 */
function casting_gateway_start_payment(array $order): array
{
    if ($order === []) {
        return ['ok' => false, 'error' => 'سفارش پیدا نشد.'];
    }
    $mode = casting_gateway_mode();
    if ($mode === 'off') {
        return ['ok' => false, 'error' => 'درگاه بانکی هنوز فعال نشده است. سفارش شما ثبت شد؛ به‌محض اتصال درگاه می‌توانید پرداخت کنید.'];
    }

    $order_id = (int) ($order['id'] ?? 0);
    $code = (string) ($order['order_code'] ?? '');

    if ($mode === 'live') {
        return casting_mellat_start_payment($order);
    }

    $token = bin2hex(random_bytes(16));
    casting_order_update($order_id, [
        'status'      => 'awaiting_payment',
        'gateway_ref' => $token,
    ]);

    return [
        'ok'       => true,
        'error'    => '',
        'redirect' => 'checkout-gateway.php?order=' . rawurlencode($code) . '&token=' . rawurlencode($token),
    ];
}

/**
 * نتیجه بازگشت از درگاه (sandbox)
 *
 * @return array{ok:bool,error:string,order?:array<string,mixed>}
 */
function casting_gateway_complete_payment(string $order_code, string $token, bool $success, string $trace = ''): array
{
    if (casting_gateway_mode() === 'off') {
        return ['ok' => false, 'error' => 'درگاه پرداخت فعلاً غیرفعال است.'];
    }
    if (!function_exists('casting_get_order_by_code')) {
        require_once __DIR__ . '/checkout.php';
    }
    $order = casting_get_order_by_code($order_code);
    if ($order === []) {
        return ['ok' => false, 'error' => 'سفارش پیدا نشد.'];
    }
    if ((string) ($order['gateway_ref'] ?? '') !== '' && !hash_equals((string) $order['gateway_ref'], $token)) {
        return ['ok' => false, 'error' => 'توکن پرداخت نامعتبر است.'];
    }
    if ((string) ($order['status'] ?? '') === 'paid') {
        return ['ok' => true, 'error' => '', 'order' => $order];
    }

    if (!$success) {
        casting_order_update((int) $order['id'], [
            'status'        => 'failed',
            'gateway_trace' => sanitize_text_field($trace !== '' ? $trace : 'FAILED'),
        ]);
        $order = casting_get_order_by_code($order_code);

        return ['ok' => false, 'error' => 'پرداخت ناموفق بود.', 'order' => $order];
    }

    if ($trace === '') {
        $trace = 'BP' . wp_date('ymdHis') . random_int(100, 999);
    }
    casting_order_update((int) $order['id'], [
        'gateway_trace' => sanitize_text_field($trace),
    ]);
    $order = casting_get_order_by_code($order_code);
    $fulfill = casting_checkout_fulfill_order($order);
    if (!$fulfill['ok']) {
        return ['ok' => false, 'error' => $fulfill['error'], 'order' => $order];
    }
    $order = casting_get_order_by_code($order_code);

    return ['ok' => true, 'error' => '', 'order' => $order];
}

/**
 * بازگشت بانک ملت (بدون نیاز به ورود)
 *
 * @param array<string, mixed> $payload
 * @return array{ok:bool,cancelled:bool,error:string,order?:array<string,mixed>,redirect:string}
 */
function casting_gateway_handle_mellat_callback(array $payload): array
{
    $ref_id = sanitize_text_field((string) ($payload['RefId'] ?? $payload['refId'] ?? ''));
    $res_code = trim((string) ($payload['ResCode'] ?? $payload['resCode'] ?? ''));
    $sale_order_id = sanitize_text_field((string) ($payload['SaleOrderId'] ?? $payload['saleOrderId'] ?? ''));
    $sale_reference_id = sanitize_text_field((string) ($payload['SaleReferenceId'] ?? $payload['saleReferenceId'] ?? ''));
    $card_pan = sanitize_text_field((string) ($payload['CardHolderPan'] ?? $payload['cardHolderPan'] ?? ''));
    $final_amount = (int) ($payload['FinalAmount'] ?? $payload['finalAmount'] ?? 0);

    $order = [];
    if ($ref_id !== '' && function_exists('casting_get_order_by_gateway_ref')) {
        $order = casting_get_order_by_gateway_ref($ref_id);
    }
    if ($order === [] && $sale_order_id !== '' && function_exists('casting_get_order_by_gateway_trace')) {
        $order = casting_get_order_by_gateway_trace($sale_order_id);
    }
    if ($order === []) {
        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => 'سفارش متناظر با بازگشت بانک پیدا نشد.',
            'redirect'  => 'membership.php',
        ];
    }

    $code = (string) ($order['order_code'] ?? '');
    $result_url = 'checkout-result.php?order=' . rawurlencode($code);

    if ((string) ($order['status'] ?? '') === 'paid') {
        return [
            'ok'        => true,
            'cancelled' => false,
            'error'     => '',
            'order'     => $order,
            'redirect'  => $result_url . '&status=success',
        ];
    }

    $stored_ref = (string) ($order['gateway_ref'] ?? '');
    $stored_oid = (string) ($order['gateway_trace'] ?? '');
    $meta = is_array($order['meta'] ?? null) ? $order['meta'] : [];
    $meta_oid = (string) (($meta['mellat']['order_id'] ?? '') ?: '');
    if ($meta_oid !== '') {
        $stored_oid = $meta_oid;
    }

    if ($ref_id !== '' && $stored_ref !== '' && !hash_equals($stored_ref, $ref_id)) {
        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => 'شناسه پرداخت با سفارش هم‌خوانی ندارد.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }
    if ($sale_order_id !== '' && $stored_oid !== '' && !hash_equals($stored_oid, $sale_order_id)) {
        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => 'شماره سفارش بانکی با رکورد داخلی هم‌خوانی ندارد.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    if ($res_code === '17') {
        casting_order_update((int) $order['id'], ['status' => 'cancelled']);

        return [
            'ok'        => false,
            'cancelled' => true,
            'error'     => 'پرداخت توسط کاربر لغو شد.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=cancel',
        ];
    }

    if ($res_code !== '0') {
        casting_order_update((int) $order['id'], [
            'status'        => 'failed',
            'gateway_trace' => $sale_reference_id !== '' ? $sale_reference_id : $stored_oid,
        ]);
        casting_order_merge_meta((int) $order['id'], [
            'mellat' => array_merge(is_array($meta['mellat'] ?? null) ? $meta['mellat'] : [], [
                'res_code' => $res_code,
                'message'  => casting_mellat_res_message($res_code),
            ]),
        ]);

        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => casting_mellat_res_message($res_code),
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    $expected_rial = casting_mellat_amount_rial($order);
    if ($final_amount > 0 && $expected_rial > 0 && $final_amount !== $expected_rial) {
        error_log('[casting-mellat] amount mismatch order=' . $code . ' expected=' . $expected_rial . ' got=' . $final_amount);
        casting_order_update((int) $order['id'], ['status' => 'failed']);

        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => 'مبلغ بازگشتی با سفارش هم‌خوانی ندارد.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    $mellat_order_id = (int) ($sale_order_id !== '' ? $sale_order_id : $stored_oid);
    $sale_ref_int = (int) $sale_reference_id;
    if ($mellat_order_id <= 0 || $sale_ref_int <= 0) {
        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => 'اطلاعات بازگشت بانک ناقص است.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    $verify = casting_mellat_verify_and_settle($mellat_order_id, $sale_ref_int);
    if (!$verify['ok']) {
        casting_order_update((int) $order['id'], [
            'status'        => 'failed',
            'gateway_trace' => (string) $sale_ref_int,
        ]);
        casting_order_merge_meta((int) $order['id'], [
            'mellat' => array_merge(is_array($meta['mellat'] ?? null) ? $meta['mellat'] : [], [
                'sale_reference_id' => (string) $sale_ref_int,
                'verify_code'       => $verify['code'],
                'message'           => $verify['error'],
                'card_pan'          => casting_mellat_mask_pan($card_pan),
            ]),
        ]);

        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => $verify['error'],
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    casting_order_update((int) $order['id'], [
        'gateway_trace' => (string) $sale_ref_int,
    ]);
    casting_order_merge_meta((int) $order['id'], [
        'mellat' => array_merge(is_array($meta['mellat'] ?? null) ? $meta['mellat'] : [], [
            'ref_id'            => $ref_id,
            'order_id'          => (string) $mellat_order_id,
            'sale_reference_id' => (string) $sale_ref_int,
            'card_pan'          => casting_mellat_mask_pan($card_pan),
            'verified'          => true,
        ]),
    ]);

    $order = casting_get_order_by_code($code);
    $fulfill = casting_checkout_fulfill_order($order);
    if (!$fulfill['ok']) {
        error_log('[casting-mellat] fulfill failed after settle order=' . $code . ' err=' . $fulfill['error']);

        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => $fulfill['error'] !== '' ? $fulfill['error'] : 'پرداخت بانکی تأیید شد ولی فعال‌سازی سفارش انجام نشد. با پشتیبانی تماس بگیرید.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    $order = casting_get_order_by_code($code);

    return [
        'ok'        => true,
        'cancelled' => false,
        'error'     => '',
        'order'     => $order,
        'redirect'  => $result_url . '&status=success',
    ];
}

/**
 * @param array<string, mixed> $order
 * @return array{ok:bool,error:string,redirect?:string}
 */
function casting_mellat_start_payment(array $order): array
{
    $terminal = casting_behpardakht_terminal_id();
    $user = casting_behpardakht_username();
    $pass = casting_behpardakht_password();
    if ($terminal === '' || $user === '' || $pass === '') {
        return ['ok' => false, 'error' => 'مشخصات درگاه بانکی روی سرور تنظیم نشده است. از منوی ادمین «درگاه ملت» ذخیره کنید.'];
    }
    if (!class_exists('SoapClient')) {
        return ['ok' => false, 'error' => 'افزونه PHP SOAP روی سرور فعال نیست. از میزبان بخواهید php-soap را نصب کند.'];
    }

    $amount_rial = casting_mellat_amount_rial($order);
    if ($amount_rial < 1000) {
        return ['ok' => false, 'error' => 'مبلغ سفارش برای درگاه بانکی معتبر نیست.'];
    }

    $mellat_order_id = casting_mellat_new_order_id((int) ($order['id'] ?? 0));
    $callback = casting_mellat_callback_url();
    $params = [
        'terminalId'     => (int) $terminal,
        'userName'       => $user,
        'userPassword'   => $pass,
        'orderId'        => $mellat_order_id,
        'amount'         => $amount_rial,
        'localDate'      => wp_date('Ymd'),
        'localTime'      => wp_date('His'),
        'additionalData' => (string) ($order['order_code'] ?? ''),
        'callBackUrl'    => $callback,
        'payerId'        => 0,
    ];

    $raw = casting_mellat_soap_call('bpPayRequest', $params);
    if (!$raw['ok']) {
        return ['ok' => false, 'error' => $raw['error']];
    }

    $parts = explode(',', $raw['value'], 2);
    $code = trim((string) ($parts[0] ?? ''));
    $ref_id = trim((string) ($parts[1] ?? ''));
    if ($code !== '0' || $ref_id === '') {
        return ['ok' => false, 'error' => casting_mellat_res_message($code !== '' ? $code : $raw['value'])];
    }

    $order_id = (int) ($order['id'] ?? 0);
    $meta = is_array($order['meta'] ?? null) ? $order['meta'] : [];
    casting_order_update($order_id, [
        'status'        => 'awaiting_payment',
        'gateway_ref'   => $ref_id,
        'gateway_trace' => (string) $mellat_order_id,
    ]);
    casting_order_merge_meta($order_id, [
        'mellat' => array_merge(is_array($meta['mellat'] ?? null) ? $meta['mellat'] : [], [
            'order_id'     => (string) $mellat_order_id,
            'ref_id'       => $ref_id,
            'amount_rial'  => $amount_rial,
            'callback_url' => $callback,
        ]),
    ]);

    return [
        'ok'       => true,
        'error'    => '',
        'redirect' => 'checkout-gateway.php?order=' . rawurlencode((string) ($order['order_code'] ?? '')),
    ];
}

function casting_mellat_callback_url(): string
{
    if (defined('CASTING_MELLAT_CALLBACK_URL') && trim((string) CASTING_MELLAT_CALLBACK_URL) !== '') {
        return trim((string) CASTING_MELLAT_CALLBACK_URL);
    }
    $origin = rtrim((string) CASTING_MAIN_SITE_URL, '/');

    return $origin . '/casting-portal/checkout-callback.php';
}

function casting_mellat_pay_url(): string
{
    if (defined('CASTING_MELLAT_PAY_URL') && trim((string) CASTING_MELLAT_PAY_URL) !== '') {
        return trim((string) CASTING_MELLAT_PAY_URL);
    }

    return 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat';
}

/**
 * مبلغ پورتال تومان است؛ ملت ریال می‌خواهد.
 *
 * @param array<string, mixed> $order
 */
function casting_mellat_amount_rial(array $order): int
{
    $toman = (int) ($order['amount_final'] ?? 0);

    return $toman * 10;
}

function casting_mellat_new_order_id(int $portal_order_id): int
{
    if (PHP_INT_SIZE >= 8) {
        return (int) sprintf('%d%04d', time(), random_int(0, 9999));
    }

    return ((time() % 2000000) * 1000) + ($portal_order_id % 1000);
}

function casting_mellat_mobile_no(int $user_id): string
{
    if ($user_id <= 0) {
        return '';
    }
    $raw = (string) get_user_meta($user_id, 'casting_mobile', true);
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($digits, '98') && strlen($digits) === 12) {
        $digits = '0' . substr($digits, 2);
    }
    if (preg_match('/^09\d{9}$/', $digits)) {
        return '98' . substr($digits, 1);
    }

    return '';
}

function casting_mellat_mask_pan(string $pan): string
{
    $digits = preg_replace('/\D+/', '', $pan) ?? '';
    if (strlen($digits) < 8) {
        return '';
    }

    return substr($digits, 0, 6) . str_repeat('*', max(0, strlen($digits) - 10)) . substr($digits, -4);
}

/**
 * @return array{ok:bool,error:string,code:string}
 */
function casting_mellat_verify_and_settle(int $order_id, int $sale_reference_id): array
{
    $base = casting_mellat_auth_params();
    $payload = $base + [
        'orderId'          => $order_id,
        'saleOrderId'      => $order_id,
        'saleReferenceId'  => $sale_reference_id,
    ];

    $verify = casting_mellat_soap_call('bpVerifyRequest', $payload);
    if (!$verify['ok']) {
        $inquiry = casting_mellat_soap_call('bpInquiryRequest', $payload);
        if (!$inquiry['ok']) {
            return ['ok' => false, 'error' => $verify['error'], 'code' => ''];
        }
        $verify = $inquiry;
    }

    $vcode = casting_mellat_result_code($verify['value']);
    if (!in_array($vcode, ['0', '43'], true)) {
        return ['ok' => false, 'error' => casting_mellat_res_message($vcode !== '' ? $vcode : $verify['value']), 'code' => $vcode];
    }

    $settle = casting_mellat_soap_call('bpSettleRequest', $payload);
    if (!$settle['ok']) {
        return ['ok' => false, 'error' => $settle['error'], 'code' => ''];
    }
    $scode = casting_mellat_result_code($settle['value']);
    if (!in_array($scode, ['0', '45'], true)) {
        return ['ok' => false, 'error' => casting_mellat_res_message($scode !== '' ? $scode : $settle['value']), 'code' => $scode];
    }

    return ['ok' => true, 'error' => '', 'code' => '0'];
}

/**
 * @return array{terminalId:int,userName:string,userPassword:string}
 */
function casting_mellat_auth_params(): array
{
    return [
        'terminalId'   => (int) casting_behpardakht_terminal_id(),
        'userName'     => casting_behpardakht_username(),
        'userPassword' => casting_behpardakht_password(),
    ];
}

function casting_mellat_result_code(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $parts = explode(',', $raw, 2);

    return trim((string) $parts[0]);
}

/**
 * @param array<string, mixed> $params
 * @return array{ok:bool,error:string,value:string}
 */
function casting_mellat_soap_call(string $method, array $params): array
{
    $client = casting_mellat_soap_client();
    if ($client === null) {
        return [
            'ok'    => false,
            'error' => 'اتصال به وب‌سرویس بانک ملت برقرار نشد.',
            'value' => '',
        ];
    }

    try {
        $result = $client->{$method}($params);
        $value = casting_mellat_soap_string($result);
        if ($value === '') {
            return ['ok' => false, 'error' => 'پاسخ خالی از درگاه بانکی.', 'value' => ''];
        }

        return ['ok' => true, 'error' => '', 'value' => $value];
    } catch (Throwable $e) {
        error_log('[casting-mellat] ' . $method . ' ' . $e->getMessage());

        return [
            'ok'    => false,
            'error' => 'خطا در ارتباط با درگاه بانک ملت. کمی بعد دوباره تلاش کنید.',
            'value' => '',
        ];
    }
}

function casting_mellat_soap_client(): ?SoapClient
{
    static $client = null;
    static $failed = false;
    if ($failed) {
        return null;
    }
    if ($client instanceof SoapClient) {
        return $client;
    }
    if (!class_exists('SoapClient')) {
        $failed = true;

        return null;
    }

    $wsdl = defined('CASTING_MELLAT_WSDL') && (string) CASTING_MELLAT_WSDL !== ''
        ? (string) CASTING_MELLAT_WSDL
        : 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl';

    try {
        $client = new SoapClient($wsdl, [
            'encoding'           => 'UTF-8',
            'exceptions'         => true,
            'trace'              => false,
            'connection_timeout' => 25,
            'cache_wsdl'         => WSDL_CACHE_DISK,
            'soap_version'       => SOAP_1_1,
            'stream_context'     => stream_context_create([
                'http' => [
                    'timeout'    => 25,
                    'user_agent' => 'CastingPortal/Mellat',
                ],
                'ssl'  => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]),
        ]);

        return $client;
    } catch (Throwable $e) {
        $failed = true;
        error_log('[casting-mellat] SoapClient ' . $e->getMessage());

        return null;
    }
}

function casting_mellat_soap_string($result): string
{
    if (is_object($result)) {
        if (isset($result->return)) {
            $result = $result->return;
        } elseif (isset($result->bpPayRequestResult)) {
            $result = $result->bpPayRequestResult;
        }
    }
    if (is_array($result) && isset($result['return'])) {
        $result = $result['return'];
    }

    return trim((string) $result);
}

function casting_mellat_res_message(string $code): string
{
    $code = trim($code);
    $map = [
        '0'   => 'تراکنش با موفقیت انجام شد.',
        '11'  => 'شماره کارت نامعتبر است.',
        '12'  => 'موجودی کافی نیست.',
        '13'  => 'رمز کارت نادرست است.',
        '14'  => 'تعداد دفعات رمز بیش از حد مجاز است.',
        '15'  => 'کارت نامعتبر است.',
        '16'  => 'دفعات برداشت وجه بیش از حد مجاز است.',
        '17'  => 'کاربر از انجام تراکنش منصرف شد.',
        '18'  => 'تاریخ انقضای کارت گذشته است.',
        '19'  => 'مبلغ برداشت بیش از حد مجاز است.',
        '21'  => 'پذیرنده نامعتبر است.',
        '22'  => 'خطای امنیتی رخ داده است.',
        '23'  => 'خطای نامشخص از سوی پذیرنده.',
        '24'  => 'اطلاعات کاربری پذیرنده نامعتبر است.',
        '25'  => 'مبلغ نامعتبر است.',
        '31'  => 'پاسخ نامعتبر است.',
        '32'  => 'فرمت اطلاعات واردشده صحیح نیست.',
        '33'  => 'حساب نامعتبر است.',
        '34'  => 'خطای سیستمی.',
        '35'  => 'تاریخ نامعتبر است.',
        '41'  => 'شماره درخواست تکراری است. دوباره پرداخت را شروع کنید.',
        '42'  => 'تراکنش Sale یافت نشد.',
        '43'  => 'قبلاً درخواست Verify داده شده است.',
        '44'  => 'درخواست Verify یافت نشد.',
        '45'  => 'تراکنش قبلاً Settle شده است.',
        '46'  => 'تراکنش Settle نشده است.',
        '47'  => 'تراکنش Settle یافت نشد.',
        '48'  => 'تراکنش قبلاً Reverse شده است.',
        '49'  => 'تراکنش Refund یافت نشد.',
        '51'  => 'تراکنش تکراری است.',
        '54'  => 'تراکنش مرجع موجود نیست.',
        '55'  => 'تراکنش نامعتبر است.',
        '61'  => 'خطا در واریز.',
        '62'  => 'آدرس بازگشت با دامنه ثبت‌شده در به‌پرداخت هم‌خوانی ندارد.',
        '98'  => 'سقف استفاده از رمز دوم ایستا به پایان رسیده است.',
        '111' => 'صادرکننده کارت نامعتبر است.',
        '112' => 'خطای سوییچ صادرکننده کارت.',
        '113' => 'پاسخی از صادرکننده کارت دریافت نشد.',
        '114' => 'دارنده کارت مجاز به این تراکنش نیست.',
        '412' => 'شناسه قبض نادرست است.',
        '413' => 'شناسه پرداخت نادرست است.',
        '414' => 'سازمان صادرکننده قبض نامعتبر است.',
        '415' => 'زمان جلسه کاری به پایان رسیده است.',
        '416' => 'خطا در ثبت اطلاعات.',
        '417' => 'شناسه پرداخت‌کننده نامعتبر است.',
        '418' => 'اشکال در تعریف اطلاعات مشتری.',
        '419' => 'تعداد دفعات ورود اطلاعات بیش از حد مجاز است.',
        '421' => 'IP سرور در به‌پرداخت ثبت نشده است. آی‌پی خروجی هاست را به شرکت به‌پرداخت اعلام کنید.',
    ];

    if (isset($map[$code])) {
        return $map[$code];
    }
    if ($code !== '') {
        return 'خطای درگاه بانکی (کد ' . $code . ').';
    }

    return 'پاسخ نامعتبر از درگاه بانکی.';
}
