<?php
declare(strict_types=1);

/**
 * بازگشت بانک ملت — مسیر جایگزین؛ مسیر ثبت‌شده در به‌پرداخت cart.php است
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/gateway.php';

casting_nocache();
casting_gateway_finish_mellat_callback();
