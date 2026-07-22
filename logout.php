<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!function_exists('casting_portal_logout_user')) {
    require_once __DIR__ . '/includes/portal-auth.php';
}
casting_portal_logout_user();
casting_set_flash('success', 'با موفقیت خارج شدید.');
casting_redirect('index.php');
