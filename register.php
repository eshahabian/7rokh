<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/rules-content.php';
if (is_file(__DIR__ . '/includes/webhook.php')) {
    require_once __DIR__ . '/includes/webhook.php';
}
require_once __DIR__ . '/includes/layout.php';

casting_nocache();

$error = '';
$name = '';
$username = '';
$email = '';
$gender = '';
$look = '';
$mobile = '';
$phone = '';
$province = '';
$city = '';
$residence = '';
$experience = '';
$height = '';
$weight = '';
$health_well = '';
$health_status = '';
$artistic_has = '';
$artistic_orgs = [];
$artistic_other = [];
$activity_license = '';
$birthdate = '';
$work_history = '';
$work_credits = [];
$artistic_works = [];
$education = '';
$education_items = [];
$activities = [];
$skill_items = [];
$language_items = [];
$availability = '';
$eye_color = '';
$hair_color = '';
$accent = '';
$accent_other = '';
$apparent_age_range = '';
$age_preview = '';

$current = casting_current_user();
if ($current) {
    $existing_role = casting_get_user_role((int) $current->ID);
    if ($existing_role === 'talent') {
        casting_redirect('panel.php');
    }
    if (casting_is_employer_role($existing_role)) {
        casting_redirect('panel.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($current && casting_get_user_role((int) $current->ID) === '') {
        $error = 'با یک حساب وردپرس وارد هستید که نقش هفت رخ ندارد. اول خارج شوید، بعد ثبت‌نام کنید.';
    }
}

if ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_register')) {
        $error = 'نشست منقضی شده. یک‌بار صفحه را رفرش کنید و دوباره فرم را بفرستید.';
    } else {
        $name = (string) ($_POST['name'] ?? '');
        $username = (string) ($_POST['username'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');
        $gender = (string) ($_POST['gender'] ?? '');
        $look = (string) ($_POST['look'] ?? '');
        $mobile = (string) ($_POST['mobile'] ?? '');
        $phone = (string) ($_POST['phone'] ?? '');
        $province = (string) ($_POST['province'] ?? '');
        $city = (string) ($_POST['city'] ?? '');
        $residence = (string) ($_POST['residence'] ?? '');
        $experience = (string) ($_POST['experience'] ?? '');
        $height = (string) ($_POST['height'] ?? '');
        $weight = (string) ($_POST['weight'] ?? '');
        $health_parsed = casting_parse_health_post($_POST);
        $health_well = $health_parsed['well'];
        $health_status = $health_parsed['detail'];
        $artistic_parsed = casting_parse_artistic_membership_post($_POST);
        $artistic_has = $artistic_parsed['has'];
        $artistic_orgs = $artistic_parsed['orgs'];
        $artistic_other = $artistic_parsed['other_items'];
        $activity_license = (string) ($_POST['activity_license'] ?? '');
        $birthdate = casting_birthdate_from_jalali_post($_POST) ?? '';
        $work_history = (string) ($_POST['work_history'] ?? '');
        $work_credits = casting_parse_work_credits_post($_POST);
        $artistic_works = casting_parse_artistic_works_post($_POST);
        $education = (string) ($_POST['education'] ?? '');
        $education_items = casting_parse_education_items_post($_POST);
        $activities = casting_parse_activities_post($_POST);
        $skill_items = casting_parse_skill_items_post($_POST);
        $language_items = casting_parse_language_items_post($_POST);
        $availability = (string) ($_POST['availability'] ?? '');
        $eye_color = (string) ($_POST['eye_color'] ?? '');
        $hair_color = (string) ($_POST['hair_color'] ?? '');
        $accent = (string) ($_POST['accent'] ?? '');
        $accent_other = (string) ($_POST['accent_other'] ?? '');
        $apparent_age_range = (string) ($_POST['apparent_age_range'] ?? '');
        $age_calc = $birthdate !== '' ? casting_age_from_birthdate($birthdate) : null;
        $age_preview = $age_calc !== null ? (string) $age_calc : '';
        $skip_talent_profile = !casting_activities_has_acting($activities);

        if ($password !== $password2) {
            $error = 'تکرار رمز عبور مطابقت ندارد.';
        } elseif ($birthdate === '' || $age_calc === null) {
            $error = 'تاریخ تولد شمسی را کامل و درست انتخاب کنید.';
        } elseif (!array_key_exists($gender, casting_gender_labels())) {
            $error = 'جنسیت را انتخاب کنید.';
        } elseif ($activities === []) {
            $error = 'حداقل یک تخصص از نوع فعالیت انتخاب کنید.';
        } elseif (!$skip_talent_profile && ($health_err = casting_validate_health_fields($health_parsed, true)) !== null) {
            $error = $health_err;
        } elseif (empty($_POST['rules_accepted'])) {
            $error = 'برای ثبت‌نام باید قوانین را مطالعه و تأیید کنید.';
        } elseif (!$skip_talent_profile && !array_key_exists($availability, casting_availability_labels())) {
            $error = 'وضعیت آمادگی برای همکاری را انتخاب کنید.';
        } else {
            try {
                $role = casting_infer_role_from_activities($activities);
                $result = casting_register_user($name, $username, $email, $password, $role);
                if (!$result['ok']) {
                    $error = $result['error'];
                } else {
                    $user_id = (int) $result['user_id'];
                    $profile_save = casting_save_registration_profile($user_id, [
                        'birthdate'        => $birthdate,
                        'gender'           => $gender,
                        'look'             => $look,
                        'mobile'           => $mobile,
                        'phone'            => $phone,
                        'province'         => $province,
                        'city'             => $city,
                        'residence'        => $residence,
                        'experience'       => $experience,
                        'height'           => $height,
                        'weight'           => $weight,
                        'health_well'      => $health_well,
                        'health_status'    => $health_status,
                        'artistic_membership' => $artistic_has,
                        'artistic_orgs'       => $artistic_orgs,
                        'artistic_other_items'=> $artistic_other,
                        'activity_license'    => $activity_license,
                        'work_history'     => $work_history,
                        'work_credits'     => $work_credits,
                        'artistic_works'   => $artistic_works,
                        'education'        => $education,
                        'education_items'  => $education_items,
                        'activities'       => $activities,
                        'skill_items'      => $skill_items,
                        'language_items'   => $language_items,
                        'availability'     => $availability,
                        'eye_color'        => $eye_color,
                        'hair_color'       => $hair_color,
                        'accent'           => $accent,
                        'accent_other'     => $accent_other,
                        'apparent_age_range' => $apparent_age_range,
                    ]);
                    if (!$profile_save['ok']) {
                        casting_delete_registered_user($user_id);
                        $error = $profile_save['error'];
                    } else {
                        $photo = casting_handle_portrait_uploads($user_id, !$skip_talent_profile);
                        if (!$photo['ok']) {
                            casting_delete_registered_user($user_id);
                            $error = $photo['error'];
                        } else {
                            $video = casting_handle_video_upload($user_id);
                            if (!$video['ok']) {
                                casting_delete_registered_user($user_id);
                                $error = $video['error'];
                            }
                        }
                    }

                    if ($error === '') {
                        if (function_exists('casting_notify_n8n_registration')) {
                            casting_notify_n8n_registration($user_id);
                        }
                        $login = casting_login($email, $password);
                        if ($login['ok']) {
                            casting_redirect(casting_dashboard_for_role((string) $result['role'], 'welcome=1'));
                        }
                        casting_redirect('login.php?registered=1');
                    }
                }
            } catch (Throwable $e) {
                $error = 'خطای سرور در ثبت‌نام: ' . $e->getMessage();
            }
        }
    }
}

$hide_talent_profile = casting_profile_hides_talent_fields($activities);
$show_artistic_works = casting_activities_show_artistic_works($activities);

casting_render_head('ثبت‌نام', 'page-register');
casting_render_header('register');

if ($current && casting_get_user_role((int) $current->ID) === '') {
    echo '<div class="flash flash-error" role="alert">شما با یک حساب وردپرس وارد هستید که نقش هفت رخ ندارد. اول خارج شوید، بعد اینجا ثبت‌نام کنید. <a href="' . casting_e(wp_logout_url(casting_url('register.php'))) . '">خروج</a></div>';
}

if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
?>
<main class="wrap panel-page">
  <section class="panel panel-wide">
    <h1>ثبت‌نام</h1>
    <p class="lede">اطلاعات پایه، عکس و ویدیو را وارد کنید. بعد از ثبت‌نام مستقیم وارد پنل می‌شوید.</p>

    <form class="form" method="post" action="register.php" enctype="multipart/form-data" autocomplete="on" data-talent-profile-toggle>
      <?php wp_nonce_field('casting_register'); ?>

      <?php casting_render_activity_fields($activities, true); ?>

      <div class="field">
        <label for="name">نام و نام خانوادگی</label>
        <input id="name" name="name" type="text" required autocomplete="name" value="<?= casting_e($name) ?>">
      </div>

      <div class="form-grid">
        <div class="field">
          <label for="username">نام کاربری</label>
          <input id="username" name="username" type="text" required minlength="3" autocomplete="username" pattern="[A-Za-z0-9._\-]+" title="فقط حروف انگلیسی، عدد، نقطه، خط تیره" value="<?= casting_e($username) ?>">
          <p class="field-hint">با همین نام کاربری بعداً وارد می‌شوید</p>
        </div>
        <div class="field">
          <label for="email">ایمیل</label>
          <input id="email" name="email" type="email" required autocomplete="email" value="<?= casting_e($email) ?>">
        </div>
      </div>

      <div class="form-grid">
        <div class="field">
          <label for="password">رمز عبور (حداقل ۸ کاراکتر)</label>
          <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="field">
          <label for="password2">تکرار رمز عبور</label>
          <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
        </div>
      </div>

      <div class="form-grid">
        <div class="field">
          <label for="mobile">موبایل</label>
          <input id="mobile" name="mobile" type="tel" required inputmode="numeric" pattern="09[0-9]{9}" value="<?= casting_e($mobile) ?>" placeholder="09121234567" autocomplete="tel-national">
        </div>
        <div class="field">
          <label for="phone">تلفن ثابت (اختیاری)</label>
          <input id="phone" name="phone" type="tel" inputmode="numeric" value="<?= casting_e($phone) ?>" placeholder="02112345678" autocomplete="tel">
        </div>
      </div>

      <?php casting_render_jalali_birthday_fields($birthdate, true); ?>
      <div class="field">
        <label for="age_display">سن (خودکار)</label>
        <input id="age_display" type="text" readonly value="<?= $age_preview !== '' ? casting_e($age_preview) . ' سال' : '' ?>" data-age-output placeholder="بعد از انتخاب تاریخ پر می‌شود">
      </div>

      <fieldset class="field">
        <legend>جنسیت</legend>
        <div class="role-grid role-grid-3">
          <?php foreach (casting_gender_labels() as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="gender" value="<?= casting_e($key) ?>" <?= $gender === $key ? 'checked' : '' ?> required>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="field" data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
        <legend>رنگ پوست</legend>
        <div class="role-grid role-grid-3">
          <?php foreach (casting_look_labels() as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="look" value="<?= casting_e($key) ?>" <?= $look === $key ? 'checked' : '' ?> required>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
      <?php casting_render_talent_trait_fields([
          'eye_color' => $eye_color,
          'hair_color' => $hair_color,
          'accent' => $accent,
          'accent_other' => $accent_other,
          'apparent_age_range' => $apparent_age_range,
      ]); ?>
      </div>

      <div class="form-grid" data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
        <div class="field">
          <label for="height">قد (سانتی‌متر)</label>
          <input id="height" name="height" type="number" min="80" max="230" value="<?= casting_e($height) ?>" placeholder="برای بازیگران">
          <p class="field-hint">برای بازیگران الزامی است</p>
        </div>
        <div class="field">
          <label for="weight">وزن (کیلوگرم)</label>
          <input id="weight" name="weight" type="number" min="20" max="250" value="<?= casting_e($weight) ?>" placeholder="برای بازیگران">
          <p class="field-hint">برای بازیگران الزامی است</p>
        </div>
      </div>

      <div data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
      <?php casting_render_health_fields($health_well, $health_status, true); ?>
      </div>

      <?php casting_render_location_fields($province, $city, '', true); ?>

      <?php casting_render_artistic_membership_fields($artistic_has, $artistic_orgs, $artistic_other); ?>

      <div class="form-grid">
        <fieldset class="field">
          <legend>دارای پروانه فعالیت</legend>
          <div class="role-grid role-grid-2">
            <?php foreach (casting_yes_no_labels() as $key => $label) : ?>
              <label class="role-option">
                <input type="radio" name="activity_license" value="<?= casting_e($key) ?>" <?= $activity_license === $key ? 'checked' : '' ?> required>
                <span><?= casting_e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div class="field">
          <label for="experience">سابقه فعالیت (سال)</label>
          <input id="experience" name="experience" type="number" min="0" max="60" required value="<?= casting_e($experience !== '' ? $experience : '0') ?>">
        </div>
      </div>

      <fieldset class="field" data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
        <legend>عکس‌های پروفایل <span class="req-mark" data-talent-required-mark>*</span></legend>
        <p class="field-hint">هر سه عکس الزامی است: کلوزاپ، مدیوم و لانگ.</p>
        <?php casting_render_portrait_upload_fields([], true); ?>
      </fieldset>

      <div class="field" data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
        <label for="video">ویدیو معرفی</label>
        <input id="video" name="video" type="file" accept="video/mp4,video/webm,video/quicktime">
        <p class="field-hint">MP4 / WebM / MOV — حداکثر ۴۰ مگابایت (اختیاری)</p>
      </div>

      <?php casting_render_profile_work_sections(['activities' => $activities, 'work_credits' => $work_credits, 'artistic_works' => $artistic_works]); ?>

      <div class="field">
        <label for="work_history">توضیح بیشتر درباره سابقه کاری (اختیاری)</label>
        <textarea id="work_history" name="work_history" rows="2" placeholder="توضیح کوتاه…"><?= casting_e($work_history) ?></textarea>
      </div>

      <?php casting_render_education_fields($education_items); ?>

      <div class="field">
        <label for="education">توضیح بیشتر درباره تحصیل (اختیاری)</label>
        <textarea id="education" name="education" rows="2" placeholder="رشته یا توضیح بیشتر…"><?= casting_e($education) ?></textarea>
      </div>

      <div data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
      <?php casting_render_language_fields($language_items); ?>
      </div>

      <div data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
      <?php casting_render_skill_fields($skill_items); ?>
      </div>

      <fieldset class="field" data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
        <legend>وضعیت آمادگی برای همکاری</legend>
        <div class="role-grid">
          <?php foreach (casting_availability_labels() as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="availability" value="<?= casting_e($key) ?>" <?= $availability === $key ? 'checked' : '' ?> required>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="field rules-consent-field" data-rules-consent>
        <label class="checkbox-row">
          <input type="checkbox" name="rules_accepted" value="1" data-rules-consent-checkbox<?= !empty($_POST['rules_accepted']) ? ' checked' : '' ?>>
          <span>قوانین را مطالعه کرده‌ام و می‌پذیرم. <button type="button" class="link-button" data-rules-lightbox-open>مطالعه قوانین</button></span>
        </label>
      </div>

      <button class="btn btn-primary" type="submit" name="casting_submit" value="1" data-register-submit<?= !empty($_POST['rules_accepted']) ? '' : ' disabled' ?>>ایجاد حساب</button>
    </form>

    <p class="form-foot">
      قبلاً ثبت‌نام کرده‌اید؟ <a href="login.php">ورود</a>
    </p>
  </section>
</main>
<div class="rules-lightbox" data-rules-lightbox aria-hidden="true">
  <div class="rules-lightbox-panel" role="dialog" aria-modal="true" aria-labelledby="rules-lightbox-title">
    <h2 class="rules-lightbox-title" id="rules-lightbox-title">قوانین <?= casting_e(casting_brand()) ?></h2>
    <p class="meta">با عضویت و استفاده از پورتال، این قوانین را می‌پذیرید.</p>
    <?php casting_render_rules_list(); ?>
  </div>
</div>
<?php casting_render_footer(); ?>
