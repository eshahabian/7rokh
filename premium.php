<?php
declare(strict_types=1);

/**
 * صفحهٔ قدیمی خرید اشتراک — همه به cart.php منتقل شده است.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$status = sanitize_key((string) ($_GET['status'] ?? ''));
$target = 'cart.php';
if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $target .= '?status=' . rawurlencode($status) . '#admin-receipts';
}

casting_redirect($target);
