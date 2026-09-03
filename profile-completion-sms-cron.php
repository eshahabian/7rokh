<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel-profile.php';
require_once __DIR__ . '/includes/sms.php';
require_once __DIR__ . '/includes/profile-completion-sms.php';

header('Content-Type: application/json; charset=utf-8');

$key = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
$expected = casting_profile_completion_sms_cron_key();
if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo wp_json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = isset($_GET['limit']) || isset($_POST['limit'])
    ? (int) ($_GET['limit'] ?? $_POST['limit'] ?? 30)
    : 30;
$page = isset($_GET['page']) || isset($_POST['page'])
    ? (int) ($_GET['page'] ?? $_POST['page'] ?? 1)
    : 1;
$dry_run = isset($_GET['dry_run']) || isset($_POST['dry_run']);
$run_all = isset($_GET['all']) || isset($_POST['all']);

if ($run_all) {
    $result = casting_profile_completion_sms_run_all($dry_run, max(30, $limit), 50);
} else {
    $result = casting_profile_completion_sms_run_batch($limit, $dry_run, $page, $page <= 1);
}
echo wp_json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
