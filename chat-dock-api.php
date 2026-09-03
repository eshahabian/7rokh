<?php
declare(strict_types=1);

/**
 * API سبک چت داک / poll زنده.
 * مسیر poll فقط پیام‌های جدید را برمی‌گرداند تا زیر ۱۰۰ کاربر همزمان فشار کم بماند.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/blocks.php';
if (is_file(__DIR__ . '/includes/chat-rules.php')) {
    require_once __DIR__ . '/includes/chat-rules.php';
}

header('Content-Type: application/json; charset=utf-8');
casting_nocache();

$user = casting_require_api_casting_user();
$my_id = (int) $user->ID;
$action = (string) ($_REQUEST['action'] ?? '');

$nonce = (string) ($_REQUEST['_wpnonce'] ?? $_SERVER['HTTP_X_WP_NONCE'] ?? '');
if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_dm')) {
    http_response_code(403);
    echo wp_json_encode(['ok' => false, 'error' => 'نشست منقضی شده. صفحه را رفرش کنید.']);
    exit;
}

if ($action === 'thread') {
    $peer_id = (int) ($_GET['peer_id'] ?? $_POST['peer_id'] ?? 0);
    if ($peer_id <= 0 || casting_get_user_role($peer_id) === '') {
        http_response_code(400);
        echo wp_json_encode(['ok' => false, 'error' => 'مخاطب نامعتبر است.']);
        exit;
    }

    $can_chat = casting_can_users_chat($my_id, $peer_id);
    if (!casting_dm_has_conversation($my_id, $peer_id) && empty($can_chat['ok'])) {
        http_response_code(403);
        echo wp_json_encode(['ok' => false, 'error' => 'اجازه گفتگو با این کاربر را ندارید.']);
        exit;
    }

    $after_id = (int) ($_GET['after_id'] ?? $_POST['after_id'] ?? 0);
    $edit_since = sanitize_text_field((string) ($_GET['edit_since'] ?? $_POST['edit_since'] ?? ''));
    $poll = ((string) ($_GET['poll'] ?? $_POST['poll'] ?? '') === '1') || $after_id > 0;
    $locked = casting_dm_thread_locked_for_user($my_id, $peer_id);

    // مسیر poll سبک: فقط پیام‌های جدید
    if ($poll && $after_id > 0) {
        if ($locked) {
            echo wp_json_encode([
                'ok'       => true,
                'locked'   => true,
                'last_id'  => $after_id,
                'messages' => [],
                'updates'  => [],
            ]);
            exit;
        }

        $max_id = casting_dm_thread_max_id($my_id, $peer_id);
        $updates = $edit_since !== ''
            ? casting_dm_thread_updates_since($my_id, $peer_id, $edit_since)
            : [];
        if ($max_id <= $after_id) {
            echo wp_json_encode([
                'ok'       => true,
                'locked'   => false,
                'last_id'  => $after_id,
                'messages' => [],
                'updates'  => $updates,
            ]);
            exit;
        }

        $fresh = casting_dm_thread_after($my_id, $peer_id, $after_id, 50);
        casting_dm_mark_delivered($my_id, $peer_id);
        casting_dm_mark_read($my_id, $peer_id);

        $messages = [];
        $last_id = $after_id;
        foreach ($fresh as $msg) {
            $id = (int) ($msg['id'] ?? 0);
            if ($id > $last_id) {
                $last_id = $id;
            }
            $messages[] = casting_dm_message_for_api($msg, $my_id);
        }

        echo wp_json_encode([
            'ok'       => true,
            'locked'   => false,
            'last_id'  => $last_id,
            'messages' => $messages,
            'updates'  => $updates,
        ]);
        exit;
    }

    casting_dm_mark_delivered($my_id, $peer_id);
    if (!$locked) {
        casting_dm_mark_read($my_id, $peer_id);
    }

    $thread = [];
    if (!$locked) {
        $thread = casting_dm_thread($my_id, $peer_id, 80);
    }
    $allow = casting_can_user_send_dm($my_id, $peer_id);
    $messages = [];
    $last_id = 0;
    foreach ($thread as $msg) {
        $id = (int) ($msg['id'] ?? 0);
        if ($id > $last_id) {
            $last_id = $id;
        }
        if ($after_id > 0 && $id <= $after_id) {
            continue;
        }
        $messages[] = casting_dm_message_for_api($msg, $my_id);
    }

    $blocked = casting_users_block_each_other($my_id, $peer_id);
    $error = '';
    if ($locked) {
        $error = casting_dm_premium_required_notice_message();
    } elseif (empty($allow['ok'])) {
        $error = (string) ($allow['error'] ?? '');
    } elseif ($blocked) {
        $error = 'به‌دلیل بلاک امکان پیام نیست.';
    }

    echo wp_json_encode([
        'ok'       => true,
        'peer'     => [
            'id'     => $peer_id,
            'name'   => $blocked ? 'کاربر' : casting_dm_peer_display_name($peer_id),
            'role'   => $blocked ? '' : casting_dm_peer_role_label($peer_id),
            'avatar' => $blocked ? '' : casting_chat_peer_avatar_url($peer_id),
        ],
        'locked'   => $locked,
        'can_send' => !$locked && !empty($allow['ok']) && !$blocked,
        'error'    => $error,
        'cart_url' => $locked ? casting_url('cart.php') : '',
        'last_id'  => $last_id,
        'messages' => $locked ? [] : $messages,
    ]);
    exit;
}

if ($action === 'inbox') {
    $fp_only = ((string) ($_GET['fp_only'] ?? $_POST['fp_only'] ?? '') === '1');
    if ($fp_only) {
        echo wp_json_encode([
            'ok'           => true,
            'fingerprint'  => casting_dm_inbox_fingerprint($my_id),
            'unread_total' => casting_dm_unread_peer_count($my_id),
        ]);
        exit;
    }

    $conversations = casting_dm_conversations($my_id);
    $rows = [];
    foreach ($conversations as $conv) {
        $peer = (int) ($conv['peer_id'] ?? 0);
        if ($peer <= 0) {
            continue;
        }
        $rows[] = [
            'peer_id'      => $peer,
            'name'         => (string) ($conv['name'] ?? ''),
            'unread'       => (int) ($conv['unread'] ?? 0),
            'locked'       => !empty($conv['locked']),
            'last_message' => (string) ($conv['last_message'] ?? ''),
            'last_at'      => (string) ($conv['last_at'] ?? ''),
        ];
    }

    echo wp_json_encode([
        'ok'           => true,
        'unread_total' => casting_dm_unread_peer_count($my_id),
        'fingerprint'  => casting_dm_inbox_fingerprint($my_id),
        'conversations'=> $rows,
    ]);
    exit;
}

if ($action === 'send') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo wp_json_encode(['ok' => false, 'error' => 'روش نامعتبر است.']);
        exit;
    }
    $peer_id = (int) ($_POST['peer_id'] ?? 0);
    $message = (string) ($_POST['message'] ?? '');
    $result = casting_dm_send($my_id, $peer_id, $message);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo wp_json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'ارسال ناموفق بود.')]);
        exit;
    }
    echo wp_json_encode([
        'ok'      => true,
        'message' => casting_dm_message_for_api([
            'id'           => (int) ($result['id'] ?? 0),
            'sender_id'    => $my_id,
            'recipient_id' => $peer_id,
            'message'      => trim($message),
            'created_at'   => current_time('mysql'),
            'edited_at'    => '',
        ], $my_id),
    ]);
    exit;
}

if ($action === 'send_photo') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo wp_json_encode(['ok' => false, 'error' => 'روش نامعتبر است.']);
        exit;
    }
    $peer_id = (int) ($_POST['peer_id'] ?? 0);
    $result = casting_dm_send_photo($my_id, $peer_id, 'photo');
    if (empty($result['ok'])) {
        http_response_code(400);
        echo wp_json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'ارسال عکس ناموفق بود.')]);
        exit;
    }
    echo wp_json_encode([
        'ok'      => true,
        'message' => casting_dm_message_for_api([
            'id'           => (int) ($result['id'] ?? 0),
            'sender_id'    => $my_id,
            'recipient_id' => $peer_id,
            'message'      => casting_dm_photo_marker((int) ($result['photo_id'] ?? 0)),
            'created_at'   => current_time('mysql'),
            'edited_at'    => '',
        ], $my_id),
    ]);
    exit;
}

if ($action === 'share_targets') {
    try {
        $can_share = casting_dm_user_can_share($my_id);
        $needs_premium = !$can_share && casting_user_requires_premium_for_dm($my_id);
        echo wp_json_encode([
            'ok'            => true,
            'can_share'     => $can_share,
            'needs_premium' => $needs_premium,
            'cart_url'      => $needs_premium ? casting_url('cart.php') : '',
            'error'         => $needs_premium ? casting_dm_premium_required_notice_message() : '',
            'quota_hint'    => $can_share ? casting_employer_free_messages_hint($my_id) : '',
            'max_peers'     => casting_dm_share_max_peers(),
            'targets'       => $can_share ? casting_dm_share_targets($my_id) : [],
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo wp_json_encode([
            'ok'    => false,
            'error' => 'بارگذاری مخاطبان ناموفق بود. کمی بعد دوباره تلاش کنید.',
        ]);
    }
    exit;
}

if ($action === 'share') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo wp_json_encode(['ok' => false, 'error' => 'روش نامعتبر است.']);
        exit;
    }
    $peer_ids = $_POST['peer_ids'] ?? [];
    $message = (string) ($_POST['message'] ?? '');
    if (!casting_dm_user_can_share($my_id) && casting_user_requires_premium_for_dm($my_id)) {
        http_response_code(403);
        echo wp_json_encode([
            'ok'            => false,
            'needs_premium' => true,
            'cart_url'      => casting_url('cart.php'),
            'error'         => casting_dm_premium_required_notice_message(),
        ]);
        exit;
    }
    $result = casting_dm_share_to_many($my_id, $peer_ids, $message);
    if (empty($result['ok']) && !empty($result['needs_premium'])) {
        http_response_code(403);
        echo wp_json_encode([
            'ok'            => false,
            'needs_premium' => true,
            'cart_url'      => (string) ($result['cart_url'] ?? casting_url('cart.php')),
            'error'         => (string) ($result['error'] ?? casting_dm_premium_required_notice_message()),
        ]);
        exit;
    }
    if (empty($result['ok'])) {
        http_response_code(400);
        echo wp_json_encode([
            'ok'            => false,
            'needs_premium' => false,
            'error'         => (string) ($result['error'] ?? 'ارسال ناموفق بود.'),
            'failed'        => $result['failed'] ?? [],
        ]);
        exit;
    }
    echo wp_json_encode([
        'ok'            => true,
        'needs_premium' => false,
        'error'         => (string) ($result['error'] ?? ''),
        'cart_url'      => (string) ($result['cart_url'] ?? ''),
        'sent'          => $result['sent'] ?? [],
        'failed'        => $result['failed'] ?? [],
        'sent_count'    => (int) ($result['sent_count'] ?? 0),
        'failed_count'  => (int) ($result['failed_count'] ?? 0),
    ]);
    exit;
}

if ($action === 'edit') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo wp_json_encode(['ok' => false, 'error' => 'روش نامعتبر است.']);
        exit;
    }
    $message_id = (int) ($_POST['message_id'] ?? 0);
    $new_message = (string) ($_POST['message'] ?? '');
    $result = casting_dm_edit_message($my_id, $message_id, $new_message);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo wp_json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'ویرایش ناموفق بود.')]);
        exit;
    }
    $msg = is_array($result['message'] ?? null) ? $result['message'] : [];
    echo wp_json_encode([
        'ok'      => true,
        'message' => casting_dm_message_for_api($msg, $my_id),
    ]);
    exit;
}

http_response_code(400);
echo wp_json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است.']);
exit;
