<?php
declare(strict_types=1);
require __DIR__ . '/../includes/reporter-publish.php';
function casting_news_reviewer($id) { return $id === 1; }
function casting_news_dir() { return $GLOBALS['dir']; }
function casting_news_state($row) { return $row['state'] ?? 'inbox'; }
function get_posts($args) { return isset($GLOBALS['post']) ? [(object) ['ID' => 20]] : []; }
function get_post_status($id) { return $GLOBALS['post']['post_status']; }
function term_exists($term, $taxonomy) { return $term === 4 ? 4 : false; }
function sanitize_text_field($s) { return strip_tags($s); }
function sanitize_textarea_field($s) { return strip_tags($s); }
function esc_html($s) { return htmlspecialchars($s); }
function wpautop($s) { return '<p>' . $s . '</p>'; }
function is_wp_error($v) { return false; }
function wp_insert_post($data, $error) { $GLOBALS['post'] = $data; $GLOBALS['inserts']++; return 20; }
function wp_update_post($data, $error) { $GLOBALS['post'] = array_merge($GLOBALS['post'], $data); return 20; }
function update_post_meta(...$args) {}
function check($condition) { if (!$condition) { throw new RuntimeException('Check failed'); } }
$dir = sys_get_temp_dir() . '/news-publish-' . bin2hex(random_bytes(6));
$GLOBALS['dir'] = $dir; $GLOBALS['inserts'] = 0;
mkdir($dir); mkdir($dir . '/3');
$id = str_repeat('a', 32); $base = $dir . '/3/' . $id;
file_put_contents($base . '.json', json_encode(['done' => true]));
$input = ['category' => 4, 'publish_title' => 'Title', 'publish_lead' => 'Lead', 'publish_body' => '<script>text</script>'];
try {
    foreach ([[2, $id, $input], [1, '../bad', $input], [1, $id, array_merge($input, ['category' => 0])], [1, $id, array_merge($input, ['placements' => ['invalid']])]] as $args) {
        $denied = false;
        try { casting_news_publish($args[0], '3', $args[1], $args[2]); } catch (RuntimeException $e) { $denied = true; }
        check($denied); check($GLOBALS['inserts'] === 0);
    }
    check(casting_news_publish(1, '3', $id, $input) === 20);
    check($GLOBALS['post']['post_status'] === 'publish');
    check(strpos($GLOBALS['post']['post_content'], '<script>') === false);
    check(casting_news_publish(1, '3', $id, $input) === 20);
    check($GLOBALS['inserts'] === 1);
    echo "Publication authorization, validation and duplicate checks passed.\n";
} finally {
    foreach (['.json', '.json.tmp', '.lock'] as $suffix) { if (is_file($base . $suffix)) { unlink($base . $suffix); } }
    rmdir($dir . '/3'); rmdir($dir);
}
