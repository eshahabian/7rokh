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
        $photo = casting_handle_photo_upload($user_id);
        if (!$photo['ok']) {
            $error = $photo['error'];
        } else {
            $video = casting_handle_video_upload($user_id);
            if (!$video['ok']) {
                $error = $video['error'];
            } else {
                $save = casting_save_profile($user_id, [
                    'birthdate'    => casting_birthdate_from_jalali_post($_POST) ?? '',
                    'age'          => $_POST['age'] ?? '',
                    'gender'       => $_POST['gender'] ?? '',
                    'city'         => $_POST['city'] ?? '',
                    'residence'    => $_POST['residence'] ?? '',
                    'height'       => $_POST['height'] ?? '',
                    'experience'   => $_POST['experience'] ?? '',
                    'look'         => $_POST['look'] ?? '',
                    'skills'       => $_POST['skills'] ?? '',
                    'bio'          => $_POST['bio'] ?? '',
                    'work_history' => $_POST['work_history'] ?? '',
                    'work_credits'    => casting_parse_work_credits_post($_POST),
                    'education'       => $_POST['education'] ?? '',
                    'education_items' => casting_parse_education_items_post($_POST),
                    'video_url'    => $_POST['video_url'] ?? '',
                    'visible'      => !empty($_POST['visible']),
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
    <p class="lede">اطلاعات، عکس و ویدیو را کامل کنید تا کارفرماها شما را پیدا کنند.</p>

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
          <label for="photo">عکس پروفایل</label>
          <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
          <p class="field-hint">JPG / PNG / WebP — حداکثر ۵ مگابایت</p>
        </div>
      </div>

      <?php casting_render_jalali_birthday_fields($profile['birthdate'], false); ?>
      <div class="form-grid">
        <div class="field">
          <label for="age_display">سن (خودکار)</label>
          <input id="age_display" type="text" readonly data-age-output value="<?= $profile['age'] !== '' ? casting_e($profile['age']) . ' سال' : '' ?>">
          <input type="hidden" name="age" value="<?= casting_e($profile['age']) ?>">
        </div>
        <div class="field">
          <label for="gender">جنسیت</label>
          <select id="gender" name="gender">
            <option value="">انتخاب کنید</option>
            <?php foreach (casting_gender_labels() as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $profile['gender'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
        <label for="city">شهر</label>
        <input id="city" name="city" type="text" value="<?= casting_e($profile['city']) ?>">
      </div>
        <div class="field">
          <label for="residence">محل سکونت</label>
          <input id="residence" name="residence" type="text" value="<?= casting_e($profile['residence']) ?>">
        </div>
        <div class="field">
          <label for="height">قد (سانتی‌متر)</label>
          <input id="height" name="height" type="number" min="80" max="230" value="<?= casting_e($profile['height']) ?>">
        </div>
        <div class="field">
          <label for="experience">تعداد سال سابقه</label>
          <input id="experience" name="experience" type="number" min="0" max="60" value="<?= casting_e($profile['experience'] !== '' ? $profile['experience'] : '0') ?>">
        </div>
        <div class="field">
          <label for="look">چهره</label>
          <select id="look" name="look">
            <option value="">انتخاب کنید</option>
            <?php foreach (casting_look_labels() as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= $profile['look'] === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="skills">مهارت‌ها</label>
          <input id="skills" name="skills" type="text" value="<?= casting_e($profile['skills']) ?>">
        </div>
      </div>

      <?php casting_render_work_credits_fields($profile['work_credits'] ?? []); ?>

      <div class="field">
        <label for="work_history">توضیح بیشتر درباره سابقه کاری (اختیاری)</label>
        <textarea id="work_history" name="work_history" rows="2"><?= casting_e($profile['work_history']) ?></textarea>
      </div>

      <?php casting_render_education_fields($profile['education_items'] ?? []); ?>

      <div class="field">
        <label for="education">توضیح بیشتر درباره تحصیل (اختیاری)</label>
        <textarea id="education" name="education" rows="2"><?= casting_e($profile['education']) ?></textarea>
      </div>

      <div class="field">
        <label for="bio">درباره من</label>
        <textarea id="bio" name="bio" rows="3"><?= casting_e($profile['bio']) ?></textarea>
      </div>

      <div class="field">
        <label for="video">آپلود ویدیو معرفی</label>
        <input id="video" name="video" type="file" accept="video/mp4,video/webm,video/quicktime">
        <p class="field-hint">MP4 / WebM / MOV — حداکثر ۴۰ مگابایت</p>
        <?php if ($profile['video_file_url'] !== '') : ?>
          <p class="field-hint"><a href="<?= casting_e($profile['video_file_url']) ?>" target="_blank" rel="noopener">ویدیو فعلی</a></p>
        <?php endif; ?>
      </div>

      <div class="field">
        <label for="video_url">یا لینک ویدیو (آپارات / یوتیوب)</label>
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
