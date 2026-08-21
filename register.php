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

$intent = sanitize_key((string) ($_GET['intent'] ?? $_POST['intent'] ?? ''));
if ($intent === 'cart') {
    $_SESSION['casting_login_intent'] = 'cart';
}

$register_path = sanitize_key((string) ($_GET['path'] ?? $_POST['path'] ?? ''));
if (!in_array($register_path, ['talent', 'hire'], true)) {
    $register_path = '';
}
$prefer_activity_category = $register_path === 'talent' ? 'acting' : ($register_path === 'hire' ? 'directing' : '');

$error = '';
$focus_field = '';
$invalid_fields = [];
$password_mismatch = false;
$otp_notice = '';
$name = '';
$username = '';
$email = '';
$gender = '';
$look = '';
$mobile = '';
$mobile2 = '';
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
$award_items = [];
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
$referral_code = '';
$pending_media = casting_register_pending_media_get();
$pending_portraits = $pending_media['portraits'];
$pending_video = $pending_media['video'];

$reg_invalid = static function (string $key) use (&$invalid_fields): string {
    return in_array($key, $invalid_fields, true) ? ' is-invalid' : '';
};

/** پر کردن فیلدها از پیش‌نویس نشست (رفرش / قطع اینترنت) */
$apply_register_draft = static function () use (
    &$name, &$username, &$email, &$gender, &$look, &$mobile, &$mobile2, &$phone,
    &$province, &$city, &$residence, &$experience, &$height, &$weight,
    &$health_well, &$health_status, &$artistic_has, &$artistic_orgs, &$artistic_other,
    &$activity_license, &$birthdate, &$work_history, &$award_items, &$work_credits, &$artistic_works,
    &$education, &$education_items, &$activities, &$skill_items, &$language_items,
    &$availability, &$eye_color, &$hair_color, &$accent, &$accent_other,
    &$apparent_age_range, &$referral_code
): void {
    $draft = casting_register_draft_get();
    if ($draft === []) {
        return;
    }
    $pick = static function (string $key, string $current) use ($draft): string {
        if ($current !== '') {
            return $current;
        }
        return isset($draft[$key]) ? (string) $draft[$key] : '';
    };
    $name = $pick('name', $name);
    $username = $pick('username', $username);
    $email = $pick('email', $email);
    $gender = $pick('gender', $gender);
    $look = $pick('look', $look);
    $mobile = $pick('mobile', $mobile);
    $mobile2 = $pick('mobile2', $mobile2);
    $phone = $pick('phone', $phone);
    $province = $pick('province', $province);
    $city = $pick('city', $city);
    $residence = $pick('residence', $residence);
    $experience = $pick('experience', $experience);
    $height = $pick('height', $height);
    $weight = $pick('weight', $weight);
    $health_well = $pick('health_well', $health_well);
    $health_status = $pick('health_status', $health_status);
    $artistic_has = $pick('artistic_membership', $artistic_has);
    if ($artistic_orgs === [] && isset($draft['artistic_orgs']) && is_array($draft['artistic_orgs'])) {
        $artistic_orgs = $draft['artistic_orgs'];
    }
    if ($artistic_other === [] && isset($draft['artistic_other_items']) && is_array($draft['artistic_other_items'])) {
        $artistic_other = $draft['artistic_other_items'];
    }
    $activity_license = $pick('activity_license', $activity_license);
    if ($birthdate === '') {
        $birthdate = casting_birthdate_from_jalali_post($draft) ?? '';
    }
    $work_history = $pick('work_history', $work_history);
    if ($award_items === [] && function_exists('casting_parse_award_items_post')) {
        $award_items = casting_parse_award_items_post($draft);
        if ($award_items === [] && !empty($draft['awards'])) {
            $award_items = casting_normalize_award_items($draft['awards']);
        }
    }
    if ($work_credits === [] && function_exists('casting_parse_work_credits_post')) {
        $work_credits = casting_parse_work_credits_post($draft);
    }
    if ($artistic_works === [] && function_exists('casting_parse_artistic_works_post')) {
        $artistic_works = casting_parse_artistic_works_post($draft);
    }
    $education = $pick('education', $education);
    if ($education_items === [] && function_exists('casting_parse_education_items_post')) {
        $education_items = casting_parse_education_items_post($draft);
    }
    if ($activities === [] && function_exists('casting_parse_activities_post')) {
        $activities = casting_parse_activities_post($draft, 0);
    }
    if ($skill_items === [] && function_exists('casting_parse_skill_items_post')) {
        $skill_items = casting_parse_skill_items_post($draft);
    }
    if ($language_items === [] && function_exists('casting_parse_language_items_post')) {
        $language_items = casting_parse_language_items_post($draft);
    }
    $availability = $pick('availability', $availability);
    $eye_color = $pick('eye_color', $eye_color);
    $hair_color = $pick('hair_color', $hair_color);
    $accent = $pick('accent', $accent);
    $accent_other = $pick('accent_other', $accent_other);
    $apparent_age_range = $pick('apparent_age_range', $apparent_age_range);
    $referral_code = $pick('referral_code', $referral_code);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $apply_register_draft();
}

$current = casting_current_user();
if ($current) {
    $existing_role = casting_get_user_role((int) $current->ID);
    if ($existing_role === 'talent') {
        casting_redirect('home.php');
    }
    if (casting_is_employer_role($existing_role)) {
        casting_redirect('home.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (casting_upload_post_too_large()) {
        $error = casting_upload_post_too_large_message();
    } elseif ($current && casting_get_user_role((int) $current->ID) === '') {
        $error = 'با یک حساب وردپرس وارد هستید که نقش ۷ رخ ندارد. اول خارج شوید، بعد ثبت‌نام کنید.';
    }
}

if ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = (string) ($_POST['_wpnonce'] ?? '');
    $otp_action = sanitize_key((string) ($_POST['otp_action'] ?? ''));
    $is_otp_only = $otp_action === 'send' || $otp_action === 'verify';

    if ($nonce === '' || !wp_verify_nonce($nonce, 'casting_register')) {
        $error = 'نشست منقضی شده. یک‌بار صفحه را رفرش کنید و دوباره فرم را بفرستید.';
    } else {
        // همیشه مقادیر فرم را برای نمایش مجدد بخوان
        $name = (string) ($_POST['name'] ?? '');
        $username = (string) ($_POST['username'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');
        $gender = (string) ($_POST['gender'] ?? '');
        $look = (string) ($_POST['look'] ?? '');
        $mobile = (string) ($_POST['mobile'] ?? '');
        $mobile2 = (string) ($_POST['mobile2'] ?? '');
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
        $award_items = casting_parse_award_items_post($_POST);
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
        $referral_code = (string) ($_POST['referral_code'] ?? '');
        casting_register_draft_save($_POST);
        $age_calc = $birthdate !== '' ? casting_age_from_birthdate($birthdate) : null;
        $age_preview = $age_calc !== null ? (string) $age_calc : '';
        $skip_talent_profile = !casting_activities_need_talent_fields($activities);
        $mobile_norm = casting_normalize_mobile($mobile);
        $otp_enabled = casting_mobile_otp_enabled();

        // نگه داشتن عکس/ویدیو در نشست (حتی هنگام ارسال/تأیید OTP)
        $pending_upload_error = casting_register_pending_capture_uploads();
        $pending_media = casting_register_pending_media_get();
        $pending_portraits = $pending_media['portraits'];
        $pending_video = $pending_media['video'];
        if ($pending_upload_error !== '') {
            $error = $pending_upload_error;
        }

        if ($error !== '') {
            // خطای آپلود یا قبلی — فقط فرم را با پیام نشان بده
        } elseif ($is_otp_only) {
            if (!$otp_enabled) {
                $error = 'تأیید موبایل موقتاً غیرفعال است؛ مستقیم ثبت‌نام را کامل کنید.';
            } else {
            $rate_error = casting_rate_limit_check('otp_send');
            if ($rate_error !== null) {
                $error = $rate_error;
            } elseif ($mobile_norm === '' || !preg_match('/^09\d{9}$/', $mobile_norm)) {
                $error = 'شماره موبایل را درست وارد کنید.';
                $focus_field = 'mobile';
                casting_rate_limit_hit('otp_send');
            } elseif (casting_mobile_is_taken($mobile_norm)) {
                $error = 'این شماره موبایل قبلاً ثبت شده است.';
                $focus_field = 'mobile';
                casting_rate_limit_hit('otp_send');
            } elseif ($otp_action === 'send') {
                $send = casting_otp_send('register', $mobile_norm);
                if (!$send['ok']) {
                    $error = $send['error'];
                    casting_rate_limit_hit('otp_send');
                } else {
                    $otp_notice = 'کد تأیید به موبایل ارسال شد.';
                }
            } else {
                $verify = casting_otp_verify('register', $mobile_norm, (string) ($_POST['otp_code'] ?? ''));
                if (!$verify['ok']) {
                    $error = $verify['error'];
                    $focus_field = 'otp_code';
                    casting_rate_limit_hit('otp_send');
                } else {
                    casting_otp_mark_session_verified('register', $mobile_norm);
                    $otp_notice = 'موبایل تأیید شد. حالا ثبت‌نام را کامل کنید.';
                }
            }
            }
        } else {
            $rate_error = casting_rate_limit_check('register');
            if ($rate_error !== null) {
                $error = $rate_error;
            } else {
                $issues = casting_register_collect_required_issues([
                    'name'             => $name,
                    'username'         => $username,
                    'email'            => $email,
                    'password'         => $password,
                    'password2'        => $password2,
                    'mobile'           => $mobile,
                    'birthdate'        => $birthdate,
                    'gender'           => $gender,
                    'look'             => $look,
                    'province'         => $province,
                    'city'             => $city,
                    'experience'       => $experience,
                    'activity_license' => $activity_license,
                    'health_well'      => $health_well,
                    'health_status'    => $health_status,
                    'availability'     => $availability,
                    'height'           => $height,
                    'weight'           => $weight,
                    'activities'       => $activities,
                    'rules_accepted'   => !empty($_POST['rules_accepted']),
                ]);
                if ($issues['errors'] !== []) {
                    $invalid_fields = $issues['fields'];
                    $password_mismatch = in_array('password2', $invalid_fields, true);
                    $error = 'لطفاً فیلدهای ستاره‌دار را کامل کنید: ' . implode(' · ', $issues['errors']);
                    $focus_field = $invalid_fields[0] ?? '';
                } elseif ($otp_enabled && !casting_otp_session_is_verified('register', $mobile_norm)) {
                    $otp_code = (string) ($_POST['otp_code'] ?? '');
                    if ($otp_code !== '') {
                        $verify = casting_otp_verify('register', $mobile_norm, $otp_code);
                        if ($verify['ok']) {
                            casting_otp_mark_session_verified('register', $mobile_norm);
                        } else {
                            $error = $verify['error'];
                            $focus_field = 'otp_code';
                            $invalid_fields = ['otp_code'];
                        }
                    } else {
                        $error = 'ابتدا موبایل را با کد پیامک تأیید کنید.';
                        $focus_field = 'otp_code';
                        $invalid_fields = ['otp_code'];
                    }
                } elseif (casting_mobile_is_taken($mobile_norm)) {
                    $error = 'این شماره موبایل قبلاً ثبت شده است.';
                    $focus_field = 'mobile';
                    $invalid_fields = ['mobile'];
                } else {
                    $mobile2_res = casting_normalize_optional_mobile2($mobile2, $mobile_norm);
                    if (!$mobile2_res['ok']) {
                        $error = $mobile2_res['error'];
                        $focus_field = 'mobile2';
                        $invalid_fields = ['mobile2'];
                    } elseif ($mobile2_res['mobile'] !== '' && casting_mobile_is_taken($mobile2_res['mobile'])) {
                        $error = 'شماره موبایل دوم قبلاً ثبت شده است.';
                        $focus_field = 'mobile2';
                        $invalid_fields = ['mobile2'];
                    } else {
                        $mobile2 = $mobile2_res['mobile'];
                    }
                }

                $otp_ok = !$otp_enabled || casting_otp_session_is_verified('register', $mobile_norm);
                if ($error === '' && !$password_mismatch && $otp_ok) {
                    if (!function_exists('casting_validate_referral_code_for_register')) {
                        require_once __DIR__ . '/includes/referral.php';
                    }
                    $referral_check = casting_validate_referral_code_for_register($referral_code);
                    if (!$referral_check['ok']) {
                        $error = (string) ($referral_check['error'] ?? 'کد معرفی معتبر نیست.');
                        $focus_field = 'referral_code';
                        $invalid_fields = ['referral_code'];
                    }
                }
                if ($error === '' && !$password_mismatch && $otp_ok) {
                    try {
                        $role = casting_infer_role_from_activities($activities);
                        $result = casting_register_user($name, $username, $email, $password, $role);
                        if (!$result['ok']) {
                            $error = $result['error'];
                            $focus_field = casting_register_focus_for_error($error);
                            if ($focus_field !== '') {
                                $invalid_fields = [$focus_field];
                            }
                        } else {
                            $user_id = (int) $result['user_id'];
                            if (trim($referral_code) !== '' && function_exists('casting_apply_referral_code')) {
                                casting_apply_referral_code($user_id, $referral_code);
                            }
                            $profile_save = casting_save_registration_profile($user_id, [
                                'birthdate'        => $birthdate,
                                'gender'           => $gender,
                                'look'             => $look,
                                'mobile'           => $mobile_norm,
                                'mobile2'          => $mobile2,
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
                                'award_items'      => $award_items,
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
                                $focus_field = casting_register_focus_for_error($error);
                                if ($focus_field !== '') {
                                    $invalid_fields = [$focus_field];
                                }
                            } else {
                                $photo = casting_register_apply_pending_media($user_id, !$skip_talent_profile, $skip_talent_profile);
                                if (!$photo['ok']) {
                                    casting_delete_registered_user($user_id);
                                    $error = $photo['error'];
                                    $focus_field = $skip_talent_profile ? 'photo_medium_single' : 'photo_closeup';
                                    $invalid_fields = [$focus_field];
                                }
                            }

                            if ($error === '') {
                                casting_register_draft_clear();
                                casting_mark_mobile_verified($user_id, $mobile_norm);
                                casting_otp_clear_session('register');
                                casting_rate_limit_clear('register');
                                casting_rate_limit_clear('otp_send');
                                if (function_exists('casting_notify_n8n_registration')) {
                                    casting_notify_n8n_registration($user_id);
                                }
                                if (!empty($_SESSION['casting_flash'])) {
                                    unset($_SESSION['casting_flash']);
                                }
                                $login = casting_login($email, $password);
                                if ($login['ok']) {
                                    casting_rate_limit_clear('login');
                                    casting_set_flash('success', 'ثبت‌نام و ورود با موفقیت انجام شد.');
                                    $after_intent = (string) ($_SESSION['casting_login_intent'] ?? '');
                                    unset($_SESSION['casting_login_intent']);
                                    if ($after_intent === 'cart') {
                                        casting_redirect('cart.php');
                                    }
                                    casting_redirect(casting_dashboard_for_role((string) $result['role']));
                                }
                                casting_redirect('login.php?registered=1' . (((string) ($_SESSION['casting_login_intent'] ?? '')) === 'cart' ? '&intent=cart' : ''));
                            }
                        }
                    } catch (Throwable $e) {
                        $error = 'خطای سرور در ثبت‌نام: ' . $e->getMessage();
                    }
                }

                if ($error !== '' || $password_mismatch) {
                    casting_rate_limit_hit('register');
                }
            }
        }
    }
}

$otp_enabled = casting_mobile_otp_enabled();
$mobile_verified = $otp_enabled && casting_otp_session_is_verified('register', casting_normalize_mobile($mobile));
$hide_talent_profile = casting_profile_hides_talent_fields($activities);
$show_artistic_works = casting_activities_show_artistic_works($activities);
$rules_accepted = !empty($_POST['rules_accepted']) || !empty(casting_register_draft_get()['rules_accepted']);

casting_render_head('ثبت‌نام', 'page-register');
casting_render_header('register');

if ($current && casting_get_user_role((int) $current->ID) === '') {
    echo '<div class="flash flash-error" role="alert">' . casting_brandify('شما با یک حساب وردپرس وارد هستید که نقش ۷ رخ ندارد. اول خارج شوید، بعد اینجا ثبت‌نام کنید.') . ' <a href="' . casting_e(wp_logout_url(casting_url('register.php'))) . '">خروج</a></div>';
}

if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
if ($otp_notice !== '') {
    echo '<div class="flash flash-success" role="alert">' . casting_e($otp_notice) . '</div>';
}
$pending_media = casting_register_pending_media_get();
$pending_portraits = $pending_media['portraits'];
$pending_video = $pending_media['video'];
?>
<main class="wrap panel-page">
  <section class="panel panel-wide">
    <h1><?= $register_path === 'hire' ? 'ثبت‌نام کارفرما' : ($register_path === 'talent' ? 'ثبت‌نام هنرمند' : 'ثبت‌نام') ?></h1>
    <p class="lede"><?php
    if ($register_path === 'talent') {
        echo $otp_enabled
            ? 'برای دیده شدن، پروفایل هنری‌ات را بساز. قبل از ایجاد حساب، موبایل را با کد پیامک تأیید کن.'
            : 'برای دیده شدن، پروفایل هنری‌ات را بساز و ثبت‌نام را کامل کن.';
    } elseif ($register_path === 'hire') {
        echo $otp_enabled
            ? 'برای پیدا کردن استعداد، حساب کارگردانی یا تهیه بساز. قبل از ایجاد حساب، موبایل را با کد پیامک تأیید کن.'
            : 'برای پیدا کردن استعداد، حساب کارگردانی یا تهیه بساز و ثبت‌نام را کامل کن.';
    } else {
        echo $otp_enabled
            ? 'اطلاعات پایه، عکس و ویدیو را وارد کنید. قبل از ایجاد حساب، موبایل را با کد پیامک تأیید کنید.'
            : 'اطلاعات پایه، عکس و ویدیو را وارد کنید و ثبت‌نام را کامل کنید.';
    }
    ?></p>
    <p class="lede-req-note" role="note">موارد ستاره‌دار الزامی می‌باشد.</p>

    <form class="form" method="post" action="register.php" enctype="multipart/form-data" autocomplete="on" data-talent-profile-toggle data-register-form<?= $focus_field !== '' ? ' data-focus-field="' . casting_e($focus_field) . '"' : '' ?><?= $invalid_fields !== [] ? ' data-invalid-fields="' . casting_e(implode(',', $invalid_fields)) . '"' : '' ?>>
      <?php wp_nonce_field('casting_register'); ?>
      <?php if ($register_path !== '') : ?>
        <input type="hidden" name="path" value="<?= casting_e($register_path) ?>">
      <?php endif; ?>

      <?php casting_render_activity_fields($activities, true, 0, $prefer_activity_category); ?>

      <div class="field<?= $reg_invalid('name') ?>">
        <label for="name">نام و نام خانوادگی <span class="req-mark">*</span></label>
        <input id="name" name="name" type="text" required autocomplete="name" value="<?= casting_e($name) ?>">
        <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
      </div>

      <div class="form-grid">
        <div class="field<?= $reg_invalid('username') ?>">
          <label for="username">نام کاربری <span class="req-mark">*</span></label>
          <input id="username" name="username" type="text" required minlength="3" autocomplete="username" pattern="[A-Za-z0-9._\-]+" title="فقط حروف انگلیسی، عدد، نقطه، خط تیره" value="<?= casting_e($username) ?>">
          <p class="field-hint">با همین نام کاربری بعداً وارد می‌شوید</p>
          <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
        </div>
        <div class="field<?= $reg_invalid('email') ?>">
          <label for="email">ایمیل <span class="req-mark">*</span></label>
          <input id="email" name="email" type="email" required autocomplete="email" value="<?= casting_e($email) ?>">
          <p class="field-hint">برای ورود و اعلان‌ها. بازیابی رمز با پیامک یا ایمیل هم ممکن است.</p>
          <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
        </div>
      </div>

      <div class="form-grid">
        <div class="field<?= $reg_invalid('password') ?>">
          <label for="password">رمز عبور (حداقل ۸ کاراکتر) <span class="req-mark">*</span></label>
          <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" data-password-source>
          <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
        </div>
        <div class="field<?= $reg_invalid('password2') ?>" data-password-confirm-field<?= $password_mismatch ? ' is-invalid' : '' ?>>
          <label for="password2">تکرار رمز عبور <span class="req-mark">*</span></label>
          <div class="field-control field-control--password-confirm">
            <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password" data-password-confirm<?= $password_mismatch ? ' aria-invalid="true"' : '' ?>>
            <span class="field-inline-error" data-password-mismatch-msg role="alert"<?= $password_mismatch ? '' : ' hidden' ?>>پسورد یکسان نیست</span>
          </div>
          <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
        </div>
      </div>

      <div class="form-grid">
        <div class="field<?= $reg_invalid('mobile') ?>">
          <label for="mobile">موبایل <span class="req-mark">*</span></label>
          <input id="mobile" name="mobile" type="tel" required inputmode="numeric" pattern="09[0-9]{9}" value="<?= casting_e($mobile) ?>" placeholder="09121234567" autocomplete="tel-national">
          <p class="field-hint">این شماره برای دیگر اعضا نمایش داده نمی‌شود.</p>
          <?php if ($mobile_verified) : ?>
            <p class="field-hint otp-verified-hint">موبایل تأیید شد ✓</p>
          <?php endif; ?>
          <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
        </div>
        <div class="field">
          <label for="phone">تلفن ثابت (اختیاری)</label>
          <input id="phone" name="phone" type="tel" inputmode="numeric" value="<?= casting_e($phone) ?>" placeholder="02112345678" autocomplete="tel">
        </div>
      </div>
      <?php casting_render_optional_mobile2_field($mobile2, $reg_invalid('mobile2') !== ''); ?>

      <?php if ($otp_enabled) : ?>
      <div class="otp-verify-block">
        <h2 class="otp-verify-title">تأییدیه موبایل</h2>
        <?php if (!$mobile_verified) : ?>
          <div class="field">
            <label for="otp_code">کد تأیید موبایل <span class="req-mark">*</span></label>
            <input id="otp_code" name="otp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="۶ رقم پیامک‌شده">
          </div>
          <div class="cta-row otp-actions">
            <button class="btn btn-ghost" type="submit" name="otp_action" value="send" formnovalidate>ارسال کد پیامک</button>
            <button class="btn btn-ghost" type="submit" name="otp_action" value="verify" formnovalidate>تأیید کد</button>
          </div>
          <p class="field-hint">اول «ارسال کد پیامک» را بزنید، کد را وارد کنید و «تأیید کد» را بزنید؛ بعد فرم را کامل و ثبت کنید.</p>
        <?php else : ?>
          <input type="hidden" name="otp_code" value="verified">
        <?php endif; ?>
      </div>
      <?php else : ?>
      <div class="otp-verify-block is-inactive" aria-disabled="true">
        <h2 class="otp-verify-title">تأییدیه موبایل</h2>
        <p class="field-hint">این بخش جایگذاری شده و فعلاً فعال نیست. پس از راه‌اندازی پیامک، شماره موبایل در همین‌جا تأیید می‌شود.</p>
        <div class="field">
          <label for="otp_code_placeholder">کد تأیید موبایل</label>
          <input id="otp_code_placeholder" type="text" inputmode="numeric" maxlength="6" placeholder="۶ رقم پیامک‌شده" disabled>
        </div>
        <div class="cta-row otp-actions">
          <button class="btn btn-ghost" type="button" disabled>ارسال کد پیامک</button>
          <button class="btn btn-ghost" type="button" disabled>تأیید کد</button>
        </div>
      </div>
      <?php endif; ?>

      <?php casting_render_jalali_birthday_fields($birthdate, true); ?>
      <div class="field">
        <label for="age_display">سن (خودکار از تاریخ تولد)</label>
        <select id="age_display" data-age-output data-age-plus="<?= (int) casting_body_metric_plus_value('age') ?>" disabled aria-live="polite">
          <option value="">بعد از انتخاب تاریخ پر می‌شود</option>
          <?php foreach (casting_body_metric_options('age') as $opt) : ?>
            <option value="<?= casting_e($opt['value']) ?>" <?= casting_body_metric_select_value('age', $age_preview) === $opt['value'] ? 'selected' : '' ?>><?= casting_e($opt['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <fieldset class="field<?= $reg_invalid('gender') ?>" id="gender">
        <legend>جنسیت <span class="req-mark">*</span></legend>
        <div class="role-grid role-grid-2">
          <?php foreach (casting_gender_labels() as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="gender" value="<?= casting_e($key) ?>" <?= $gender === $key ? 'checked' : '' ?> required>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
      </fieldset>

      <fieldset class="field<?= $reg_invalid('look') ?>" data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
        <legend>رنگ پوست <span class="req-mark" data-talent-required-mark>*</span></legend>
        <div class="role-grid role-grid-3">
          <?php foreach (casting_look_labels() as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="look" value="<?= casting_e($key) ?>" <?= $look === $key ? 'checked' : '' ?> required>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
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
          <label for="height">قد (سانتی‌متر) <span class="req-mark" data-talent-required-mark>*</span></label>
          <?php casting_render_body_metric_select('height', 'height', 'height', $height, 'انتخاب کنید', true); ?>
        </div>
        <div class="field">
          <label for="weight">وزن (کیلوگرم) <span class="req-mark" data-talent-required-mark>*</span></label>
          <?php casting_render_body_metric_select('weight', 'weight', 'weight', $weight, 'انتخاب کنید', true); ?>
        </div>
      </div>

      <div data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?>>
      <?php casting_render_health_fields($health_well, $health_status, true); ?>
      </div>

      <?php casting_render_location_fields($province, $city, '', true, 'form-grid', false); ?>

      <?php casting_render_artistic_membership_fields($artistic_has, $artistic_orgs, $artistic_other); ?>

      <div class="form-grid">
        <fieldset class="field<?= $reg_invalid('activity_license') ?>">
          <legend>دارای پروانه فعالیت <span class="req-mark">*</span></legend>
          <div class="role-grid role-grid-2">
            <?php foreach (casting_yes_no_labels() as $key => $label) : ?>
              <label class="role-option">
                <input type="radio" name="activity_license" value="<?= casting_e($key) ?>" <?= $activity_license === $key ? 'checked' : '' ?> required>
                <span><?= casting_e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
        </fieldset>
        <div class="field<?= $reg_invalid('experience') ?>">
          <label for="experience">سابقه فعالیت (سال) <span class="req-mark">*</span></label>
          <input id="experience" name="experience" type="number" min="0" max="60" required value="<?= casting_e($experience !== '' ? $experience : '0') ?>">
          <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
        </div>
      </div>

      <fieldset class="field<?= $reg_invalid('photo_closeup') || $reg_invalid('photo_medium') || $reg_invalid('photo_long') ? ' is-invalid' : '' ?>" data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?> id="profile-photos-actor">
        <legend>عکس‌های پروفایل <span class="req-mark" data-talent-required-mark>*</span></legend>
        <p class="field-hint">هر سه عکس الزامی است: کلوزاپ، مدیوم و لانگ.<?= $pending_portraits !== [] ? ' عکس‌های قبلی‌تان نگه داشته شده‌اند.' : '' ?></p>
        <?php casting_render_portrait_upload_fields($pending_portraits, true); ?>
        <p class="field-req-hint" data-field-req-hint hidden>عکس‌های ستاره‌دار الزامی هستند.</p>
      </fieldset>

      <fieldset class="field<?= $reg_invalid('photo_medium_single') ?>" data-non-talent-profile-photo<?= $hide_talent_profile ? '' : ' hidden' ?> id="profile-photo-single">
        <legend>عکس پروفایل <span class="req-mark">*</span></legend>
        <p class="field-hint">یک عکس واضح از خودتان بارگذاری کنید.<?= !empty($pending_portraits['medium']) ? ' عکس قبلی نگه داشته شده است.' : '' ?></p>
        <?php casting_render_single_profile_photo_field($pending_portraits, true, 'photo_medium_single'); ?>
        <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
      </fieldset>

      <div class="field" data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?> data-file-preview-card>
        <label for="video">ویدیو معرفی</label>
        <div class="video-preview-frame" data-file-preview-frame<?= $pending_video ? '' : ' hidden' ?>>
          <video controls playsinline preload="metadata" data-file-preview-video<?= $pending_video ? ' src="' . casting_e((string) $pending_video['url']) . '"' : '' ?>></video>
        </div>
        <input id="video" name="video" type="file" accept="video/mp4,video/webm,video/quicktime" data-file-preview-input data-file-preview-kind="video" data-upload-kind="video" data-max-bytes="<?= (int) casting_upload_max_bytes('video') ?>">
        <p class="field-hint">MP4 / WebM / MOV — حداکثر <?= casting_e(casting_upload_max_label_fa('video')) ?> (اختیاری)<?= $pending_video ? ' · ویدیوی قبلی نگه داشته شده است.' : '' ?></p>
      </div>

      <?php casting_render_profile_work_sections(['activities' => $activities, 'work_credits' => $work_credits, 'artistic_works' => $artistic_works]); ?>

      <div class="field">
        <label for="work_history">توضیح بیشتر درباره سابقه کاری (اختیاری)</label>
        <textarea id="work_history" name="work_history" rows="2" placeholder="توضیح کوتاه…"><?= casting_e($work_history) ?></textarea>
      </div>

      <?php casting_render_award_fields($award_items); ?>

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

      <fieldset class="field<?= $reg_invalid('availability') ?>" data-talent-profile-field<?= $hide_talent_profile ? ' hidden' : '' ?> id="availability">
        <legend>وضعیت آمادگی برای همکاری <span class="req-mark" data-talent-required-mark>*</span></legend>
        <div class="role-grid">
          <?php foreach (casting_availability_labels() as $key => $label) : ?>
            <label class="role-option">
              <input type="radio" name="availability" value="<?= casting_e($key) ?>" <?= $availability === $key ? 'checked' : '' ?> required>
              <span><?= casting_e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="field-req-hint" data-field-req-hint hidden>این گزینه ستاره‌دار الزامی است.</p>
      </fieldset>

      <div class="field<?= $reg_invalid('referral_code') ?>">
        <label for="referral_code">کد معرفی (اختیاری)</label>
        <input id="referral_code" name="referral_code" type="text" maxlength="32" autocomplete="off" dir="ltr" value="<?= casting_e($referral_code) ?>" placeholder="7ROKHAB12CD34">
        <p class="field-hint">اگر کسی شما را معرفی کرده، کد معرفی‌اش را اینجا وارد کنید.</p>
      </div>

      <div class="field rules-consent-field<?= $reg_invalid('rules_accepted') ?>" data-rules-consent>
        <label class="checkbox-row">
          <input type="checkbox" name="rules_accepted" value="1" id="rules_accepted" data-rules-consent-checkbox<?= $rules_accepted ? ' checked' : '' ?>>
          <span>قوانین را مطالعه کرده‌ام و می‌پذیرم. <span class="req-mark">*</span> <button type="button" class="link-button" data-rules-lightbox-open>مطالعه قوانین</button></span>
        </label>
        <p class="field-req-hint" data-field-req-hint hidden>تأیید قوانین ستاره‌دار الزامی است.</p>
      </div>

      <button class="btn btn-primary" type="submit" name="casting_submit" value="1" data-register-submit<?= $rules_accepted ? '' : ' disabled' ?>>ایجاد حساب</button>
    </form>

    <p class="form-foot">
      قبلاً ثبت‌نام کرده‌اید؟ <a href="login.php">ورود به پنل کاربری</a>
    </p>
  </section>
</main>
<div class="rules-lightbox" data-rules-lightbox aria-hidden="true">
  <div class="rules-lightbox-panel" role="dialog" aria-modal="true" aria-labelledby="rules-lightbox-title">
    <h2 class="rules-lightbox-title" id="rules-lightbox-title">قوانین <?= casting_brand_html() ?></h2>
    <p class="meta">با عضویت و استفاده از پورتال، این قوانین را می‌پذیرید.</p>
    <?php casting_render_rules_list(); ?>
  </div>
</div>
<script>
window.CASTING_REGISTER = {
  uploadUrl: <?= wp_json_encode(casting_url('register-pending-upload.php')) ?>,
  nonce: <?= wp_json_encode(wp_create_nonce('casting_register')) ?>,
  draftKey: "casting_register_draft_v1"
};
</script>
<?php casting_render_footer(); ?>
