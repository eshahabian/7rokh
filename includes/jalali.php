<?php
declare(strict_types=1);

function casting_jalali_months(): array
{
    return [
        1  => 'فروردین',
        2  => 'اردیبهشت',
        3  => 'خرداد',
        4  => 'تیر',
        5  => 'مرداد',
        6  => 'شهریور',
        7  => 'مهر',
        8  => 'آبان',
        9  => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];
}

/**
 * @return array{0:int,1:int,2:int}
 */
function casting_gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

/**
 * @return array{0:int,1:int,2:int}
 */
function casting_jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy -= 979;
    $jm -= 1;
    $jd -= 1;
    $j_day_no = (365 * $jy) + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4);
    for ($i = 0; $i < $jm; $i++) {
        $j_day_no += ($i < 6) ? 31 : 30;
    }
    $j_day_no += $jd;
    $g_day_no = $j_day_no + 79;
    $gy = 1600 + (400 * intdiv($g_day_no, 146097));
    $g_day_no %= 146097;
    $leap = true;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * intdiv($g_day_no, 36524);
        $g_day_no %= 36524;
        if ($g_day_no >= 365) {
            $g_day_no++;
        } else {
            $leap = false;
        }
    }
    $gy += 4 * intdiv($g_day_no, 1461);
    $g_day_no %= 1461;
    if ($g_day_no >= 366) {
        $leap = false;
        $g_day_no--;
        $gy += intdiv($g_day_no, 365);
        $g_day_no %= 365;
    }
    $gd = $g_day_no + 1;
    $sal_a = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    for ($gm = 1; $gm <= 12 && $gd > $sal_a[$gm]; $gm++) {
        $gd -= $sal_a[$gm];
    }
    return [$gy, $gm, $gd];
}

function casting_jalali_is_leap(int $jy): bool
{
    $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    $bl = count($breaks);
    $jp = $breaks[0];
    $jump = 0;
    for ($i = 1; $i < $bl; $i++) {
        $jm = $breaks[$i];
        $jump = $jm - $jp;
        if ($jy < $jm) {
            break;
        }
        $jp = $jm;
    }
    $n = $jy - $jp;
    if ($n < $jump) {
        if ($jump - $n < 6) {
            $n = $n - $jump + intdiv($jump + 4, 33) * 33;
        }
        $leap = ((($n + 1) % 33) - 1) % 4;
        if ($leap === -1) {
            $leap = 4;
        }
    } else {
        $n = $jy + 1;
        $leap = ((($n % 33) - 1) % 4);
        if ($leap === -1) {
            $leap = 4;
        }
    }
    return $leap === 0;
}

function casting_jalali_month_days(int $jy, int $jm): int
{
    if ($jm <= 6) {
        return 31;
    }
    if ($jm <= 11) {
        return 30;
    }
    return casting_jalali_is_leap($jy) ? 30 : 29;
}

function casting_jalali_today(): array
{
    $t = current_time('timestamp');
    return casting_gregorian_to_jalali((int) date('Y', $t), (int) date('n', $t), (int) date('j', $t));
}

/**
 * از فیلدهای فرم شمسی → تاریخ میلادی Y-m-d
 */
function casting_birthdate_from_jalali_post(array $post): ?string
{
    $jy = (int) ($post['birth_jy'] ?? 0);
    $jm = (int) ($post['birth_jm'] ?? 0);
    $jd = (int) ($post['birth_jd'] ?? 0);
    if ($jy < 1300 || $jy > 1500 || $jm < 1 || $jm > 12 || $jd < 1) {
        return null;
    }
    if ($jd > casting_jalali_month_days($jy, $jm)) {
        return null;
    }
    [$gy, $gm, $gd] = casting_jalali_to_gregorian($jy, $jm, $jd);
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

function casting_format_jalali_from_gregorian(string $ymd): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return '';
    }
    [$jy, $jm, $jd] = casting_gregorian_to_jalali((int) $m[1], (int) $m[2], (int) $m[3]);
    $months = casting_jalali_months();
    return sprintf('%d %s %d', $jd, $months[$jm] ?? $jm, $jy);
}

/**
 * @return array{jy:int,jm:int,jd:int}
 */
function casting_jalali_parts_from_gregorian(string $ymd): array
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        [$jy, $jm, $jd] = casting_gregorian_to_jalali((int) $m[1], (int) $m[2], (int) $m[3]);
        return ['jy' => $jy, 'jm' => $jm, 'jd' => $jd];
    }
    return ['jy' => 0, 'jm' => 0, 'jd' => 0];
}

function casting_render_jalali_birthday_fields(string $gregorian = '', bool $required = true): void
{
    $parts = casting_jalali_parts_from_gregorian($gregorian);
    $today = casting_jalali_today();
    $maxYear = $today[0] - 5;
    $minYear = $today[0] - 90;
    $selJy = $parts['jy'] > 0 ? $parts['jy'] : 0;
    $selJm = $parts['jm'] > 0 ? $parts['jm'] : 0;
    $selJd = $parts['jd'] > 0 ? $parts['jd'] : 0;
    $req = $required ? 'required' : '';
    ?>
  <div class="field jalali-birth" data-jalali-birth>
    <span class="jalali-label">تاریخ تولد (شمسی)</span>
    <div class="jalali-row">
      <label class="sr-only" for="birth_jd">روز</label>
      <select id="birth_jd" name="birth_jd" data-jalali-day <?= $req ?>>
        <option value="">روز</option>
        <?php for ($d = 1; $d <= 31; $d++) : ?>
          <option value="<?= $d ?>" <?= $selJd === $d ? 'selected' : '' ?>><?= $d ?></option>
        <?php endfor; ?>
      </select>
      <label class="sr-only" for="birth_jm">ماه</label>
      <select id="birth_jm" name="birth_jm" data-jalali-month <?= $req ?>>
        <option value="">ماه</option>
        <?php foreach (casting_jalali_months() as $num => $name) : ?>
          <option value="<?= $num ?>" <?= $selJm === $num ? 'selected' : '' ?>><?= casting_e($name) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="sr-only" for="birth_jy">سال</label>
      <select id="birth_jy" name="birth_jy" data-jalali-year <?= $req ?>>
        <option value="">سال</option>
        <?php for ($y = $maxYear; $y >= $minYear; $y--) : ?>
          <option value="<?= $y ?>" <?= $selJy === $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>
    <?php
}
