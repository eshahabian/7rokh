# mu-plugin — جداسازی ورود پورتال از وردپرس

## دو نوع کاربر

| نوع | شناسایی | ورود |
|-----|---------|------|
| **وردپرس** | بدون meta `casting_role` | wp-login — بدون تغییر |
| **پورتال** | دارای meta `casting_role` | فقط `/casting-portal/login.php` |

## نصب

### خودکار (پیشنهادی)
با deploy از git (`.cpanel.yml`) فایل guard کپی می‌شود به:

```
public_html/wp-content/mu-plugins/casting-wp-admin-guard.php
```

**مهم:** فقط یک فایل — loader جدا نصب نکنید.

### دستی (یک‌بار)
```
casting-portal/mu-plugin/casting-wp-admin-guard.php
  → public_html/wp-content/mu-plugins/casting-wp-admin-guard.php
```

## نتیجه

- کاربران وردپرس (بدون casting_role) → بدون تغییر
- اعضای پورتال → wp-login مسدود + کوکی سایت اصلی ست نمی‌شود
- استثنا: `CASTING_PORTAL_OWNER` در config.php (پیش‌فرض: eshahabian)
- پورتال session جدا: `casting_portal_sid`

## سازگاری

فایل guard با PHP 7.4 سازگار است (بدون str_contains).
