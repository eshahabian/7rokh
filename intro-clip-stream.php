<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/intro-clip.php';

casting_nocache();

$user = function_exists('casting_current_user') ? casting_current_user() : null;
if (!$user instanceof WP_User) {
    status_header(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ورود لازم است.';
    exit;
}
$viewer_id = (int) $user->ID;
if (!casting_user_can_use_member_portal($viewer_id)) {
    status_header(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'دسترسی غیرمجاز.';
    exit;
}
if (!function_exists('casting_user_is_suspended')) {
    require_once __DIR__ . '/includes/admin-access.php';
}
if (function_exists('casting_user_is_suspended') && casting_user_is_suspended($viewer_id)) {
    status_header(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'حساب معلق است.';
    exit;
}
$owner_id = (int) ($_GET['uid'] ?? 0);
$nonce = (string) ($_GET['n'] ?? '');

if ($owner_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'casting_intro_clip_' . $owner_id)) {
    status_header(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'دسترسی نامعتبر است.';
    exit;
}

if (!casting_user_can_view_intro_clip($viewer_id, $owner_id)) {
    status_header(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'اجازه مشاهده این فایل را ندارید.';
    exit;
}

casting_intro_clip_output($owner_id);
