<?php
declare(strict_types=1);

const CASTING_CONTACT_INBOX_MAX = 500;

/**
 * @return array<string, string>
 */
function casting_contact_channel_labels(): array
{
    return [
        'site_admin'  => 'مدیر سایت',
        'brand_admin' => 'مدیر هفت رخ',
    ];
}

function casting_contact_recipient_login(string $channel): string
{
    $channel = sanitize_key($channel);
    if ($channel === 'site_admin') {
        return (string) (defined('CASTING_CONTACT_SITE_ADMIN') ? CASTING_CONTACT_SITE_ADMIN : 'eshahabian');
    }
    if ($channel === 'brand_admin') {
        return (string) (defined('CASTING_CONTACT_BRAND_ADMIN') ? CASTING_CONTACT_BRAND_ADMIN : 'Ardavan');
    }
    return '';
}

function casting_contact_recipient_id(string $channel): int
{
    $login = casting_contact_recipient_login($channel);
    if ($login === '') {
        return 0;
    }
    $user = get_user_by('login', $login);
    return $user ? (int) $user->ID : 0;
}

/**
 * @return list<string>
 */
function casting_contact_channels_for_recipient(int $user_id): array
{
    $user_id = max(0, $user_id);
    if ($user_id <= 0) {
        return [];
    }
    $out = [];
    foreach (array_keys(casting_contact_channel_labels()) as $channel) {
        if (casting_contact_recipient_id($channel) === $user_id) {
            $out[] = $channel;
        }
    }
    return $out;
}

function casting_contact_user_is_recipient(int $user_id): bool
{
    return casting_contact_channels_for_recipient($user_id) !== [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function casting_contact_load_inbox(): array
{
    $inbox = get_option('casting_contact_inbox', []);
    return is_array($inbox) ? $inbox : [];
}

function casting_contact_save_inbox(array $inbox): void
{
    if (count($inbox) > CASTING_CONTACT_INBOX_MAX) {
        $inbox = array_slice($inbox, 0, CASTING_CONTACT_INBOX_MAX);
    }
    update_option('casting_contact_inbox', $inbox, false);
}

/**
 * @return array{id:string,name:string,email:string,subject:string,message:string,user_id:int,recipient_id:int,channel:string,at:string,read:bool}
 */
function casting_contact_save_message(
    string $name,
    string $email,
    string $subject,
    string $message,
    int $user_id = 0,
    int $recipient_id = 0,
    string $channel = ''
): array {
    $entry = [
        'id'           => uniqid('ct_', true),
        'name'         => sanitize_text_field($name),
        'email'        => sanitize_email($email),
        'subject'      => sanitize_text_field($subject),
        'message'      => sanitize_textarea_field($message),
        'user_id'      => max(0, $user_id),
        'recipient_id' => max(0, $recipient_id),
        'channel'      => sanitize_key($channel),
        'at'           => current_time('mysql'),
        'read'         => false,
    ];

    $inbox = casting_contact_load_inbox();
    array_unshift($inbox, $entry);
    casting_contact_save_inbox($inbox);

    return $entry;
}

/**
 * @return array{ok:bool,error:string,message:array<string,mixed>|null}
 */
function casting_contact_send_panel_message(int $sender_id, string $channel, string $subject, string $message): array
{
    $sender_id = max(0, $sender_id);
    $channel = sanitize_key($channel);
    $subject = sanitize_text_field($subject);
    $message = sanitize_textarea_field($message);

    if ($sender_id <= 0) {
        return ['ok' => false, 'error' => 'برای ارسال پیام وارد پنل شوید.', 'message' => null];
    }
    if (!array_key_exists($channel, casting_contact_channel_labels())) {
        return ['ok' => false, 'error' => 'گیرنده نامعتبر است.', 'message' => null];
    }
    if (casting_strlen($subject) < 2) {
        return ['ok' => false, 'error' => 'موضوع را وارد کنید.', 'message' => null];
    }
    if (casting_strlen($message) < 10) {
        return ['ok' => false, 'error' => 'متن پیام خیلی کوتاه است.', 'message' => null];
    }
    if (casting_strlen($message) > 3000) {
        return ['ok' => false, 'error' => 'متن پیام خیلی بلند است.', 'message' => null];
    }

    $recipient_id = casting_contact_recipient_id($channel);
    if ($recipient_id <= 0) {
        return ['ok' => false, 'error' => 'گیرنده پیام در سامانه پیدا نشد.', 'message' => null];
    }
    if ($recipient_id === $sender_id) {
        return ['ok' => false, 'error' => 'نمی‌توانید به خودتان پیام بفرستید.', 'message' => null];
    }

    $sender = get_user_by('id', $sender_id);
    if (!$sender) {
        return ['ok' => false, 'error' => 'کاربر فرستنده پیدا نشد.', 'message' => null];
    }

    $saved = casting_contact_save_message(
        (string) $sender->display_name,
        (string) $sender->user_email,
        $subject,
        $message,
        $sender_id,
        $recipient_id,
        $channel
    );

    return ['ok' => true, 'error' => '', 'message' => $saved];
}

/**
 * @param array<string, mixed> $row
 * @return array{id:string,name:string,email:string,subject:string,message:string,user_id:int,recipient_id:int,channel:string,at:string,read:bool,sender_login:string}
 */
function casting_contact_normalize_row(array $row): array
{
    $sender_login = '';
    $user_id = (int) ($row['user_id'] ?? 0);
    if ($user_id > 0) {
        $sender = get_user_by('id', $user_id);
        if ($sender) {
            $sender_login = (string) $sender->user_login;
        }
    }

    return [
        'id'           => (string) ($row['id'] ?? ''),
        'name'         => (string) ($row['name'] ?? ''),
        'email'        => (string) ($row['email'] ?? ''),
        'subject'      => (string) ($row['subject'] ?? ''),
        'message'      => (string) ($row['message'] ?? ''),
        'user_id'      => $user_id,
        'recipient_id' => (int) ($row['recipient_id'] ?? 0),
        'channel'      => sanitize_key((string) ($row['channel'] ?? '')),
        'at'           => (string) ($row['at'] ?? ''),
        'read'         => !empty($row['read']),
        'sender_login' => $sender_login,
    ];
}

function casting_contact_row_visible_to_recipient(array $row, int $recipient_id, string $channel = ''): bool
{
    $recipient_id = max(0, $recipient_id);
    if ($recipient_id <= 0) {
        return false;
    }

    $row_recipient = (int) ($row['recipient_id'] ?? 0);
    $row_channel = sanitize_key((string) ($row['channel'] ?? ''));

    if ($row_recipient > 0) {
        if ($row_recipient !== $recipient_id) {
            return false;
        }
        if ($channel !== '' && $row_channel !== '' && $row_channel !== $channel) {
            return false;
        }
        return true;
    }

    // پیام‌های قدیمی (قبل از گیرنده مشخص) — فقط برای مدیر سایت
    if ($channel !== '' && $channel !== 'site_admin') {
        return false;
    }
    return casting_contact_recipient_id('site_admin') === $recipient_id;
}

/**
 * @return array<int, array{id:string,name:string,email:string,subject:string,message:string,user_id:int,recipient_id:int,channel:string,at:string,read:bool,sender_login:string}>
 */
function casting_contact_list_for_recipient(int $recipient_id, string $channel = '', int $limit = 200, bool $unread_only = false): array
{
    $recipient_id = max(0, $recipient_id);
    if ($recipient_id <= 0) {
        return [];
    }

    $channel = sanitize_key($channel);
    $out = [];
    foreach (casting_contact_load_inbox() as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (!casting_contact_row_visible_to_recipient($row, $recipient_id, $channel)) {
            continue;
        }
        $normalized = casting_contact_normalize_row($row);
        if ($unread_only && $normalized['read']) {
            continue;
        }
        $out[] = $normalized;
        if (count($out) >= max(1, $limit)) {
            break;
        }
    }
    return $out;
}

/**
 * @return array<int, array{id:string,name:string,email:string,subject:string,message:string,user_id:int,recipient_id:int,channel:string,at:string,read:bool,sender_login:string}>
 */
function casting_contact_list_messages(int $limit = 200, bool $unread_only = false): array
{
    $out = [];
    foreach (casting_contact_load_inbox() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $normalized = casting_contact_normalize_row($row);
        if ($unread_only && $normalized['read']) {
            continue;
        }
        $out[] = $normalized;
        if (count($out) >= max(1, $limit)) {
            break;
        }
    }
    return $out;
}

function casting_contact_unread_count_for_user(int $user_id): int
{
    return count(casting_contact_list_for_recipient($user_id, '', 500, true));
}

function casting_contact_unread_count(): int
{
    return count(casting_contact_list_messages(500, true));
}

function casting_contact_mark_read(string $id): bool
{
    return casting_contact_mark_read_for_recipient($id, 0);
}

function casting_contact_mark_read_for_recipient(string $id, int $recipient_id): bool
{
    $id = trim($id);
    if ($id === '') {
        return false;
    }

    $inbox = casting_contact_load_inbox();
    $found = false;
    foreach ($inbox as &$row) {
        if (!is_array($row) || (string) ($row['id'] ?? '') !== $id) {
            continue;
        }
        if ($recipient_id > 0 && !casting_contact_row_visible_to_recipient($row, $recipient_id)) {
            return false;
        }
        $row['read'] = true;
        $found = true;
        break;
    }
    unset($row);

    if (!$found) {
        return false;
    }
    casting_contact_save_inbox($inbox);
    return true;
}
