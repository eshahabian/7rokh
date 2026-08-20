<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/member-preview.php';
require_once __DIR__ . '/includes/director-workspace.php';

casting_nocache();

$user = casting_require_casting_user();
casting_touch_last_active((int) $user->ID);
$viewer_id = (int) $user->ID;
$member_id = max(0, (int) ($_GET['id'] ?? $_POST['member_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_member_preview')) {
        echo wp_json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $action = sanitize_key((string) ($_POST['action'] ?? ''));
    $result = casting_member_preview_handle_action($viewer_id, $member_id, $action, $_POST);
    echo wp_json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($member_id <= 0 || !casting_member_preview_can_view($viewer_id, $member_id)) {
    http_response_code(403);
    if (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(['ok' => false, 'error' => 'دسترسی به این پروفایل مجاز نیست.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo 'دسترسی مجاز نیست.';
    exit;
}

ob_start();
casting_render_member_preview_panel($member_id, $viewer_id);
$html = (string) ob_get_clean();

if (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
