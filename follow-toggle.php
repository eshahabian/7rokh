<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/follows.php';

header('Content-Type: application/json; charset=utf-8');
casting_nocache();

$user = casting_current_user();
if (!$user) {
    http_response_code(401);
    echo wp_json_encode(['ok' => false, 'error' => 'وارد شوید.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo wp_json_encode(['ok' => false, 'error' => 'روش نامعتبر است.']);
    exit;
}

$nonce = (string) ($_POST['_wpnonce'] ?? $_SERVER['HTTP_X_WP_NONCE'] ?? '');
if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_follow')) {
    http_response_code(403);
    echo wp_json_encode(['ok' => false, 'error' => 'نشست منقضی شده. صفحه را رفرش کنید.']);
    exit;
}

$target_id = (int) ($_POST['user_id'] ?? 0);
$result = casting_follow_toggle((int) $user->ID, $target_id);
if (!$result['ok']) {
    http_response_code(400);
}
echo wp_json_encode($result);
exit;
