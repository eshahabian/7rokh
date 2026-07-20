<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contact-messages.php';
require_once __DIR__ . '/includes/admin-access.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

if (casting_contact_user_is_recipient($user_id) || !casting_user_has_admin_permission($user_id, 'view_contact_messages')) {
    casting_redirect('contact.php');
}

casting_require_admin_permission('view_contact_messages');
casting_redirect('contact.php');
