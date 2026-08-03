<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!function_exists('casting_portal_logout_user')) {
    require_once __DIR__ . '/includes/portal-auth.php';
}
casting_portal_logout_user();

$reason = sanitize_key((string) ($_GET['reason'] ?? ''));
if ($reason === 'idle' && function_exists('casting_session_idle_message')) {
    casting_set_flash('error', casting_session_idle_message());
} elseif ($reason === 'replaced' && function_exists('casting_session_replaced_message')) {
    casting_set_flash('error', casting_session_replaced_message());
} else {
    casting_set_flash('success', 'با موفقیت خارج شدید.');
}
casting_redirect('login.php');
