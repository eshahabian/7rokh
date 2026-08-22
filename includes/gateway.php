<?php
declare(strict_types=1);

/**
 * درگاه پرداخت — sandbox داخلی، به‌پرداخت ملت (SOAP)، یا سامان SEP (REST)
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
    if ($mode === 'off' && casting_gateway_has_credentials()) {
        return 'live';
    }

    return $mode;
}

function casting_gateway_provider(): string
{
    $provider = '';
    if (function_exists('get_option')) {
        $from_opt = strtolower(trim((string) get_option('casting_gateway_provider', '')));
        if (in_array($from_opt, ['mellat', 'sep'], true)) {
            $provider = $from_opt;
        }
    }
    if ($provider === '' && defined('CASTING_GATEWAY_PROVIDER')) {
        $raw = strtolower(trim((string) CASTING_GATEWAY_PROVIDER));
        if (in_array($raw, ['mellat', 'sep'], true)) {
            $provider = $raw;
        }
    }
    if ($provider === '') {
        $provider = 'mellat';
    }
    if ($provider === 'sep' && !casting_sep_has_credentials() && casting_behpardakht_has_credentials()) {
        return 'mellat';
    }
    if ($provider === 'mellat' && !casting_behpardakht_has_credentials() && casting_sep_has_credentials()) {
        return 'sep';
    }

    return $provider;
}

function casting_gateway_has_credentials(): bool
{
    if (casting_gateway_provider() === 'sep') {
        return casting_sep_has_credentials();
    }

    return casting_behpardakht_has_credentials();
}

function casting_gateway_label(): string
{
    return casting_gateway_provider() === 'sep' ? 'بانک سامان (SEP)' : 'بانک ملت';
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
 * @return array{ok:bool,error:string}
 */
function casting_gateway_store_provider(string $provider): array
{
    $provider = strtolower(trim($provider));
    if (!in_array($provider, ['mellat', 'sep'], true)) {
        return ['ok' => false, 'error' => 'درگاه انتخاب‌شده معتبر نیست.'];
    }
    if (!function_exists('update_option')) {
        return ['ok' => false, 'error' => 'ذخیره در وردپرس ممکن نیست.'];
    }

    update_option('casting_gateway_provider', $provider, false);
    update_option('casting_gateway_mode', 'live', false);

    $path = function_exists('casting_local_config_path')
        ? casting_local_config_path()
        : (dirname(__DIR__) . '/config.local.php');
    if (is_string($path) && $path !== '' && (is_writable($path) || (!file_exists($path) && is_writable(dirname($path))))) {
        $line = "define('CASTING_GATEWAY_PROVIDER', '" . str_replace("'", "\\'", $provider) . "');";
        $src = is_readable($path) ? (string) file_get_contents($path) : "<?php\n";
        if (!str_contains($src, '<?php')) {
            $src = "<?php\n" . $src;
        }
        $pattern = "/define\s*\(\s*['\"]CASTING_GATEWAY_PROVIDER['\"]\s*,\s*['\"][^'\"]*['\"]\s*\)\s*;/";
        if (preg_match($pattern, $src)) {
            $src = preg_replace($pattern, $line, $src, 1) ?? $src;
        } else {
            $src = rtrim($src) . "\n" . $line . "\n";
        }
        @file_put_contents($path, $src, LOCK_EX);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_gateway_store_sep_credentials(string $terminal): array
{
    $terminal = trim($terminal);
    if ($terminal === '') {
        return ['ok' => false, 'error' => 'شماره ترمینال سامان را وارد کنید.'];
    }
    if (!function_exists('update_option')) {
        return ['ok' => false, 'error' => 'ذخیره در وردپرس ممکن نیست.'];
    }

    update_option('casting_gateway_mode', 'live', false);
    update_option('casting_gateway_provider', 'sep', false);
    update_option('casting_sep_terminal_id', $terminal, false);

    $path = function_exists('casting_local_config_path')
        ? casting_local_config_path()
        : (dirname(__DIR__) . '/config.local.php');
    if (is_string($path) && $path !== '' && (is_writable($path) || (!file_exists($path) && is_writable(dirname($path))))) {
        $pairs = [
            'CASTING_GATEWAY_MODE'     => 'live',
            'CASTING_GATEWAY_PROVIDER' => 'sep',
            'CASTING_SEP_TERMINAL_ID'  => $terminal,
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
 * @return array{ok:bool,error:string}
 */
function casting_gateway_store_provider(string $provider): array
{
    $provider = strtolower(trim($provider));
    if (!in_array($provider, ['mellat', 'sep'], true)) {
        return ['ok' => false, 'error' => 'درگاه انتخاب‌شده معتبر نیست.'];
    }
    if (!function_exists('update_option')) {
        return ['ok' => false, 'error' => 'ذخیره در وردپرس ممکن نیست.'];
    }

    update_option('casting_gateway_provider', $provider, false);
    update_option('casting_gateway_mode', 'live', false);

    $path = function_exists('casting_local_config_path')
        ? casting_local_config_path()
        : (dirname(__DIR__) . '/config.local.php');
    if (is_string($path) && $path !== '' && (is_writable($path) || (!file_exists($path) && is_writable(dirname($path))))) {
        $line = "define('CASTING_GATEWAY_PROVIDER', '" . str_replace("'", "\\'", $provider) . "');";
        $src = is_readable($path) ? (string) file_get_contents($path) : "<?php\n";
        if (!str_contains($src, '<?php')) {
            $src = "<?php\n" . $src;
        }
        $pattern = "/define\s*\(\s*['\"]CASTING_GATEWAY_PROVIDER['\"]\s*,\s*['\"][^'\"]*['\"]\s*\)\s*;/";
        if (preg_match($pattern, $src)) {
            $src = preg_replace($pattern, $line, $src, 1) ?? $src;
        } else {
            $src = rtrim($src) . "\n" . $line . "\n";
        }
        @file_put_contents($path, $src, LOCK_EX);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_gateway_store_sep_credentials(string $terminal): array
{
    $terminal = trim($terminal);
    if ($terminal === '') {
        return ['ok' => false, 'error' => 'شماره ترمینال سامان را وارد کنید.'];
    }
    if (!function_exists('update_option')) {
        return ['ok' => false, 'error' => 'ذخیره در وردپرس ممکن نیست.'];
    }

    update_option('casting_gateway_mode', 'live', false);
    update_option('casting_gateway_provider', 'sep', false);
    update_option('casting_sep_terminal_id', $terminal, false);

    $path = function_exists('casting_local_config_path')
        ? casting_local_config_path()
        : (dirname(__DIR__) . '/config.local.php');
    if (is_string($path) && $path !== '' && (is_writable($path) || (!file_exists($path) && is_writable(dirname($path))))) {
        $pairs = [
            'CASTING_GATEWAY_MODE'     => 'live',
            'CASTING_GATEWAY_PROVIDER' => 'sep',
            'CASTING_SEP_TERMINAL_ID'  => $terminal,
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
        return casting_gateway_provider() === 'sep'
            ? casting_sep_start_payment($order)
            : casting_mellat_start_payment($order);
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

function casting_request_is_mellat_callback(): bool
{
    $payload = array_merge($_GET, $_POST);
    $ref = trim((string) ($payload['RefId'] ?? $payload['refId'] ?? ''));
    $res = trim((string) ($payload['ResCode'] ?? $payload['resCode'] ?? ''));
    $sale = trim((string) ($payload['SaleOrderId'] ?? $payload['saleOrderId'] ?? ''));

    return $ref !== '' && ($res !== '' || $sale !== '');
}

function casting_gateway_finish_mellat_callback(): void
{
    $result = casting_gateway_handle_mellat_callback(array_merge($_GET, $_POST));

    if (!empty($result['error']) && empty($result['ok']) && empty($result['cancelled'])) {
        error_log('[casting-mellat] callback: ' . (string) $result['error']);
    }

    $redirect = (string) ($result['redirect'] ?? 'membership.php');
    if (!empty($result['ok'])) {
        casting_set_flash('success', 'پرداخت شما با موفقیت انجام شد.');
    } elseif (!empty($result['cancelled'])) {
        casting_set_flash('error', 'پرداخت لغو شد.');
    } else {
        casting_set_flash('error', (string) ($result['error'] !== '' ? $result['error'] : 'پرداخت ناموفق بود.'));
    }

    casting_redirect($redirect);
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

    return $origin . '/casting-portal/cart.php';
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

function casting_sep_setting(string $option_key, string $constant_name): string
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

function casting_sep_terminal_id(): string
{
    return casting_sep_setting('casting_sep_terminal_id', 'CASTING_SEP_TERMINAL_ID');
}

function casting_sep_has_credentials(): bool
{
    return casting_sep_terminal_id() !== '';
}

function casting_sep_token_url(): string
{
    if (defined('CASTING_SEP_TOKEN_URL') && trim((string) CASTING_SEP_TOKEN_URL) !== '') {
        return trim((string) CASTING_SEP_TOKEN_URL);
    }

    return 'https://sep.shaparak.ir/OnlinePG/OnlinePG';
}

function casting_sep_pay_url(): string
{
    if (defined('CASTING_SEP_PAY_URL') && trim((string) CASTING_SEP_PAY_URL) !== '') {
        return trim((string) CASTING_SEP_PAY_URL);
    }

    return 'https://sep.shaparak.ir/OnlinePG/OnlinePG';
}

function casting_sep_verify_url(): string
{
    if (defined('CASTING_SEP_VERIFY_URL') && trim((string) CASTING_SEP_VERIFY_URL) !== '') {
        return trim((string) CASTING_SEP_VERIFY_URL);
    }

    return 'https://sep.shaparak.ir/verifyTxnRandomSessionkey/ipg/VerifyTransaction';
}

function casting_sep_callback_url(): string
{
    if (defined('CASTING_SEP_CALLBACK_URL') && trim((string) CASTING_SEP_CALLBACK_URL) !== '') {
        return trim((string) CASTING_SEP_CALLBACK_URL);
    }
    $origin = rtrim((string) CASTING_MAIN_SITE_URL, '/');

    return $origin . '/casting-portal/cart.php';
}

/**
 * @param array<string, mixed> $order
 */
function casting_sep_amount_rial(array $order): int
{
    return casting_mellat_amount_rial($order);
}

function casting_sep_cell_number(int $user_id): string
{
    if ($user_id <= 0) {
        return '';
    }
    $raw = (string) get_user_meta($user_id, 'casting_mobile', true);
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($digits, '98') && strlen($digits) === 12) {
        $digits = '0' . substr($digits, 2);
    }
    if (str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }
    if (preg_match('/^9\d{9}$/', $digits)) {
        return $digits;
    }

    return '';
}

function casting_request_is_sep_callback(): bool
{
    if (casting_request_is_mellat_callback()) {
        return false;
    }
    $payload = array_merge($_GET, $_POST);
    $res = trim((string) ($payload['ResNum'] ?? ''));
    $state = trim((string) ($payload['State'] ?? $payload['state'] ?? ''));
    $ref = trim((string) ($payload['RefNum'] ?? ''));

    return $res !== '' && ($state !== '' || $ref !== '');
}

function casting_gateway_finish_sep_callback(): void
{
    $result = casting_gateway_handle_sep_callback(array_merge($_GET, $_POST));

    if (!empty($result['error']) && empty($result['ok']) && empty($result['cancelled'])) {
        error_log('[casting-sep] callback: ' . (string) $result['error']);
    }

    $redirect = (string) ($result['redirect'] ?? 'membership.php');
    if (!empty($result['ok'])) {
        casting_set_flash('success', 'پرداخت شما با موفقیت انجام شد.');
    } elseif (!empty($result['cancelled'])) {
        casting_set_flash('error', 'پرداخت لغو شد.');
    } else {
        casting_set_flash('error', (string) ($result['error'] !== '' ? $result['error'] : 'پرداخت ناموفق بود.'));
    }

    casting_redirect($redirect);
}

/**
 * @param array<string, mixed> $payload
 * @return array{ok:bool,cancelled:bool,error:string,order?:array<string,mixed>,redirect:string}
 */
function casting_gateway_handle_sep_callback(array $payload): array
{
    $ref_num = sanitize_text_field((string) ($payload['RefNum'] ?? ''));
    $res_num = sanitize_text_field((string) ($payload['ResNum'] ?? ''));
    $state = trim((string) ($payload['State'] ?? $payload['state'] ?? ''));
    $trace_no = sanitize_text_field((string) ($payload['TraceNo'] ?? ''));
    $terminal = sanitize_text_field((string) ($payload['TerminalId'] ?? $payload['MID'] ?? ''));
    $amount = (int) ($payload['Amount'] ?? 0);
    $secure_pan = sanitize_text_field((string) ($payload['SecurePan'] ?? ''));

    if (!function_exists('casting_get_order_by_code')) {
        require_once __DIR__ . '/checkout.php';
    }

    $order = $res_num !== '' ? casting_get_order_by_code($res_num) : [];
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

    $meta = is_array($order['meta'] ?? null) ? $order['meta'] : [];
    $stored_token = (string) ($order['gateway_ref'] ?? '');
    $stored_res = (string) (($meta['sep']['res_num'] ?? '') ?: $code);
    if ($stored_res !== '' && $res_num !== '' && !hash_equals($stored_res, $res_num)) {
        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => 'شماره خرید با سفارش هم‌خوانی ندارد.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    if (strcasecmp($state, 'CanceledByUser') === 0) {
        casting_order_update((int) $order['id'], ['status' => 'cancelled']);

        return [
            'ok'        => false,
            'cancelled' => true,
            'error'     => 'پرداخت توسط کاربر لغو شد.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=cancel',
        ];
    }

    if (strcasecmp($state, 'OK') !== 0 || $ref_num === '') {
        $message = casting_sep_state_message($state);
        casting_order_update((int) $order['id'], [
            'status'        => 'failed',
            'gateway_trace' => $trace_no !== '' ? $trace_no : $ref_num,
        ]);
        casting_order_merge_meta((int) $order['id'], [
            'sep' => array_merge(is_array($meta['sep'] ?? null) ? $meta['sep'] : [], [
                'state'   => $state,
                'message' => $message,
            ]),
        ]);

        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => $message,
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    if (casting_sep_refnum_already_used($ref_num, (int) ($order['id'] ?? 0))) {
        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => 'این رسید دیجیتالی قبلاً استفاده شده است.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    $expected_rial = casting_sep_amount_rial($order);
    if ($amount > 0 && $expected_rial > 0 && $amount !== $expected_rial) {
        error_log('[casting-sep] amount mismatch order=' . $code . ' expected=' . $expected_rial . ' got=' . $amount);
        casting_order_update((int) $order['id'], ['status' => 'failed']);

        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => 'مبلغ بازگشتی با سفارش هم‌خوانی ندارد.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    $terminal_id = $terminal !== '' ? $terminal : casting_sep_terminal_id();
    $verify = casting_sep_verify_transaction($ref_num, $terminal_id);
    if (!$verify['ok']) {
        casting_order_update((int) $order['id'], [
            'status'        => 'failed',
            'gateway_trace' => $trace_no !== '' ? $trace_no : $ref_num,
        ]);
        casting_order_merge_meta((int) $order['id'], [
            'sep' => array_merge(is_array($meta['sep'] ?? null) ? $meta['sep'] : [], [
                'ref_num'    => $ref_num,
                'trace_no'   => $trace_no,
                'verify_msg' => $verify['error'],
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

    $verified_amount = (int) ($verify['amount'] ?? 0);
    if ($expected_rial > 0 && $verified_amount > 0 && $verified_amount !== $expected_rial) {
        error_log('[casting-sep] verify amount mismatch order=' . $code . ' expected=' . $expected_rial . ' got=' . $verified_amount);
        casting_order_update((int) $order['id'], ['status' => 'failed']);

        return [
            'ok'        => false,
            'cancelled' => false,
            'error'     => 'مبلغ تأیید‌شده با سفارش هم‌خوانی ندارد.',
            'order'     => $order,
            'redirect'  => $result_url . '&status=failed',
        ];
    }

    casting_order_update((int) $order['id'], [
        'gateway_trace' => $ref_num,
    ]);
    casting_order_merge_meta((int) $order['id'], [
        'sep' => array_merge(is_array($meta['sep'] ?? null) ? $meta['sep'] : [], [
            'token'       => $stored_token,
            'res_num'     => $res_num,
            'ref_num'     => $ref_num,
            'trace_no'    => $trace_no,
            'terminal_id' => $terminal_id,
            'secure_pan'  => casting_mellat_mask_pan($secure_pan),
            'verified'    => true,
            'amount_rial' => $verified_amount > 0 ? $verified_amount : $expected_rial,
        ]),
    ]);

    $order = casting_get_order_by_code($code);
    $fulfill = casting_checkout_fulfill_order($order);
    if (!$fulfill['ok']) {
        error_log('[casting-sep] fulfill failed after verify order=' . $code . ' err=' . $fulfill['error']);

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

function casting_sep_refnum_already_used(string $ref_num, int $except_order_id): bool
{
    $ref_num = trim($ref_num);
    if ($ref_num === '') {
        return false;
    }
    if (!function_exists('casting_orders_ensure_table')) {
        require_once __DIR__ . '/checkout.php';
    }
    casting_orders_ensure_table();
    global $wpdb;
    $table = casting_orders_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$table} WHERE gateway_trace = %s AND status = 'paid' AND id <> %d LIMIT 1",
        $ref_num,
        $except_order_id
    ), ARRAY_A);

    return is_array($row) && !empty($row['id']);
}

/**
 * @return array{ok:bool,error:string,redirect?:string}
 */
function casting_sep_start_payment(array $order): array
{
    $terminal = casting_sep_terminal_id();
    if ($terminal === '') {
        return ['ok' => false, 'error' => 'شماره ترمینال درگاه سامان روی سرور تنظیم نشده است. از منوی ادمین «درگاه پرداخت» ذخیره کنید.'];
    }

    $amount_rial = casting_sep_amount_rial($order);
    if ($amount_rial < 1000) {
        return ['ok' => false, 'error' => 'مبلغ سفارش برای درگاه بانکی معتبر نیست.'];
    }

    $order_code = (string) ($order['order_code'] ?? '');
    if ($order_code === '') {
        return ['ok' => false, 'error' => 'کد سفارش معتبر نیست.'];
    }

    $callback = casting_sep_callback_url();
    $payload = [
        'Action'      => 'Token',
        'TerminalId'  => $terminal,
        'RedirectUrl' => $callback,
        'ResNum'      => $order_code,
        'Amount'      => $amount_rial,
    ];
    $cell = casting_sep_cell_number((int) ($order['user_id'] ?? 0));
    if ($cell !== '') {
        $payload['CellNumber'] = $cell;
    }

    $token_result = casting_sep_request_token($payload);
    if (!$token_result['ok']) {
        return ['ok' => false, 'error' => $token_result['error']];
    }

    $order_id = (int) ($order['id'] ?? 0);
    $meta = is_array($order['meta'] ?? null) ? $order['meta'] : [];
    casting_order_update($order_id, [
        'status'        => 'awaiting_payment',
        'gateway_ref'   => (string) $token_result['token'],
        'gateway_trace' => $order_code,
    ]);
    casting_order_merge_meta($order_id, [
        'sep' => array_merge(is_array($meta['sep'] ?? null) ? $meta['sep'] : [], [
            'token'        => (string) $token_result['token'],
            'res_num'      => $order_code,
            'amount_rial'  => $amount_rial,
            'callback_url' => $callback,
            'pay_url'      => (string) $token_result['pay_url'],
        ]),
    ]);

    return [
        'ok'       => true,
        'error'    => '',
        'redirect' => 'checkout-gateway.php?order=' . rawurlencode($order_code),
    ];
}

/**
 * @param array<string, mixed> $payload
 * @return array{ok:bool,error:string,token?:string,pay_url?:string}
 */
function casting_sep_request_token(array $payload): array
{
    $response = wp_remote_post(casting_sep_token_url(), [
        'timeout' => 25,
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept'       => 'application/json',
        ],
        'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    if (is_wp_error($response)) {
        error_log('[casting-sep] token ' . $response->get_error_message());

        return ['ok' => false, 'error' => 'خطا در ارتباط با درگاه بانک سامان. کمی بعد دوباره تلاش کنید.'];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!is_array($data)) {
        error_log('[casting-sep] token invalid json http=' . $code . ' body=' . substr($body, 0, 300));

        return ['ok' => false, 'error' => 'پاسخ نامعتبر از درگاه بانک سامان.'];
    }

    $status = (int) ($data['status'] ?? 0);
    if ($status !== 1) {
        $desc = trim((string) ($data['errorDesc'] ?? $data['ErrorDesc'] ?? ''));
        $err_code = trim((string) ($data['errorCode'] ?? $data['ErrorCode'] ?? ''));

        return [
            'ok'    => false,
            'error' => $desc !== '' ? $desc : ('خطای درگاه سامان' . ($err_code !== '' ? ' (کد ' . $err_code . ')' : '')),
        ];
    }

    $token = trim((string) ($data['token'] ?? ''));
    if ($token === '') {
        return ['ok' => false, 'error' => 'توکن پرداخت از درگاه سامان دریافت نشد.'];
    }

    $pay_url = casting_sep_pay_url();
    $headers = wp_remote_retrieve_headers($response);
    if (is_object($headers) && method_exists($headers, 'get')) {
        $ipg = trim((string) ($headers->get('x-ipg-url') ?? $headers->get('X-IPG-Url') ?? ''));
        if ($ipg !== '') {
            $pay_url = $ipg;
        }
    } elseif (is_array($headers)) {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, 'x-ipg-url') === 0 && trim((string) $value) !== '') {
                $pay_url = trim((string) $value);
                break;
            }
        }
    }

    return ['ok' => true, 'error' => '', 'token' => $token, 'pay_url' => $pay_url];
}

/**
 * @return array{ok:bool,error:string,amount:int}
 */
function casting_sep_verify_transaction(string $ref_num, string $terminal): array
{
    $ref_num = trim($ref_num);
    $terminal = trim($terminal);
    if ($ref_num === '' || $terminal === '') {
        return ['ok' => false, 'error' => 'اطلاعات تأیید تراکنش ناقص است.', 'amount' => 0];
    }

    $payload = [
        'RefNum'           => $ref_num,
        'TerminalNumber'   => (int) $terminal,
    ];

    $response = wp_remote_post(casting_sep_verify_url(), [
        'timeout' => 25,
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept'       => 'application/json',
        ],
        'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    if (is_wp_error($response)) {
        error_log('[casting-sep] verify ' . $response->get_error_message());

        return ['ok' => false, 'error' => 'خطا در تأیید تراکنش با بانک سامان.', 'amount' => 0];
    }

    $body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!is_array($data)) {
        error_log('[casting-sep] verify invalid json body=' . substr($body, 0, 300));

        return ['ok' => false, 'error' => 'پاسخ نامعتبر از سرویس تأیید سامان.', 'amount' => 0];
    }

    $result_code = (int) ($data['ResultCode'] ?? -999);
    $success = !empty($data['Success']);
    $detail = is_array($data['TransactionDetail'] ?? null) ? $data['TransactionDetail'] : [];
    $amount = (int) ($detail['AffectiveAmount'] ?? $detail['OrginalAmount'] ?? 0);

    if (!$success || $result_code !== 0) {
        $desc = trim((string) ($data['ResultDescription'] ?? ''));

        return [
            'ok'    => false,
            'error' => $desc !== '' ? $desc : casting_sep_verify_message($result_code),
            'amount' => $amount,
        ];
    }

    return ['ok' => true, 'error' => '', 'amount' => $amount];
}

function casting_sep_state_message(string $state): string
{
    $state = trim($state);
    $map = [
        'CanceledByUser'              => 'کاربر از پرداخت انصراف داد.',
        'OK'                          => 'پرداخت با موفقیت انجام شد.',
        'Failed'                      => 'پرداخت انجام نشد.',
        'SessionIsNull'               => 'کاربر در زمان مجاز پاسخی ارسال نکرد.',
        'InvalidParameters'           => 'پارامترهای ارسالی نامعتبر است.',
        'MerchantIpAddressIsInvalid'  => 'آدرس IP سرور پذیرنده در سامان ثبت نشده است.',
        'TokenNotFound'               => 'توکن پرداخت یافت نشد.',
        'TokenRequired'               => 'این ترمینال فقط تراکنش توکنی می‌پذیرد.',
        'TerminalNotFound'            => 'شماره ترمینال یافت نشد.',
        'MultisettlePolicyErrors'     => 'محدودیت‌های مدل چندحسابی رعایت نشده است.',
    ];
    if (isset($map[$state])) {
        return $map[$state];
    }
    if ($state !== '') {
        return 'خطای درگاه سامان (' . $state . ').';
    }

    return 'پرداخت ناموفق بود.';
}

function casting_sep_verify_message(int $code): string
{
    $map = [
        -2  => 'تراکنش در سامان یافت نشد.',
        -6  => 'بیش از ۳۰ دقیقت از زمان تراکنش گذشته است.',
        0   => 'تراکنش با موفقیت تأیید شد.',
        2   => 'درخواست تأیید تکراری است.',
        -105 => 'ترمینال در سیستم موجود نیست.',
        -104 => 'ترمینال غیرفعال است.',
        -106 => 'آدرس IP سرور در سامان مجاز نیست.',
        5   => 'تراکنش برگشت خورده است.',
    ];

    return $map[$code] ?? ('خطای تأیید تراکنش سامان (کد ' . $code . ').');
}
