<?php
declare(strict_types=1);

/**
 * موقت — تست کاربر shaverdi
 * یک ماه عضویت ویژه + یک سهمیه تازه برای همین دور تست.
 * وقتی تست تمام شد این فایل و require آن در bootstrap.php را حذف کنید.
 */

function casting_tmp_test_grant_login(): string
{
    return 'shaverdi';
}

function casting_tmp_test_grant_apply(): void
{
    $user = get_user_by('login', casting_tmp_test_grant_login());
    if (!$user instanceof WP_User) {
        return;
    }
    $user_id = (int) $user->ID;
    if ($user_id <= 0) {
        return;
    }

    if (!function_exists('casting_premium_activate_for_user')) {
        require_once __DIR__ . '/premium.php';
    }
    if (!function_exists('casting_ad_credit_grant')) {
        require_once __DIR__ . '/ad-posters.php';
    }

    if (get_option('casting_tmp_test_shaverdi_v1', '') !== '1') {
        casting_premium_activate_for_user($user_id, 30, 'featured_30', 0, 'tmp-test-shaverdi');
        update_option('casting_tmp_test_shaverdi_v1', '1', true);
    }

    // دور جدید تست: یک سهمیه باز تا بتواند عکس بفرستد؛ بعد از تأیید ادمین بسته می‌شود.
    if (get_option('casting_tmp_test_shaverdi_v3', '') !== '1') {
        casting_premium_activate_for_user($user_id, 30, 'featured_30', 0, 'tmp-test-shaverdi');
        casting_ad_credit_grant($user_id, 'TMP-TEST', 'banner_theater', 'tmp-test-shaverdi:round3');
        if (function_exists('casting_user_set_ads_unlocked')) {
            casting_user_set_ads_unlocked($user_id, true);
        }
        update_option('casting_tmp_test_shaverdi_v3', '1', true);
    }
}
