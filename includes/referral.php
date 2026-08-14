<?php
declare(strict_types=1);

/**
 * سیستم کد معرفی کاربران پورتال
 *
 * متاها:
 * - casting_referral_code  کد معرف خود کاربر (با پیشوند 7ROKH)
 * - casting_referred_by    شناسه کاربر معرف
 * - casting_referred_at    زمان استفاده از کد
 */

function casting_referral_prefix(): string
{
    return '7ROKH';
}

function casting_referral_normalize_code(string $code): string
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';

    return $code;
}

/**
 * کد ذخیره‌شده را به شکل استاندارد با پیشوند برمی‌گرداند.
 */
function casting_referral_with_prefix(string $code): string
{
    $code = casting_referral_normalize_code($code);
    if ($code === '') {
        return '';
    }
    $prefix = casting_referral_prefix();
    if (str_starts_with($code, $prefix)) {
        return $code;
    }

    return $prefix . $code;
}

function casting_referral_strip_prefix(string $code): string
{
    $code = casting_referral_normalize_code($code);
    if ($code === '') {
        return '';
    }
    $prefix = casting_referral_prefix();
    if (str_starts_with($code, $prefix)) {
        return substr($code, strlen($prefix));
    }

    return $code;
}

function casting_referral_generate_candidate(int $user_id = 0): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $len = strlen($alphabet);
    $out = '';
    try {
        for ($i = 0; $i < 8; $i++) {
            $out .= $alphabet[random_int(0, $len - 1)];
        }
    } catch (Throwable $e) {
        $seed = md5((string) $user_id . microtime(true) . wp_salt('auth'));
        $out = strtoupper(substr(preg_replace('/[^A-Z0-9]/', 'X', base_convert($seed, 16, 36)) ?? 'X', 0, 8));
        $out = str_pad($out, 8, 'X');
    }

    return casting_referral_with_prefix($out);
}

function casting_referral_code_owner_id(string $code): int
{
    $code = casting_referral_normalize_code($code);
    if ($code === '' || strlen($code) < 4) {
        return 0;
    }

    $candidates = array_values(array_unique(array_filter([
        casting_referral_with_prefix($code),
        casting_referral_strip_prefix($code),
        $code,
    ], static fn(string $c): bool => $c !== '' && strlen($c) >= 4)));

    foreach ($candidates as $candidate) {
        $users = get_users([
            'meta_key'   => 'casting_referral_code',
            'meta_value' => $candidate,
            'number'     => 1,
            'fields'     => 'ID',
        ]);
        if (is_array($users) && $users !== []) {
            return (int) $users[0];
        }
    }

    return 0;
}

/**
 * اگر کد قدیمی بدون پیشوند باشد، پیشوند را اضافه و ذخیره می‌کند.
 * رابطهٔ casting_referred_by دست نخورده می‌ماند.
 */
function casting_referral_ensure_prefixed_code(int $user_id): string
{
    $user_id = max(0, $user_id);
    if ($user_id <= 0) {
        return '';
    }

    $existing = casting_referral_normalize_code((string) get_user_meta($user_id, 'casting_referral_code', true));
    if ($existing === '') {
        return '';
    }

    $prefixed = casting_referral_with_prefix($existing);
    if ($prefixed !== $existing) {
        update_user_meta($user_id, 'casting_referral_code', $prefixed);
    }

    return $prefixed;
}

function casting_assign_referral_code(int $user_id): string
{
    $user_id = max(0, $user_id);
    if ($user_id <= 0) {
        return '';
    }

    $existing = casting_referral_ensure_prefixed_code($user_id);
    if ($existing !== '') {
        return $existing;
    }

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $candidate = casting_referral_generate_candidate($user_id + $attempt);
        $owner = casting_referral_code_owner_id($candidate);
        if ($owner > 0 && $owner !== $user_id) {
            continue;
        }
        update_user_meta($user_id, 'casting_referral_code', $candidate);

        return $candidate;
    }

    $fallback = casting_referral_with_prefix('U' . strtoupper(substr(md5((string) $user_id . wp_salt('auth')), 0, 7)));
    update_user_meta($user_id, 'casting_referral_code', $fallback);

    return $fallback;
}

function casting_get_referral_code(int $user_id): string
{
    $user_id = max(0, $user_id);
    if ($user_id <= 0) {
        return '';
    }

    $code = casting_referral_ensure_prefixed_code($user_id);
    if ($code !== '') {
        return $code;
    }

    if (casting_get_user_role($user_id) === '') {
        return '';
    }

    return casting_assign_referral_code($user_id);
}

/**
 * برای کاربران قدیمی که هنوز کد ندارند.
 */
function casting_referral_backfill_missing_codes(int $limit = 500): int
{
    $limit = max(1, min(2000, $limit));
    $users = get_users([
        'fields'     => 'ID',
        'meta_key'   => 'casting_role',
        'meta_query' => [
            'relation' => 'OR',
            [
                'key'     => 'casting_referral_code',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => 'casting_referral_code',
                'value'   => '',
                'compare' => '=',
            ],
        ],
        'number'     => $limit,
    ]);
    if (!is_array($users) || $users === []) {
        return 0;
    }

    $assigned = 0;
    foreach ($users as $id) {
        $id = (int) $id;
        if ($id <= 0 || casting_get_user_role($id) === '') {
            continue;
        }
        if (casting_assign_referral_code($id) !== '') {
            $assigned++;
        }
    }

    return $assigned;
}

/**
 * پیشوند 7ROKH را برای همهٔ کدهای قبلی اعمال می‌کند (بدون تغییر معرف‌ها).
 */
function casting_referral_backfill_prefix(int $limit = 800): int
{
    $limit = max(1, min(2000, $limit));
    $prefix = casting_referral_prefix();
    $users = get_users([
        'fields'     => 'ID',
        'meta_key'   => 'casting_referral_code',
        'meta_query' => [
            [
                'key'     => 'casting_referral_code',
                'value'   => '',
                'compare' => '!=',
            ],
        ],
        'number'     => $limit,
    ]);
    if (!is_array($users) || $users === []) {
        return 0;
    }

    $updated = 0;
    foreach ($users as $id) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        $raw = casting_referral_normalize_code((string) get_user_meta($id, 'casting_referral_code', true));
        if ($raw === '' || str_starts_with($raw, $prefix)) {
            continue;
        }
        if (casting_referral_ensure_prefixed_code($id) !== '') {
            $updated++;
        }
    }

    return $updated;
}

/**
 * یک‌بار در هر درخواست ادمین/پروفایل — جلوگیری از فشار روی دیتابیس
 */
function casting_referral_maybe_backfill(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $flag = (string) get_option('casting_referral_backfill_done', '');
    if ($flag !== '1') {
        $assigned = casting_referral_backfill_missing_codes(800);
        if ($assigned === 0) {
            update_option('casting_referral_backfill_done', '1', false);
        }
    }

    $prefix_flag = (string) get_option('casting_referral_prefix_backfill_done', '');
    if ($prefix_flag !== '1') {
        $updated = casting_referral_backfill_prefix(800);
        if ($updated === 0) {
            update_option('casting_referral_prefix_backfill_done', '1', false);
        }
    }
}

/**
 * @return array{ok:bool,error?:string,referrer_id?:int}
 */
function casting_validate_referral_code_for_register(string $code, int $exclude_user_id = 0): array
{
    $code = casting_referral_normalize_code($code);
    if ($code === '') {
        return ['ok' => true, 'referrer_id' => 0];
    }

    $referrer_id = casting_referral_code_owner_id($code);
    if ($referrer_id <= 0) {
        return ['ok' => false, 'error' => 'کد معرفی معتبر نیست.'];
    }
    if ($exclude_user_id > 0 && $referrer_id === $exclude_user_id) {
        return ['ok' => false, 'error' => 'نمی‌توانید از کد معرفی خودتان استفاده کنید.'];
    }
    if (casting_get_user_role($referrer_id) === '') {
        return ['ok' => false, 'error' => 'کد معرفی معتبر نیست.'];
    }

    return ['ok' => true, 'referrer_id' => $referrer_id];
}

/**
 * @return array{ok:bool,error?:string,referrer_id?:int}
 */
function casting_apply_referral_code(int $new_user_id, string $code): array
{
    $new_user_id = max(0, $new_user_id);
    if ($new_user_id <= 0) {
        return ['ok' => false, 'error' => 'کاربر نامعتبر است.'];
    }

    $code = casting_referral_normalize_code($code);
    if ($code === '') {
        return ['ok' => true, 'referrer_id' => 0];
    }

    $existing = (int) get_user_meta($new_user_id, 'casting_referred_by', true);
    if ($existing > 0) {
        return ['ok' => true, 'referrer_id' => $existing];
    }

    $check = casting_validate_referral_code_for_register($code, $new_user_id);
    if (!$check['ok']) {
        return $check;
    }

    $referrer_id = (int) ($check['referrer_id'] ?? 0);
    if ($referrer_id <= 0) {
        return ['ok' => true, 'referrer_id' => 0];
    }

    update_user_meta($new_user_id, 'casting_referred_by', $referrer_id);
    update_user_meta($new_user_id, 'casting_referred_at', current_time('mysql'));

    return ['ok' => true, 'referrer_id' => $referrer_id];
}

function casting_user_referred_by(int $user_id): int
{
    $user_id = max(0, $user_id);
    if ($user_id <= 0) {
        return 0;
    }

    return max(0, (int) get_user_meta($user_id, 'casting_referred_by', true));
}

/**
 * زمان عضویت / فعال بودن از تاریخ ثبت‌نام
 */
function casting_user_membership_started_at(int $user_id): string
{
    $user_id = max(0, $user_id);
    if ($user_id <= 0) {
        return '';
    }

    $meta = trim((string) get_user_meta($user_id, 'casting_registered_at', true));
    if ($meta !== '') {
        return $meta;
    }

    $user = get_user_by('id', $user_id);
    if (!$user) {
        return '';
    }

    return (string) $user->user_registered;
}

function casting_user_active_duration_label(int $user_id): string
{
    $started = casting_user_membership_started_at($user_id);
    if ($started === '') {
        return '—';
    }

    $ts = strtotime($started);
    if ($ts === false) {
        return '—';
    }

    $diff = max(0, time() - $ts);
    $days = (int) floor($diff / DAY_IN_SECONDS);
    if ($days < 1) {
        $hours = (int) floor($diff / HOUR_IN_SECONDS);
        if ($hours < 1) {
            return 'کمتر از یک ساعت';
        }

        return $hours . ' ساعت';
    }
    if ($days < 30) {
        return $days . ' روز';
    }

    $months = (int) floor($days / 30);
    $rem_days = $days % 30;
    if ($months < 12) {
        if ($rem_days === 0) {
            return $months . ' ماه';
        }

        return $months . ' ماه و ' . $rem_days . ' روز';
    }

    $years = (int) floor($months / 12);
    $rem_months = $months % 12;
    if ($rem_months === 0) {
        return $years . ' سال';
    }

    return $years . ' سال و ' . $rem_months . ' ماه';
}

function casting_user_last_active_label(int $user_id): string
{
    $last = trim((string) get_user_meta($user_id, 'casting_last_active', true));
    if ($last === '') {
        return '—';
    }
    if (function_exists('casting_format_jalali_datetime_compact')) {
        return casting_format_jalali_datetime_compact($last);
    }

    return $last;
}

/**
 * @return list<array{id:int,name:string,login:string,role:string,referred_at:string,active_duration:string,last_active:string,photo_url:string}>
 */
function casting_list_referred_users(int $referrer_id, int $limit = 200): array
{
    $referrer_id = max(0, $referrer_id);
    if ($referrer_id <= 0) {
        return [];
    }

    $limit = max(1, min(500, $limit));
    $users = get_users([
        'meta_key'   => 'casting_referred_by',
        'meta_value' => (string) $referrer_id,
        'number'     => $limit,
        'orderby'    => 'registered',
        'order'      => 'DESC',
    ]);
    if (!is_array($users) || $users === []) {
        return [];
    }

    $out = [];
    foreach ($users as $user) {
        $id = (int) $user->ID;
        if ($id <= 0 || casting_get_user_role($id) === '') {
            continue;
        }
        $photo = '';
        if (function_exists('casting_get_profile')) {
            $profile = casting_get_profile($id);
            $photo = (string) ($profile['photo_url'] ?? '');
        }
        $out[] = [
            'id'              => $id,
            'name'            => (string) $user->display_name,
            'login'           => (string) $user->user_login,
            'role'            => casting_get_user_role($id),
            'referred_at'     => (string) get_user_meta($id, 'casting_referred_at', true),
            'active_duration' => casting_user_active_duration_label($id),
            'last_active'     => casting_user_last_active_label($id),
            'photo_url'       => $photo,
        ];
    }

    return $out;
}

function casting_referred_users_count(int $referrer_id): int
{
    $referrer_id = max(0, $referrer_id);
    if ($referrer_id <= 0) {
        return 0;
    }

    $query = new WP_User_Query([
        'meta_key'    => 'casting_referred_by',
        'meta_value'  => (string) $referrer_id,
        'fields'      => 'ID',
        'number'      => 1,
        'count_total' => true,
    ]);

    return (int) $query->get_total();
}

/**
 * رندر بخش کد معرفی در پروفایل خود کاربر
 */
function casting_render_referral_profile_section(int $user_id, bool $is_admin_view = false): void
{
    $user_id = max(0, $user_id);
    if ($user_id <= 0) {
        return;
    }

    casting_referral_maybe_backfill();
    $code = casting_get_referral_code($user_id);
    if ($code === '') {
        return;
    }

    $owner = get_user_by('id', $user_id);
    $owner_name = $owner ? trim((string) $owner->display_name) : '';
    if ($owner_name === '') {
        $owner_name = 'این کاربر';
    }

    $referrals = casting_list_referred_users($user_id);
    $count = count($referrals);
    $active_self = casting_user_active_duration_label($user_id);
    $referred_by = casting_user_referred_by($user_id);
    $referrer_name = '';
    if ($referred_by > 0) {
        $ref_user = get_user_by('id', $referred_by);
        $referrer_name = $ref_user ? (string) $ref_user->display_name : '';
    }
    ?>
    <div class="bio-block referral-block" id="referral-code">
      <h3>کد معرفی</h3>
      <p class="meta">این کد را با دیگران به اشتراک بگذارید تا هنگام ثبت‌نام وارد کنند.</p>
      <ul class="info-list">
        <li>
          <strong>کد معرفی <?= casting_e($owner_name) ?>:</strong>
          <span class="membership-number referral-code" dir="ltr"><?= casting_e($code) ?></span>
        </li>
        <li><strong>مدت فعال بودن حساب <?= casting_e($owner_name) ?>:</strong> <?= casting_e($active_self) ?></li>
        <?php if ($is_admin_view && $referred_by > 0) : ?>
          <li>
            <strong>معرف این کاربر:</strong>
            <?php if ($referrer_name !== '') : ?>
              <a href="member.php?id=<?= (int) $referred_by ?>"><?= casting_e($referrer_name) ?></a>
            <?php else : ?>
              #<?= (int) $referred_by ?>
            <?php endif; ?>
          </li>
        <?php endif; ?>
        <li><strong>ثبت‌نام با کد <?= casting_e($owner_name) ?>:</strong> <?= (int) $count ?> نفر</li>
      </ul>

      <?php if ($referrals !== []) : ?>
        <h4 class="referral-list-title">افراد معرفی‌شده</h4>
        <ul class="panel-list referral-list">
          <?php foreach ($referrals as $row) : ?>
            <li class="panel-list-item referral-list-item">
              <div class="referral-list-main">
                <a href="member.php?id=<?= (int) $row['id'] ?>"><strong><?= casting_e($row['name']) ?></strong></a>
                <span class="meta"><?= casting_e(casting_role_label($row['role'])) ?> · <?= casting_e($row['login']) ?></span>
              </div>
              <div class="referral-list-meta meta">
                <span>فعال: <?= casting_e($row['active_duration']) ?></span>
                <span>آخرین فعالیت: <?= casting_e($row['last_active']) ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else : ?>
        <p class="meta referral-empty">هنوز کسی با کد <?= casting_e($owner_name) ?> ثبت‌نام نکرده است.</p>
      <?php endif; ?>
    </div>
    <?php
}
