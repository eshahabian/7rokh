<?php
declare(strict_types=1);

/**
 * بازگشت بانک ملت — POST از Shaparak، بدون نیاز به ورود
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/gateway.php';

casting_nocache();

$payload = array_merge($_GET, $_POST);
$result = casting_gateway_handle_mellat_callback($payload);

if (!empty($result['error']) && empty($result['ok']) && empty($result['cancelled'])) {
    error_log('[casting-mellat] callback: ' . (string) $result['error']);
}

$redirect = (string) ($result['redirect'] ?? 'membership.php');
if ($result['ok']) {
    casting_set_flash('success', 'پرداخت شما با موفقیت انجام شد.');
} elseif (!empty($result['cancelled'])) {
    casting_set_flash('error', 'پرداخت لغو شد.');
} else {
    casting_set_flash('error', (string) ($result['error'] !== '' ? $result['error'] : 'پرداخت ناموفق بود.'));
}

casting_redirect($redirect);
