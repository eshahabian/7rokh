<?php
declare(strict_types=1);

/**
 * اصول UX پنل (الهام از Backstage):
 * 1) اسکن سریع — آمار و کارت‌ها در یک نگاه
 * 2) فیلتر در دسترس — میانبر تخصص‌ها به جستجو
 * 3) اکشن روی کارت — پیش‌نمایش و دنبال‌کردن بدون رفتن به صفحه جدا
 */

/**
 * @return list<array{key:string,label:string,href:string}>
 */
function casting_panel_home_quick_filters(): array
{
    $cats = casting_activity_categories();
    $preferred = ['acting', 'directing', 'production', 'camera', 'writing', 'art'];
    $out = [
        [
            'key'   => 'all',
            'label' => 'همه',
            'href'  => casting_url('search-users.php'),
        ],
    ];
    foreach ($preferred as $key) {
        if (!isset($cats[$key])) {
            continue;
        }
        $out[] = [
            'key'   => $key,
            'label' => (string) $cats[$key]['label'],
            'href'  => casting_url('search-users.php?activity_category=' . rawurlencode($key)),
        ];
    }

    return $out;
}

function casting_render_panel_home_quick_filters(bool $can_search): void
{
    $filters = casting_panel_home_quick_filters();
    ?>
  <nav class="panel-home-filters" aria-label="میانبر تخصص‌ها">
    <div class="panel-home-filters-head">
      <h2 class="panel-home-filters-title">جستجوی سریع</h2>
      <?php if ($can_search) : ?>
        <a class="panel-home-filters-more" href="<?= casting_e(casting_url('search-users.php')) ?>">فیلتر کامل</a>
      <?php endif; ?>
    </div>
    <div class="panel-home-filter-chips" role="list">
      <?php foreach ($filters as $chip) : ?>
        <?php if ($can_search) : ?>
          <a class="panel-home-filter-chip" role="listitem" href="<?= casting_e($chip['href']) ?>"><?= casting_e($chip['label']) ?></a>
        <?php else : ?>
          <span class="panel-home-filter-chip is-disabled" role="listitem" title="جستجو برای کارگردان یا عضو ویژه فعال است"><?= casting_e($chip['label']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php if (!$can_search) : ?>
      <p class="panel-home-filters-hint meta">برای استفاده از جستجو، حساب ویژه فعال کنید یا با نقش کارگردان وارد شوید.</p>
    <?php endif; ?>
  </nav>
    <?php
}
