<?php
declare(strict_types=1);

/** Send an alert after a new portal message or submission has been saved. */
function casting_admin_message_notify_sms(int $sender_id, int $recipient_id, string $channel = 'chat'): void
{
    if (defined('CASTING_ADMIN_MESSAGE_SMS_ENABLED') && !CASTING_ADMIN_MESSAGE_SMS_ENABLED) {
        return;
    }

    // Deliver before the response ends. On LiteSpeed, calling litespeed_finish_request()
    // or fastcgi_finish_request() from a shutdown function stops the process first,
    // so the SMS never leaves.
    ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(60);
    }
    casting_admin_message_deliver_sms($sender_id, $recipient_id, $channel);
}

function casting_admin_message_deliver_sms(int $sender_id, int $recipient_id, string $channel): void
{
    try {
        if ($channel === 'chat' && ($sender_id <= 0 || $recipient_id <= 0 || $sender_id === $recipient_id
            || !get_user_by('id', $sender_id) || !get_user_by('id', $recipient_id))) {
            return;
        }

        $manager_ids = [];
        foreach (['eshahabian', 'ardavan'] as $login) {
            $manager = get_user_by('login', $login);
            if ($manager && (int) $manager->ID > 0) {
                $manager_ids[(int) $manager->ID] = true;
            }
        }
        $targets = $manager_ids;
        unset($targets[$sender_id]);
        $alert_recipient = $channel === 'chat' || isset($manager_ids[$sender_id]);
        if ($alert_recipient && $recipient_id > 0 && $recipient_id !== $sender_id
            && get_user_by('id', $recipient_id)) {
            $targets[$recipient_id] = true;
        }
        if (!$targets) {
            return;
        }
        if (!function_exists('casting_sms_send_text')) {
            require_once __DIR__ . '/sms.php';
        }
        if (!casting_sms_is_configured()) {
            return;
        }
        if (!function_exists('casting_normalize_mobile')) {
            require_once __DIR__ . '/profile.php';
        }

        $notices = [
            'contact' => 'پاسخ جدیدی در بخش تماس با ما ثبت شد.',
            'contact_inbox' => 'پیام جدیدی در بخش تماس با ما ثبت شد.',
            'contest' => 'ویدیوی جدیدی برای مسابقه ثبت شد.',
            'news' => 'خبر جدیدی برای بررسی و انتشار ارسال شد.',
            'chat' => 'پیام جدیدی در پیام‌رسان پورتال ثبت شد.',
        ];
        $notice = $notices[$channel] ?? $notices['chat'];
        $body = "۷ رخ\n" . $notice . "\nبرای بررسی وارد حساب خود شوید.";
        $sent_mobiles = [];
        $mobile_keys = ['casting_mobile', 'casting_mobile2'];
        foreach (array_keys($targets) as $user_id) {
            foreach ($mobile_keys as $meta_key) {
                $mobile = casting_normalize_mobile((string) get_user_meta($user_id, $meta_key, true));
                if (!preg_match('/^09\d{9}$/', $mobile) || isset($sent_mobiles[$mobile])) {
                    continue;
                }
                $sent_mobiles[$mobile] = true;
                try {
                    $result = casting_sms_send_text($mobile, $body, 8);
                    if (empty($result['ok'])) {
                        error_log('[casting-admin-message-sms] Gateway request failed (' . $channel . ', user ' . $user_id
                            . ', code ' . (int) ($result['code'] ?? -1) . ', HTTP ' . (int) ($result['http'] ?? 0) . ')');
                    } else {
                        $ref = (string) ($result['ref_id'] ?? '');
                        if (preg_match('/^[a-zA-Z0-9-]{1,64}$/D', $ref)) {
                            error_log('[casting-admin-message-sms] Gateway accepted (' . $channel . ', user ' . $user_id . ', ref ' . $ref . ')');
                        }
                    }
                } catch (Throwable $e) {
                    error_log('[casting-admin-message-sms] Gateway request failed for user ' . $user_id);
                }
            }
        }
        if ($sent_mobiles === []) {
            error_log('[casting-admin-message-sms] No valid mobile for channel ' . $channel);
        }
    } catch (Throwable $e) {
        error_log('[casting-admin-message-sms] Notification failed for channel ' . $channel);
    }
}
