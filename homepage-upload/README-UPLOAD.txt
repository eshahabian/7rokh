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

عکس‌های صفحه اول روی هاست می‌مانند (آپلود دوباره لازم نیست):
  assets/images/home-slide-1.png تا 6
  assets/images/rokh-nastaliq.png (اگر هست)
  assets/images/projects/ و assets/images/landing/ اگر داخلشان عکس باشد
  assets/images/hafez/

اینستاگرام در صفحه اول نیست و آپلود نمی‌شود.

config.php و config.local.php را دست نزن — رمزها همان‌جا می‌ماند.

بعد از آپلود سایت را با Ctrl+F5 باز کن:
  https://7rokh.com/casting-portal/
اگر باز هم خطا بود این را باز کن:
  https://7rokh.com/casting-portal/portal-health.php
