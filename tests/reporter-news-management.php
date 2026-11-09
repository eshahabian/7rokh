<?php
declare(strict_types=1);
// Standalone: php tests/reporter-news-management.php (no WordPress required).
function get_user_by(string $field, int $id) {
    return (object) ['user_login' => [1 => 'eshahabian', 2 => 'ardavan', 3 => 'member'][ $id ] ?? 'other'];
}
function wp_mkdir_p(string $dir): bool { return mkdir($dir, 0700, true); }
require_once dirname(__DIR__) . '/includes/reporter-news.php';
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
$tmp = sys_get_temp_dir() . '/casting-news-test-' . bin2hex(random_bytes(8));
mkdir($tmp);
mkdir($tmp . '/public');
mkdir($tmp . '/private');
mkdir($tmp . '/private/3');
$_SERVER['DOCUMENT_ROOT'] = $tmp . '/public';
define('CASTING_NEWS_PRIVATE_DIR', $tmp . '/private');
$id = str_repeat('a', 32);
$base = $tmp . '/private/3/' . $id;
$row = ['id' => $id, 'uid' => 3, 'done' => true, 'title' => 'Test', 'created' => '2026-11-09T21:00:00Z'];
file_put_contents($base . '.json', json_encode($row));
file_put_contents($base . '.bin', 'original media');
$read = static fn(): array => json_decode((string) file_get_contents($base . '.json'), true);
try {
    check(casting_news_state($row) === 'inbox', 'Legacy submissions must stay in inbox');
    casting_news_move(1, '3', $id, 'archived');
    check(casting_news_state($read()) === 'archived', 'Owner must archive');
    $timestamp = $read()['archived_at'];
    casting_news_move(1, '3', $id, 'archived');
    check($read()['archived_at'] === $timestamp, 'Repeated action must be idempotent');
    casting_news_move(2, '3', $id, 'deleted');
    check(casting_news_state($read()) === 'deleted', 'Second admin must soft-delete');
    check(file_get_contents($base . '.bin') === 'original media', 'Media must survive deletion');
    casting_news_move(1, '3', $id, 'inbox');
    check(casting_news_state($read()) === 'inbox', 'Deleted news must restore');
    $before = file_get_contents($base . '.json');
    foreach ([[3, '3', $id, 'deleted'], [1, '../3', $id, 'deleted'], [1, '3', '../x', 'deleted'], [1, '3', $id, 'erase']] as $args) {
        $denied = false;
        try { casting_news_move(...$args); } catch (RuntimeException $e) { $denied = true; }
        check($denied, 'Unauthorized or invalid action must fail');
        check(file_get_contents($base . '.json') === $before, 'Denied action must not mutate news');
    }
    check(casting_news_date('2026-11-09T21:00:00Z') === '1405/08/19', 'Archive must use Tehran midnight and Jalali dates');
    echo "News management checks passed.\n";
} finally {
    foreach (['.json', '.json.tmp', '.bin', '.lock'] as $suffix) {
        if (is_file($base . $suffix)) { unlink($base . $suffix); }
    }
    rmdir($tmp . '/private/3'); rmdir($tmp . '/private'); rmdir($tmp . '/public'); rmdir($tmp);
}
