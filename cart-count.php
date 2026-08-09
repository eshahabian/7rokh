<?php
declare(strict_types=1);

/**
 * شمارنده سبد برای سایت اصلی — session پورتال (کاربر یا مهمان)
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cart.php';

casting_nocache();
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$uid = function_exists('casting_portal_session_user_id')
    ? (int) casting_portal_session_user_id()
    : 0;

$count = (int) casting_cart_count();
casting_cart_sync_count_cookie($count);

echo wp_json_encode([
    'ok'        => true,
    'count'     => $count,
    'logged_in' => $uid > 0,
]);
exit;
