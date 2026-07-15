<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
casting_redirect('member.php?id=' . (int) $user->ID);
