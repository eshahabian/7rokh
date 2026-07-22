<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';

header('Content-Type: application/json; charset=utf-8');
casting_nocache();

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (!casting_user_can_member_search($user_id)) {
    echo wp_json_encode(['ok' => false, 'items' => [], 'error' => 'disabled'], JSON_UNESCAPED_UNICODE);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));

if ($q === '' || casting_strlen($q) < 2) {
    echo wp_json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = casting_search_members_by_name($q, $user_id, 12);
echo wp_json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
