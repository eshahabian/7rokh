<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/visitors.php';
require_once __DIR__ . '/includes/premium.php';
require_once __DIR__ . '/includes/chat-rules.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/panel-profile.php';

$viewer = casting_require_casting_user();
$viewer_id = (int) $viewer->ID;
$id = (int) ($_GET['id'] ?? 0);
$member = $id > 0 ? get_user_by('id', $id) : false;
$member_role = $member ? casting_get_user_role((int) $member->ID) : '';

if (!$member || $member_role === '') {
    casting_set_flash('error', 'کاربر پیدا نشد.');
    casting_redirect('search-users.php');
}

$is_self = $viewer_id === $id;
if ($is_self) {
    casting_redirect('panel.php#profile');
}

if (!$is_self) {
    casting_record_profile_visit($id, $viewer_id);
}

$profile = casting_get_profile($id);
if (!$profile['visible'] && !$is_self) {
    casting_set_flash('error', 'این پروفایل فعلاً قابل مشاهده نیست.');
    casting_redirect('search-users.php');
}

$error = '';
$project = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['block_action'], $_POST['block_id'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_block')) {
            $error = 'درخواست نامعتبر است.';
        } else {
            $target = (int) $_POST['block_id'];
            if ((string) $_POST['block_action'] === 'block') {
                $res = casting_block_user($viewer_id, $target);
            } else {
                $res = casting_unblock_user($viewer_id, $target);
            }
            casting_set_flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'انجام شد.' : $res['error']);
            casting_redirect('member.php?id=' . $target);
        }
    } elseif (casting_is_employer_role(casting_get_user_role($viewer_id)) && $member_role === 'talent') {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_request_' . $id)) {
            $error = 'نشست منقضی شده. دوباره تلاش کنید.';
        } else {
            $project = (string) ($_POST['project'] ?? '');
            $message = (string) ($_POST['message'] ?? '');
            $result = casting_send_talent_request($viewer_id, $id, $message, $project);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', !empty($result['warning']) ? $result['warning'] : 'درخواست ارسال شد.');
                casting_redirect('member.php?id=' . $id);
            }
        }
    }
}

casting_render_panel_start($member->display_name, 'search');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
casting_render_member_profile_view($id, $viewer_id, false, $project, $message);
casting_render_panel_end();
