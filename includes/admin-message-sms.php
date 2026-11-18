<?php
declare(strict_types=1);

/** Notify a member only about a newly saved message from the two site managers. */
function casting_admin_message_notify_sms(int $sender_id, int $recipient_id, string $channel = 'chat'): void
{
    try {
        if (defined('CASTING_ADMIN_MESSAGE_SMS_ENABLED') && !CASTING_ADMIN_MESSAGE_SMS_ENABLED) {
            return;
        }
        if ($sender_id <= 0 || $recipient_id <= 0 || $sender_id === $recipient_id) {
            return;
        }
        $sender = get_user_by('id', $sender_id);
        if (!$sender || !in_array(strtolower((string) $sender->user_login), ['eshahabian', 'ardavan'], true)) {
            return;
        }
        if (!get_user_by('id', $recipient_id)) {
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
        $mobile = casting_normalize_mobile((string) get_user_meta($recipient_id, 'casting_mobile', true));
        if (!preg_match('/^09\d{9}$/', $mobile)) {
            return;
        }
        $origin = rtrim((string) CASTING_MAIN_SITE_URL, '/');
        $body = $channel === 'contact'
            ? "۷ رخ\nمدیریت سایت به پیام شما در تماس با ما پاسخ داده است.\nبرای مشاهده پاسخ وارد حساب خود شوید:\n" . $origin . '/casting-portal/contact.php'
            : "۷ رخ\nشما یک پیام جدید از مدیریت سایت دارید.\nبرای مشاهده پیام وارد حساب خود شوید:\n" . $origin . '/casting-portal/chat.php';
        $result = casting_sms_send_text($mobile, $body);
        if (empty($result['ok'])) {
            // Do not log message contents, phone numbers or gateway credentials.
            error_log('[casting-admin-message-sms] Delivery failed for user ' . $recipient_id);
        }
    } catch (Throwable $e) {
        // An SMS failure must never turn a saved message into a failed send.
        error_log('[casting-admin-message-sms] Notification failed for user ' . $recipient_id);
    }
}
