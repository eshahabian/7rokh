# mu-plugin — جداسازی دو نوع کاربر

## دو نوع کاربر

| نوع | شناسایی | ورود |
|-----|---------|------|
| **وردپرس** | بدون meta `casting_role` | wp-login / wp-admin — بدون تغییر |
| **پورتال** | دارای meta `casting_role` | فقط `/casting-portal/login.php` |

## نصب

### خودکار (پیشنهادی)
با deploy از git (`.cpanel.yml`) فایل loader خودکار کپی می‌شود به:

```
public_html/wp-content/mu-plugins/casting-wp-admin-guard-loader.php
```

این loader همیشه guard را از `casting-portal/mu-plugin/casting-wp-admin-guard.php` می‌خواند.

### دستی (یک‌بار)
اگر deploy خودکار ندارید:

```
cp casting-portal/mu-plugin/casting-wp-admin-guard-loader.php \
   public_html/wp-content/mu-plugins/casting-wp-admin-guard-loader.php
```

## نتیجه

- کاربران وردپرس (بدون casting_role) → **هیچ تغییری**
- اعضای پورتال → در wp-login وردپرس **ورود مسدود**
- اگر قبلاً کوکی wp-login داشتند → روی سایت اصلی logout می‌شوند
- استثنا: **CASTING_PORTAL_OWNER** (پیش‌فرض: eshahabian)

پورتال session جدا دارد (`casting_portal_sid`).
