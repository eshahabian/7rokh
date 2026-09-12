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
        'brand_admin' => 'مدیر ۷ رخ',
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

function casting_contact_user_can_manage_inbox(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    if (casting_contact_user_is_recipient($user_id)) {
        return true;
    }
    if (function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id)) {
        return true;
    }

    return function_exists('casting_user_is_listed_portal_admin') && casting_user_is_listed_portal_admin($user_id);
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

function casting_contact_keep_sender_login(): string
{
    return 'mn_niky';
}

function casting_contact_row_matches_login(array $row, string $login): bool
{
    $login = strtolower(trim($login));
    if ($login === '') {
        return false;
    }
    $uid = (int) ($row['user_id'] ?? 0);
    if ($uid > 0) {
        $user = get_user_by('id', $uid);
        if ($user && strtolower((string) $user->user_login) === $login) {
            return true;
        }
    }
    $sender_login = strtolower(trim((string) ($row['sender_login'] ?? '')));
    if ($sender_login === $login) {
        return true;
    }
    $user = get_user_by('login', $login);

    return $user && $uid > 0 && (int) $user->ID === $uid;
}

/**
 * یک‌بار همه پیام‌های تماس با ما را پاک می‌کند؛ فقط mn_niky می‌ماند.
 */
function casting_contact_maybe_purge_except_keep_login(): int
{
    if ((string) get_option('casting_contact_purged_keep_mn_niky_v1', '') === '1') {
        return 0;
    }
    $keep = casting_contact_keep_sender_login();
    $inbox = casting_contact_load_inbox();
    $kept = [];
    foreach ($inbox as $row) {
        if (is_array($row) && casting_contact_row_matches_login($row, $keep)) {
            $kept[] = $row;
        }
    }
    $removed = count($inbox) - count($kept);
    casting_contact_save_inbox($kept);
    update_option('casting_contact_purged_keep_mn_niky_v1', '1', false);

    return $removed;
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
 * @return array<string, string>
 */
function casting_contact_available_channels_for_sender(int $user_id): array
{
    $all = casting_contact_channel_labels();
    if ($user_id <= 0) {
        return $all;
    }
    $out = [];
    foreach ($all as $key => $label) {
        if (casting_contact_recipient_id($key) === $user_id) {
            continue;
        }
        $out[$key] = $label;
    }
    return $out;
}

/**
 * @return array{ok:bool,error:string,message:array<string,mixed>|null}
 */
function casting_contact_send_message(
    string $channel,
    string $subject,
    string $message,
    int $user_id = 0,
    string $name = '',
    string $email = ''
): array {
    $user_id = max(0, $user_id);
    $channel = sanitize_key($channel);
    $subject = sanitize_text_field($subject);
    $message = sanitize_textarea_field($message);
    $name = sanitize_text_field($name);
    $email = sanitize_email($email);

    if (!array_key_exists($channel, casting_contact_channel_labels())) {
        return ['ok' => false, 'error' => 'گیرنده را انتخاب کنید.', 'message' => null];
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

    if ($user_id > 0) {
        if ($recipient_id === $user_id) {
            return ['ok' => false, 'error' => 'نمی‌توانید به خودتان پیام بفرستید.', 'message' => null];
        }
        $sender = get_user_by('id', $user_id);
        if (!$sender) {
            return ['ok' => false, 'error' => 'کاربر فرستنده پیدا نشد.', 'message' => null];
        }
        $name = (string) $sender->display_name;
        $email = (string) $sender->user_email;
    } else {
        if (casting_strlen($name) < 2) {
            return ['ok' => false, 'error' => 'نام را وارد کنید.', 'message' => null];
        }
        if (!is_email($email)) {
            return ['ok' => false, 'error' => 'ایمیل معتبر نیست.', 'message' => null];
        }
    }

    $saved = casting_contact_save_message(
        $name,
        $email,
        $subject,
        $message,
        $user_id,
        $recipient_id,
        $channel
    );

    return ['ok' => true, 'error' => '', 'message' => $saved];
}

/**
 * @return array{ok:bool,error:string,message:array<string,mixed>|null}
 */
function casting_contact_send_panel_message(int $sender_id, string $channel, string $subject, string $message): array
{
    return casting_contact_send_message($channel, $subject, $message, $sender_id);
}

/**
 * @param array{name:string,email:string,subject:string,message:string,channel:string,logged_in:bool,user_id:int,action:string,form_id:string} $state
 */
function casting_render_contact_send_form(array $state): void
{
    $channels = casting_contact_available_channels_for_sender((int) $state['user_id']);
    if ($channels === []) {
        return;
    }

    $form_id = (string) ($state['form_id'] ?? 'contact-form');
    $action = (string) ($state['action'] ?? 'contact.php');
    $channel = sanitize_key((string) ($state['channel'] ?? ''));
    if ($channel === '' || !isset($channels[$channel])) {
        $channel = (string) array_key_first($channels);
    }
    $logged_in = !empty($state['logged_in']);
    ?>
    <section class="contact-send-card">
      <form id="<?= casting_e($form_id) ?>" class="form" method="post" action="<?= casting_e($action) ?>">
        <?php wp_nonce_field('casting_contact'); ?>
        <input type="hidden" name="action" value="send">
        <?php if (($state['return_to'] ?? '') === 'home') : ?>
          <input type="hidden" name="return_to" value="home">
        <?php endif; ?>
        <?php if (!$logged_in) : ?>
          <div class="form-grid">
            <div class="field">
              <label for="<?= casting_e($form_id) ?>-name">نام</label>
              <input id="<?= casting_e($form_id) ?>-name" name="name" type="text" required value="<?= casting_e((string) $state['name']) ?>">
            </div>
            <div class="field">
              <label for="<?= casting_e($form_id) ?>-email">ایمیل</label>
              <input id="<?= casting_e($form_id) ?>-email" name="email" type="email" required value="<?= casting_e((string) $state['email']) ?>">
            </div>
          </div>
        <?php endif; ?>
        <div class="field">
          <label for="<?= casting_e($form_id) ?>-channel">می‌خواهم با چه کسی تماس بگیرم؟</label>
          <select id="<?= casting_e($form_id) ?>-channel" name="channel" required>
            <?php foreach ($channels as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $channel === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="<?= casting_e($form_id) ?>-subject">موضوع</label>
          <input id="<?= casting_e($form_id) ?>-subject" name="subject" type="text" required value="<?= casting_e((string) $state['subject']) ?>">
        </div>
        <div class="field">
          <label for="<?= casting_e($form_id) ?>-message">پیام</label>
          <textarea id="<?= casting_e($form_id) ?>-message" name="message" rows="5" required maxlength="3000"><?= casting_e((string) $state['message']) ?></textarea>
        </div>
        <p class="field-hint">این پیام فقط در «تماس با ما» ثبت می‌شود و وارد پیام‌رسان نمی‌شود. بدون عضویت ویژه هم می‌توانید به مدیران پورتال پیام بدهید.</p>
        <button class="btn btn-primary" type="submit">ارسال پیام</button>
      </form>
    </section>
    <?php
}

/**
 * @param array<string, mixed> $row
 * @return array{id:string,name:string,email:string,subject:string,message:string,user_id:int,recipient_id:int,channel:string,at:string,read:bool,sender_login:string,replies:list<array{at:string,admin_id:int,admin_name:string,message:string,via:string}>}
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

    $replies = [];
    $raw_replies = $row['replies'] ?? [];
    if (is_array($raw_replies)) {
        foreach ($raw_replies as $reply) {
            if (!is_array($reply)) {
                continue;
            }
            $text = sanitize_textarea_field((string) ($reply['message'] ?? ''));
            if ($text === '') {
                continue;
            }
            $replies[] = [
                'at'         => (string) ($reply['at'] ?? ''),
                'admin_id'   => (int) ($reply['admin_id'] ?? 0),
                'admin_name' => sanitize_text_field((string) ($reply['admin_name'] ?? '')),
                'message'    => $text,
                'via'        => sanitize_key((string) ($reply['via'] ?? '')),
            ];
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
        'replies'      => $replies,
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

function casting_contact_row_visible_to_manager(array $row, int $user_id): bool
{
    if (!casting_contact_user_can_manage_inbox($user_id)) {
        return false;
    }
    if (function_exists('casting_user_is_listed_portal_admin') && casting_user_is_listed_portal_admin($user_id)) {
        return true;
    }
    if (function_exists('casting_user_is_portal_owner') && casting_user_is_portal_owner($user_id)) {
        return true;
    }

    return casting_contact_row_visible_to_recipient($row, $user_id);
}

/**
 * @return array<int, array{id:string,name:string,email:string,subject:string,message:string,user_id:int,recipient_id:int,channel:string,at:string,read:bool,sender_login:string,replies:list<array{at:string,admin_id:int,admin_name:string,message:string,via:string}>}>
 */
function casting_contact_list_for_manager(int $user_id, int $limit = 200, bool $unread_only = false): array
{
    $user_id = max(0, $user_id);
    if ($user_id <= 0 || !casting_contact_user_can_manage_inbox($user_id)) {
        return [];
    }

    $out = [];
    foreach (casting_contact_load_inbox() as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (!casting_contact_row_visible_to_manager($row, $user_id)) {
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
 * پیام‌های تماس با ما که این کاربر خودش فرستاده (جدا از پیام‌رسان).
 *
 * @return array<int, array{id:string,name:string,email:string,subject:string,message:string,user_id:int,recipient_id:int,channel:string,at:string,read:bool,sender_login:string,replies:list<array{at:string,admin_id:int,admin_name:string,message:string,via:string}>}>
 */
function casting_contact_list_for_sender(int $sender_id, int $limit = 100): array
{
    $sender_id = max(0, $sender_id);
    if ($sender_id <= 0) {
        return [];
    }

    $out = [];
    foreach (casting_contact_load_inbox() as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((int) ($row['user_id'] ?? 0) !== $sender_id) {
            continue;
        }
        $out[] = casting_contact_normalize_row($row);
        if (count($out) >= max(1, $limit)) {
            break;
        }
    }

    return $out;
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
    if (casting_contact_user_can_manage_inbox($user_id)) {
        return count(casting_contact_list_for_manager($user_id, 500, true));
    }

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

function casting_contact_mark_thread_read(string $message_id, int $admin_id): bool
{
    $message_id = trim($message_id);
    if ($message_id === '' || $admin_id <= 0 || !casting_contact_user_can_manage_inbox($admin_id)) {
        return false;
    }
    $inbox = casting_contact_load_inbox();
    $sender_id = 0;
    $sender_email = '';
    foreach ($inbox as $row) {
        if (!is_array($row) || (string) ($row['id'] ?? '') !== $message_id) {
            continue;
        }
        if (!casting_contact_row_visible_to_manager($row, $admin_id)) {
            return false;
        }
        $sender_id = (int) ($row['user_id'] ?? 0);
        $sender_email = strtolower(trim((string) ($row['email'] ?? '')));
        break;
    }
    if ($sender_id <= 0 && $sender_email === '') {
        return casting_contact_mark_read_for_recipient($message_id, $admin_id);
    }
    $found = false;
    foreach ($inbox as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $same = $sender_id > 0
            ? ((int) ($row['user_id'] ?? 0) === $sender_id)
            : (strtolower(trim((string) ($row['email'] ?? ''))) === $sender_email);
        if (!$same || !casting_contact_row_visible_to_manager($row, $admin_id)) {
            continue;
        }
        $row['read'] = true;
        $found = true;
    }
    unset($row);
    if (!$found) {
        return false;
    }
    casting_contact_save_inbox($inbox);

    return true;
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
        if ($recipient_id > 0 && !casting_contact_row_visible_to_manager($row, $recipient_id)) {
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

/**
 * @return array{ok:bool,error:string,via:string}
 */
function casting_contact_reply(string $message_id, int $admin_id, string $reply): array
{
    $message_id = trim($message_id);
    $reply = sanitize_textarea_field(trim($reply));
    $admin_id = max(0, $admin_id);
    if ($message_id === '' || $admin_id <= 0) {
        return ['ok' => false, 'error' => 'درخواست نامعتبر است.', 'via' => ''];
    }
    if (!casting_contact_user_can_manage_inbox($admin_id)) {
        return ['ok' => false, 'error' => 'اجازه پاسخ به پیام‌های تماس با ما را ندارید.', 'via' => ''];
    }
    if (casting_strlen($reply) < 2) {
        return ['ok' => false, 'error' => 'متن پاسخ را بنویسید.', 'via' => ''];
    }
    if (casting_strlen($reply) > 2000) {
        return ['ok' => false, 'error' => 'متن پاسخ حداکثر ۲۰۰۰ کاراکتر باشد.', 'via' => ''];
    }

    $inbox = casting_contact_load_inbox();
    $found = false;
    $via = '';
    foreach ($inbox as &$row) {
        if (!is_array($row) || (string) ($row['id'] ?? '') !== $message_id) {
            continue;
        }
        if (!casting_contact_row_visible_to_manager($row, $admin_id)) {
            return ['ok' => false, 'error' => 'پیام پیدا نشد.', 'via' => ''];
        }

        $sender_id = (int) ($row['user_id'] ?? 0);
        $email = sanitize_email((string) ($row['email'] ?? ''));
        $subject = (string) ($row['subject'] ?? 'تماس با ما');
        $via = 'inbox';

        if ($sender_id <= 0) {
            if (!is_email($email)) {
                return ['ok' => false, 'error' => 'فرستنده عضو پورتال نیست و ایمیل معتبری هم ندارد.', 'via' => ''];
            }
            if (!function_exists('casting_send_mail')) {
                require_once __DIR__ . '/mail.php';
            }
            $body = "پاسخ پشتیبانی ۷ رخ به پیام شما\nموضوع: {$subject}\n\n{$reply}\n";
            $mail = casting_send_mail($email, 'پاسخ: ' . $subject, $body);
            if (empty($mail['ok'])) {
                return ['ok' => false, 'error' => (string) ($mail['error'] ?? 'ارسال ایمیل پاسخ ناموفق بود.'), 'via' => ''];
            }
            $via = 'email';
        }

        $admin = get_user_by('id', $admin_id);
        if (!isset($row['replies']) || !is_array($row['replies'])) {
            $row['replies'] = [];
        }
        $row['replies'][] = [
            'at'         => current_time('mysql'),
            'admin_id'   => $admin_id,
            'admin_name' => $admin ? (string) $admin->display_name : '',
            'message'    => $reply,
            'via'        => $via,
        ];
        $row['read'] = true;
        $found = true;
        break;
    }
    unset($row);

    if (!$found) {
        return ['ok' => false, 'error' => 'پیام پیدا نشد.', 'via' => ''];
    }
    casting_contact_save_inbox($inbox);

    return ['ok' => true, 'error' => '', 'via' => $via];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array{key:string,user_id:int,name:string,login:string,email:string,unread:bool,latest_id:string,reply_via:string,rows:list<array<string,mixed>>}>
 */
function casting_contact_group_threads(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        $uid = (int) ($row['user_id'] ?? 0);
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $key = $uid > 0 ? 'u:' . $uid : ('e:' . ($email !== '' ? $email : (string) ($row['id'] ?? uniqid('g', true))));
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'key'       => $key,
                'user_id'   => $uid,
                'name'      => (string) ($row['name'] ?? ''),
                'login'     => (string) ($row['sender_login'] ?? ''),
                'email'     => (string) ($row['email'] ?? ''),
                'unread'    => false,
                'latest_id' => '',
                'reply_via' => '',
                'rows'      => [],
            ];
        }
        $groups[$key]['rows'][] = $row;
        if (empty($row['read'])) {
            $groups[$key]['unread'] = true;
        }
    }

    $out = [];
    foreach ($groups as $group) {
        usort($group['rows'], static function (array $a, array $b): int {
            return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
        });
        $last = $group['rows'][count($group['rows']) - 1];
        $group['latest_id'] = (string) ($last['id'] ?? '');
        $group['reply_via'] = ((int) ($last['user_id'] ?? 0) > 0)
            ? 'همین صفحه'
            : (((string) ($last['email'] ?? '') !== '') ? 'ایمیل' : '');
        $out[] = $group;
    }

    return $out;
}

/**
 * @param array{at?:string,name?:string,text:string,subject?:string,mine:bool} $bubble
 */
function casting_render_contact_chat_bubble(array $bubble): void
{
    $mine = !empty($bubble['mine']);
    $name = trim((string) ($bubble['name'] ?? ''));
    $subject = trim((string) ($bubble['subject'] ?? ''));
    $at = trim((string) ($bubble['at'] ?? ''));
    $text = (string) ($bubble['text'] ?? '');
    $plain_len = function_exists('casting_strlen') ? casting_strlen($text) : strlen($text);
    $line_count = substr_count($text, "\n") + 1;
    $collapsible = $plain_len > 160 || $line_count > 3;
    ?>
    <article class="contact-chat-bubble<?= $mine ? ' is-mine' : ' is-theirs' ?><?= $collapsible ? ' is-collapsible is-collapsed' : '' ?>">
      <?php if ($name !== '') : ?>
        <header><?= casting_e($name) ?></header>
      <?php endif; ?>
      <?php if ($subject !== '') : ?>
        <p class="contact-chat-subject"><?= casting_e($subject) ?></p>
      <?php endif; ?>
      <div class="contact-chat-body"<?= $collapsible ? ' data-contact-chat-body' : '' ?>>
        <p><?= nl2br(casting_e($text)) ?></p>
      </div>
      <?php if ($collapsible) : ?>
        <button type="button" class="contact-chat-more" data-contact-chat-toggle aria-expanded="false">
          <span data-contact-chat-more-label>بیشتر</span>
        </button>
      <?php endif; ?>
      <?php if ($at !== '') : ?>
        <time><?= casting_e($at) ?></time>
      <?php endif; ?>
    </article>
    <?php
}

/**
 * @param array{key:string,user_id:int,name:string,login:string,email:string,unread:bool,latest_id:string,reply_via:string,rows:list<array<string,mixed>>} $group
 * @param array<string, string> $channels
 */
function casting_render_contact_chat_thread(array $group, array $channels, bool $viewer_is_admin): void
{
    $name = (string) ($group['name'] ?? '');
    $login = (string) ($group['login'] ?? '');
    $email = (string) ($group['email'] ?? '');
    $latest_id = (string) ($group['latest_id'] ?? '');
    $reply_via = (string) ($group['reply_via'] ?? '');
    ?>
    <section class="contact-chat-card<?= !empty($group['unread']) ? ' is-unread' : '' ?>">
      <header class="contact-chat-card-head">
        <div>
          <strong><?= casting_e($name !== '' ? $name : 'کاربر') ?></strong>
          <?php if ($login !== '') : ?>
            <span class="meta">@<?= casting_e($login) ?></span>
          <?php elseif ($email !== '') : ?>
            <a class="meta" href="mailto:<?= casting_e($email) ?>"><?= casting_e($email) ?></a>
          <?php endif; ?>
        </div>
        <?php if (!empty($group['unread'])) : ?><span class="chip chip-active">جدید</span><?php endif; ?>
      </header>
      <div class="contact-chat-thread" role="log">
        <?php foreach ($group['rows'] as $row) :
            $channel_label = $channels[$row['channel'] ?? ''] ?? '';
            $subject = trim((string) ($row['subject'] ?? ''));
            if ($channel_label !== '') {
                $subject = $subject !== '' ? ($channel_label . ' · ' . $subject) : $channel_label;
            }
            casting_render_contact_chat_bubble([
                'mine'    => !$viewer_is_admin,
                'name'    => $viewer_is_admin ? (string) ($row['name'] ?? '') : 'شما',
                'subject' => $subject,
                'text'    => (string) ($row['message'] ?? ''),
                'at'      => (string) ($row['at'] ?? ''),
            ]);
            foreach ($row['replies'] ?? [] as $reply) {
                if (!is_array($reply)) {
                    continue;
                }
                $admin_name = trim((string) ($reply['admin_name'] ?? ''));
                casting_render_contact_chat_bubble([
                    'mine' => $viewer_is_admin,
                    'name' => $viewer_is_admin
                        ? ($admin_name !== '' ? $admin_name : 'شما')
                        : ($admin_name !== '' ? $admin_name : 'مدیر'),
                    'text' => (string) ($reply['message'] ?? ''),
                    'at'   => (string) ($reply['at'] ?? ''),
                ]);
            }
        endforeach; ?>
      </div>
      <?php if ($viewer_is_admin && $latest_id !== '' && $reply_via !== '') : ?>
        <form class="admin-contact-reply-form" method="post" action="contact.php#contact-inbox">
          <?php wp_nonce_field('casting_contact'); ?>
          <input type="hidden" name="action" value="reply">
          <input type="hidden" name="message_id" value="<?= casting_e($latest_id) ?>">
          <label class="visually-hidden" for="contact-reply-<?= casting_e($latest_id) ?>">پاسخ</label>
          <textarea id="contact-reply-<?= casting_e($latest_id) ?>" name="reply" rows="2" required maxlength="2000" placeholder="پیام خود را بنویسید…"></textarea>
          <div class="admin-contact-reply-actions">
            <button class="btn btn-primary btn-sm" type="submit">ارسال</button>
            <?php if (!empty($group['unread'])) : ?>
              <button class="btn btn-ghost btn-sm" type="submit" name="action" value="mark_read" formnovalidate>علامت خوانده</button>
            <?php endif; ?>
          </div>
        </form>
      <?php endif; ?>
    </section>
    <?php
}
