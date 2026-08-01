<?php
declare(strict_types=1);

/**
 * ثبت‌نام کاربر در دیتابیس وردپرس + ذخیره نقش کستینگ
 */
function casting_register_user(string $name, string $username, string $email, string $password, string $role): array
{
    $name = sanitize_text_field($name);
    $username = sanitize_user($username, true);
    $email = sanitize_email($email);
    $role = sanitize_key($role);

    if ($name === '' || casting_strlen($name) < 2) {
        return ['ok' => false, 'error' => 'نام باید حداقل ۲ کاراکتر باشد.'];
    }
    if ($username === '' || strlen($username) < 3) {
        return ['ok' => false, 'error' => 'نام کاربری باید حداقل ۳ کاراکتر و فقط حروف/عدد باشد.'];
    }
    if (username_exists($username)) {
        return ['ok' => false, 'error' => 'این نام کاربری قبلاً گرفته شده است.'];
    }
    if (!is_email($email)) {
        return ['ok' => false, 'error' => 'ایمیل معتبر نیست.'];
    }
    if (email_exists($email)) {
        return ['ok' => false, 'error' => 'این ایمیل قبلاً ثبت شده است.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.'];
    }
    if (!casting_valid_role($role)) {
        return ['ok' => false, 'error' => 'نقش انتخاب‌شده معتبر نیست.'];
    }

    $user_id = wp_insert_user([
        'user_login'   => $username,
        'user_pass'    => $password,
        'user_email'   => $email,
        'display_name' => $name,
        'nickname'     => $name,
        'first_name'   => $name,
        'role'         => 'subscriber',
    ]);

    if (is_wp_error($user_id)) {
        return ['ok' => false, 'error' => 'ثبت‌نام ناموفق: ' . $user_id->get_error_message()];
    }

    update_user_meta((int) $user_id, 'casting_role', $role);
    update_user_meta((int) $user_id, 'casting_registered_at', current_time('mysql'));
    update_user_meta((int) $user_id, 'casting_visible', '1');

    if (!function_exists('casting_assign_membership_number')) {
        require_once __DIR__ . '/membership-number.php';
    }
    casting_assign_membership_number((int) $user_id, $role);

    if (!function_exists('casting_follow_default_admins')) {
        require_once __DIR__ . '/follows.php';
    }
    casting_follow_default_admins((int) $user_id);

    return ['ok' => true, 'user_id' => (int) $user_id, 'role' => $role];
}

/**
 * @return array{ok:bool,error?:string}
 */
function casting_update_user_email(int $user_id, string $email): array
{
    $email = sanitize_email($email);
    if (!is_email($email)) {
        return ['ok' => false, 'error' => 'ایمیل معتبر نیست.'];
    }

    $existing = email_exists($email);
    if ($existing && (int) $existing !== $user_id) {
        return ['ok' => false, 'error' => 'این ایمیل قبلاً ثبت شده است.'];
    }

    $result = wp_update_user([
        'ID'         => $user_id,
        'user_email' => $email,
    ]);
    if ($result instanceof WP_Error) {
        return ['ok' => false, 'error' => 'ذخیره ایمیل ناموفق: ' . $result->get_error_message()];
    }

    return ['ok' => true];
}

function casting_delete_registered_user(int $user_id): void
{
    if ($user_id <= 0) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($user_id);
}

/**
 * آدرس پنل بر اساس نقش
 */
function casting_dashboard_for_role(string $role, string $query = ''): string
{
    unset($role);
    $page = 'home.php';
    if ($query !== '') {
        return $page . (str_contains($query, '?') ? $query : '?' . ltrim($query, '?'));
    }
    return $page;
}

/**
 * ورود با نام کاربری یا ایمیل — نقش را خودش تشخیص می‌دهد
 */
function casting_login(string $login, string $password, string $portal = ''): array
{
    $login = trim($login);

    if ($login === '') {
        return ['ok' => false, 'error' => 'نام کاربری یا ایمیل را وارد کنید.'];
    }
    if ($password === '') {
        return ['ok' => false, 'error' => 'رمز عبور را وارد کنید.'];
    }

    if (is_email($login)) {
        $user = get_user_by('email', sanitize_email($login));
    } else {
        $user = get_user_by('login', sanitize_user($login, true));
    }

    if (!$user) {
        return ['ok' => false, 'error' => 'نام کاربری/ایمیل یا رمز عبور اشتباه است.'];
    }

    $role = casting_get_user_role((int) $user->ID);
    if ($role === '') {
        return ['ok' => false, 'error' => 'این حساب برای پورتال ۷ رخ ثبت نشده است.'];
    }

    $portal = sanitize_key($portal);
    if ($portal === 'talent' && $role !== 'talent') {
        return ['ok' => false, 'error' => 'این بخش فقط برای هنرمندان است.'];
    }
    if ($portal === 'employer' && !casting_is_employer_role($role)) {
        return ['ok' => false, 'error' => 'این بخش فقط برای کارفرماست.'];
    }

    if (!wp_check_password($password, $user->user_pass, (int) $user->ID)) {
        return ['ok' => false, 'error' => 'نام کاربری/ایمیل یا رمز عبور اشتباه است.'];
    }

    if (!function_exists('casting_portal_login_user')) {
        require_once __DIR__ . '/portal-auth.php';
    }
    casting_portal_login_user($user, true);

    return ['ok' => true, 'user' => $user, 'role' => $role];
}

/**
 * ارسال لینک بازیابی رمز به ایمیل یا پیامک
 *
 * @param 'email'|'sms' $channel
 * @return array{ok:bool,error:string,message:string}
 */
function casting_request_password_reset(string $login, string $channel = 'email'): array
{
    $login = trim($login);
    $channel = $channel === 'sms' ? 'sms' : 'email';
    if ($login === '') {
        return [
            'ok'      => false,
            'error'   => $channel === 'sms' ? 'شماره موبایل یا نام کاربری را وارد کنید.' : 'نام کاربری یا ایمیل را وارد کنید.',
            'message' => '',
        ];
    }

    $user = null;
    if ($channel === 'sms' && function_exists('casting_normalize_mobile')) {
        $maybe_mobile = casting_normalize_mobile($login);
        if (preg_match('/^09\d{9}$/', $maybe_mobile)) {
            $found = casting_find_user_by_mobile($maybe_mobile);
            if (!empty($found['ok']) && !empty($found['user_id'])) {
                $user = get_user_by('id', (int) $found['user_id']);
            }
        }
    }

    if (!$user) {
        if (is_email($login)) {
            $user = get_user_by('email', sanitize_email($login));
        } else {
            $user = get_user_by('login', sanitize_user($login, true));
        }
    }

    // پیام یکسان برای جلوگیری از افشای وجود حساب
    $generic = $channel === 'sms'
        ? 'اگر حسابی با این مشخصات در ۷ رخ باشد، لینک بازیابی رمز به موبایل آن ارسال می‌شود.'
        : 'اگر حسابی با این مشخصات در ۷ رخ باشد، لینک بازیابی رمز به ایمیل آن ارسال می‌شود.';

    if (!$user || casting_get_user_role((int) $user->ID) === '') {
        return ['ok' => true, 'error' => '', 'message' => $generic];
    }

    $key = get_password_reset_key($user);
    if (is_wp_error($key)) {
        return ['ok' => false, 'error' => 'ارسال لینک بازیابی ممکن نشد. کمی بعد دوباره تلاش کنید.', 'message' => ''];
    }

    $url = casting_url(
        'reset-password.php?key=' . rawurlencode((string) $key) . '&login=' . rawurlencode($user->user_login)
    );
    $brand = casting_brand();

    if ($channel === 'sms') {
        if (!function_exists('casting_sms_send_text')) {
            require_once __DIR__ . '/sms.php';
        }
        if (!function_exists('casting_normalize_mobile')) {
            require_once __DIR__ . '/profile.php';
        }
        $mobile = casting_normalize_mobile((string) get_user_meta((int) $user->ID, 'casting_mobile', true));
        if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
            return [
                'ok'      => false,
                'error'   => 'برای این حساب موبایل معتبری ثبت نشده است. از بازیابی با ایمیل استفاده کنید.',
                'message' => '',
            ];
        }
        if (!casting_sms_is_configured()) {
            return ['ok' => false, 'error' => 'ارسال پیامک در حال حاضر ممکن نیست.', 'message' => ''];
        }

        $body = "بازیابی رمز {$brand}:\n{$url}";
        $sms = casting_sms_send_text($mobile, $body);
        if (!$sms['ok']) {
            return ['ok' => false, 'error' => $sms['error'], 'message' => ''];
        }

        return ['ok' => true, 'error' => '', 'message' => $generic];
    }

    if (!casting_mail_is_smtp_ready()) {
        return [
            'ok'      => false,
            'error'   => trim(casting_mail_setup_hint()) ?: 'ارسال ایمیل در حال حاضر ممکن نیست.',
            'message' => '',
        ];
    }

    $subject = sprintf('[%s] بازیابی رمز عبور', $brand);
    $body = "سلام {$user->display_name},\n\n"
        . "درخواست بازیابی رمز عبور برای حساب شما در {$brand} ثبت شد.\n"
        . "برای تعیین رمز جدید روی لینک زیر کلیک کنید:\n\n"
        . $url . "\n\n"
        . "اگر این درخواست از طرف شما نبوده، این ایمیل را نادیده بگیرید.\n";

    $mail = casting_send_mail($user->user_email, $subject, $body);
    if (!$mail['ok']) {
        $error = $mail['error'];
        if (stripos($error, 'authenticate') !== false || stripos($error, 'احراز') !== false) {
            $error = 'رمز SMTP اکانت noreply@7rokh.ir اشتباه است. در cPanel رمز ایمیل را چک کنید و همان را در config.local.php بگذارید.';
        } elseif (!casting_mail_is_smtp_ready()) {
            $hint = casting_mail_setup_hint();
            if ($hint !== '') {
                $error .= ' —' . $hint;
            }
        }
        return [
            'ok'      => false,
            'error'   => $error,
            'message' => '',
        ];
    }

    return ['ok' => true, 'error' => '', 'message' => $generic];
}

/**
 * تنظیم رمز جدید با کلید بازیابی
 *
 * @return array{ok:bool,error:string}
 */
function casting_reset_password_with_key(string $login, string $key, string $password, string $password2): array
{
    $login = sanitize_user(trim($login), true);
    $key = trim($key);

    if ($login === '' || $key === '') {
        return ['ok' => false, 'error' => 'لینک بازیابی نامعتبر است.'];
    }
    if ($password === '' || strlen($password) < 8) {
        return ['ok' => false, 'error' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.'];
    }
    if ($password !== $password2) {
        return ['ok' => false, 'error' => 'تکرار رمز عبور مطابقت ندارد.'];
    }

    $user = check_password_reset_key($key, $login);
    if (is_wp_error($user)) {
        return ['ok' => false, 'error' => 'لینک بازیابی منقضی یا نامعتبر است. دوباره درخواست دهید.'];
    }

    if (casting_get_user_role((int) $user->ID) === '') {
        return ['ok' => false, 'error' => 'این حساب برای پورتال ۷ رخ ثبت نشده است.'];
    }

    reset_password($user, $password);
    return ['ok' => true, 'error' => ''];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_change_password(int $user_id, string $current, string $new, string $confirm): array
{
    if (strlen($new) < 8) {
        return ['ok' => false, 'error' => 'رمز جدید باید حداقل ۸ کاراکتر باشد.'];
    }
    if ($new !== $confirm) {
        return ['ok' => false, 'error' => 'تکرار رمز جدید مطابقت ندارد.'];
    }

    $user = get_user_by('id', $user_id);
    if (!$user) {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    if (!wp_check_password($current, $user->user_pass, $user_id)) {
        return ['ok' => false, 'error' => 'رمز فعلی اشتباه است.'];
    }

    wp_set_password($new, $user_id);
    if (!function_exists('casting_portal_login_user')) {
        require_once __DIR__ . '/portal-auth.php';
    }
    $user = get_user_by('id', $user_id);
    if ($user instanceof WP_User) {
        casting_portal_login_user($user, true);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * مرحله ۱ تغییر شماره: بررسی رمز و ارسال OTP به موبایل جدید
 *
 * @return array{ok:bool,error:string}
 */
function casting_change_phone_request_otp(int $user_id, string $password, string $mobile_raw): array
{
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    if (!wp_check_password($password, $user->user_pass, $user_id)) {
        return ['ok' => false, 'error' => 'رمز عبور اشتباه است.'];
    }

    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile_raw);
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['ok' => false, 'error' => 'شماره موبایل را درست وارد کنید (مثلاً ۰۹۱۲۱۲۳۴۵۶۷).'];
    }

    $current = casting_normalize_mobile((string) get_user_meta($user_id, 'casting_mobile', true));
    if ($current === $mobile) {
        return ['ok' => false, 'error' => 'این شماره همان شماره فعلی شماست.'];
    }
    if (casting_mobile_is_taken($mobile, $user_id)) {
        return ['ok' => false, 'error' => 'این شماره موبایل قبلاً برای حساب دیگری ثبت شده است.'];
    }

    return casting_otp_send('change_phone', $mobile);
}

/**
 * مرحله ۲: تأیید OTP و ذخیره شماره
 *
 * @return array{ok:bool,error:string}
 */
function casting_change_phone_confirm(int $user_id, string $password, string $mobile_raw, string $otp_code): array
{
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    if (!wp_check_password($password, $user->user_pass, $user_id)) {
        return ['ok' => false, 'error' => 'رمز عبور اشتباه است.'];
    }

    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile_raw);
    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['ok' => false, 'error' => 'شماره موبایل را درست وارد کنید.'];
    }
    if (casting_mobile_is_taken($mobile, $user_id)) {
        return ['ok' => false, 'error' => 'این شماره موبایل قبلاً برای حساب دیگری ثبت شده است.'];
    }

    $verify = casting_otp_verify('change_phone', $mobile, $otp_code);
    if (!$verify['ok']) {
        return $verify;
    }

    casting_mark_mobile_verified($user_id, $mobile);

    return ['ok' => true, 'error' => ''];
}

/**
 * @deprecated از casting_change_phone_request_otp / casting_change_phone_confirm استفاده کنید
 * @return array{ok:bool,error:string}
 */
function casting_change_phone(int $user_id, string $password, string $mobile_raw): array
{
    return casting_change_phone_request_otp($user_id, $password, $mobile_raw);
}

/**
 * ورود با کد پیامک
 *
 * @return array{ok:bool,error?:string,user?:WP_User,role?:string}
 */
function casting_login_with_otp(string $mobile_raw, string $otp_code): array
{
    if (!function_exists('casting_normalize_mobile')) {
        require_once __DIR__ . '/profile.php';
    }
    $mobile = casting_normalize_mobile($mobile_raw);
    $verify = casting_otp_verify('login', $mobile, $otp_code);
    if (!$verify['ok']) {
        return ['ok' => false, 'error' => $verify['error']];
    }

    $found = casting_find_user_by_mobile($mobile);
    if (empty($found['ok']) || empty($found['user_id'])) {
        return ['ok' => false, 'error' => 'حسابی با این شماره پیدا نشد.'];
    }

    $user = get_user_by('id', (int) $found['user_id']);
    if (!$user) {
        return ['ok' => false, 'error' => 'حسابی با این شماره پیدا نشد.'];
    }
    $role = casting_get_user_role((int) $user->ID);
    if ($role === '') {
        return ['ok' => false, 'error' => 'این حساب برای پورتال ۷ رخ فعال نیست.'];
    }

    if (!casting_user_mobile_is_verified((int) $user->ID)) {
        casting_mark_mobile_verified((int) $user->ID, $mobile);
    }

    if (!function_exists('casting_portal_login_user')) {
        require_once __DIR__ . '/portal-auth.php';
    }
    casting_portal_login_user($user, true);

    return ['ok' => true, 'user' => $user, 'role' => $role];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_cancel_membership(int $user_id, string $password): array
{
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }
    if (!wp_check_password($password, $user->user_pass, $user_id)) {
        return ['ok' => false, 'error' => 'رمز عبور اشتباه است.'];
    }

    update_user_meta($user_id, 'casting_visible', '0');
    update_user_meta($user_id, 'casting_cancelled_at', current_time('mysql'));
    delete_user_meta($user_id, 'casting_role');
    if (!function_exists('casting_portal_logout_user')) {
        require_once __DIR__ . '/portal-auth.php';
    }
    casting_portal_logout_user();

    return ['ok' => true, 'error' => ''];
}
