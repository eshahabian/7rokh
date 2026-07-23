<?php
declare(strict_types=1);

require_once __DIR__ . '/blocks.php';
require_once __DIR__ . '/chat-rules.php';
require_once __DIR__ . '/chat.php';

/**
 * ارسال درخواست همکاری کارفرما به هنرمند + ایمیل
 */
function casting_send_talent_request(int $employer_id, int $talent_id, string $message, string $project = ''): array
{
    $employer = get_user_by('id', $employer_id);
    $talent = get_user_by('id', $talent_id);

    if (!$employer || !$talent) {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    if (casting_get_user_role($employer_id) === '' || !casting_is_employer_role(casting_get_user_role($employer_id))) {
        return ['ok' => false, 'error' => 'فقط کارفرما می‌تواند درخواست بفرستد.'];
    }
    $to_role = casting_get_user_role($talent_id);
    $from_role = casting_get_user_role($employer_id);
    if ($to_role === 'talent') {
        // بازیگر
    } elseif ($to_role === 'director' && $from_role === 'producer') {
        // تهیه‌کننده → کارگردان
    } else {
        return ['ok' => false, 'error' => 'گیرنده برای این نوع درخواست مجاز نیست.'];
    }
    if (casting_users_block_each_other($employer_id, $talent_id)) {
        return ['ok' => false, 'error' => 'به‌دلیل بلاک، ارسال درخواست ممکن نیست.'];
    }
    $chat_allow = casting_can_start_chat($employer_id, $talent_id);
    if (!$chat_allow['ok']) {
        return ['ok' => false, 'error' => $chat_allow['error']];
    }

    $message = sanitize_textarea_field($message);
    $project = sanitize_text_field($project);
    if ($message === '') {
        return ['ok' => false, 'error' => 'متن درخواست را بنویسید.'];
    }
    if (casting_strlen($message) > 2000) {
        return ['ok' => false, 'error' => 'متن درخواست خیلی بلند است.'];
    }

    $last_key = 'casting_req_last_' . $talent_id;
    $last = (int) get_user_meta($employer_id, $last_key, true);
    if ($last > 0 && (time() - $last) < 15 * 60) {
        return ['ok' => false, 'error' => 'به‌تازگی به این هنرمند درخواست داده‌اید. کمی بعد دوباره تلاش کنید.'];
    }

    $request = [
        'id'            => uniqid('req_', true),
        'employer_id'   => $employer_id,
        'talent_id'     => $talent_id,
        'employer'      => $employer->display_name,
        'employer_role' => casting_role_label(casting_get_user_role($employer_id)),
        'employer_mail' => $employer->user_email,
        'talent_name'    => $talent->display_name,
        'project'       => $project,
        'message'       => $message,
        'created_at'    => current_time('mysql'),
        'status'        => 'pending',
        'reply'         => '',
        'replied_at'    => '',
        'seen_at'       => '',
        'employer_seen_at' => '',
        'chat_seeded'   => false,
        'reply_in_chat' => false,
    ];

    casting_store_request_for_users($request);
    update_user_meta($employer_id, $last_key, time());

    $mail = casting_mail_talent_request($talent, $employer, $request);
    if (!$mail['ok']) {
        return [
            'ok'      => true,
            'warning' => 'درخواست ذخیره شد، ولی ارسال ایمیل ناموفق بود. تنظیم SMTP وردپرس را چک کنید.',
        ];
    }

    return ['ok' => true];
}

function casting_store_request_for_users(array $request): void
{
    $talent_id = (int) $request['talent_id'];
    $employer_id = (int) $request['employer_id'];

    $inbox = get_user_meta($talent_id, 'casting_requests', true);
    if (!is_array($inbox)) {
        $inbox = [];
    }
    array_unshift($inbox, $request);
    update_user_meta($talent_id, 'casting_requests', array_slice($inbox, 0, 100));

    $outbox = get_user_meta($employer_id, 'casting_sent_requests', true);
    if (!is_array($outbox)) {
        $outbox = [];
    }
    array_unshift($outbox, $request);
    update_user_meta($employer_id, 'casting_sent_requests', array_slice($outbox, 0, 100));
}

/**
 * @return array{active:string,archive:string}|null
 */
function casting_user_request_storage_keys(int $user_id): ?array
{
    $role = casting_get_user_role($user_id);
    if ($role === 'talent') {
        return ['active' => 'casting_requests', 'archive' => 'casting_requests_archive'];
    }
    if (casting_is_employer_role($role)) {
        return ['active' => 'casting_sent_requests', 'archive' => 'casting_sent_requests_archive'];
    }

    return null;
}

/**
 * @return list<string>
 */
function casting_user_request_directions(int $user_id): array
{
    $role = casting_get_user_role($user_id);
    $directions = [];
    if ($role === 'talent' || $role === 'director') {
        $directions[] = 'received';
    }
    if (casting_is_employer_role($role)) {
        $directions[] = 'sent';
    }

    return $directions;
}

function casting_request_list_meta_key(int $user_id, string $direction, string $bucket): ?string
{
    if (!in_array($direction, ['sent', 'received'], true)) {
        return null;
    }
    if (!in_array($bucket, ['active', 'archive'], true)) {
        return null;
    }

    $role = casting_get_user_role($user_id);
    if ($direction === 'received' && ($role === 'talent' || $role === 'director')) {
        return $bucket === 'archive' ? 'casting_requests_archive' : 'casting_requests';
    }
    if ($direction === 'sent' && casting_is_employer_role($role)) {
        return $bucket === 'archive' ? 'casting_sent_requests_archive' : 'casting_sent_requests';
    }

    return null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_get_user_request_list_by_direction(int $user_id, string $direction, string $bucket = 'active'): array
{
    $meta_key = casting_request_list_meta_key($user_id, $direction, $bucket);
    if ($meta_key === null) {
        return [];
    }
    $list = get_user_meta($user_id, $meta_key, true);

    return is_array($list) ? $list : [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_user_sent_requests(int $user_id, string $bucket = 'active'): array
{
    return casting_get_user_request_list_by_direction($user_id, 'sent', $bucket);
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_user_received_requests(int $user_id, string $bucket = 'active'): array
{
    return casting_get_user_request_list_by_direction($user_id, 'received', $bucket);
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_get_user_request_list(int $user_id, string $bucket = 'active'): array
{
    $keys = casting_user_request_storage_keys($user_id);
    if ($keys === null) {
        return [];
    }
    $meta_key = $bucket === 'archive' ? $keys['archive'] : $keys['active'];
    $list = get_user_meta($user_id, $meta_key, true);

    return is_array($list) ? $list : [];
}

function casting_update_request_in_user_lists(int $user_id, array $updated): bool
{
    $req_id = (string) ($updated['id'] ?? '');
    if ($req_id === '') {
        return false;
    }

    $ok = false;
    foreach (casting_user_request_directions($user_id) as $direction) {
        foreach (['active', 'archive'] as $bucket) {
            $meta_key = casting_request_list_meta_key($user_id, $direction, $bucket);
            if ($meta_key === null) {
                continue;
            }
            $list = get_user_meta($user_id, $meta_key, true);
            if (!is_array($list)) {
                continue;
            }
            $changed = false;
            foreach ($list as $i => $item) {
                if (!is_array($item) || (string) ($item['id'] ?? '') !== $req_id) {
                    continue;
                }
                $list[$i] = array_merge($item, $updated);
                $ok = true;
                $changed = true;
                break;
            }
            if ($changed) {
                update_user_meta($user_id, $meta_key, $list);
            }
        }
    }

    return $ok;
}

function casting_update_request_everywhere(array $updated): bool
{
    $talent_id = (int) ($updated['talent_id'] ?? 0);
    $employer_id = (int) ($updated['employer_id'] ?? 0);
    if ($talent_id <= 0 || $employer_id <= 0 || (string) ($updated['id'] ?? '') === '') {
        return false;
    }

    $ok_t = casting_update_request_in_user_lists($talent_id, $updated);
    $ok_e = casting_update_request_in_user_lists($employer_id, $updated);

    return $ok_t || $ok_e;
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_move_user_request(int $user_id, string $request_id, string $from_bucket, string $to_bucket, string $direction = 'default'): array
{
    $request_id = trim($request_id);
    if ($request_id === '' || !in_array($from_bucket, ['active', 'archive'], true) || !in_array($to_bucket, ['active', 'archive'], true)) {
        return ['ok' => false, 'error' => 'درخواست نامعتبر است.'];
    }
    if ($from_bucket === $to_bucket) {
        return ['ok' => false, 'error' => 'عملیات نامعتبر است.'];
    }

    if ($direction === 'default') {
        $keys = casting_user_request_storage_keys($user_id);
        if ($keys === null) {
            return ['ok' => false, 'error' => 'این بخش برای نقش شما فعال نیست.'];
        }
        $from_key = $keys[$from_bucket];
        $to_key = $keys[$to_bucket];
    } else {
        $from_key = casting_request_list_meta_key($user_id, $direction, $from_bucket);
        $to_key = casting_request_list_meta_key($user_id, $direction, $to_bucket);
        if ($from_key === null || $to_key === null) {
            return ['ok' => false, 'error' => 'این بخش برای نقش شما فعال نیست.'];
        }
    }
    $from_list = get_user_meta($user_id, $from_key, true);
    if (!is_array($from_list)) {
        $from_list = [];
    }

    $found = null;
    $found_index = null;
    foreach ($from_list as $i => $item) {
        if (is_array($item) && (string) ($item['id'] ?? '') === $request_id) {
            $found = $item;
            $found_index = $i;
            break;
        }
    }
    if ($found === null || $found_index === null) {
        return ['ok' => false, 'error' => 'درخواست پیدا نشد.'];
    }

    unset($from_list[$found_index]);
    $from_list = array_values($from_list);
    update_user_meta($user_id, $from_key, $from_list);

    if ($to_bucket === 'archive') {
        $found['archived_at'] = current_time('mysql');
    } else {
        unset($found['archived_at']);
    }

    $to_list = get_user_meta($user_id, $to_key, true);
    if (!is_array($to_list)) {
        $to_list = [];
    }
    array_unshift($to_list, $found);
    update_user_meta($user_id, $to_key, array_slice($to_list, 0, 100));

    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_archive_user_request(int $user_id, string $request_id): array
{
    return casting_move_user_request($user_id, $request_id, 'active', 'archive');
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_unarchive_user_request(int $user_id, string $request_id): array
{
    return casting_move_user_request($user_id, $request_id, 'archive', 'active');
}

function casting_remove_request_from_user(int $user_id, string $request_id, string $direction): bool
{
    $request_id = trim($request_id);
    if ($request_id === '') {
        return false;
    }

    $ok = false;
    foreach (['active', 'archive'] as $bucket) {
        $meta_key = casting_request_list_meta_key($user_id, $direction, $bucket);
        if ($meta_key === null) {
            continue;
        }
        $list = get_user_meta($user_id, $meta_key, true);
        if (!is_array($list)) {
            continue;
        }
        $changed = false;
        foreach ($list as $i => $item) {
            if (!is_array($item) || (string) ($item['id'] ?? '') !== $request_id) {
                continue;
            }
            unset($list[$i]);
            $changed = true;
            $ok = true;
            break;
        }
        if ($changed) {
            update_user_meta($user_id, $meta_key, array_values($list));
        }
    }

    return $ok;
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_delete_user_request(int $user_id, string $request_id, string $direction = 'default'): array
{
    $request_id = trim($request_id);
    if ($request_id === '') {
        return ['ok' => false, 'error' => 'درخواست نامعتبر است.'];
    }

    if ($direction === 'default') {
        $ok = false;
        foreach (casting_user_request_directions($user_id) as $dir) {
            if (casting_remove_request_from_user($user_id, $request_id, $dir)) {
                $ok = true;
            }
        }
    } else {
        $ok = casting_remove_request_from_user($user_id, $request_id, $direction);
    }

    return $ok
        ? ['ok' => true, 'error' => '']
        : ['ok' => false, 'error' => 'درخواست پیدا نشد.'];
}

/**
 * پس‌گیری درخواست: از لیست فرستنده و گیرنده حذف می‌شود.
 *
 * @return array{ok:bool,error:string}
 */
function casting_withdraw_request(int $employer_id, string $request_id): array
{
    $request_id = trim($request_id);
    if ($request_id === '') {
        return ['ok' => false, 'error' => 'درخواست نامعتبر است.'];
    }

    $req = casting_find_user_request($employer_id, $request_id);
    if ($req === null) {
        return ['ok' => false, 'error' => 'درخواست پیدا نشد.'];
    }
    if ((int) ($req['employer_id'] ?? 0) !== $employer_id) {
        return ['ok' => false, 'error' => 'دسترسی مجاز نیست.'];
    }
    if (casting_request_status_key($req) !== 'pending') {
        return ['ok' => false, 'error' => 'فقط درخواست‌های در انتظار پاسخ قابل پس‌گیری هستند.'];
    }

    $recipient_id = (int) ($req['talent_id'] ?? 0);
    $ok_recipient = $recipient_id > 0
        ? casting_remove_request_from_user($recipient_id, $request_id, 'received')
        : false;
    $ok_sender = casting_remove_request_from_user($employer_id, $request_id, 'sent');

    if (!$ok_recipient && !$ok_sender) {
        return ['ok' => false, 'error' => 'پس‌گیری درخواست ناموفق بود.'];
    }

    delete_user_meta($employer_id, 'casting_req_last_' . $recipient_id);

    return ['ok' => true, 'error' => ''];
}

/**
 * پاسخ هنرمند: accept | reject + نظر
 */
function casting_respond_to_request(int $recipient_id, string $request_id, string $decision, string $reply): array
{
    $decision = sanitize_key($decision);
    if (!in_array($decision, ['accepted', 'rejected'], true)) {
        return ['ok' => false, 'error' => 'تصمیم نامعتبر است.'];
    }

    $reply = sanitize_textarea_field($reply);
    if (casting_strlen($reply) > 2000) {
        return ['ok' => false, 'error' => 'نظر شما خیلی بلند است.'];
    }

    $inbox = casting_user_received_requests($recipient_id);
    $found = null;
    foreach ($inbox as $item) {
        if (is_array($item) && (string) ($item['id'] ?? '') === $request_id) {
            $found = $item;
            break;
        }
    }
    if (!$found) {
        return ['ok' => false, 'error' => 'درخواست پیدا نشد.'];
    }
    if ((string) ($found['status'] ?? '') !== 'pending' && (string) ($found['status'] ?? '') !== 'new') {
        return ['ok' => false, 'error' => 'به این درخواست قبلاً پاسخ داده شده است.'];
    }

    $found['status'] = $decision;
    $found['reply'] = $reply;
    $found['replied_at'] = current_time('mysql');

    if (!casting_update_request_everywhere($found)) {
        return ['ok' => false, 'error' => 'ذخیره پاسخ ناموفق بود.'];
    }

    if (!empty($found['chat_seeded'])) {
        $reply_text = trim("💬 پاسخ:\n" . (string) $found['reply']);
        if ($reply_text !== '' && casting_dm_insert_raw($recipient_id, (int) $found['employer_id'], $reply_text, (string) $found['replied_at'])) {
            $found['reply_in_chat'] = true;
            casting_update_request_everywhere($found);
        }
    }

    $employer = get_user_by('id', (int) $found['employer_id']);
    $responder = get_user_by('id', $recipient_id);
    if ($employer && $responder) {
        casting_mail_employer_response($employer, $responder, $found);
    }

    return ['ok' => true, 'status' => $decision];
}

function casting_mail_talent_request(WP_User $talent, WP_User $employer, array $request): array
{
    $to = $talent->user_email;
    if (!is_email($to)) {
        return ['ok' => false, 'error' => 'ایمیل هنرمند معتبر نیست.'];
    }

    $brand = casting_brand();
    $subject = sprintf('[%s] درخواست همکاری از %s', $brand, $employer->display_name);
    $login_url = casting_url('my-requests.php');
    $lines = [
        'سلام ' . $talent->display_name . '،',
        '',
        'یک درخواست همکاری جدید در ' . $brand . ' برای شما ثبت شده است.',
        '',
        'فرستنده: ' . $employer->display_name . ' (' . ($request['employer_role'] ?? 'کارفرما') . ')',
        'ایمیل کارفرما: ' . $employer->user_email,
    ];
    if (!empty($request['project'])) {
        $lines[] = 'پروژه / نقش: ' . $request['project'];
    }
    $lines[] = '';
    $lines[] = 'متن درخواست:';
    $lines[] = $request['message'];
    $lines[] = '';
    $lines[] = 'برای قبول یا رد درخواست وارد پنل شوید:';
    $lines[] = $login_url;
    $lines[] = '';
    $lines[] = '— ' . $brand;

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $employer->display_name . ' <' . $employer->user_email . '>',
    ];

    $sent = wp_mail($to, $subject, implode("\n", $lines), $headers);
    return $sent ? ['ok' => true] : ['ok' => false, 'error' => 'wp_mail failed'];
}

function casting_mail_employer_response(WP_User $employer, WP_User $talent, array $request): array
{
    $to = $employer->user_email;
    if (!is_email($to)) {
        return ['ok' => false, 'error' => 'ایمیل کارفرما معتبر نیست.'];
    }

    $brand = casting_brand();
    $status_label = ($request['status'] ?? '') === 'accepted' ? 'قبول' : 'رد';
    $subject = sprintf('[%s] پاسخ هنرمند (%s): %s', $brand, $status_label, $talent->display_name);

    $lines = [
        'سلام ' . $employer->display_name . '،',
        '',
        'هنرمند «' . $talent->display_name . '» به درخواست شما پاسخ داد.',
        'نتیجه: ' . $status_label,
    ];
    if (!empty($request['project'])) {
        $lines[] = 'پروژه: ' . $request['project'];
    }
    if (!empty($request['reply'])) {
        $lines[] = '';
        $lines[] = 'نظر هنرمند:';
        $lines[] = $request['reply'];
    }
    $lines[] = '';
    $lines[] = 'ایمیل هنرمند: ' . $talent->user_email;
    $lines[] = '';
    $lines[] = '— ' . $brand;

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $talent->display_name . ' <' . $talent->user_email . '>',
    ];

    $sent = wp_mail($to, $subject, implode("\n", $lines), $headers);
    return $sent ? ['ok' => true] : ['ok' => false, 'error' => 'wp_mail failed'];
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_get_talent_requests(int $talent_id): array
{
    return casting_get_user_request_list($talent_id, 'active');
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_get_employer_sent_requests(int $employer_id): array
{
    return casting_get_user_request_list($employer_id, 'active');
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_user_archived_requests(int $user_id): array
{
    return casting_get_user_request_list($user_id, 'archive');
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_user_requests(int $user_id): array
{
    return casting_get_user_request_list($user_id, 'active');
}

function casting_user_request_count(int $user_id): int
{
    return count(casting_user_requests($user_id));
}

function casting_find_user_request(int $user_id, string $request_id): ?array
{
    $request_id = trim($request_id);
    if ($request_id === '') {
        return null;
    }
    foreach (casting_user_request_directions($user_id) as $direction) {
        foreach (['active', 'archive'] as $bucket) {
            foreach (casting_get_user_request_list_by_direction($user_id, $direction, $bucket) as $req) {
                if (is_array($req) && (string) ($req['id'] ?? '') === $request_id) {
                    return $req;
                }
            }
        }
    }

    return null;
}

function casting_request_status_key(array $req): string
{
    $status = sanitize_key((string) ($req['status'] ?? 'pending'));
    return $status === 'new' ? 'pending' : $status;
}

function casting_request_is_unread(int $user_id, array $req): bool
{
    $role = casting_get_user_role($user_id);
    $status = casting_request_status_key($req);

    if ($role === 'talent' || $role === 'director') {
        return $status === 'pending' && (string) ($req['seen_at'] ?? '') === '';
    }
    if (casting_is_employer_role($role)) {
        if ($status === 'pending') {
            return false;
        }

        return (string) ($req['employer_seen_at'] ?? '') === '';
    }

    return false;
}

function casting_user_new_request_count(int $user_id): int
{
    $role = casting_get_user_role($user_id);
    $count = 0;

    if ($role === 'talent' || $role === 'director') {
        foreach (casting_user_received_requests($user_id) as $req) {
            if (is_array($req) && casting_request_is_unread($user_id, $req)) {
                $count++;
            }
        }
    }
    if (casting_is_employer_role($role)) {
        foreach (casting_user_sent_requests($user_id) as $req) {
            if (is_array($req) && casting_request_is_unread($user_id, $req)) {
                $count++;
            }
        }
    }

    return $count;
}

function casting_request_seed_chat(array $request): void
{
    if (!empty($request['chat_seeded'])) {
        return;
    }

    $employer_id = (int) ($request['employer_id'] ?? 0);
    $talent_id = (int) ($request['talent_id'] ?? 0);
    if ($employer_id <= 0 || $talent_id <= 0) {
        return;
    }

    $lines = ['📋 درخواست همکاری'];
    if (!empty($request['project'])) {
        $lines[] = 'پروژه: ' . (string) $request['project'];
    }
    $lines[] = '';
    $lines[] = (string) ($request['message'] ?? '');
    $message = trim(implode("\n", $lines));
    if ($message === '') {
        return;
    }

    $created_at = (string) ($request['created_at'] ?? '');
    if ($created_at === '') {
        $created_at = current_time('mysql');
    }

    casting_dm_insert_raw($employer_id, $talent_id, $message, $created_at);
}

/**
 * @return array{ok:bool,error:string,peer_id?:int,request_id?:string}
 */
function casting_open_request_chat(int $user_id, string $request_id): array
{
    $req = casting_find_user_request($user_id, $request_id);
    if ($req === null) {
        return ['ok' => false, 'error' => 'درخواست پیدا نشد.'];
    }

    $role = casting_get_user_role($user_id);
    if ($role === 'talent' || ($role === 'director' && (int) ($req['employer_id'] ?? 0) !== $user_id)) {
        $peer_id = (int) ($req['employer_id'] ?? 0);
        if ($peer_id <= 0) {
            return ['ok' => false, 'error' => 'کارفرما پیدا نشد.'];
        }
        $allow = casting_can_users_chat($user_id, $peer_id);
        if (!$allow['ok']) {
            return $allow;
        }

        $updated = $req;
        if ((string) ($updated['seen_at'] ?? '') === '') {
            $updated['seen_at'] = current_time('mysql');
        }
        if (empty($updated['chat_seeded'])) {
            casting_request_seed_chat($updated);
            $updated['chat_seeded'] = true;
        }
        casting_update_request_everywhere($updated);

        return ['ok' => true, 'error' => '', 'peer_id' => $peer_id, 'request_id' => $request_id];
    }

    if (casting_is_employer_role($role)) {
        $peer_id = (int) ($req['talent_id'] ?? 0);
        if ($peer_id <= 0 || $peer_id === $user_id) {
            return ['ok' => false, 'error' => 'طرف گفتگو پیدا نشد.'];
        }
        $allow = casting_can_users_chat($user_id, $peer_id);
        if (!$allow['ok']) {
            return $allow;
        }

        $updated = $req;
        if ((string) ($updated['employer_seen_at'] ?? '') === '') {
            $updated['employer_seen_at'] = current_time('mysql');
        }
        if (empty($updated['chat_seeded'])) {
            casting_request_seed_chat($updated);
            $updated['chat_seeded'] = true;
        }
        if (
            (string) ($updated['reply'] ?? '') !== ''
            && casting_request_status_key($updated) !== 'pending'
            && empty($updated['reply_in_chat'])
        ) {
            $reply = trim("💬 پاسخ هنرمند:\n" . (string) $updated['reply']);
            $replied_at = (string) ($updated['replied_at'] ?? '');
            if ($replied_at === '') {
                $replied_at = current_time('mysql');
            }
            if (casting_dm_insert_raw($peer_id, $user_id, $reply, $replied_at)) {
                $updated['reply_in_chat'] = true;
            }
        }
        casting_update_request_everywhere($updated);

        return ['ok' => true, 'error' => '', 'peer_id' => $peer_id, 'request_id' => $request_id];
    }

    return ['ok' => false, 'error' => 'این بخش برای نقش شما فعال نیست.'];
}

function casting_request_status_label(string $status): string
{
    if ($status === 'accepted') {
        return 'قبول شده';
    }
    if ($status === 'rejected') {
        return 'رد شده';
    }
    if ($status === 'pending' || $status === 'new') {
        return 'در انتظار پاسخ';
    }
    return $status;
}

function casting_render_talent_requests_list(int $user_id, array $requests, string $form_action = 'my-requests.php', string $view = 'active', string $box = 'default'): void
{
    $is_archive = $view === 'archive';
    if ($requests === []) {
        echo $is_archive
            ? '<p class="meta">بایگانی خالی است.</p>'
            : '<p class="meta">هنوز درخواستی نیامده است.</p>';
        return;
    }
    ?>
    <div class="request-list">
      <?php foreach ($requests as $req) :
          $req_id = (string) ($req['id'] ?? '');
          $status = casting_request_status_key($req);
          $pending = $status === 'pending';
          $is_unread = !$is_archive && casting_request_is_unread($user_id, $req);
          $open_url = 'my-requests.php';
          if ($req_id !== '') {
              $open_url .= ($is_archive ? '?view=archive&' : '?') . 'open=' . rawurlencode($req_id);
          } elseif ($is_archive) {
              $open_url .= '?view=archive';
          }
          if ($box === 'received') {
              $open_url .= (str_contains($open_url, '?') ? '&' : '?') . 'box=received';
          }
          ?>
        <article class="request-item status-<?= casting_e($status) ?><?= $is_unread ? ' is-unread' : '' ?><?= $is_archive ? ' is-archived' : '' ?>">
          <a class="request-item-open" href="<?= casting_e($open_url) ?>">
            <header>
              <strong><?= casting_e((string) ($req['employer'] ?? 'کارفرما')) ?></strong>
              <span><?= casting_e((string) ($req['employer_role'] ?? '')) ?></span>
              <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
              <?php if ($is_archive && !empty($req['archived_at'])) : ?>
                <span class="req-archived-at">بایگانی: <?= casting_e((string) $req['archived_at']) ?></span>
              <?php endif; ?>
              <?php if ($is_unread) : ?>
                <span class="req-status req-status-new">جدید</span>
              <?php else : ?>
                <span class="req-status"><?= casting_e(casting_request_status_label($status)) ?></span>
              <?php endif; ?>
            </header>
            <?php if (!empty($req['project'])) : ?>
              <p><strong>پروژه:</strong> <?= casting_e((string) $req['project']) ?></p>
            <?php endif; ?>
            <p><?= nl2br(casting_e(casting_chat_preview((string) ($req['message'] ?? ''), 160))) ?></p>
            <span class="request-item-cta">مشاهده و گفتگو با کارفرما ←</span>
          </a>
          <div class="request-item-actions">
            <?php if ($is_archive) : ?>
              <form method="post" action="<?= casting_e($form_action) ?>">
                <?php wp_nonce_field('casting_archive_request'); ?>
                <input type="hidden" name="request_id" value="<?= casting_e($req_id) ?>">
                <input type="hidden" name="view" value="archive">
                <?php if ($box === 'received') : ?>
                  <input type="hidden" name="box" value="received">
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm" type="submit" name="request_archive_action" value="unarchive">بازگردانی از بایگانی</button>
              </form>
            <?php else : ?>
              <form method="post" action="<?= casting_e($form_action) ?>" onsubmit="return confirm('این درخواست به بایگانی منتقل شود؟');">
                <?php wp_nonce_field('casting_archive_request'); ?>
                <input type="hidden" name="request_id" value="<?= casting_e($req_id) ?>">
                <?php if ($box === 'received') : ?>
                  <input type="hidden" name="box" value="received">
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm" type="submit" name="request_archive_action" value="archive">بایگانی</button>
              </form>
            <?php endif; ?>
          </div>
          <?php if (!$is_archive && $pending && !$is_unread) : ?>
            <form class="form request-reply-form" method="post" action="<?= casting_e($form_action) ?>">
              <?php wp_nonce_field('casting_respond_request'); ?>
              <input type="hidden" name="request_id" value="<?= casting_e($req_id) ?>">
              <?php if ($box === 'received') : ?>
                <input type="hidden" name="box" value="received">
              <?php endif; ?>
              <div class="field">
                <label for="reply-<?= casting_e($req_id) ?>">پاسخ سریع (اختیاری)</label>
                <textarea id="reply-<?= casting_e($req_id) ?>" name="reply" rows="2" maxlength="2000"></textarea>
              </div>
              <div class="cta-row">
                <button class="btn btn-primary" type="submit" name="decision" value="accepted">قبول</button>
                <button class="btn btn-reject" type="submit" name="decision" value="rejected">رد</button>
              </div>
            </form>
          <?php elseif (!empty($req['reply'])) : ?>
            <p class="request-reply"><strong>پاسخ شما:</strong> <?= nl2br(casting_e((string) $req['reply'])) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}

function casting_render_employer_sent_requests_list(int $employer_id, array $requests, string $form_action = 'my-requests.php', string $view = 'active', string $box = 'default'): void
{
    $is_archive = $view === 'archive';
    if ($requests === []) {
        if ($is_archive) {
            echo '<p class="meta">بایگانی خالی است.</p>';
        } else {
            ?>
    <p class="meta">هنوز درخواستی نفرستاده‌اید. از <a href="search-users.php">جستجوی کاربران</a> شروع کنید.</p>
            <?php
        }
        return;
    }
    ?>
    <div class="request-list">
      <?php foreach ($requests as $req) :
          $req_id = (string) ($req['id'] ?? '');
          $status = casting_request_status_key($req);
          $is_unread = !$is_archive && casting_request_is_unread($employer_id, $req);
          $open_url = 'my-requests.php';
          if ($req_id !== '') {
              $open_url .= ($is_archive ? '?view=archive&' : '?') . 'open=' . rawurlencode($req_id);
          } elseif ($is_archive) {
              $open_url .= '?view=archive';
          }
          if ($box === 'sent') {
              $open_url .= (str_contains($open_url, '?') ? '&' : '?') . 'box=sent';
          }
          ?>
        <article class="request-item status-<?= casting_e($status) ?><?= $is_unread ? ' is-unread' : '' ?><?= $is_archive ? ' is-archived' : '' ?>">
          <?php if (!empty($req['talent_id'])) : ?>
          <a class="request-item-open" href="<?= casting_e($open_url) ?>">
            <header>
              <strong><?= casting_e((string) ($req['talent_name'] ?? 'کاربر')) ?></strong>
              <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
              <?php if ($is_archive && !empty($req['archived_at'])) : ?>
                <span class="req-archived-at">بایگانی: <?= casting_e((string) $req['archived_at']) ?></span>
              <?php endif; ?>
              <?php if ($is_unread) : ?>
                <span class="req-status req-status-new">پاسخ جدید</span>
              <?php else : ?>
                <span class="req-status"><?= casting_e(casting_request_status_label($status)) ?></span>
              <?php endif; ?>
            </header>
            <?php if (!empty($req['project'])) : ?>
              <p><strong>پروژه:</strong> <?= casting_e((string) $req['project']) ?></p>
            <?php endif; ?>
            <p><?= nl2br(casting_e(casting_chat_preview((string) ($req['message'] ?? ''), 160))) ?></p>
            <?php if (!empty($req['reply'])) : ?>
              <p class="request-reply"><strong>پاسخ هنرمند:</strong> <?= nl2br(casting_e((string) $req['reply'])) ?></p>
            <?php endif; ?>
            <span class="request-item-cta">مشاهده و گفتگو ←</span>
          </a>
          <div class="request-item-actions">
            <?php if ($is_archive) : ?>
              <form method="post" action="<?= casting_e($form_action) ?>">
                <?php wp_nonce_field('casting_archive_request'); ?>
                <input type="hidden" name="request_id" value="<?= casting_e($req_id) ?>">
                <input type="hidden" name="view" value="archive">
                <?php if ($box === 'sent') : ?>
                  <input type="hidden" name="box" value="sent">
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm" type="submit" name="request_archive_action" value="unarchive">بازگردانی از بایگانی</button>
              </form>
              <form method="post" action="<?= casting_e($form_action) ?>" onsubmit="return confirm('این درخواست برای همیشه از لیست شما حذف شود؟');">
                <?php wp_nonce_field('casting_manage_request'); ?>
                <input type="hidden" name="request_id" value="<?= casting_e($req_id) ?>">
                <input type="hidden" name="view" value="archive">
                <?php if ($box === 'sent') : ?>
                  <input type="hidden" name="box" value="sent">
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm" type="submit" name="request_manage_action" value="delete">حذف</button>
              </form>
            <?php else : ?>
              <?php if ($status === 'pending') : ?>
                <form method="post" action="<?= casting_e($form_action) ?>" onsubmit="return confirm('درخواست پس گرفته شود و از لیست بازیگر هم حذف گردد؟');">
                  <?php wp_nonce_field('casting_manage_request'); ?>
                  <input type="hidden" name="request_id" value="<?= casting_e($req_id) ?>">
                  <?php if ($box === 'sent') : ?>
                    <input type="hidden" name="box" value="sent">
                  <?php endif; ?>
                  <button class="btn btn-reject btn-sm" type="submit" name="request_manage_action" value="withdraw">عدم ارسال</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= casting_e($form_action) ?>" onsubmit="return confirm('این درخواست فقط از لیست شما حذف شود؟ (برای بازیگر همچنان نمایش داده می‌شود مگر «عدم ارسال» بزنید.)');">
                <?php wp_nonce_field('casting_manage_request'); ?>
                <input type="hidden" name="request_id" value="<?= casting_e($req_id) ?>">
                <?php if ($box === 'sent') : ?>
                  <input type="hidden" name="box" value="sent">
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm" type="submit" name="request_manage_action" value="delete">حذف</button>
              </form>
              <form method="post" action="<?= casting_e($form_action) ?>" onsubmit="return confirm('این درخواست به بایگانی منتقل شود؟');">
                <?php wp_nonce_field('casting_archive_request'); ?>
                <input type="hidden" name="request_id" value="<?= casting_e($req_id) ?>">
                <?php if ($box === 'sent') : ?>
                  <input type="hidden" name="box" value="sent">
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm" type="submit" name="request_archive_action" value="archive">بایگانی</button>
              </form>
            <?php endif; ?>
          </div>
          <div class="cta-row">
            <a class="btn btn-ghost" href="member.php?id=<?= (int) $req['talent_id'] ?>">مشاهده پروفایل</a>
          </div>
          <?php else : ?>
            <header>
              <strong><?= casting_e((string) ($req['talent_name'] ?? 'کاربر')) ?></strong>
              <time><?= casting_e((string) ($req['created_at'] ?? '')) ?></time>
              <span class="req-status"><?= casting_e(casting_request_status_label($status)) ?></span>
            </header>
            <p><?= nl2br(casting_e((string) ($req['message'] ?? ''))) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}

/**
 * @param list<array{talent_id:int,name:string,photo_url:string,city:string}> $highlighted
 */
function casting_render_director_send_request_compose(int $director_id, array $highlighted, bool $open = false, string $project = '', string $message = '', int $selected_talent_id = 0): void
{
    if ($selected_talent_id <= 0 && $highlighted !== []) {
        $selected_talent_id = (int) ($highlighted[0]['talent_id'] ?? 0);
    }
    ?>
    <details class="request-compose" id="request-compose"<?= $open ? ' open' : '' ?>>
      <summary class="request-compose-summary">ارسال درخواست همکاری</summary>
      <div class="request-compose-body">
        <?php if ($highlighted !== []) : ?>
          <p class="field-hint">ابتدا از بین بازیگرانی که برجسته کرده‌اید انتخاب کنید و درخواست را بفرستید.</p>
          <form class="form request-compose-form" method="post" action="my-requests.php?box=sent#request-compose">
            <?php wp_nonce_field('casting_send_request'); ?>
            <input type="hidden" name="send_collaboration_request" value="1">
            <fieldset class="request-pick-list">
              <legend>انتخاب‌های برجسته شما</legend>
              <?php foreach ($highlighted as $talent) :
                  $tid = (int) $talent['talent_id'];
                  ?>
                <label class="request-pick-item">
                  <input
                    type="radio"
                    name="talent_id"
                    value="<?= $tid ?>"
                    <?= $selected_talent_id === $tid ? 'checked' : '' ?>
                    required
                  >
                  <span class="request-pick-card">
                    <span class="request-pick-photo">
                      <?php if (($talent['photo_url'] ?? '') !== '') : ?>
                        <img src="<?= casting_e((string) $talent['photo_url']) ?>" alt="">
                      <?php else : ?>
                        <span class="photo-placeholder">?</span>
                      <?php endif; ?>
                    </span>
                    <span class="request-pick-meta">
                      <strong><?= casting_e((string) $talent['name']) ?></strong>
                      <?php if (($talent['city'] ?? '') !== '') : ?>
                        <span><?= casting_e((string) $talent['city']) ?></span>
                      <?php endif; ?>
                    </span>
                  </span>
                </label>
              <?php endforeach; ?>
            </fieldset>
            <div class="field">
              <label for="req_project">نام پروژه / نقش (اختیاری)</label>
              <input id="req_project" name="project" type="text" maxlength="191" value="<?= casting_e($project) ?>">
            </div>
            <div class="field">
              <label for="req_message">متن درخواست</label>
              <textarea id="req_message" name="message" rows="4" required maxlength="2000"><?= casting_e($message) ?></textarea>
            </div>
            <div class="cta-row">
              <button class="btn btn-primary" type="submit">ارسال درخواست</button>
            </div>
          </form>
        <?php else : ?>
          <p class="field-hint">هنوز بازیگری را برجسته نکرده‌اید. از جستجو پروفایل را باز کنید و «هایلایت» را بزنید تا اینجا سریع‌تر در دسترس باشد.</p>
        <?php endif; ?>

        <div class="request-compose-search">
          <a class="btn btn-ghost" href="search-users.php">جستجوی بازیگران</a>
          <?php if ($highlighted !== []) : ?>
            <span class="meta">یا بازیگر دیگری را از جستجو پیدا کنید.</span>
          <?php endif; ?>
        </div>
      </div>
    </details>
    <?php
}
