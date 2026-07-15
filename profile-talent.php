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
                    'birthdate'        => casting_birthdate_from_jalali_post($_POST) ?? '',
                    'age'              => $_POST['age'] ?? '',
                    'gender'           => $_POST['gender'] ?? '',
                    'mobile'           => $_POST['mobile'] ?? '',
                    'phone'            => $_POST['phone'] ?? '',
                    'province'         => $_POST['province'] ?? '',
                    'city'             => $_POST['city'] ?? '',
                    'height'           => $_POST['height'] ?? '',
                    'weight'           => $_POST['weight'] ?? '',
                    'health_status'    => $_POST['health_status'] ?? '',
                    'experience'       => $_POST['experience'] ?? '',
                    'artistic_membership' => $_POST['artistic_membership'] ?? '',
                    'artistic_orgs'       => $_POST['artistic_orgs'] ?? [],
                    'artistic_other_items'=> $_POST['artistic_other_items'] ?? [],
                    'activity_license'    => $_POST['activity_license'] ?? '',
                    'look'             => $_POST['look'] ?? '',
                    'skill_items'      => casting_parse_skill_items_post($_POST),
                    'language_items'   => casting_parse_language_items_post($_POST),
                    'availability'     => $_POST['availability'] ?? '',
                    'bio'              => $_POST['bio'] ?? '',
                    'work_history'     => $_POST['work_history'] ?? '',
                    'work_credits'     => casting_parse_work_credits_post($_POST),
                    'education'        => $_POST['education'] ?? '',
                    'education_items'  => casting_parse_education_items_post($_POST),
                    'activities'       => casting_parse_activities_post($_POST),
                    'video_url'        => $_POST['video_url'] ?? '',
                    'visible'          => !empty($_POST['visible']),
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

casting_render_panel_start('ویرایش پروفایل', 'profile');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($success !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($success) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card panel-wide">
    <h1>ویرایش پروفایل</h1>
    <p class="lede">اطلاعات، عکس و ویدیو را کامل کنید.</p>

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

      <div class="form-grid">
        <div class="field">
          <label for="mobile">موبایل</label>
          <input id="mobile" name="mobile" type="tel" inputmode="numeric" pattern="09[0-9]{9}" value="<?= casting_e($profile['mobile'] ?? '') ?>" placeholder="09121234567">
        </div>
        <div class="field">
          <label for="phone">تلفن ثابت</label>
          <input id="phone" name="phone" type="tel" inputmode="numeric" value="<?= casting_e($profile['phone'] ?? '') ?>" placeholder="02112345678">
        </div>
      </div>

      <?php casting_render_jalali_birthday_fields($profile['birthdate'], false); ?>
      <div class="field">
        <label for="age_display">سن (خودکار)</label>
        <input id="age_display" type="text" readonly data-age-output value="<?= $profile['age'] !== '' ? casting_e($profile['age']) . ' سال' : '' ?>">
        <input type="hidden" name="age" value="<?= casting_e($profile['age']) ?>">
      </div>

      <fieldset class="field">
        <legend>جنسیت</legend>
        <div class="role-grid role-grid-3">
          <?php foreach (casting_gender_labels() as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="gender" value="<?= casting_e($key) ?>" <?= $profile['gender'] === $key ? 'checked' : '' ?>>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="field">
        <legend>رنگ پوست</legend>
        <div class="role-grid role-grid-3">
          <?php foreach (casting_look_labels() as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="look" value="<?= casting_e($key) ?>" <?= $profile['look'] === $key ? 'checked' : '' ?>>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="form-grid">
        <div class="field">
          <label for="height">قد (سانتی‌متر)</label>
          <input id="height" name="height" type="number" min="80" max="230" value="<?= casting_e($profile['height']) ?>">
          <p class="field-hint">برای بازیگران و مدل‌ها الزامی است</p>
        </div>
        <div class="field">
          <label for="weight">وزن (کیلوگرم)</label>
          <input id="weight" name="weight" type="number" min="20" max="250" value="<?= casting_e($profile['weight'] ?? '') ?>">
          <p class="field-hint">برای بازیگران و مدل‌ها الزامی است</p>
        </div>
      </div>

      <div class="field">
        <label for="health_status">وضعیت سلامت</label>
        <textarea id="health_status" name="health_status" rows="2" maxlength="500" placeholder="مثلاً سالم، بدون محدودیت خاص…"><?= casting_e($profile['health_status'] ?? '') ?></textarea>
      </div>

      <?php casting_render_location_fields((string) ($profile['province'] ?? ''), (string) ($profile['city'] ?? ''), '', false); ?>

      <?php
      $artistic = $profile['artistic_membership'] ?? ['has' => '', 'orgs' => [], 'other_items' => []];
      casting_render_artistic_membership_fields(
          (string) ($artistic['has'] ?? ''),
          is_array($artistic['orgs'] ?? null) ? $artistic['orgs'] : [],
          is_array($artistic['other_items'] ?? null) ? $artistic['other_items'] : []
      );
      ?>

      <div class="form-grid">
        <div class="field">
          <label for="activity_license">دارای پروانه فعالیت</label>
          <select id="activity_license" name="activity_license">
            <option value="">انتخاب کنید</option>
            <?php foreach (casting_yes_no_labels() as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= ($profile['activity_license'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="experience">سابقه فعالیت (سال)</label>
          <input id="experience" name="experience" type="number" min="0" max="60" value="<?= casting_e($profile['experience'] !== '' ? $profile['experience'] : '0') ?>">
        </div>
        <div class="field">
          <label for="availability">وضعیت آمادگی برای همکاری</label>
          <select id="availability" name="availability">
            <option value="">انتخاب کنید</option>
            <?php foreach (casting_availability_labels() as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= ($profile['availability'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php casting_render_activity_fields($profile['activities'] ?? [], true); ?>

      <?php casting_render_language_fields($profile['language_items'] ?? []); ?>

      <?php casting_render_skill_fields($profile['skill_items'] ?? [], (string) ($profile['skills_other'] ?? '')); ?>


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
<?php casting_render_panel_end(); ?>
