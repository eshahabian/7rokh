<?php
declare(strict_types=1);

/**
 * بازگشت بانک — مسیر جایگزین؛ مسیر ثبت‌شده در سامان/ملت معمولاً cart.php است
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/checkout.php';
require_once __DIR__ . '/includes/gateway.php';

casting_nocache();
if (casting_request_is_mellat_callback()) {
    casting_gateway_finish_mellat_callback();
}
if (casting_request_is_sep_callback()) {
    casting_gateway_finish_sep_callback();
}

casting_set_flash('error', 'پاسخی از درگاه بانکی دریافت نشد.');
casting_redirect('membership.php');
