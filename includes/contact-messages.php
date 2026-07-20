<?php
declare(strict_types=1);

const CASTING_CONTACT_INBOX_MAX = 500;

/**
 * @return array{id:string,name:string,email:string,subject:string,message:string,user_id:int,at:string,mail_sent:bool,read:bool}
 */
function casting_contact_save_message(
    string $name,
    string $email,
    string $subject,
    string $message,
    int $user_id = 0,
    bool $mail_sent = false
): array {
    $entry = [
        'id'        => uniqid('ct_', true),
        'name'      => sanitize_text_field($name),
        'email'     => sanitize_email($email),
        'subject'   => sanitize_text_field($subject),
        'message'   => sanitize_textarea_field($message),
        'user_id'   => max(0, $user_id),
        'at'        => current_time('mysql'),
        'mail_sent' => $mail_sent,
        'read'      => false,
    ];

    $inbox = get_option('casting_contact_inbox', []);
    if (!is_array($inbox)) {
        $inbox = [];
    }
    array_unshift($inbox, $entry);
    if (count($inbox) > CASTING_CONTACT_INBOX_MAX) {
        $inbox = array_slice($inbox, 0, CASTING_CONTACT_INBOX_MAX);
    }
    update_option('casting_contact_inbox', $inbox, false);

    return $entry;
}

/**
 * @return array<int, array{id:string,name:string,email:string,subject:string,message:string,user_id:int,at:string,mail_sent:bool,read:bool}>
 */
function casting_contact_list_messages(int $limit = 200, bool $unread_only = false): array
{
    $inbox = get_option('casting_contact_inbox', []);
    if (!is_array($inbox)) {
        return [];
    }

    $out = [];
    foreach ($inbox as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($unread_only && !empty($row['read'])) {
            continue;
        }
        $out[] = [
            'id'        => (string) ($row['id'] ?? ''),
            'name'      => (string) ($row['name'] ?? ''),
            'email'     => (string) ($row['email'] ?? ''),
            'subject'   => (string) ($row['subject'] ?? ''),
            'message'   => (string) ($row['message'] ?? ''),
            'user_id'   => (int) ($row['user_id'] ?? 0),
            'at'        => (string) ($row['at'] ?? ''),
            'mail_sent' => !empty($row['mail_sent']),
            'mail_error'  => (string) ($row['mail_error'] ?? ''),
            'read'      => !empty($row['read']),
        ];
        if (count($out) >= max(1, $limit)) {
            break;
        }
    }
    return $out;
}

function casting_contact_unread_count(): int
{
    return count(casting_contact_list_messages(500, true));
}

function casting_contact_mark_read(string $id): bool
{
    $id = trim($id);
    if ($id === '') {
        return false;
    }
    $inbox = get_option('casting_contact_inbox', []);
    if (!is_array($inbox)) {
        return false;
    }
    $found = false;
    foreach ($inbox as &$row) {
        if (!is_array($row) || (string) ($row['id'] ?? '') !== $id) {
            continue;
        }
        $row['read'] = true;
        $found = true;
        break;
    }
    unset($row);
    if (!$found) {
        return false;
    }
    update_option('casting_contact_inbox', $inbox, false);
    return true;
}

function casting_contact_mark_mail_sent(string $id): bool
{
    $id = trim($id);
    if ($id === '') {
        return false;
    }
    $inbox = get_option('casting_contact_inbox', []);
    if (!is_array($inbox)) {
        return false;
    }
    $found = false;
    foreach ($inbox as &$row) {
        if (!is_array($row) || (string) ($row['id'] ?? '') !== $id) {
            continue;
        }
        $row['mail_sent'] = true;
        $found = true;
        break;
    }
    unset($row);
    if (!$found) {
        return false;
    }
    update_option('casting_contact_inbox', $inbox, false);
    return true;
}

function casting_contact_set_mail_error(string $id, string $error): bool
{
    $id = trim($id);
    $error = sanitize_text_field($error);
    if ($id === '' || $error === '') {
        return false;
    }
    $inbox = get_option('casting_contact_inbox', []);
    if (!is_array($inbox)) {
        return false;
    }
    $found = false;
    foreach ($inbox as &$row) {
        if (!is_array($row) || (string) ($row['id'] ?? '') !== $id) {
            continue;
        }
        $row['mail_error'] = $error;
        $found = true;
        break;
    }
    unset($row);
    if (!$found) {
        return false;
    }
    update_option('casting_contact_inbox', $inbox, false);
    return true;
}
