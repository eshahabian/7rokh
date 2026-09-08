آپلود صفحه اول پورتال ۷رخ
==========================

این بسته عکس‌های home-slide را روی صفحهٔ اول نشان می‌دهد
(همان فایل‌هایی که روی هاست هست: home-slide-1.png تا 6).
پوستر تبلیغات دیگر جای این عکس‌ها را نمی‌گیرد.

مسیر روی هاست:
  /home/rokhcom/public_html/casting-portal/

دانلود مستقیم زیپ:
  https://github.com/eshahabian/7rokh/raw/cursor/homepage-restore-upload-bb1c/homepage_restore_upload.zip

کار در VS Code / Cursor با SFTP:
1) زیپ را باز کن و محتویات را داخل casting-portal کپی کن (جایگزین فایل قدیمی).
   خودِ README-UPLOAD.txt را روی هاست نفرست.
2) روی هر فایل راست‌کلیک → Upload
   یا پوشه‌های includes و assets را Upload کن.

حتماً این‌ها را بفرست (ساختار پوشه حفظ شود):
  index.php
  register.php
  portal-health.php
  includes/safe-load.php
  includes/layout.php
  includes/bootstrap.php
  includes/ad-posters.php
  includes/profile.php
  includes/hafez.php
  includes/pwa.php
  assets/css/style.css
  assets/js/main.js
  assets/fonts/Shoor-Regular.woff2
  assets/fonts/Shoor-SemiBold.woff2
  assets/images/hafez/   (verse-01.png تا verse-19.png)

عکس‌هایی که روی هاست هستند — دوباره نفرست (زیپ را سنگین می‌کنند):
  assets/images/home-slide-1.png تا home-slide-6.png
  assets/images/promo-slide-1.png تا 6   (پنل داخلی، نه صفحه اول)
  assets/images/shop-call-*.webp         (صفحه خرید، نه صفحه اول)
  assets/images/rokh-nastaliq.png یا .jpg  (اگر روی هاست هست، همان استفاده می‌شود)

config.php و config.local.php را دست نزن — رمزها همان‌جا می‌ماند.

بعد از آپلود سایت را با Ctrl+F5 باز کن:
  https://7rokh.com/casting-portal/
اگر خطا بود:
  https://7rokh.com/casting-portal/portal-health.php


چرا «تنظیمات» کامل برنگشت؟
--------------------------
کد صفحه اول در گیت فقط همین را داشته:
  اسلایدر home-slide-1 تا 6 + متن معرفی + اینماد + بیت حافظ در فوتر.

پوشه‌های زیر روی هاست هستند ولی هیچ‌وقت در گیت commit نشده‌اند:
  assets/images/landing/     (روی هاست تقریباً خالی است — ۷۷ بایت)
  assets/images/projects/    (حدود ۴ کیلوبایت)
  assets/images/rokh-nastaliq.png / .jpg

چون فایل لایه‌بندی (HTML/CSS) آن گالری‌ها در گیت نیست،
نمی‌شود همان چیدمان قدیمی را از صفر بازسازی کرد.
اگر داخل landing یا projects روی هاست عکسی باشد،
همین بسته آن را به‌صورت ردیف عکس نشان می‌دهد.
اگر پوشه خالی باشد، بخشی ظاهر نمی‌شود.

اینستاگرام:
  در کد صفحه اول گیت هرگز استفاده نشده.
  پوشه instagram روی هاست تقریباً خالی است.
  عکس‌های اینستاگرام را در صفحه اول نمی‌آوریم.

فروش (shop-call):
  مال صفحهٔ خرید است، نه صفحه اول عمومی.
