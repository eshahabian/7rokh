<?php
/**
 * Plugin Name: Casting Portal — جداسازی کاربران
 * Description: بارگذاری guard پورتال از casting-portal/mu-plugin/ — با هر deploy به‌روز می‌شود.
 *
 * این فایل یک‌بار در wp-content/mu-plugins/ کپی می‌شود (خودکار با .cpanel.yml).
 */

declare(strict_types=1);

$guard = ABSPATH . 'casting-portal/mu-plugin/casting-wp-admin-guard.php';
if (is_readable($guard)) {
    require_once $guard;
}
