<?php
declare(strict_types=1);

/**
 * ارسال رویداد ثبت‌نام به n8n (Webhook)
 * اگر CASTING_N8N_REGISTER_WEBHOOK خالی باشد، کاری انجام نمی‌شود.
 */
function casting_notify_n8n_registration(int $user_id, array $extra = []): void
{
    if (!defined('CASTING_N8N_REGISTER_WEBHOOK') || CASTING_N8N_REGISTER_WEBHOOK === '') {
        return;
    }

    $user = get_user_by('id', $user_id);
    if (!$user) {
        return;
    }

    $role = casting_get_user_role($user_id);
    $profile = casting_get_profile($user_id);
    $provinces = casting_province_labels();

    $payload = [
        'event'      => 'user_registered',
        'site'       => casting_brand(),
        'user_id'    => $user_id,
        'name'       => (string) $user->display_name,
        'username'   => (string) $user->user_login,
        'email'      => (string) $user->user_email,
        'role'       => $role,
        'role_label' => casting_role_label($role),
        'mobile'     => (string) ($profile['mobile'] ?? ''),
        'city'       => (string) ($profile['city'] ?? ''),
        'province'   => $provinces[$profile['province'] ?? ''] ?? (string) ($profile['province'] ?? ''),
        'registered_at' => (string) get_user_meta($user_id, 'casting_registered_at', true),
        'profile_url' => casting_url('member.php?id=' . $user_id),
    ];

    if ($extra !== []) {
        $payload = array_merge($payload, $extra);
    }

    $headers = [
        'Content-Type' => 'application/json; charset=utf-8',
    ];
    if (defined('CASTING_N8N_WEBHOOK_SECRET') && CASTING_N8N_WEBHOOK_SECRET !== '') {
        $headers['X-Webhook-Secret'] = CASTING_N8N_WEBHOOK_SECRET;
    }

    wp_remote_post(CASTING_N8N_REGISTER_WEBHOOK, [
        'blocking' => false,
        'timeout'  => 5,
        'headers'  => $headers,
        'body'     => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
}
