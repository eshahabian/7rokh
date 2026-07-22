<?php
declare(strict_types=1);

/**
 * محدودیت تعداد درخواست بر اساس IP (با transient وردپرس).
 *
 * @return array{max: int, window: int}
 */
function casting_rate_limit_config(string $action): array
{
    $defaults = [
        'login'           => ['max' => 10, 'window' => 900],
        'register'        => ['max' => 3, 'window' => 3600],
        'forgot_password' => ['max' => 5, 'window' => 3600],
        'contact_send'    => ['max' => 5, 'window' => 3600],
    ];

    return $defaults[$action] ?? ['max' => 10, 'window' => 900];
}

function casting_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
}

function casting_rate_limit_transient_key(string $action): string
{
    $hash = md5(casting_client_ip() . '|' . $action);

    return 'casting_rl_' . sanitize_key($action) . '_' . $hash;
}

function casting_rate_limit_check(string $action): ?string
{
    $config = casting_rate_limit_config($action);
    $key = casting_rate_limit_transient_key($action);
    $data = get_transient($key);
    if (!is_array($data)) {
        return null;
    }

    $count = (int) ($data['count'] ?? 0);
    $expires = (int) ($data['expires'] ?? 0);
    if ($count < $config['max']) {
        return null;
    }

    $retry = max(60, $expires - time());
    $minutes = max(1, (int) ceil($retry / 60));

    return sprintf('تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً %d دقیقه دیگر دوباره تلاش کنید.', $minutes);
}

function casting_rate_limit_hit(string $action): void
{
    $config = casting_rate_limit_config($action);
    $key = casting_rate_limit_transient_key($action);
    $data = get_transient($key);
    $now = time();

    if (!is_array($data)) {
        $data = [
            'count'   => 0,
            'expires' => $now + $config['window'],
        ];
    }

    $data['count'] = (int) ($data['count'] ?? 0) + 1;
    if (empty($data['expires']) || (int) $data['expires'] <= $now) {
        $data['expires'] = $now + $config['window'];
    }

    $ttl = max(1, (int) $data['expires'] - $now);
    set_transient($key, $data, $ttl);
}

function casting_rate_limit_clear(string $action): void
{
    delete_transient(casting_rate_limit_transient_key($action));
}

function casting_rate_limit_clear_all(): void
{
    foreach (['login', 'register', 'forgot_password', 'contact_send'] as $action) {
        casting_rate_limit_clear($action);
    }
}
