<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/layout.php';

$user = casting_require_login('talent');
$user_id = (int) $user->ID;
$profile = casting_get_profile($user_id);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_profile')) {
        $error = 'درخواست نامعتبر است. دوباره تلاش کنید.';
    } else {
        $upload = casting_handle_photo_upload($user_id);
        if (!$upload['ok']) {
            $error = $upload['error'];
        } else {
            $save = casting_save_profile($user_id, [
                'age'        => $_POST['age'] ?? '',
                'gender'     => $_POST['gender'] ?? '',
                'city'       => $_POST['city'] ?? '',
                'height'     => $_POST['height'] ?? '',
                'experience' => $_POST['experience'] ?? '',
                'look'       => $_POST['look'] ?? '',
                'skills'     => $_POST['skills'] ?? '',
                'bio'        => $_POST['bio'] ?? '',
                'video_url'  => $_POST['video_url'] ?? '',
                'visible'    => !empty($_POST['visible']),
            ]);
            if (!$save['ok']) {
                $error = $save['error'];
            } else {
                $success = 'پروفایل ذخیره شد.';
                $profile = casting_get_profile($user_id);
            }
        }
    }
}

casting_render_head('پروفایل هنرجو', 'page-profile');
casting_render_header('profile');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<main class="wrap panel-page">
  <section class="panel panel-wide">
    <h1>پروفایل هنرجو</h1>
    <p class="lede">اطلاعات و عکس خود را کامل کنید تا کارفرماها شما را پیدا کنند.</p>

    <form class="form" method="post" action="" enctype="multipart/form-data" data-loading>
      <?php wp_nonce_field('casting_profile'); ?>

      <div class="photo-row">
        <div class="photo-preview">
          <?php if ($profile['photo_url'] !== '') : ?>
            <img src="<?= casting_e($profile['photo_url']) ?>" alt="عکس پروفایل">
          <?php else : ?>
            <div class="photo-placeholder">بدون عکس</div>
          <?php endif; ?>
        </div>
        <div class="field" style="flex:1">
          <label for="photo">عکس پروفایل (JPG / PNG / WebP — حداکثر ۵ مگابایت)</label>
          <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
        </div>
      </div>

      <div class="form-grid">
        <div class="field">
          <label for="age">سن</label>
          <input id="age" name="age" type="number" min="5" max="100" required value="<?= casting_e($profile['age']) ?>">
        </div>
        <div class="field">
          <label for="gender">جنسیت</label>
          <select id="gender" name="gender" required>
            <option value="">انتخاب کنید</option>
            <?php foreach (casting_gender_labels() as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $profile['gender'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="city">شهر</label>
          <input id="city" name="city" type="text" required value="<?= casting_e($profile['city']) ?>">
        </div>
        <div class="field">
          <label for="height">قد (سانتی‌متر)</label>
          <input id="height" name="height" type="number" min="80" max="230" required value="<?= casting_e($profile['height']) ?>">
        </div>
        <div class="field">
          <label for="experience">سابقه (سال)</label>
          <input id="experience" name="experience" type="number" min="0" max="60" value="<?= casting_e($profile['experience'] !== '' ? $profile['experience'] : '0') ?>">
        </div>
        <div class="field">
          <label for="look">تیپ / چهره</label>
          <input id="look" name="look" type="text" placeholder="مثلاً کلاسیک، مدرن، کودکانه…" value="<?= casting_e($profile['look']) ?>">
        </div>
      </div>

      <div class="field">
        <label for="skills">مهارت‌ها</label>
        <input id="skills" name="skills" type="text" placeholder="بازیگری، دوبله، حرکات نمایشی…" value="<?= casting_e($profile['skills']) ?>">
      </div>

      <div class="field">
        <label for="bio">درباره من / سوابق</label>
        <textarea id="bio" name="bio" rows="4"><?= casting_e($profile['bio']) ?></textarea>
      </div>

      <div class="field">
        <label for="video_url">لینک ویدیو معرفی (آپارات / یوتیوب — اختیاری)</label>
        <input id="video_url" name="video_url" type="url" placeholder="https://" value="<?= casting_e($profile['video_url']) ?>">
      </div>

      <label class="check-row">
        <input type="checkbox" name="visible" value="1" <?= $profile['visible'] ? 'checked' : '' ?>>
        <span>پروفایل برای کارفرماها قابل مشاهده باشد</span>
      </label>

      <button class="btn btn-primary" type="submit">ذخیره پروفایل</button>
    </form>
  </section>
</main>
<?php casting_render_footer(); ?>
