<?php
declare(strict_types=1);

function casting_mail_is_smtp_enabled(): bool
{
    return defined('CASTING_SMTP_HOST')
        && is_string(CASTING_SMTP_HOST)
        && CASTING_SMTP_HOST !== ''
        && defined('CASTING_SMTP_USER')
        && is_string(CASTING_SMTP_USER)
        && CASTING_SMTP_USER !== '';
}

function casting_mail_is_smtp_ready(): bool
{
    if (!casting_mail_is_smtp_enabled()) {
        return false;
    }
    $pass = defined('CASTING_SMTP_PASS') ? (string) CASTING_SMTP_PASS : '';
    return $pass !== '';
}

function casting_mail_setup_hint(): string
{
    if (!casting_mail_is_smtp_enabled()) {
        return ' SMTP را در config.php تنظیم کنید.';
    }
    if (!casting_mail_is_smtp_ready()) {
        return ' فایل config.local.php بسازید (از config.local.php.example) و CASTING_SMTP_PASS را با رمز info@7rokh.ir پر کنید.';
    }
    return '';
}

function casting_mail_from_address(): string
{
    if (defined('CASTING_MAIL_FROM') && is_string(CASTING_MAIL_FROM) && is_email(CASTING_MAIL_FROM)) {
        return CASTING_MAIL_FROM;
    }
    if (casting_mail_is_smtp_enabled()) {
        return CASTING_SMTP_USER;
    }
    $admin = (string) get_option('admin_email');
    return is_email($admin) ? $admin : 'wordpress@' . (string) wp_parse_url(home_url(), PHP_URL_HOST);
}

function casting_mail_from_name(): string
{
    if (defined('CASTING_MAIL_FROM_NAME') && is_string(CASTING_MAIL_FROM_NAME) && CASTING_MAIL_FROM_NAME !== '') {
        return CASTING_MAIL_FROM_NAME;
    }
    return casting_brand();
}

/**
 * @return list<string>
 */
function casting_contact_notify_emails(): array
{
    $recipients = [];
    if (defined('CASTING_CONTACT_NOTIFY_EMAILS') && is_array(CASTING_CONTACT_NOTIFY_EMAILS)) {
        foreach (CASTING_CONTACT_NOTIFY_EMAILS as $addr) {
            if (is_string($addr) && is_email($addr)) {
                $recipients[] = $addr;
            }
        }
    }
    if ($recipients !== []) {
        return array_values(array_unique($recipients));
    }
    $admin = (string) get_option('admin_email');
    return is_email($admin) ? [$admin] : [];
}

function casting_init_phpmailer($phpmailer): void
{
    if (!casting_mail_is_smtp_enabled()) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = CASTING_SMTP_HOST;
    $phpmailer->Port = defined('CASTING_SMTP_PORT') ? (int) CASTING_SMTP_PORT : 587;
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = CASTING_SMTP_USER;
    $phpmailer->Password = defined('CASTING_SMTP_PASS') ? (string) CASTING_SMTP_PASS : '';

    $secure = defined('CASTING_SMTP_SECURE') ? strtolower((string) CASTING_SMTP_SECURE) : 'tls';
    if ($secure === 'ssl' || $secure === 'tls') {
        $phpmailer->SMTPSecure = $secure;
    } else {
        $phpmailer->SMTPSecure = '';
        $phpmailer->SMTPAutoTLS = false;
    }

    $phpmailer->CharSet = 'UTF-8';
    $phpmailer->setFrom(casting_mail_from_address(), casting_mail_from_name(), false);
}

function casting_filter_mail_from(string $from): string
{
    return casting_mail_from_address();
}

function casting_filter_mail_from_name(string $name): string
{
    return casting_mail_from_name();
}

add_action('phpmailer_init', 'casting_init_phpmailer');
add_filter('wp_mail_from', 'casting_filter_mail_from');
add_filter('wp_mail_from_name', 'casting_filter_mail_from_name');

/**
 * @param list<string> $headers
 * @return array{ok:bool,error:string}
 */
function casting_send_mail(string $to, string $subject, string $body, array $headers = []): array
{
    if (!is_email($to)) {
        return ['ok' => false, 'error' => 'آدرس گیرنده معتبر نیست.'];
    }

    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers = array_values(array_unique($headers));

    $last_error = '';
    $capture = static function ($wp_error) use (&$last_error): void {
        if ($wp_error instanceof WP_Error) {
            $last_error = $wp_error->get_error_message();
        }
    };
    add_action('wp_mail_failed', $capture);

    $sent = wp_mail($to, $subject, $body, $headers);

    remove_action('wp_mail_failed', $capture);

    if ($sent) {
        return ['ok' => true, 'error' => ''];
    }

    if ($last_error === '') {
        if (!casting_mail_is_smtp_ready()) {
            $last_error = trim(casting_mail_setup_hint()) ?: 'SMTP آماده نیست.';
        } else {
            $last_error = 'ارسال ایمیل ناموفق بود.';
        }
    }

    return ['ok' => false, 'error' => $last_error];
}

/**
 * @return array{ok:bool,error:string,sent_count:int}
 */
function casting_send_contact_message(string $name, string $email, string $subject, string $message): array
{
    $recipients = casting_contact_notify_emails();
    if ($recipients === []) {
        return ['ok' => false, 'error' => 'هیچ ایمیل گیرنده‌ای در config.php تعریف نشده است.', 'sent_count' => 0];
    }

    $brand = casting_brand();
    $mail_subject = sprintf('[%s] تماس: %s', $brand, $subject);
    $body = "نام: {$name}\nایمیل: {$email}\nموضوع: {$subject}\n\n{$message}\n";
    $headers = ['Reply-To: ' . $email];

    $sent_count = 0;
    $last_error = '';
    foreach ($recipients as $to) {
        $result = casting_send_mail($to, $mail_subject, $body, $headers);
        if ($result['ok']) {
            $sent_count++;
            continue;
        }
        $last_error = $result['error'];
    }

    if ($sent_count > 0) {
        return ['ok' => true, 'error' => '', 'sent_count' => $sent_count];
    }

    return ['ok' => false, 'error' => $last_error !== '' ? $last_error : 'ارسال ایمیل ناموفق بود.', 'sent_count' => 0];
}
