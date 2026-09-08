آپلود صفحه اول پورتال ۷رخ
==========================

نسخهٔ سالم صفحهٔ اول (لندینگ + بازیابی خطای وردپرس).
مسیر روی هاست:
  /home/rokhcom/public_html/casting-portal/

کار در VS Code / Cursor با SFTP:
1) محتویات همین پوشه را داخل casting-portal روی سیستم خودت کپی کن (جایگزین فایل قدیمی). README را کپی نکن.
2) روی هر فایل راست‌کلیک → Upload
   یا کل پوشه‌های includes و assets را Upload کن.

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
  assets/images/home-slide-1.png تا home-slide-6.png
  assets/images/promo-slide-1.png تا promo-slide-6.png
  assets/images/hafez/  (همه)

config.php و config.local.php را دست نزن — رمزها همان‌جا می‌ماند.

بعد از آپلود سایت را با Ctrl+F5 باز کن:
  https://7rokh.com/casting-portal/
اگر باز هم خطا بود این را باز کن:
  https://7rokh.com/casting-portal/portal-health.php

اسلایدهای home-slide و promo-slide را نفرستادم چون روی هاست از قبل هستند و زیپ را خیلی سنگین می‌کنند.
اگر اسلایدها هم خالی‌اند، از گیت‌هاب همین فایل‌ها را بگیر:
  assets/images/home-slide-1.png تا 6
  assets/images/promo-slide-1.png تا 6
