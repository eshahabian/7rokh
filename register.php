<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/layout.php';

casting_nocache();

$error = '';
$name = '';
$username = '';
$email = '';
$role = 'talent';
$gender = '';
$look = '';
$city = '';
$residence = '';
$experience = '';
$birthdate = '';
$work_history = '';
$work_credits = [];
$education = '';
$education_items = [];
$age_preview = '';

$current = casting_current_user();
if ($current) {
    $existing_role = casting_get_user_role((int) $current->ID);
    if ($existing_role === 'talent') {
        casting_redirect('dashboard-talent.php');
    }
    if (casting_is_employer_role($existing_role)) {
        casting_redirect('dashboard-employer.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_register')) {
        $error = 'نشست منقضی شده. یک‌بار صفحه را رفرش کنید و دوباره فرم را بفرستید.';
    } else {
        $name = (string) ($_POST['name'] ?? '');
        $username = (string) ($_POST['username'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');
        $role = (string) ($_POST['role'] ?? 'talent');
        $gender = (string) ($_POST['gender'] ?? '');
        $look = (string) ($_POST['look'] ?? '');
        $city = (string) ($_POST['city'] ?? '');
        $residence = (string) ($_POST['residence'] ?? '');
        $experience = (string) ($_POST['experience'] ?? '');
        $birthdate = casting_birthdate_from_jalali_post($_POST) ?? '';
        $work_history = (string) ($_POST['work_history'] ?? '');
        $work_credits = casting_parse_work_credits_post($_POST);
        $education = (string) ($_POST['education'] ?? '');
        $education_items = casting_parse_education_items_post($_POST);
        $age_calc = $birthdate !== '' ? casting_age_from_birthdate($birthdate) : null;
        $age_preview = $age_calc !== null ? (string) $age_calc : '';

        if ($password !== $password2) {
            $error = 'تکرار رمز عبور مطابقت ندارد.';
        } elseif ($birthdate === '' || $age_calc === null) {
            $error = 'تاریخ تولد شمسی را کامل و درست انتخاب کنید.';
        } elseif (!array_key_exists($gender, casting_gender_labels())) {
            $error = 'جنسیت را انتخاب کنید (زن یا مرد).';
        } else {
            try {
                $result = casting_register_user($name, $username, $email, $password, $role);
                if (!$result['ok']) {
                    $error = $result['error'];
                } else {
                    $user_id = (int) $result['user_id'];
                    $profile_save = casting_save_registration_profile($user_id, [
                        'birthdate'    => $birthdate,
                        'gender'       => $gender,
                        'look'         => $look,
                        'city'         => $city,
                        'residence'    => $residence,
                        'experience'   => $experience,
                        'work_history' => $work_history,
                        'work_credits'    => $work_credits,
                        'education'       => $education,
                        'education_items' => $education_items,
                    ]);
                    if (!$profile_save['ok']) {
                        $error = $profile_save['error'];
                    } else {
                        $photo = casting_handle_photo_upload($user_id);
                        if (!$photo['ok']) {
                            $error = $photo['error'];
                        } else {
                            $video = casting_handle_video_upload($user_id);
                            if (!$video['ok']) {
                                $error = $video['error'];
                            }
                        }
                    }

                    if ($error === '') {
                        $portal = $result['role'] === 'talent' ? 'talent' : 'employer';
                        $login = casting_login($email, $password, $portal);
                        if ($login['ok']) {
                            $dest = $result['role'] === 'talent'
                                ? 'dashboard-talent.php?welcome=1'
                                : 'dashboard-employer.php?welcome=1';
                            casting_redirect($dest);
                        }
                        $login_page = $result['role'] === 'talent' ? 'login-talent.php' : 'login-employer.php';
                        casting_redirect($login_page . '?registered=1');
                    }
                }
            } catch (Throwable $e) {
                $error = 'خطای سرور در ثبت‌نام: ' . $e->getMessage();
            }
        }
    }
}

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

    <form class="form" method="post" action="register.php" enctype="multipart/form-data" autocomplete="on">
      <?php wp_nonce_field('casting_register'); ?>

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

      <?php casting_render_jalali_birthday_fields($birthdate, true); ?>
      <div class="form-grid">
        <div class="field">
          <label for="age_display">سن (خودکار)</label>
          <input id="age_display" type="text" readonly value="<?= $age_preview !== '' ? casting_e($age_preview) . ' سال' : '' ?>" data-age-output placeholder="بعد از انتخاب تاریخ پر می‌شود">
        </div>
        <fieldset class="field">
          <legend>جنسیت</legend>
          <div class="role-grid role-grid-2">
            <?php foreach (casting_gender_labels() as $key => $label) : ?>
              <label class="role-option">
                <input type="radio" name="gender" value="<?= casting_e($key) ?>" <?= $gender === $key ? 'checked' : '' ?> required>
                <span><?= casting_e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      </div>

      <fieldset class="field">
        <legend>چهره</legend>
        <div class="role-grid role-grid-2">
          <?php foreach (casting_look_labels() as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="look" value="<?= casting_e($key) ?>" <?= $look === $key ? 'checked' : '' ?> required>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="form-grid">
        <div class="field">
          <label for="city">شهر</label>
          <input id="city" name="city" type="text" required value="<?= casting_e($city) ?>" placeholder="مثلاً تهران">
        </div>
        <div class="field">
          <label for="residence">محل سکونت</label>
          <input id="residence" name="residence" type="text" required value="<?= casting_e($residence) ?>" placeholder="منطقه / محله">
        </div>
        <div class="field">
          <label for="experience">تعداد سال سابقه</label>
          <input id="experience" name="experience" type="number" min="0" max="60" required value="<?= casting_e($experience !== '' ? $experience : '0') ?>">
        </div>
      </div>

      <div class="form-grid">
        <div class="field">
          <label for="photo">عکس پروفایل</label>
          <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
          <p class="field-hint">JPG / PNG / WebP — حداکثر ۵ مگابایت</p>
        </div>
        <div class="field">
          <label for="video">ویدیو معرفی</label>
          <input id="video" name="video" type="file" accept="video/mp4,video/webm,video/quicktime">
          <p class="field-hint">MP4 / WebM / MOV — حداکثر ۴۰ مگابایت</p>
        </div>
      </div>

      <?php casting_render_work_credits_fields($work_credits); ?>

      <div class="field">
        <label for="work_history">توضیح بیشتر درباره سابقه کاری (اختیاری)</label>
        <textarea id="work_history" name="work_history" rows="2" placeholder="توضیح کوتاه…"><?= casting_e($work_history) ?></textarea>
      </div>

      <?php casting_render_education_fields($education_items); ?>

      <div class="field">
        <label for="education">توضیح بیشتر درباره تحصیل (اختیاری)</label>
        <textarea id="education" name="education" rows="2" placeholder="رشته یا توضیح بیشتر…"><?= casting_e($education) ?></textarea>
      </div>

      <fieldset class="field">
        <legend>نقش شما</legend>
        <div class="role-grid">
          <?php foreach (CASTING_ROLES as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="role" value="<?= casting_e($key) ?>" <?= $role === $key ? 'checked' : '' ?>>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <button class="btn btn-primary" type="submit" name="casting_submit" value="1">ایجاد حساب</button>
    </form>

    <p class="form-foot">
      قبلاً ثبت‌نام کرده‌اید؟
      <a href="login-talent.php">ورود هنرجو</a>
      ·
      <a href="login-employer.php">ورود کارفرما</a>
    </p>
  </section>
</main>
<?php casting_render_footer(); ?>
