<?php
declare(strict_types=1);

/**
 * ثبت‌نام کاربر در دیتابیس وردپرس + ذخیره نقش کستینگ
 */
function casting_register_user(string $name, string $email, string $password, string $role): array
{
    $name = sanitize_text_field($name);
    $email = sanitize_email($email);
    $role = sanitize_key($role);

    if ($name === '' || casting_strlen($name) < 2) {
        return ['ok' => false, 'error' => 'نام باید حداقل ۲ کاراکتر باشد.'];
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

    $username_base = sanitize_user(current(explode('@', $email)), true);
    if ($username_base === '') {
        $username_base = 'user';
    }
    $username = $username_base;
    $i = 1;
    while (username_exists($username)) {
        $username = $username_base . $i;
        $i++;
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

    return ['ok' => true, 'user_id' => (int) $user_id, 'role' => $role];
}

/**
 * ورود با بررسی نقش مناسب برای هر درگاه
 */
function casting_login(string $email, string $password, string $portal): array
{
    $email = sanitize_email($email);
    $portal = sanitize_key($portal);

    if (!is_email($email)) {
        return ['ok' => false, 'error' => 'ایمیل معتبر نیست.'];
    }
    if ($password === '') {
        return ['ok' => false, 'error' => 'رمز عبور را وارد کنید.'];
    }

    $user = get_user_by('email', $email);
    if (!$user) {
        return ['ok' => false, 'error' => 'ایمیل یا رمز عبور اشتباه است.'];
    }

    $role = casting_get_user_role((int) $user->ID);
    if ($role === '') {
        return ['ok' => false, 'error' => 'این حساب برای پورتال کستینگ ثبت نشده است.'];
    }

    if ($portal === 'talent' && $role !== 'talent') {
        return ['ok' => false, 'error' => 'شما هنرجو نیستید. از ورود کارفرما استفاده کنید.'];
    }
    if ($portal === 'employer' && !casting_is_employer_role($role)) {
        return ['ok' => false, 'error' => 'شما کارفرما نیستید. از ورود هنرجو استفاده کنید.'];
    }

    $creds = [
        'user_login'    => $user->user_login,
        'user_password' => $password,
        'remember'      => true,
    ];

    $signed = wp_signon($creds, is_ssl());
    if (is_wp_error($signed)) {
        return ['ok' => false, 'error' => 'ایمیل یا رمز عبور اشتباه است.'];
    }

    return ['ok' => true, 'user' => $signed, 'role' => $role];
}
