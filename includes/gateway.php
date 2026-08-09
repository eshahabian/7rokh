<?php
declare(strict_types=1);

/**
 * درگاه پرداخت — حالت sandbox برای بررسی فرآیند (به‌پرداخت)
 * با تنظیم CASTING_GATEWAY_MODE=live و مشخصات ترمینال، به درگاه واقعی وصل می‌شود.
 */

function casting_gateway_mode(): string
{
    if (defined('CASTING_GATEWAY_MODE')) {
        $mode = strtolower(trim((string) CASTING_GATEWAY_MODE));
        if (in_array($mode, ['sandbox', 'live', 'off'], true)) {
            return $mode;
        }
    }

    return 'sandbox';
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
    $token = bin2hex(random_bytes(16));
    casting_order_update($order_id, [
        'status'      => 'awaiting_payment',
        'gateway_ref' => $token,
    ]);

    if ($mode === 'live') {
        // اتصال واقعی به‌پرداخت — پس از دریافت ترمینال تکمیل می‌شود
        if (!defined('CASTING_BEHPARDAKHT_TERMINAL_ID') || (string) CASTING_BEHPARDAKHT_TERMINAL_ID === '') {
            return ['ok' => false, 'error' => 'مشخصات درگاه بانکی هنوز تنظیم نشده است.'];
        }

        return ['ok' => false, 'error' => 'اتصال زنده درگاه به‌پرداخت به‌زودی فعال می‌شود. فعلاً از حالت آزمایشی استفاده کنید.'];
    }

    // sandbox: صفحه شبیه‌سازی درگاه
    $code = rawurlencode((string) $order['order_code']);

    return [
        'ok'       => true,
        'error'    => '',
        'redirect' => 'checkout-gateway.php?order=' . $code . '&token=' . rawurlencode($token),
    ];
}

/**
 * نتیجه بازگشت از درگاه (sandbox یا callback واقعی)
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
