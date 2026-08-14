<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$user = casting_require_api_casting_user();

echo wp_json_encode([
    'ok'           => true,
    'idle_seconds' => function_exists('casting_session_idle_seconds') ? casting_session_idle_seconds() : 300,
    'user_id'      => (int) $user->ID,
]);
