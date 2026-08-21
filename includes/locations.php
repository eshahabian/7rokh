<?php
declare(strict_types=1);

/**
 * شهرهای پیشنهادی هر استان (پیشنهاد؛ شهر خارج از لیست هم قابل ثبت است)
 *
 * @return array<string, list<string>>
 */
function casting_province_cities_map(): array
{
    return [
        'azarbaijan_east' => ['تبریز', 'مراغه', 'مرند', 'میانه', 'اهر', 'بناب', 'سراب', 'آذرشهر', 'شبستر', 'هادیشهر', 'جلفا'],
        'azarbaijan_west' => ['ارومیه', 'خوی', 'مهاباد', 'میاندوآب', 'بوکان', 'سلماس', 'نقده', 'پیرانشهر', 'تکاب', 'شاهین‌دژ', 'اشنویه'],
        'ardabil' => ['اردبیل', 'پارس‌آباد', 'مشگین‌شهر', 'خلخال', 'گرمی', 'نمین', 'بیله‌سوار', 'کوثر'],
        'isfahan' => ['اصفهان', 'کاشان', 'نجف‌آباد', 'خمینی‌شهر', 'شاهین‌شهر', 'فولادشهر', 'زرین‌شهر', 'نطنز', 'اردستان', 'گلپایگان', 'نایین', 'مبارکه', 'فلاورجان', 'تیران', 'خوانسار'],
        'alborz' => ['کرج', 'فردیس', 'نظرآباد', 'محمدشهر', 'ماهدشت', 'اشتهارد', 'هشتگرد', 'طالقان', 'گرمدره', 'مهرشهر'],
        'ilam' => ['ایلام', 'ایوان', 'دهلران', 'آبدانان', 'مهران', 'دره‌شهر', 'بدره', 'چرداول'],
        'bushehr' => ['بوشهر', 'برازجان', 'گناوه', 'کنگان', 'عسلویه', 'دیر', 'جم', 'خورموج', 'دیلم'],
        'tehran' => ['تهران', 'اسلامشهر', 'شهریار', 'قدس', 'ملارد', 'پاکدشت', 'ری', 'ورامین', 'پردیس', 'بومهن', 'دماوند', 'فیروزکوه', 'رباط‌کریم', 'پرند', 'اندیشه', 'چهاردانگه', 'باقرشهر', 'کهریزک', 'لواسان', 'فشم'],
        'chaharmahal' => ['شهرکرد', 'بروجن', 'لردگان', 'فارسان', 'کیان', 'سامان', 'فرخ‌شهر', 'گندمان'],
        'khorasan_south' => ['بیرجند', 'قائن', 'فردوس', 'نهبندان', 'طبس', 'سربیشه', 'خوسف', 'بشرویه'],
        'khorasan_razavi' => ['مشهد', 'نیشابور', 'سبزوار', 'تربت‌حیدریه', 'کاشمر', 'قوچان', 'تربت‌جام', 'چناران', 'گناباد', 'تایباد', 'درگز', 'خواف', 'فریمان', 'سرخس'],
        'khorasan_north' => ['بجنورد', 'شیروان', 'اسفراین', 'جاجرم', 'آشخانه', 'فاروج', 'گرمه'],
        'khuzestan' => ['اهواز', 'آبادان', 'خرمشهر', 'دزفول', 'اندیمشک', 'ماهشهر', 'بهبهان', 'شوشتر', 'ایذه', 'شوش', 'رامهرمز', 'مسجدسلیمان', 'امیدیه', 'هندیجان'],
        'zanjan' => ['زنجان', 'ابهر', 'خرمدره', 'قیدار', 'ماهنشان', 'سلطانیه', 'آب‌بر'],
        'semnan' => ['سمنان', 'شاهرود', 'دامغان', 'گرمسار', 'مهدی‌شهر', 'ایوانکی', 'شهمیرزاد'],
        'sistan' => ['زاهدان', 'زابل', 'چابهار', 'ایرانشهر', 'خاش', 'سراوان', 'کنارک', 'میرجاوه', 'نیک‌شهر'],
        'fars' => ['شیراز', 'مرودشت', 'جهرم', 'فسا', 'کازرون', 'لار', 'داراب', 'آباده', 'نی‌ریز', 'اقلید', 'فیروزآباد', 'گراش', 'لامرد'],
        'qazvin' => ['قزوین', 'الوند', 'تاکستان', 'آبیک', 'محمدیه', 'بویین‌زهرا', 'آوج'],
        'qom' => ['قم', 'جعفریه', 'کهک', 'سلفچگان', 'قنوات'],
        'kurdistan' => ['سنندج', 'سقز', 'مریوان', 'بانه', 'قروه', 'بیجار', 'کامیاران', 'دیواندره'],
        'kerman' => ['کرمان', 'سیرجان', 'رفسنجان', 'جیرفت', 'بم', 'زرند', 'کهنوج', 'شهربابک', 'راور', 'بردسیر'],
        'kermanshah' => ['کرمانشاه', 'اسلام‌آباد غرب', 'جوانرود', 'کنگاور', 'صحنه', 'هرسین', 'سنقر', 'پاوه', 'قصرشیرین'],
        'kohgiluyeh' => ['یاسوج', 'گچساران', 'دوگنبدان', 'دهدشت', 'سی‌سخت', 'لیکک', 'چرام'],
        'golestan' => ['گرگان', 'گنبد کاووس', 'علی‌آباد کتول', 'بندر ترکمن', 'آق‌قلا', 'کردکوی', 'کلاله', 'مینودشت', 'آزادشهر'],
        'gilan' => ['رشت', 'بندرانزلی', 'لاهیجان', 'لنگرود', 'آستارا', 'تالش', 'صومعه‌سرا', 'فومن', 'رودسر', 'آستانه اشرفیه', 'ماسال', 'رودبار'],
        'lorestan' => ['خرم‌آباد', 'بروجرد', 'دورود', 'الیگودرز', 'کوهدشت', 'نورآباد', 'ازنا', 'پلدختر', 'الشتر'],
        'mazandaran' => ['ساری', 'بابل', 'آمل', 'قائم‌شهر', 'بهشهر', 'چالوس', 'بابلسر', 'نوشهر', 'تنکابن', 'رامسر', 'نور', 'محمودآباد', 'جویبار', 'فریدونکنار'],
        'markazi' => ['اراک', 'ساوه', 'خمین', 'محلات', 'دلیجان', 'شازند', 'تفرش', 'آشتیان', 'کمیجان'],
        'hormozgan' => ['بندرعباس', 'میناب', 'قشم', 'کیش', 'بندر لنگه', 'حاجی‌آباد', 'رودان', 'بستک', 'پارسیان'],
        'hamadan' => ['همدان', 'ملایر', 'نهاوند', 'اسدآباد', 'تویسرکان', 'کبودرآهنگ', 'رزن', 'بهار'],
        'yazd' => ['یزد', 'میبد', 'اردکان', 'بافق', 'مهریز', 'ابرکوه', 'تفت', 'اشکذر'],
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

/**
 * استان معتبر + هر نام شهر غیرخالی (حتی خارج از لیست پیشنهادی) پذیرفته می‌شود.
 */
function casting_is_valid_city_for_province(string $province, string $city): bool
{
    $city = casting_normalize_city_name($city);
    if ($city === '' || !array_key_exists($province, casting_province_labels())) {
        return false;
    }

    return true;
}

/**
 * فیلدهای استان و شهر وابسته به هم
 *
 * @param bool|null $city_allow_all برای فیلتر جستجو: گزینه «همه». برای ثبت‌نام/پروفایل false تا شهر آزاد تایپ شود.
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
    $free_city = !$city_allow_all;
    ?>
  <div class="<?= casting_e($wrapper_class) ?>" data-location-fields data-location-map="<?= casting_e((string) $json) ?>"<?= $city_allow_all ? ' data-location-city-all="1"' : '' ?><?= $free_city ? ' data-location-city-free="1"' : '' ?>>
    <div class="field">
      <label for="province">استان<?= $required ? ' <span class="req-mark">*</span>' : '' ?></label>
      <select id="province" name="province" data-location-province<?= $req ?>>
        <option value=""><?= casting_e($province_empty) ?></option>
        <?php foreach ($provinces as $key => $label) : ?>
          <option value="<?= casting_e($key) ?>" <?= $province === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="city">شهر<?= $required ? ' <span class="req-mark">*</span>' : '' ?></label>
      <?php if ($free_city) : ?>
        <?php
        $list_id = 'casting-city-suggest';
        $city_placeholder = $province === '' ? 'اول استان را انتخاب کنید' : 'از پیشنهاد انتخاب کنید یا بنویسید';
        ?>
        <input
          id="city"
          name="city"
          type="text"
          list="<?= casting_e($list_id) ?>"
          value="<?= casting_e($city) ?>"
          placeholder="<?= casting_e($city_placeholder) ?>"
          autocomplete="address-level2"
          data-location-city
          data-location-city-input
          <?= $req ?>
          <?= $province === '' ? 'disabled' : '' ?>
        >
        <datalist id="<?= casting_e($list_id) ?>" data-location-city-list>
          <?php foreach ($cities as $name) : ?>
            <option value="<?= casting_e($name) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <p class="field-hint">اگر شهرتان در لیست نیست، همان را بنویسید؛ ذخیره می‌شود.</p>
      <?php else : ?>
        <?php
        $city_empty = $required
            ? ($province === '' ? 'اول استان را انتخاب کنید' : 'انتخاب شهر…')
            : ($province === '' ? 'اول استان' : 'همه');
        ?>
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
          <?php if ($city !== '' && $city !== $all_label && !in_array($city, $cities, true)) : ?>
            <option value="<?= casting_e($city) ?>" selected><?= casting_e($city) ?></option>
          <?php endif; ?>
        </select>
      <?php endif; ?>
    </div>
  </div>
    <?php
}
