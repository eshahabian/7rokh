<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = casting_current_user();
$logged_in = $user && casting_get_user_role((int) $user->ID) !== '';
if ($logged_in) {
    require_once __DIR__ . '/includes/panel.php';
} else {
    require_once __DIR__ . '/includes/layout.php';
}

if ($logged_in) {
    casting_render_panel_start('قوانین', 'rules');
} else {
    casting_render_head('قوانین', 'page-rules');
    casting_render_header('rules');
}
casting_render_flash();
?>
<?php if (!$logged_in) : ?><main class="wrap panel-page"><?php endif; ?>
  <section class="<?= $logged_in ? 'dash-card panel-wide rules-page' : 'panel panel-wide rules-page' ?>">
    <h1>قوانین <?= casting_e(casting_brand()) ?></h1>
    <p class="lede">با عضویت و استفاده از پورتال، این قوانین را می‌پذیرید.</p>

    <ol class="rules-list">
      <li>
        <h2>هدف پورتال</h2>
        <p>هفت رخ بستری برای معرفی هنرمندان و ارتباط حرفه‌ای کارگردان‌ها و تهیه‌کنندگان است. استفاده از اطلاعات فقط برای اهداف شغلی و پروژه‌ای مجاز است.</p>
      </li>
      <li>
        <h2>عضویت و صحت اطلاعات</h2>
        <p>هر کاربر مسئول صحت اطلاعات پروفایل خود است. ثبت اطلاعات نادرست، جعلی یا گمراه‌کننده می‌تواند به تعلیق یا حذف حساب منجر شود.</p>
      </li>
      <li>
        <h2>حریم خصوصی</h2>
        <p>اطلاعات تماس و محتوای پروفایل فقط در چارچوب پورتال و برای کاربران مجاز نمایش داده می‌شود. انتشار یا سوءاستفاده از اطلاعات دیگران ممنوع است.</p>
      </li>
      <li>
        <h2>گفتگو و بلاک</h2>
        <p>پیام‌ها خصوصی هستند. در صورت مزاحمت می‌توانید کاربر را بلاک کنید. هم‌صنف‌ها نمی‌توانند با یکدیگر گفتگو کنند.</p>
      </li>
      <li>
        <h2>خدمات ویژه</h2>
        <p>اشتراک ویژه پس از تأیید فیش پرداخت فعال می‌شود و پروفایل در اولویت جستجو قرار می‌گیرد.</p>
      </li>
      <li>
        <h2>مسئولیت‌ها</h2>
        <p>هفت رخ واسط معرفی است و طرف قرارداد میان کاربران محسوب نمی‌شود.</p>
      </li>
    </ol>
  </section>
<?php if ($logged_in) : ?>
<?php casting_render_panel_end(); ?>
<?php else : ?>
</main>
<?php casting_render_footer(); ?>
<?php endif; ?>
