<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$profile = casting_get_profile($user_id);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_photo')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $photo = casting_handle_photo_upload($user_id);
        if (!$photo['ok']) {
            $error = $photo['error'];
        } else {
            casting_set_flash('success', 'تصویر پروفایل به‌روز شد.');
            casting_redirect('profile-photo.php');
        }
    }
}

casting_render_panel_start('ویرایش تصویر', 'photo');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card">
  <h1>ویرایش تصویر</h1>
  <div class="photo-row">
    <div class="profile-photo">
      <?php if ($profile['photo_url'] !== '') : ?>
        <img src="<?= casting_e($profile['photo_url']) ?>" alt="">
      <?php else : ?>
        <div class="photo-placeholder tall">بدون عکس</div>
      <?php endif; ?>
    </div>
    <form class="form" method="post" action="profile-photo.php" enctype="multipart/form-data">
      <?php wp_nonce_field('casting_photo'); ?>
      <div class="field">
        <label for="photo">انتخاب عکس جدید</label>
        <input id="photo" name="photo" type="file" accept="image/*" required>
      </div>
      <button class="btn btn-primary" type="submit">ذخیره تصویر</button>
    </form>
  </div>
</section>
<?php casting_render_panel_end(); ?>
