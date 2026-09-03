<?php
declare(strict_types=1);

/**
 * @return list<string>
 */
function casting_portal_broadcast_sms_user_mobiles(int $user_id): array
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $out = [];
    foreach (['casting_mobile', 'casting_mobile2'] as $meta_key) {
        $mobile = casting_normalize_mobile((string) get_user_meta($user_id, $meta_key, true));
        if ($mobile !== '' && preg_match('/^09\d{9}$/', $mobile)) {
            $out[] = $mobile;
        }
    }

    return array_values(array_unique($out));
}

function casting_portal_broadcast_sms_normalize_message(string $message): string
{
    $message = str_replace(["\r\n", "\r"], "\n", trim($message));
    $message = preg_replace("/\n{3,}/", "\n\n", $message) ?? $message;

    return trim($message);
}

function casting_portal_broadcast_sms_message_is_valid(string $message): bool
{
    $message = casting_portal_broadcast_sms_normalize_message($message);

    return $message !== '' && mb_strlen($message, 'UTF-8') <= 500;
}

/**
 * @return list<array{user_id:int,name:string,login:string,mobile:string}>
 */
function casting_portal_broadcast_sms_build_recipient_list(bool $include_suspended = false): array
{
    if (!function_exists('casting_get_user_role')) {
        require_once __DIR__ . '/auth.php';
    }

    $recipients = [];
    $seen_mobiles = [];
    $page = 1;
    $per_page = 100;

    while (true) {
        $query = new WP_User_Query([
            'meta_key'     => 'casting_role',
            'meta_compare' => 'EXISTS',
            'number'       => $per_page,
            'offset'       => ($page - 1) * $per_page,
            'orderby'      => 'ID',
            'order'        => 'ASC',
            'fields'       => 'all',
        ]);
        $users = $query->get_results();
        if ($users === []) {
            break;
        }

        foreach ($users as $user) {
            $user_id = (int) $user->ID;
            if (casting_get_user_role($user_id) === '') {
                continue;
            }
            if (!$include_suspended
                && function_exists('casting_user_is_suspended')
                && casting_user_is_suspended($user_id)) {
                continue;
            }
            foreach (casting_portal_broadcast_sms_user_mobiles($user_id) as $mobile) {
                if (isset($seen_mobiles[$mobile])) {
                    continue;
                }
                $seen_mobiles[$mobile] = true;
                $recipients[] = [
                    'user_id' => $user_id,
                    'name'    => (string) $user->display_name,
                    'login'   => (string) $user->user_login,
                    'mobile'  => $mobile,
                ];
            }
        }

        if (count($users) < $per_page) {
            break;
        }
        $page++;
    }

    return $recipients;
}

/**
 * @return array{ok:bool,error:string,ref_id:string,code:int}
 */
function casting_portal_broadcast_sms_send_text(string $mobile, string $message): array
{
    if (!function_exists('casting_sms_is_configured') || !casting_sms_is_configured()) {
        return ['ok' => false, 'error' => 'پیامک در پورتال پیکربندی نشده است.', 'ref_id' => '', 'code' => -1];
    }
    if (!function_exists('casting_sms_send_text')) {
        require_once __DIR__ . '/sms.php';
    }
    if (!casting_portal_broadcast_sms_message_is_valid($message)) {
        return ['ok' => false, 'error' => 'متن پیامک خالی یا بیش از حد طولانی است.', 'ref_id' => '', 'code' => -1];
    }

    $result = casting_sms_send_text($mobile, casting_portal_broadcast_sms_normalize_message($message));

    return [
        'ok'     => !empty($result['ok']),
        'error'  => (string) ($result['error'] ?? ''),
        'ref_id' => (string) ($result['ref_id'] ?? ''),
        'code'   => (int) ($result['code'] ?? 0),
    ];
}

/**
 * @return array{
 *   ok:bool,
 *   dry_run:bool,
 *   total:int,
 *   sent:int,
 *   failed:int,
 *   offset:int,
 *   next_offset:int,
 *   done:bool,
 *   items:array<int, array{user_id:int,name:string,login:string,mobile:string,status:string,error:string}>,
 *   errors:array<int,string>
 * }
 */
function casting_portal_broadcast_sms_run_batch(string $message, int $limit = 30, int $offset = 0, bool $dry_run = false): array
{
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $recipients = casting_portal_broadcast_sms_build_recipient_list(false);
    $total = count($recipients);
    $slice = array_slice($recipients, $offset, $limit);
    $sent = 0;
    $failed = 0;
    $items = [];
    $errors = [];

    if (!casting_portal_broadcast_sms_message_is_valid($message)) {
        return [
            'ok'          => false,
            'dry_run'     => $dry_run,
            'total'       => $total,
            'sent'        => 0,
            'failed'      => 0,
            'offset'      => $offset,
            'next_offset' => $offset,
            'done'        => true,
            'items'       => [],
            'errors'      => ['متن پیامک خالی یا بیش از حد طولانی است.'],
        ];
    }

    foreach ($slice as $row) {
        $item = [
            'user_id' => (int) $row['user_id'],
            'name'    => (string) $row['name'],
            'login'   => (string) $row['login'],
            'mobile'  => (string) $row['mobile'],
            'status'  => $dry_run ? 'would_send' : 'sent',
            'error'   => '',
        ];
        if ($dry_run) {
            $items[] = $item;
            $sent++;
            continue;
        }
        $result = casting_portal_broadcast_sms_send_text((string) $row['mobile'], $message);
        if (!empty($result['ok'])) {
            $items[] = $item;
            $sent++;
        } else {
            $failed++;
            $item['status'] = 'failed';
            $item['error'] = (string) ($result['error'] ?? 'ناموفق');
            $items[] = $item;
            $errors[] = $row['mobile'] . ': ' . $item['error'];
        }
    }

    $next_offset = $offset + count($slice);

    return [
        'ok'          => true,
        'dry_run'     => $dry_run,
        'total'       => $total,
        'sent'        => $sent,
        'failed'      => $failed,
        'offset'      => $offset,
        'next_offset' => $next_offset,
        'done'        => $next_offset >= $total,
        'items'       => $items,
        'errors'      => $errors,
    ];
}

/**
 * @return array{
 *   ok:bool,
 *   dry_run:bool,
 *   total:int,
 *   sent:int,
 *   failed:int,
 *   done:bool,
 *   items:array,
 *   errors:array<int,string>
 * }
 */
function casting_portal_broadcast_sms_run_all(string $message, bool $dry_run = false, int $per_batch = 50): array
{
    $per_batch = max(10, min(200, $per_batch));
    $offset = 0;
    $total = [
        'ok'      => true,
        'dry_run' => $dry_run,
        'total'   => 0,
        'sent'    => 0,
        'failed'  => 0,
        'done'    => false,
        'items'   => [],
        'errors'  => [],
    ];

    while (true) {
        $batch = casting_portal_broadcast_sms_run_batch($message, $per_batch, $offset, $dry_run);
        if (empty($batch['ok'])) {
            $total['ok'] = false;
            $total['errors'] = array_merge($total['errors'], $batch['errors'] ?? ['ارسال ناموفق بود.']);
            break;
        }
        $total['total'] = (int) ($batch['total'] ?? 0);
        $total['sent'] += (int) ($batch['sent'] ?? 0);
        $total['failed'] += (int) ($batch['failed'] ?? 0);
        $total['items'] = array_merge($total['items'], $batch['items'] ?? []);
        $total['errors'] = array_merge($total['errors'], $batch['errors'] ?? []);
        if (!empty($batch['done'])) {
            $total['done'] = true;
            break;
        }
        $offset = (int) ($batch['next_offset'] ?? ($offset + $per_batch));
        if ($offset <= 0) {
            break;
        }
    }

    return $total;
}
