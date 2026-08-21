<?php
declare(strict_types=1);

/**
 * محدودیت تعداد درخواست بر اساس IP (با transient وردپرس).
 *
 * @return array{max: int, window: int, progressive?: bool}
 */
function casting_rate_limit_config(string $action): array
{
    $defaults = [
        'login'           => ['max' => 3, 'window' => 3600, 'progressive' => true],
        'login_otp'       => ['max' => 3, 'window' => 3600, 'progressive' => true],
        'register'        => ['max' => 3, 'window' => 3600, 'progressive' => true],
        'otp_send'        => ['max' => 3, 'window' => 3600, 'progressive' => true],
        'forgot_password' => ['max' => 5, 'window' => 3600],
        'contact_send'    => ['max' => 5, 'window' => 3600],
        'change_phone'    => ['max' => 3, 'window' => 3600, 'progressive' => true],
        'change_email'    => ['max' => 5, 'window' => 3600],
        'register_upload' => ['max' => 40, 'window' => 3600],
    ];

    return $defaults[$action] ?? ['max' => 10, 'window' => 900];
}

/**
 * زمان‌های قفل تصاعدی (ثانیه): ۲ دقیقه → ۱۰ دقیقه → ۲۰ دقیقه → ۱ ساعت
 *
 * @return list<int>
 */
function casting_rate_limit_lock_durations(): array
{
    return [2 * 60, 10 * 60, 20 * 60, 60 * 60];
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

function casting_rate_limit_is_progressive(string $action): bool
{
    $config = casting_rate_limit_config($action);

    return !empty($config['progressive']);
}

/**
 * متن زمان باقی‌مانده به فارسی
 */
function casting_rate_limit_format_wait(int $seconds): string
{
    $seconds = max(1, $seconds);
    if ($seconds >= 3600) {
        $hours = (int) ceil($seconds / 3600);

        return $hours === 1 ? '۱ ساعت' : $hours . ' ساعت';
    }
    $minutes = max(1, (int) ceil($seconds / 60));

    return $minutes . ' دقیقه';
}

/**
 * اگر قفل فعال باشد پیام خطا برمی‌گرداند؛ وگرنه null
 */
function casting_rate_limit_check(string $action): ?string
{
    $key = casting_rate_limit_transient_key($action);
    $data = get_transient($key);
    if (!is_array($data)) {
        return null;
    }

    $now = time();

    if (casting_rate_limit_is_progressive($action)) {
        $locked_until = (int) ($data['locked_until'] ?? 0);
        if ($locked_until > $now) {
            $wait = casting_rate_limit_format_wait($locked_until - $now);

            return 'به‌خاطر تلاش‌های ناموفق، فعلاً امکان ادامه نیست. لطفاً ' . $wait . ' دیگر دوباره تلاش کنید.';
        }

        return null;
    }

    $config = casting_rate_limit_config($action);
    $count = (int) ($data['count'] ?? 0);
    $expires = (int) ($data['expires'] ?? 0);
    if ($count < $config['max']) {
        return null;
    }

    $retry = max(60, $expires - $now);

    return sprintf(
        'تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً %s دیگر دوباره تلاش کنید.',
        casting_rate_limit_format_wait($retry)
    );
}

/**
 * ثبت یک تلاش ناموفق؛ در حالت تصاعدی بعد از max شکست، قفل بعدی اعمال می‌شود.
 */
function casting_rate_limit_hit(string $action): void
{
    $config = casting_rate_limit_config($action);
    $key = casting_rate_limit_transient_key($action);
    $data = get_transient($key);
    $now = time();

    if (casting_rate_limit_is_progressive($action)) {
        if (!is_array($data)) {
            $data = [
                'failures'     => 0,
                'strikes'      => 0,
                'locked_until' => 0,
            ];
        }

        $locked_until = (int) ($data['locked_until'] ?? 0);
        if ($locked_until > $now) {
            // هنوز قفل است؛ TTL را تمدید نکن ولی داده را نگه دار
            $ttl = max(1, $locked_until - $now);
            set_transient($key, $data, $ttl + (30 * 24 * 3600));

            return;
        }

        $failures = (int) ($data['failures'] ?? 0) + 1;
        $strikes = (int) ($data['strikes'] ?? 0);
        $data['failures'] = $failures;

        $max = max(1, (int) $config['max']);
        if ($failures >= $max) {
            $durations = casting_rate_limit_lock_durations();
            $level = min($strikes, count($durations) - 1);
            $lock_for = $durations[$level];
            $data['failures'] = 0;
            $data['strikes'] = min($strikes + 1, count($durations));
            $data['locked_until'] = $now + $lock_for;
            // نگه داشتن سابقه strikes تا بعد از قفل بعدی هم باقی بماند
            set_transient($key, $data, $lock_for + (30 * 24 * 3600));

            return;
        }

        set_transient($key, $data, (int) $config['window'] + (30 * 24 * 3600));

        return;
    }

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
    foreach (['login', 'login_otp', 'register', 'otp_send', 'forgot_password', 'contact_send', 'change_phone', 'change_email'] as $action) {
        casting_rate_limit_clear($action);
    }
}
