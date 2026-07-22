# mu-plugin — جداسازی دو نوع کاربر

## دو نوع کاربر

| نوع | شناسایی | ورود |
|-----|---------|------|
| **وردپرس** | بدون meta `casting_role` | wp-login / wp-admin — بدون تغییر |
| **پورتال** | دارای meta `casting_role` | فقط `/casting-portal/login.php` |

## نصب (یک‌بار روی وردپرس اصلی)

```
public_html/wp-content/mu-plugins/casting-wp-admin-guard.php
```

فایل را از `casting-portal/mu-plugin/` کopy کنید.

## نتیجه

- کاربران وردپرس (نویسنده، مدیر، مشترک بدون پورتال) → **هیچ تغییری**
- اعضای پورتال → در 7rokh.ir لاگین نیستند، wp-admin ندارند
- ورود از wp-login برای اعضای پورتال **مسدود** — پیام: «از پورتال وارد شوید»
- استثنا: **eshahabian** (مدیر پورتال)

پورتال خودش session جدا دارد (`/casting-portal/`).
