<?php
declare(strict_types=1);

require_once __DIR__ . '/contact-messages.php';

function casting_mail_is_smtp_enabled(): bool
{
    return defined('CASTING_SMTP_HOST')
        && is_string(CASTING_SMTP_HOST)
        && CASTING_SMTP_HOST !== ''
        && defined('CASTING_SMTP_USER')
        && is_string(CASTING_SMTP_USER)
        && CASTING_SMTP_USER !== '';
}

function casting_local_config_path(): string
{
    return dirname(__DIR__) . '/config.local.php';
}

function casting_read_local_smtp_pass(): string
{
    $path = casting_local_config_path();
    if (!is_readable($path)) {
        return '';
    }

    $src = file_get_contents($path);
    if (!is_string($src) || $src === '') {
        return '';
    }

    if (!preg_match_all("/define\s*\(\s*['\"]CASTING_SMTP_PASS['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $src, $matches)) {
        return '';
    }
    foreach (array_reverse($matches[1]) as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

/**
 * منبع رمز فعلی: option (پنل) | constant (config.local لودشده) | file | empty
 */
function casting_smtp_password_source(): string
{
    if (function_exists('get_option')) {
        $from_opt = trim((string) get_option('casting_smtp_pass', ''));
        if ($from_opt !== '') {
            return 'option';
        }
    }
    $pass = defined('CASTING_SMTP_PASS') ? trim((string) CASTING_SMTP_PASS) : '';
    if ($pass !== '') {
        return 'constant';
    }
    if (casting_read_local_smtp_pass() !== '') {
        return 'file';
    }

    return '';
}

function casting_smtp_password(): string
{
    // اولویت با رمز ذخیره‌شده از پنل (دیتابیس) — اگر کهنه باشد، از پنل «پاک کردن» بزنید تا config.local خوانده شود
    if (function_exists('get_option')) {
        $from_opt = trim((string) get_option('casting_smtp_pass', ''));
        if ($from_opt !== '') {
            return $from_opt;
        }
    }
    $pass = defined('CASTING_SMTP_PASS') ? trim((string) CASTING_SMTP_PASS) : '';
    if ($pass !== '') {
        return $pass;
    }

    return casting_read_local_smtp_pass();
}

function casting_smtp_escape_php_string(string $value): string
{
    return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_smtp_clear_stored_password(): array
{
    if (!function_exists('delete_option')) {
        return ['ok' => false, 'error' => 'پاک کردن رمز در وردپرس ممکن نیست.'];
    }
    delete_option('casting_smtp_pass');

    return ['ok' => true, 'error' => ''];
}

function casting_smtp_host(): string
{
    if (!empty($GLOBALS['casting_smtp_host_override']) && is_string($GLOBALS['casting_smtp_host_override'])) {
        return strtolower(trim($GLOBALS['casting_smtp_host_override']));
    }
    if (function_exists('get_option')) {
        $from_opt = trim((string) get_option('casting_smtp_host', ''));
        if ($from_opt !== '') {
            return $from_opt;
        }
    }
    if (defined('CASTING_SMTP_HOST') && is_string(CASTING_SMTP_HOST) && CASTING_SMTP_HOST !== '') {
        return CASTING_SMTP_HOST;
    }

    return 'localhost';
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_smtp_store_host(string $host): array
{
    $host = strtolower(trim($host));
    $allowed = ['localhost', '127.0.0.1', 'mail.7rokh.com', 'mail.7rokh.ir', '7rokh.com'];
    if (!in_array($host, $allowed, true)) {
        return ['ok' => false, 'error' => 'میزبان SMTP مجاز نیست.'];
    }
    if (!function_exists('update_option')) {
        return ['ok' => false, 'error' => 'ذخیره در وردپرس ممکن نیست.'];
    }
    update_option('casting_smtp_host', $host, false);

    return ['ok' => true, 'error' => ''];
}

/**
 * ذخیره رمز SMTP در دیتابیس (و در صورت امکان در config.local.php)
 *
 * @return array{ok:bool,error:string}
 */
function casting_smtp_store_password(string $pass): array
{
    $pass = trim($pass);
    if ($pass === '') {
        return ['ok' => false, 'error' => 'رمز SMTP خالی است.'];
    }
    if (!function_exists('update_option')) {
        return ['ok' => false, 'error' => 'ذخیره در وردپرس ممکن نیست.'];
    }

    update_option('casting_smtp_pass', $pass, false);

    $path = casting_local_config_path();
    if (is_string($path) && $path !== '' && (is_writable($path) || (!file_exists($path) && is_writable(dirname($path))))) {
        $quoted = casting_smtp_escape_php_string($pass);
        $line = "define('CASTING_SMTP_PASS', '" . $quoted . "');\n";
        $src = is_readable($path) ? (string) file_get_contents($path) : '';
        if ($src === '') {
            $src = "<?php\n" . $line;
        } elseif (preg_match("/define\s*\(\s*['\"]CASTING_SMTP_PASS['\"]\s*,\s*['\"][^'\"]*['\"]\s*\)\s*;/", $src)) {
            $src = preg_replace(
                "/define\s*\(\s*['\"]CASTING_SMTP_PASS['\"]\s*,\s*['\"][^'\"]*['\"]\s*\)\s*;/",
                "define('CASTING_SMTP_PASS', '" . $quoted . "');",
                $src,
                1
            ) ?? $src;
        } else {
            if (!str_contains($src, '<?php')) {
                $src = "<?php\n" . $src;
            }
            $src = rtrim($src) . "\n" . $line;
        }
        @file_put_contents($path, $src, LOCK_EX);
    }

    return ['ok' => true, 'error' => ''];
}

function casting_mail_is_smtp_ready(): bool
{
    if (!casting_mail_is_smtp_enabled()) {
        return false;
    }

    return casting_smtp_password() !== '';
}

function casting_mail_setup_hint(): string
{
    if (!casting_mail_is_smtp_enabled()) {
        return ' SMTP را در config.php تنظیم کنید.';
    }
    if (!casting_mail_is_smtp_ready()) {
        $local = is_readable(casting_local_config_path());
        if ($local) {
            return ' رمز SMTP ذخیره نشده. در پنل «تست ایمیل SMTP» رمز noreply@7rokh.com را وارد و ذخیره کنید.';
        }

        return ' فایل public_html/casting-portal/config.local.php روی سرور نیست — از config.local.php.example کپی کنید و CASTING_SMTP_PASS را با رمز noreply@7rokh.com پر کنید.';
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

/**
 * @return array{smtp_ready:bool,local_config:bool,pass_loaded:bool,pass_source:string,pass_len:int,host:string,port:int,user:string,from:string,secure:string}
 */
function casting_mail_status(): array
{
    $pass = casting_smtp_password();

    return [
        'smtp_ready'   => casting_mail_is_smtp_ready(),
        'local_config' => is_readable(casting_local_config_path()),
        'pass_loaded'  => $pass !== '',
        'pass_source'  => casting_smtp_password_source(),
        'pass_len'     => casting_strlen($pass),
        'host'         => casting_smtp_host(),
        'port'         => casting_smtp_port(),
        'user'         => casting_smtp_username(),
        'from'         => casting_mail_from_address(),
        'secure'       => casting_smtp_secure(),
        'auth'         => (function_exists('get_option') ? (string) get_option('casting_smtp_auth', '1') : '1') !== '0',
    ];
}

function casting_smtp_port(): int
{
    if (isset($GLOBALS['casting_smtp_port_override']) && is_numeric($GLOBALS['casting_smtp_port_override'])) {
        return (int) $GLOBALS['casting_smtp_port_override'];
    }
    if (function_exists('get_option')) {
        $from_opt = (int) get_option('casting_smtp_port', 0);
        if ($from_opt > 0) {
            return $from_opt;
        }
    }
    if (defined('CASTING_SMTP_PORT')) {
        return (int) CASTING_SMTP_PORT;
    }

    return 465;
}

function casting_smtp_secure(): string
{
    if (isset($GLOBALS['casting_smtp_secure_override']) && is_string($GLOBALS['casting_smtp_secure_override'])) {
        return strtolower(trim($GLOBALS['casting_smtp_secure_override']));
    }
    if (function_exists('get_option')) {
        $from_opt = strtolower(trim((string) get_option('casting_smtp_secure', '')));
        if ($from_opt !== '') {
            return $from_opt;
        }
    }
    if (defined('CASTING_SMTP_SECURE')) {
        return strtolower((string) CASTING_SMTP_SECURE);
    }

    return 'ssl';
}

function casting_smtp_username(): string
{
    if (function_exists('get_option')) {
        $override = trim((string) get_option('casting_smtp_user_override', ''));
        if ($override !== '') {
            return $override;
        }
    }
    if (defined('CASTING_SMTP_USER') && is_string(CASTING_SMTP_USER) && CASTING_SMTP_USER !== '') {
        return CASTING_SMTP_USER;
    }

    return 'noreply@7rokh.com';
}

/**
 * @return list<array{host:string,port:int,secure:string,auth:bool,label:string}>
 */
function casting_smtp_probe_profiles(): array
{
    $current = [
        'host'   => casting_smtp_host(),
        'port'   => casting_smtp_port(),
        'secure' => casting_smtp_secure(),
        'auth'   => true,
        'label'  => 'current',
    ];
    $candidates = [
        $current,
        ['host' => 'localhost', 'port' => 587, 'secure' => 'tls', 'auth' => true, 'label' => 'localhost:587/tls'],
        ['host' => '127.0.0.1', 'port' => 587, 'secure' => 'tls', 'auth' => true, 'label' => '127.0.0.1:587/tls'],
        ['host' => 'localhost', 'port' => 465, 'secure' => 'ssl', 'auth' => true, 'label' => 'localhost:465/ssl'],
        ['host' => '127.0.0.1', 'port' => 465, 'secure' => 'ssl', 'auth' => true, 'label' => '127.0.0.1:465/ssl'],
        ['host' => 'localhost', 'port' => 25, 'secure' => 'none', 'auth' => true, 'label' => 'localhost:25/auth'],
        ['host' => 'localhost', 'port' => 25, 'secure' => 'none', 'auth' => false, 'label' => 'localhost:25/no-auth'],
        ['host' => '127.0.0.1', 'port' => 25, 'secure' => 'none', 'auth' => false, 'label' => '127.0.0.1:25/no-auth'],
    ];

    $seen = [];
    $out = [];
    foreach ($candidates as $row) {
        $key = $row['host'] . '|' . $row['port'] . '|' . $row['secure'] . '|' . ($row['auth'] ? '1' : '0');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $row;
    }

    return $out;
}

/**
 * @param array{host:string,port:int,secure:string,auth?:bool} $profile
 */
function casting_smtp_apply_profile(array $profile): void
{
    $GLOBALS['casting_smtp_host_override'] = (string) $profile['host'];
    $GLOBALS['casting_smtp_port_override'] = (int) $profile['port'];
    $GLOBALS['casting_smtp_secure_override'] = (string) $profile['secure'];
    $GLOBALS['casting_smtp_auth_override'] = array_key_exists('auth', $profile) ? (bool) $profile['auth'] : true;
}

function casting_smtp_clear_profile_overrides(): void
{
    unset(
        $GLOBALS['casting_smtp_host_override'],
        $GLOBALS['casting_smtp_port_override'],
        $GLOBALS['casting_smtp_secure_override'],
        $GLOBALS['casting_smtp_auth_override']
    );
}

/**
 * @param array{host:string,port:int,secure:string,auth?:bool} $profile
 */
function casting_smtp_persist_profile(array $profile): void
{
    if (!function_exists('update_option')) {
        return;
    }
    update_option('casting_smtp_host', (string) $profile['host'], false);
    update_option('casting_smtp_port', (int) $profile['port'], false);
    update_option('casting_smtp_secure', (string) $profile['secure'], false);
    update_option('casting_smtp_auth', array_key_exists('auth', $profile) ? ((bool) $profile['auth'] ? '1' : '0') : '1', false);
}

function casting_init_phpmailer($phpmailer): void
{
    if (!casting_mail_is_smtp_ready()) {
        return;
    }

    $port = casting_smtp_port();
    $secure = casting_smtp_secure();
    $host = casting_smtp_host();
    $auth = true;
    if (isset($GLOBALS['casting_smtp_auth_override'])) {
        $auth = (bool) $GLOBALS['casting_smtp_auth_override'];
    } elseif (function_exists('get_option')) {
        $stored = (string) get_option('casting_smtp_auth', '1');
        $auth = $stored !== '0';
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $host;
    $phpmailer->Port = $port;
    $phpmailer->SMTPAuth = $auth;
    $phpmailer->AuthType = '';
    $phpmailer->Username = casting_smtp_username();
    $phpmailer->Password = $auth ? casting_smtp_password() : '';

    if ($secure === 'ssl' || $secure === 'tls') {
        $phpmailer->SMTPSecure = $secure;
        $phpmailer->SMTPAutoTLS = ($secure === 'tls');
    } else {
        $phpmailer->SMTPSecure = '';
        $phpmailer->SMTPAutoTLS = false;
    }
    $phpmailer->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    if (!empty($GLOBALS['casting_smtp_debug'])) {
        $phpmailer->SMTPDebug = 2;
        $phpmailer->Debugoutput = static function (string $str, int $level): void {
            if (!isset($GLOBALS['casting_smtp_debug_log']) || !is_string($GLOBALS['casting_smtp_debug_log'])) {
                $GLOBALS['casting_smtp_debug_log'] = '';
            }
            $GLOBALS['casting_smtp_debug_log'] .= $str;
        };
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

add_action('phpmailer_init', 'casting_init_phpmailer', 999);
add_filter('wp_mail_from', 'casting_filter_mail_from');
add_filter('wp_mail_from_name', 'casting_filter_mail_from_name');

/**
 * تست مستقیم AUTH روی سوکت SMTP — بدون PHPMailer/wp_mail
 *
 * @return array{ok:bool,error:string,transcript:string,working:?array{host:string,port:int,secure:string,user:string}}
 */
function casting_smtp_diagnose(): array
{
    $pass = casting_smtp_password();
    if ($pass === '') {
        return ['ok' => false, 'error' => 'رمز SMTP خالی است.', 'transcript' => '', 'working' => null];
    }

    $users = array_values(array_unique(array_filter([
        defined('CASTING_SMTP_USER') ? (string) CASTING_SMTP_USER : '',
        'noreply@7rokh.com',
        'noreply',
    ])));

    $targets = [
        ['host' => '127.0.0.1', 'port' => 587, 'secure' => 'tls'],
        ['host' => 'localhost', 'port' => 587, 'secure' => 'tls'],
        ['host' => '127.0.0.1', 'port' => 465, 'secure' => 'ssl'],
        ['host' => 'localhost', 'port' => 465, 'secure' => 'ssl'],
        ['host' => '127.0.0.1', 'port' => 25, 'secure' => 'none'],
        ['host' => 'localhost', 'port' => 25, 'secure' => 'none'],
    ];

    $transcript = '';
    foreach ($targets as $target) {
        foreach ($users as $user) {
            $label = $user . ' @ ' . $target['host'] . ':' . $target['port'] . '/' . $target['secure'];
            $transcript .= "\n===== {$label} =====\n";
            $result = casting_smtp_socket_auth($target['host'], $target['port'], $target['secure'], $user, $pass);
            $transcript .= $result['transcript'];
            if ($result['ok']) {
                casting_smtp_persist_profile([
                    'host'   => $target['host'],
                    'port'   => $target['port'],
                    'secure' => $target['secure'],
                ]);
                if (function_exists('update_option') && $user !== '' && defined('CASTING_SMTP_USER') && $user !== CASTING_SMTP_USER) {
                    update_option('casting_smtp_user_override', $user, false);
                }

                return [
                    'ok'         => true,
                    'error'      => '',
                    'transcript' => $transcript,
                    'working'    => [
                        'host'   => $target['host'],
                        'port'   => $target['port'],
                        'secure' => $target['secure'],
                        'user'   => $user,
                    ],
                ];
            }
        }
    }

    return [
        'ok'         => false,
        'error'      => 'هیچ ترکیب Host/Port/User با رمز فعلی AUTH نشد. پاسخ‌های سرور را در جزئیات ببینید.',
        'transcript' => $transcript,
        'working'    => null,
    ];
}

/**
 * @return array{ok:bool,transcript:string}
 */
function casting_smtp_socket_auth(string $host, int $port, string $secure, string $user, string $pass): array
{
    $log = '';
    $errno = 0;
    $errstr = '';
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return ['ok' => false, 'transcript' => "CONNECT FAIL {$errno} {$errstr}\n"];
    }
    stream_set_timeout($fp, 12);

    $read = static function () use ($fp, &$log): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $log .= '<< ' . rtrim($line) . "\n";
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = static function (string $cmd, bool $secret = false) use ($fp, &$log): void {
        $log .= '>> ' . ($secret ? '[hidden]' : $cmd) . "\n";
        fwrite($fp, $cmd . "\r\n");
    };

    $banner = $read();
    if ($banner === '' || $banner[0] !== '2') {
        fclose($fp);
        return ['ok' => false, 'transcript' => $log . "BAD BANNER\n"];
    }

    $write('EHLO casting-portal');
    $ehlo = $read();

    if ($secure === 'tls') {
        if (stripos($ehlo, 'STARTTLS') === false) {
            fclose($fp);
            return ['ok' => false, 'transcript' => $log . "NO STARTTLS\n"];
        }
        $write('STARTTLS');
        $tls = $read();
        if ($tls === '' || $tls[0] !== '2') {
            fclose($fp);
            return ['ok' => false, 'transcript' => $log . "STARTTLS FAIL\n"];
        }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return ['ok' => false, 'transcript' => $log . "TLS HANDSHAKE FAIL\n"];
        }
        $write('EHLO casting-portal');
        $ehlo = $read();
    }

    if (stripos($ehlo, 'AUTH') === false) {
        // روی پورت ۲۵ گاهی AUTH نیست؛ برای تشخیص جدا گزارش می‌شود
        fclose($fp);
        return ['ok' => false, 'transcript' => $log . "NO AUTH CAPABILITY\n"];
    }

    $write('AUTH LOGIN');
    $auth = $read();
    if (strpos($auth, '334') !== 0) {
        fclose($fp);
        return ['ok' => false, 'transcript' => $log . "AUTH LOGIN REJECTED\n"];
    }
    $write(base64_encode($user), true);
    $read();
    $write(base64_encode($pass), true);
    $final = $read();
    $ok = strpos($final, '235') === 0;
    if ($ok) {
        $write('QUIT');
        $read();
    }
    fclose($fp);

    return ['ok' => $ok, 'transcript' => $log];
}
/**
 * @param list<string> $headers
 * @return array{ok:bool,error:string,debug:string,raw_error:string}
 */
function casting_send_mail(string $to, string $subject, string $body, array $headers = [], bool $debug = false): array
{
    if (!is_email($to)) {
        return ['ok' => false, 'error' => 'آدرس گیرنده معتبر نیست.', 'debug' => '', 'raw_error' => ''];
    }

    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers = array_values(array_unique($headers));

    $last_error = '';
    $capture = static function ($wp_error) use (&$last_error): void {
        if ($wp_error instanceof WP_Error) {
            $last_error = $wp_error->get_error_message();
            $data = $wp_error->get_error_data('wp_mail_failed');
            if (is_string($data) && $data !== '') {
                $last_error = $data;
            }
        }
    };
    add_action('wp_mail_failed', $capture);

    $GLOBALS['casting_smtp_debug'] = $debug;
    $GLOBALS['casting_smtp_debug_log'] = '';

    $profiles = casting_smtp_probe_profiles();
    $sent = false;
    $debug_log = '';
    $worked = null;

    foreach ($profiles as $index => $profile) {
        casting_smtp_clear_profile_overrides();
        if ($index > 0) {
            casting_smtp_apply_profile($profile);
        }
        $last_error = '';
        if ($index > 0) {
            $GLOBALS['casting_smtp_debug_log'] = ($GLOBALS['casting_smtp_debug_log'] ?? '')
                . "\n--- try {$profile['label']} ---\n";
        }

        $sent = wp_mail($to, $subject, $body, $headers);
        $debug_log = is_string($GLOBALS['casting_smtp_debug_log'] ?? null)
            ? (string) $GLOBALS['casting_smtp_debug_log']
            : $debug_log;

        global $phpmailer;
        if (!$sent && isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            $last_error = (string) $phpmailer->ErrorInfo;
        }

        if ($sent) {
            $worked = $profile;
            if ($index > 0) {
                casting_smtp_persist_profile($profile);
            }
            break;
        }

        // فقط وقتی auth/connect شکست خورد پروفایل بعدی را امتحان کن
        $retryable = $last_error !== '' && (
            stripos($last_error, 'authenticate') !== false
            || stripos($last_error, 'احراز') !== false
            || stripos($last_error, 'connect') !== false
            || stripos($last_error, 'اتصال') !== false
            || stripos($last_error, 'timed out') !== false
            || stripos($last_error, 'certificate') !== false
        );
        if (!$retryable) {
            break;
        }
    }

    casting_smtp_clear_profile_overrides();
    unset($GLOBALS['casting_smtp_debug'], $GLOBALS['casting_smtp_debug_log']);
    remove_action('wp_mail_failed', $capture);

    $raw_error = $last_error;

    if ($sent) {
        $note = $worked && ($worked['label'] ?? '') !== 'current'
            ? (' (پروفایل کاری: ' . $worked['host'] . ':' . $worked['port'] . '/' . $worked['secure'] . ')')
            : '';
        return ['ok' => true, 'error' => '', 'debug' => $debug_log . $note, 'raw_error' => ''];
    }

    if ($last_error === '') {
        if (!casting_mail_is_smtp_ready()) {
            $last_error = trim(casting_mail_setup_hint()) ?: 'SMTP آماده نیست.';
        } else {
            $last_error = 'ارسال ایمیل ناموفق بود.';
        }
    } elseif (
        stripos($last_error, 'authenticate') !== false
        || stripos($last_error, 'احراز') !== false
    ) {
        $last_error = 'اتصال SMTP به سرور برقرار شد ولی احراز هویت رد شد. Webmail ≠ SMTP: گاهی رمز درست است ولی پورت/رمزنگاری یا محدودیت سی‌پنل مانع می‌شود. در «تست ایمیل SMTP» جزئیات SMTP را باز کنید و همان متن خام را بفرستید. همچنین «پاک کردن رمز دیتابیس» → ذخیره دوباره رمز → ارسال تست را یک‌بار انجام دهید.';
    }

    return ['ok' => false, 'error' => $last_error, 'debug' => $debug_log, 'raw_error' => $raw_error];
}

/**
 * @return array{ok:bool,error:string,saved:bool}
 */
function casting_send_contact_message(string $name, string $email, string $subject, string $message, int $user_id = 0): array
{
    $result = casting_contact_send_message(
        'site_admin',
        $subject,
        $message,
        max(0, $user_id),
        $name,
        $email
    );
    return [
        'ok'    => $result['ok'],
        'error' => $result['error'],
        'saved' => $result['ok'],
    ];
}
