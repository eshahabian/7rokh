<?php
declare(strict_types=1);

/**
 * سازگاری با لینک قدیمی — انتخاب درگاه داخل checkout.php است
 */
require_once __DIR__ . '/includes/bootstrap.php';

$order = sanitize_text_field((string) ($_GET['order'] ?? ''));
$url = 'checkout.php?order=' . rawurlencode($order) . '&step=gateway';
casting_redirect($url);
