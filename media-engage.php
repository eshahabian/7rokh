<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/media-engagement.php';
require_once __DIR__ . '/includes/media-protect.php';
require_once __DIR__ . '/includes/director-workspace.php';

casting_nocache();
header('Content-Type: application/json; charset=utf-8');

$user = casting_current_user();
if (!$user) {
    echo wp_json_encode(['ok' => false, 'error' => 'وارد شوید.']);
    exit;
}

$user_id = (int) $user->ID;
if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_media_engage')) {
    echo wp_json_encode(['ok' => false, 'error' => 'نشست منقضی شده. صفحه را رفرش کنید.']);
    exit;
}

$action = sanitize_key((string) ($_POST['engage_action'] ?? ''));
$media_id = (int) ($_POST['media_id'] ?? 0);

if ($action === 'like') {
    echo wp_json_encode(casting_media_toggle_like($media_id, $user_id));
    exit;
}

if ($action === 'comment') {
    echo wp_json_encode(casting_media_add_comment($media_id, $user_id, (string) ($_POST['body'] ?? '')));
    exit;
}

if ($action === 'save') {
    echo wp_json_encode(casting_media_toggle_save($user_id, $media_id));
    exit;
}

echo wp_json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است.']);
exit;
