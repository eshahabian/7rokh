<?php
declare(strict_types=1);

/**
 * کپچای ریاضی ساده مبتنی بر نشست (ضد ربات سبک)
 */

function casting_captcha_session_key(): string
{
    return 'casting_math_captcha';
}

/**
 * @return array{question:string,token:string}
 */
function casting_captcha_issue(): array
{
    $a = random_int(2, 9);
    $b = random_int(1, 9);
    $op = random_int(0, 1) === 0 ? '+' : '−';
    if ($op === '+') {
        $answer = $a + $b;
        $question = $a . ' + ' . $b;
    } else {
        if ($b > $a) {
            [$a, $b] = [$b, $a];
        }
        $answer = $a - $b;
        $question = $a . ' − ' . $b;
    }
    $token = bin2hex(random_bytes(8));
    $_SESSION[casting_captcha_session_key()] = [
        'answer' => (string) $answer,
        'token'  => $token,
        'at'     => time(),
    ];

    return ['question' => $question . ' = ؟', 'token' => $token];
}

/**
 * @return array{ok:bool,error:string}
 */
function casting_captcha_verify(string $answer_raw, string $token): array
{
    $store = $_SESSION[casting_captcha_session_key()] ?? null;
    unset($_SESSION[casting_captcha_session_key()]);

    if (!is_array($store) || ($store['token'] ?? '') === '' || !hash_equals((string) $store['token'], $token)) {
        return ['ok' => false, 'error' => 'کپچا منقضی شده. صفحه را تازه کنید و دوباره تلاش کنید.'];
    }
    if ((time() - (int) ($store['at'] ?? 0)) > 900) {
        return ['ok' => false, 'error' => 'کپچا منقضی شده. دوباره وارد کنید.'];
    }

    $answer = preg_replace('/\D+/', '', casting_fa_to_en_digits(trim($answer_raw))) ?? '';
    if ($answer === '' || !hash_equals((string) ($store['answer'] ?? ''), $answer)) {
        return ['ok' => false, 'error' => 'پاسخ کپچا اشتباه است.'];
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * بعد از عبور موفق از کپچا در مرحله OTP، برای ادامه ثبت‌نام دوباره نخواهیم.
 */
function casting_captcha_mark_register_passed(string $mobile): void
{
    $_SESSION['casting_register_captcha_ok'] = [
        'mobile' => $mobile,
        'at'     => time(),
    ];
}

function casting_captcha_register_passed_for(string $mobile): bool
{
    $row = $_SESSION['casting_register_captcha_ok'] ?? null;
    if (!is_array($row)) {
        return false;
    }
    if ((time() - (int) ($row['at'] ?? 0)) > 1800) {
        unset($_SESSION['casting_register_captcha_ok']);

        return false;
    }

    return hash_equals((string) ($row['mobile'] ?? ''), $mobile);
}

function casting_captcha_clear_register_passed(): void
{
    unset($_SESSION['casting_register_captcha_ok']);
}

function casting_fa_to_en_digits(string $value): string
{
    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return str_replace($fa, $en, $value);
}

function casting_render_captcha_field(string $invalid_class = ''): void
{
    $captcha = casting_captcha_issue();
    ?>
<div class="field captcha-field<?= $invalid_class !== '' ? ' ' . casting_e($invalid_class) : '' ?>">
  <label for="captcha_answer">کد امنیتی <span class="req-mark">*</span></label>
  <div class="captcha-row">
    <span class="captcha-question" aria-hidden="true"><?= casting_e($captcha['question']) ?></span>
    <input type="hidden" name="captcha_token" value="<?= casting_e($captcha['token']) ?>">
    <input id="captcha_answer" name="captcha_answer" type="text" inputmode="numeric" required autocomplete="off" placeholder="پاسخ" aria-label="پاسخ کپچا: <?= casting_e($captcha['question']) ?>">
  </div>
  <p class="field-hint">حاصل عبارت بالا را وارد کنید.</p>
  <p class="field-req-hint" data-field-req-hint hidden>کپچا الزامی است.</p>
</div>
    <?php
}
