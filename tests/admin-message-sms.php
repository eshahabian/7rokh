<?php
declare(strict_types=1);

// Standalone tests with a fake SMS gateway; no real messages are sent.
define('CASTING_MAIN_SITE_URL', 'https://7rokh.ir');
$users = [1 => 'eshahabian', 2 => 'Ardavan', 3 => 'member', 4 => 'other_admin'];
$mobiles = [3 => '09123456789'];
$mobiles2 = [];
$options = [];
$sms = [];
$configured = true;
$sms_mode = 'success';
$save_fail = false;
function get_user_by($field, $value) {
    global $users;
    $id = $field === 'id' ? (int) $value : array_search(strtolower((string) $value), array_map('strtolower', $users), true);
    return isset($users[$id]) ? (object) ['ID' => $id, 'user_login' => $users[$id], 'display_name' => $users[$id]] : false;
}
function get_user_meta($id, $key, $single = true) {
    global $mobiles, $mobiles2;
    if ($key === 'casting_mobile2') {
        return $mobiles2[$id] ?? '';
    }
    return $key === 'casting_mobile' ? ($mobiles[$id] ?? '') : '';
}
function casting_normalize_mobile($value) { return trim($value); }
function casting_sms_is_configured() { global $configured; return $configured; }
function casting_sms_send_text($mobile, $body, $timeout = 25) {
    global $sms, $sms_mode, $options;
    $sms[] = ['mobile' => $mobile, 'body' => $body, 'inbox' => $options['casting_contact_inbox'] ?? [], 'dm_count' => isset($GLOBALS['wpdb']) ? count($GLOBALS['wpdb']->rows) : 0];
    if ($sms_mode === 'throw') { throw new RuntimeException('Gateway unavailable'); }
    return ['ok' => $sms_mode === 'success'];
}
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function update_option($key, $value, $autoload = false) {
    global $options, $save_fail;
    if ($save_fail) { return false; }
    $options[$key] = $value;
    return true;
}
function sanitize_key($value) { return strtolower($value); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function sanitize_email($value) { return trim($value); }
function is_email($value) { return (bool) filter_var($value, FILTER_VALIDATE_EMAIL); }
function casting_strlen($value) { return strlen($value); }
function current_time($format) { return '2026-11-18 12:00:00'; }
function casting_user_is_listed_portal_admin($id) { return in_array($id, [1, 2, 4], true); }
function casting_send_mail($email, $subject, $body) { return ['ok' => true]; }
require_once dirname(__DIR__) . '/includes/contact-messages.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }

foreach ([1, 2] as $id) { casting_admin_message_notify_sms($id, 3); }
check(count($sms) === 2, 'Both managers notify, case insensitively');
check($sms[0]['mobile'] === $mobiles[3], 'Use recipient mobile');
check(strpos($sms[0]['body'], 'http') === false && strpos($sms[0]['body'], '7rokh.ir') === false && strpos($sms[0]['body'], '/casting-portal/') === false, 'DM notification contains no link');
foreach ([[99, 3], [1, 1], [0, 3], [1, 0], [1, 99], [4, 3, 'contact']] as $args) { casting_admin_message_notify_sms(...$args); }
check(count($sms) === 2, 'Invalid participants, self and non-manager replies with no manager mobiles do not notify');
$mobiles[3] = 'invalid'; casting_admin_message_notify_sms(1, 3);
$mobiles[3] = ''; casting_admin_message_notify_sms(1, 3);
$mobiles[3] = '09123456789'; $configured = false; casting_admin_message_notify_sms(1, 3);
check(count($sms) === 2, 'Invalid/missing mobiles and disabled SMS do not send');
$configured = true;

$row = ['id' => 'ct_test', 'user_id' => 3, 'recipient_id' => 1, 'channel' => 'site_admin', 'email' => 'member@example.com', 'subject' => 'Help', 'read' => false];
foreach ([1, 2] as $id) {
    $options['casting_contact_inbox'] = [$row];
    $before = count($sms);
    $result = casting_contact_reply('ct_test', $id, 'Private reply');
    check($result['ok'] && count($sms) === $before + 1, 'Each manager contact reply notifies once');
    $last = $sms[count($sms) - 1];
    check(count($last['inbox'][0]['replies']) === 1, 'Persist reply before calling gateway');
    check(strpos($last['body'], 'http') === false && strpos($last['body'], '7rokh.ir') === false && strpos($last['body'], '/casting-portal/') === false, 'Contact notification contains no link');
    check(strpos($last['body'], 'Private reply') === false, 'Do not disclose reply text in SMS');
}
$before = count($sms);
check(!casting_contact_reply('ct_test', 3, 'Unauthorized')['ok'], 'Reject member reply');
check(!casting_contact_reply('missing', 1, 'Missing')['ok'], 'Reject missing message');
check(!casting_contact_reply('ct_test', 1, '')['ok'], 'Reject empty reply');
$options['casting_contact_inbox'] = [$row]; $save_fail = true;
check(!casting_contact_reply('ct_test', 1, 'Unsaved reply')['ok'], 'Report storage failure');
$save_fail = false;
check(count($sms) === $before, 'Rejected or unsaved replies do not notify');
foreach (['failure', 'throw'] as $mode) {
    $sms_mode = $mode;
    $options['casting_contact_inbox'] = [$row];
    check(casting_contact_reply('ct_test', 1, 'Saved despite gateway failure')['ok'], 'SMS failure must not fail a saved reply');
    check(count($options['casting_contact_inbox'][0]['replies']) === 1, 'Reply survives gateway failure');
}
$sms_mode = 'success'; $before = count($sms);
$row['user_id'] = 0; $options['casting_contact_inbox'] = [$row];
check(casting_contact_reply('ct_test', 1, 'Guest email reply')['ok'], 'Guest email response stays supported');
check(count($sms) === $before, 'Guest without a member mobile receives no SMS');

$mobiles[1] = '09123456780';
$mobiles[2] = '09123456781';
$before = count($sms);
$save_fail = true;
check(!casting_contact_send_message('site_admin', 'Subject', 'An unsaved message', 0, 'Guest', 'guest@example.com')['ok'], 'Report failed contact submission');
$save_fail = false;
check(count($sms) === $before, 'Failed contact submission does not notify');
check(casting_contact_send_message('site_admin', 'Subject', 'A private guest message', 0, 'Guest', 'guest@example.com')['ok'], 'Save guest contact submission');
check(array_column(array_slice($sms, $before), 'mobile') === [$mobiles[1], $mobiles[2]], 'Contact submission alerts both managers');
$before = count($sms);
$mobiles[4] = '09123456782';
casting_admin_message_notify_sms(3, 4);
check(array_column(array_slice($sms, $before), 'mobile') === [$mobiles[1], $mobiles[2]], 'Member-to-member DM alerts the managers');
$before = count($sms);
casting_admin_message_notify_sms(1, 2);
check(array_column(array_slice($sms, $before), 'mobile') === [$mobiles[2]], 'Manager-to-manager DM alerts the recipient once');
foreach (['contest', 'news'] as $channel) {
    $before = count($sms);
    $sms_mode = 'throw';
    casting_admin_message_notify_sms(3, 0, $channel);
    check(array_column(array_slice($sms, $before), 'mobile') === [$mobiles[1], $mobiles[2]], 'A failed manager delivery does not stop the second attempt');
}
$sms_mode = 'success';
$mobiles2[1] = '09120000001';
$mobiles2[2] = '09120000002';
$both_numbers = ['09123456780', '09120000001', '09123456781', '09120000002'];
foreach (['chat' => [3, 1], 'news' => [3, 0], 'contest' => [3, 0]] as $channel => $pair) {
    $before = count($sms);
    casting_admin_message_notify_sms($pair[0], $pair[1], $channel);
    check(array_column(array_slice($sms, $before), 'mobile') === $both_numbers, 'Both manager numbers are alerted for ' . $channel);
}
$mobiles2 = [];
unset($mobiles[1], $mobiles[2]);
echo "Admin message SMS and contact reply checks passed.\n";

// Exercise the real DM send functions against an in-memory database.
define('ARRAY_A', 'ARRAY_A');
function casting_user_is_portal_owner($id) { return $id === 1; }
function casting_user_is_super_admin($id) { return in_array($id, [1, 2, 4], true); }
function casting_get_user_role($id) { return 'talent'; }
function casting_is_employer_role($role) { return false; }
function casting_media_handle_upload_as_user($field, $id, $context) { return 100; }
function is_wp_error($value) { return false; }
function wp_get_attachment_image_url($id, $size) { return 'https://7rokh.ir/photo.gif'; }
function wp_delete_attachment($id, $force) { return true; }
class SmsTestDatabase {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $fail = false;
    public $rows = [];
    public function prepare($sql, ...$values) { return $values[0] ?? $sql; }
    public function get_var($sql) { return $sql; }
    public function get_results($sql, $format) { return [['Field' => 'delivered_at'], ['Field' => 'read_at'], ['Field' => 'edited_at']]; }
    public function get_charset_collate() { return ''; }
    public function query($sql) { return 1; }
    public function insert($table, $row, $formats) {
        if ($this->fail) { return false; }
        $this->rows[] = $row;
        $this->insert_id++;
        return 1;
    }
}
$wpdb = new SmsTestDatabase();
$options['casting_chat_db_version'] = '4';
require_once dirname(__DIR__) . '/includes/chat.php';
foreach ([1, 2] as $id) {
    $before = count($sms);
    $result = casting_dm_send($id, 3, 'Private direct message');
    check($result['ok'] && count($sms) === $before + 1, 'Successful manager DM notifies once');
    check($sms[count($sms) - 1]['dm_count'] === count($wpdb->rows), 'Save DM before sending SMS');
}
$before = count($sms);
$wpdb->fail = true;
check(!casting_dm_send(1, 3, 'Storage failure')['ok'], 'DM storage error returned');
$wpdb->fail = false;
check(!casting_dm_send(1, 3, '')['ok'], 'Empty DM rejected');
check(count($sms) === $before, 'Unsent DMs do not notify');
$sms_mode = 'throw';
check(casting_dm_send(1, 3, 'Gateway failure')['ok'], 'Saved DM succeeds despite SMS failure');
$sms_mode = 'success';
$before = count($sms);
$mobiles[1] = '09123456780';
$mobiles[2] = '09123456781';
check(casting_dm_send(3, 1, 'Private member message')['ok'], 'Member DM succeeds');
check(array_column(array_slice($sms, $before), 'mobile') === [$mobiles[1], $mobiles[2]], 'Member DM alerts both managers once');
check(strpos($sms[$before]['body'], 'Private member message') === false, 'SMS omits the DM text');
$before = count($sms);
check(casting_dm_send(1, 3, 'A manager message')['ok'], 'Manager DM succeeds');
check(array_column(array_slice($sms, $before), 'mobile') === [$mobiles[2], $mobiles[3]], 'Manager DM alerts the other manager and the member');
$before = count($sms);
$photo = tempnam(sys_get_temp_dir(), 'casting-sms-photo-');
file_put_contents($photo, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
try {
    $_FILES['photo'] = ['error' => UPLOAD_ERR_OK, 'size' => filesize($photo), 'tmp_name' => $photo];
    check(casting_dm_send_photo(2, 3)['ok'], 'Manager photo send succeeds');
    check(array_column(array_slice($sms, $before), 'mobile') === [$mobiles[1], $mobiles[3]], 'Manager photo alerts the other manager and the member');
} finally {
    unlink($photo);
}
echo "Direct message SMS checks passed.\n";
