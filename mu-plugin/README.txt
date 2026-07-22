# mu-plugin — جداسازی دو نوع کاربر

## دو نوع کاربر

| نوع | شناسایی | ورود |
|-----|---------|------|
| **وردپرس** | بدون meta `casting_role` | wp-login — بدون تغییر |
| **پورتال** | دارای meta `casting_role` | فقط `/casting-portal/login.php` |

## نصب (مهم)

فایل guard باید **مستقیم** در mu-plugins وردپرس باشد:

```
public_html/wp-content/mu-plugins/casting-wp-admin-guard.php
```

### deploy خودکار (.cpanel.yml)
با git push فایل guard کپی می‌شود.

### دستی (cPanel File Manager)
کپی از:
```
casting-portal/mu-plugin/casting-wp-admin-guard.php
```
به:
```
wp-content/mu-plugins/casting-wp-admin-guard.php
```

## بررسی

پنل → **تست ایمیل** → بخش «جداسازی ورود»
- Guard فعال: ✓
- فایل mu-plugin: ✓

## تست

- کاربر پورتال (غیر eshahabian) + `7rokh.ir/wp-login.php` → **خطا**
- همان کاربر + `casting-portal/login.php` → **ورود**
- **eshahabian** (owner) → wp-login **مجاز** (استثنا)

## نتیجه

- کاربران وردپرس بدون casting_role → بدون تغییر
- اعضای پورتال → wp-login و کوکی سایت اصلی مسدود
- پورتال session جدا: `casting_portal_sid`
