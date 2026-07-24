<?php
declare(strict_types=1);

/**
 * شهرهای هر استان
 *
 * @return array<string, list<string>>
 */
function casting_province_cities_map(): array
{
    return [
        'azarbaijan_east' => ['تبریز', 'مراغه', 'مرند', 'میانه', 'اهر', 'بناب', 'سراب', 'آذرشهر'],
        'azarbaijan_west' => ['ارومیه', 'خوی', 'مهاباد', 'میاندوآب', 'بوکان', 'سلماس', 'نقده', 'پیرانشهر'],
        'ardabil' => ['اردبیل', 'پارس‌آباد', 'مشگین‌شهر', 'خلخال', 'گرمی', 'نمین'],
        'isfahan' => ['اصفهان', 'کاشان', 'نجف‌آباد', 'خمینی‌شهر', 'شاهین‌شهر', 'فولادشهر', 'زرین‌شهر', 'نطنز', 'اردستان', 'گلپایگان', 'نایین', 'مبارکه'],
        'alborz' => ['کرج', 'فردیس', 'نظرآباد', 'محمدشهر', 'ماهدشت', 'اشتهارد', 'هشتگرد', 'طالقان'],
        'ilam' => ['ایلام', 'ایوان', 'دهلران', 'آبدانان', 'مهران', 'دره‌شهر'],
        'bushehr' => ['بوشهر', 'برازجان', 'گناوه', 'کنگان', 'عسلویه', 'دیر', 'جم'],
        'tehran' => ['تهران', 'اسلامشهر', 'شهریار', 'قدس', 'ملارد', 'پاکدشت', 'ری', 'ورامین', 'پردیس', 'بومهن', 'دماوند', 'فیروزکوه', 'رباط‌کریم', 'پرند', 'اندیشه'],
        'chaharmahal' => ['شهرکرد', 'بروجن', 'لردگان', 'فارسان', 'کیان', 'سامان'],
        'khorasan_south' => ['بیرجند', 'قائن', 'فردوس', 'نهبندان', 'طبس', 'سربیشه'],
        'khorasan_razavi' => ['مشهد', 'نیشابور', 'سبزوار', 'تربت‌حیدریه', 'کاشمر', 'قوچان', 'تربت‌جام', 'چناران', 'گناباد', 'تایباد'],
        'khorasan_north' => ['بجنورد', 'شیروان', 'اسفراین', 'جاجرم', 'آشخانه', 'فاروج'],
        'khuzestan' => ['اهواز', 'آبادان', 'خرمشهر', 'دزفول', 'اندیمشک', 'ماهشهر', 'بهبهان', 'شوشتر', 'ایذه', 'شوش', 'رامهرمز'],
        'zanjan' => ['زنجان', 'ابهر', 'خرمدره', 'قیدار', 'ماهنشان', 'سلطانیه'],
        'semnan' => ['سمنان', 'شاهرود', 'دامغان', 'گرمسار', 'مهدی‌شهر', 'ایوانکی'],
        'sistan' => ['زاهدان', 'زابل', 'چابهار', 'ایرانشهر', 'خاش', 'سراوان', 'کنارک'],
        'fars' => ['شیراز', 'مرودشت', 'جهرم', 'فسا', 'کازرون', 'لار', 'داراب', 'آباده', 'نی‌ریز', 'اقلید'],
        'qazvin' => ['قزوین', 'الوند', 'تاکستان', 'آبیک', 'محمدیه', 'بویین‌زهرا'],
        'qom' => ['قم', 'جعفریه', 'کهک', 'سلفچگان'],
        'kurdistan' => ['سنندج', 'سقز', 'مریوان', 'بانه', 'قروه', 'بیجار', 'کامیاران'],
        'kerman' => ['کرمان', 'سیرجان', 'رفسنجان', 'جیرفت', 'بم', 'زرند', 'کهنوج', 'شهربابک'],
        'kermanshah' => ['کرمانشاه', 'اسلام‌آباد غرب', 'جوانرود', 'کنگاور', 'صحنه', 'هرسین', 'سنقر'],
        'kohgiluyeh' => ['یاسوج', 'گچساران', 'دوگنبدان', 'دهدشت', 'سی‌سخت', 'لیکک'],
        'golestan' => ['گرگان', 'گنبد کاووس', 'علی‌آباد کتول', 'بندر ترکمن', 'آق‌قلا', 'کردکوی', 'کلاله'],
        'gilan' => ['رشت', 'بندرانزلی', 'لاهیجان', 'لنگرود', 'آستارا', 'تالش', 'صومعه‌سرا', 'فومن', 'رودسر'],
        'lorestan' => ['خرم‌آباد', 'بروجرد', 'دورود', 'الیگودرز', 'کوهدشت', 'نورآباد', 'ازنا'],
        'mazandaran' => ['ساری', 'بابل', 'آمل', 'قائم‌شهر', 'بهشهر', 'چالوس', 'بابلسر', 'نوشهر', 'تنکابن', 'رامسر', 'نور'],
        'markazi' => ['اراک', 'ساوه', 'خمین', 'محلات', 'دلیجان', 'شازند', 'تفرش'],
        'hormozgan' => ['بندرعباس', 'میناب', 'قشم', 'کیش', 'بندر لنگه', 'حاجی‌آباد', 'رودان'],
        'hamadan' => ['همدان', 'ملایر', 'نهاوند', 'اسدآباد', 'تویسرکان', 'کبودرآهنگ'],
        'yazd' => ['یزد', 'میبد', 'اردکان', 'بافق', 'مهریز', 'ابرکوه', 'تفت'],
    ];
}

/**
 * @return list<string>
 */
function casting_cities_for_province(string $province): array
{
    $map = casting_province_cities_map();
    return $map[$province] ?? [];
}

function casting_city_all_label(): string
{
    return 'همه';
}

function casting_city_search_filter_value(string $city): string
{
    $city = casting_normalize_city_name($city);
    if ($city === '' || $city === casting_city_all_label()) {
        return '';
    }

    return $city;
}

function casting_is_valid_city_for_province(string $province, string $city): bool
{
    $city = casting_normalize_city_name($city);
    if ($city === '' || !array_key_exists($province, casting_province_labels())) {
        return false;
    }
    if ($city === casting_city_all_label()) {
        return true;
    }

    return in_array($city, casting_cities_for_province($province), true);
}

/**
 * فیلدهای استان و شهر وابسته به هم
 */
function casting_render_location_fields(
    string $province = '',
    string $city = '',
    string $residence = '',
    bool $required = true,
    string $wrapper_class = 'form-grid',
    ?bool $city_allow_all = null
): void {
    unset($residence);
    if ($city_allow_all === null) {
        $city_allow_all = true;
    }
    $provinces = casting_province_labels();
    $cities = $province !== '' ? casting_cities_for_province($province) : [];
    $all_label = casting_city_all_label();
    $map = ['cities' => casting_province_cities_map()];
    $json = wp_json_encode($map, JSON_UNESCAPED_UNICODE);
    $req = $required ? ' required' : '';
    $province_empty = $required ? 'انتخاب استان…' : 'همه';
    $city_empty = $required
        ? ($province === '' ? 'اول استان را انتخاب کنید' : 'انتخاب شهر…')
        : ($province === '' ? 'اول استان' : 'همه');
    ?>
  <div class="<?= casting_e($wrapper_class) ?>" data-location-fields data-location-map="<?= casting_e((string) $json) ?>"<?= $city_allow_all ? ' data-location-city-all="1"' : '' ?>>
    <div class="field">
      <label for="province">استان</label>
      <select id="province" name="province" data-location-province<?= $req ?>>
        <option value=""><?= casting_e($province_empty) ?></option>
        <?php foreach ($provinces as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $province === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="city">شهر</label>
      <select id="city" name="city" data-location-city<?= $req ?> <?= $province === '' ? 'disabled' : '' ?>>
        <option value=""><?= casting_e($city_empty) ?></option>
        <?php if ($city_allow_all && $province !== '') : ?>
          <option value="<?= casting_e($all_label) ?>" <?= $city === $all_label ? 'selected' : '' ?>><?= casting_e($all_label) ?></option>
        <?php elseif ($city === $all_label && $province !== '') : ?>
          <option value="<?= casting_e($all_label) ?>" selected><?= casting_e($all_label) ?></option>
        <?php endif; ?>
        <?php foreach ($cities as $name) : ?>
          <option value="<?= casting_e($name) ?>" <?= $city === $name ? 'selected' : '' ?>><?= casting_e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
    <?php
}
