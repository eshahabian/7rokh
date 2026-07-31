<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';

$user = casting_require_casting_user();
$user_id = (int) $user->ID;
$profile = casting_get_profile($user_id);
$error = '';
$is_actor_photos = casting_user_uses_actor_portrait_set($user_id);

if (!casting_user_can_upload_portraits($user_id)) {
    casting_set_flash('error', 'بارگذاری عکس پروفایل برای این حساب مجاز نیست.');
    casting_redirect('panel.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_photo')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $has_file = false;
        if ($is_actor_photos) {
            foreach (array_keys(casting_portrait_slots()) as $slot) {
                if (!empty($_FILES['photo_' . $slot]['name'])) {
                    $has_file = true;
                    break;
                }
            }
        } else {
            $has_file = !empty($_FILES['photo_medium']['name']);
        }

        if (!$has_file) {
            $error = $is_actor_photos ? 'حداقل یک عکس جدید انتخاب کنید.' : 'عکس پروفایل را انتخاب کنید.';
        } else {
            $result = $is_actor_photos
                ? casting_handle_portrait_uploads($user_id, false)
                : casting_handle_portrait_upload($user_id, 'medium');
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', $is_actor_photos ? 'عکس‌های پروفایل به‌روز شد.' : 'عکس پروفایل به‌روز شد.');
                casting_redirect('profile-photo.php');
            }
        }
    }
}

$profile = casting_get_profile($user_id);
$is_actor_photos = casting_user_uses_actor_portrait_set($user_id);

casting_render_panel_start('ویرایش تصویر', 'photo');
?>
<section class="dash-card panel-wide">
  <h1>ویرایش تصویر</h1>
  <?php if ($is_actor_photos) : ?>
    <p class="lede">سه عکس پروفایل را بارگذاری کنید: کلوزاپ (صورت)، مدیوم (نیم‌تنه)، لانگ (تمام‌قد).</p>
  <?php else : ?>
    <p class="lede">عکس پروفایل خود را بارگذاری یا به‌روز کنید.</p>
  <?php endif; ?>

  <form class="form" method="post" action="profile-photo.php" enctype="multipart/form-data">
    <?php wp_nonce_field('casting_photo'); ?>
    <?php if ($is_actor_photos) : ?>
      <?php casting_render_portrait_upload_fields($profile['portraits'] ?? [], false); ?>
    <?php else : ?>
      <?php casting_render_single_profile_photo_field($profile['portraits'] ?? [], false); ?>
    <?php endif; ?>
    <div class="portrait-form-feedback">
      <?php if ($error !== '') : ?>
        <div class="flash flash-error" role="alert"><?= casting_e($error) ?></div>
      <?php endif; ?>
      <?php casting_render_flash(); ?>
    </div>
    <button class="btn btn-primary" type="submit"><?= $is_actor_photos ? 'ذخیره عکس‌ها' : 'ذخیره عکس پروفایل' ?></button>
  </form>
</section>
<?php casting_render_panel_end(); ?>
