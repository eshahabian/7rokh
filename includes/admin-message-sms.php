<?php
declare(strict_types=1);

/** Send an alert after a new portal message or submission has been saved. */
function casting_admin_message_notify_sms(int $sender_id, int $recipient_id, string $channel = 'chat'): void
{
    try {
        if (defined('CASTING_ADMIN_MESSAGE_SMS_ENABLED') && !CASTING_ADMIN_MESSAGE_SMS_ENABLED) {
            return;
        }
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
        if (isset($manager_ids[$sender_id]) && $recipient_id > 0 && $recipient_id !== $sender_id
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

        $notice = match ($channel) {
            'contact' => 'پاسخ جدیدی در بخش تماس با ما ثبت شد.',
            'contact_inbox' => 'پیام جدیدی در بخش تماس با ما ثبت شد.',
            'contest' => 'ویدیوی جدیدی برای مسابقه ثبت شد.',
            'news' => 'خبر جدیدی برای بررسی و انتشار ارسال شد.',
            default => 'پیام جدیدی در پیام‌رسان پورتال ثبت شد.',
        };
        $body = "۷ رخ\n" . $notice . "\nبرای بررسی وارد حساب خود شوید.";
        $sent_mobiles = [];
        $mobile_keys = $channel === 'contest' ? ['casting_mobile', 'casting_mobile2'] : ['casting_mobile'];
        foreach (array_keys($targets) as $user_id) {
            foreach ($mobile_keys as $meta_key) {
                $mobile = casting_normalize_mobile((string) get_user_meta($user_id, $meta_key, true));
                if (!preg_match('/^09\d{9}$/', $mobile) || isset($sent_mobiles[$mobile])) {
                    continue;
                }
                $sent_mobiles[$mobile] = true;
                try {
                    $result = casting_sms_send_text($mobile, $body);
                    if (empty($result['ok'])) {
                        error_log('[casting-admin-message-sms] Gateway request failed for user ' . $user_id . ' (code ' . (int) ($result['code'] ?? -1) . ')');
                    }
                } catch (Throwable $e) {
                    error_log('[casting-admin-message-sms] Gateway request failed for user ' . $user_id);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[casting-admin-message-sms] Notification failed for channel ' . $channel);
    }
}
