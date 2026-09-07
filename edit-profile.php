<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/panel-profile.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$profile = casting_get_profile($user_id);
$profile_error = '';
$profile_success = '';
$profile_errors = [];

$profile_post = casting_process_profile_post($user_id);
if ($profile_post['error'] !== '') {
    $profile_error = $profile_post['error'];
}
if ($profile_success === '' && isset($_GET['saved']) && (string) $_GET['saved'] === '1') {
    $profile_success = 'پروفایل ذخیره شد.';
}
if ($profile_post['success'] !== '') {
    $profile_success = $profile_post['success'];
}
if ($profile_post['profile'] !== null) {
    $profile = $profile_post['profile'];
}
if (isset($profile_post['errors']) && is_array($profile_post['errors'])) {
    foreach ($profile_post['errors'] as $item) {
        if (is_string($item) && $item !== '') {
            $profile_errors[] = $item;
        }
    }
}
if ($profile_errors === [] && $profile_error !== '') {
    $profile_errors[] = $profile_error;
}

casting_render_panel_start('ویرایش پروفایل من', 'edit-profile');
if (isset($_GET['welcome']) && (string) $_GET['welcome'] === '1' && $profile_success === '' && $profile_errors === []) {
    echo '<div class="flash flash-success profile-save-flash" role="status">ثبت‌نام انجام شد. پروفایل را همین‌جا کامل کنید؛ با هر بار ذخیره، همان تغییراتی که وارد کرده‌اید ثبت می‌شود.</div>';
}
if ($profile_errors !== []) {
    echo '<div class="flash flash-error profile-save-flash" role="alert">';
    echo '<p><strong>پروفایل ذخیره نشد:</strong></p><ul>';
    foreach ($profile_errors as $item) {
        echo '<li>' . casting_e($item) . '</li>';
    }
    echo '</ul></div>';
} elseif ($profile_error !== '') {
    echo '<div class="flash flash-error profile-save-flash" role="alert">' . casting_e($profile_error) . '</div>';
}
if ($profile_success !== '') {
    echo '<div class="flash flash-success profile-save-flash" role="alert">' . casting_e($profile_success) . '</div>';
}
casting_render_flash();

casting_render_panel_completion_card($profile, $user_id);

casting_panel_render_section($user_id, static function () use ($user_id, $profile): void {
    casting_render_profile_edit_form($user_id, $profile, true);
}, 'ویرایش پروفایل');

casting_render_panel_end();
?>
