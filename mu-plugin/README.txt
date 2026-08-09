# mu-plugin — جداسازی ورود پورتال از وردپرس + سبد خرید

## دو نوع کاربر

| نوع | شناسایی | ورود |
|-----|---------|------|
| **وردپرس** | بدون meta `casting_role` | wp-login — بدون تغییر |
| **پورتال** | دارای meta `casting_role` | فقط `/casting-portal/login.php` |

## فایل‌ها

| فایل | نقش |
|------|-----|
| `casting-wp-admin-guard.php` | جداسازی ورود پورتال از wp-admin |
| `casting-main-cart-nav.php` | لینک «سبد خرید» + badge در منوی سایت اصلی |

## نصب

### خودکار (پیشنهادی)
با deploy از git (`.cpanel.yml`) فایل‌ها کپی می‌شوند به:

```
public_html/wp-content/mu-plugins/casting-wp-admin-guard.php
public_html/wp-content/mu-plugins/casting-main-cart-nav.php
```

**مهم:** loader جدا نصب نکنید.

### دستی (یک‌بار)
```
casting-portal/mu-plugin/casting-wp-admin-guard.php
  → public_html/wp-content/mu-plugins/casting-wp-admin-guard.php

casting-portal/mu-plugin/casting-main-cart-nav.php
  → public_html/wp-content/mu-plugins/casting-main-cart-nav.php
```

## سبد خرید در سایت اصلی

- لینک به `/casting-portal/cart.php`
- آیکون سبد **کنار فیسبوک/توییتر** در نوار بالای هدر تزریق می‌شود (JS)
- شمارنده از کوکی `casting_cart_count` (path=`/`) که پورتال هنگام تغییر سبد ست می‌کند
- اگر سوشال‌ها پیدا نشوند، لینک شناور پایین صفحه به‌عنوان پشتیبان می‌آید
- آدرس سفارشی (اختیاری): در `wp-config.php` تعریف کنید  
  `define('CASTING_PORTAL_CART_URL', 'https://7rokh.ir/casting-portal/cart.php');`

## نتیجه

- کاربران وردپرس (بدون casting_role) → بدون تغییر
- اعضای پورتال → wp-login مسدود + کوکی سایت اصلی ست نمی‌شود
- استثنا: `CASTING_PORTAL_OWNER` در config.php (پیش‌فرض: eshahabian)
- پورتال session جدا: `casting_portal_sid`

## سازگاری

فایل‌ها با PHP 7.4 سازگارند (بدون str_contains).
