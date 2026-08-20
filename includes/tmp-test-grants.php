<?php
declare(strict_types=1);

/**
 * موقت — تست کاربر shaverdi
 * یک ماه عضویت ویژه + یک سهمیه ارسال پوستر (مثل پرداخت‌شده).
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

    if (get_option('casting_tmp_test_shaverdi_v1', '') !== '1') {
        if (!function_exists('casting_premium_activate_for_user')) {
            require_once __DIR__ . '/premium.php';
        }
        casting_premium_activate_for_user($user_id, 30, 'featured_30', 0, 'tmp-test-shaverdi');

        if (!function_exists('casting_ad_credit_grant')) {
            require_once __DIR__ . '/ad-posters.php';
        }
        casting_ad_credit_grant($user_id, 'TMP-TEST', 'banner_theater', 'tmp-test-shaverdi:banner_theater');
        if (function_exists('casting_user_set_ads_unlocked')) {
            casting_user_set_ads_unlocked($user_id, true);
        }
        update_option('casting_tmp_test_shaverdi_v1', '1', true);
    }

    if (!function_exists('casting_user_has_active_ad_poster')) {
        require_once __DIR__ . '/ad-posters.php';
    }
    if (function_exists('casting_user_has_active_ad_poster') && casting_user_has_active_ad_poster($user_id)) {
        casting_ad_tmp_test_close_open_credits($user_id);
    }
}
