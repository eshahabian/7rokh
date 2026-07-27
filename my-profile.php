<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/panel-profile.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;

casting_render_panel_start('مشاهده پروفایل من', 'my-profile');
casting_render_flash();
casting_render_member_profile_view($user_id, $user_id, true);
casting_render_panel_end();
