# اپ اندروید هفت رخ (Capacitor)

شل اندروید که پورتال را از این آدرس لود می‌کند:

`https://7rokh.ir/casting-portal/`

## پیش‌نیاز

- Node.js LTS
- Android Studio (SDK + Emulator یا گوشی با USB Debugging)

## نصب و اجرا

```bash
cd mobile
npm install
npx cap add android
npx cap sync android
npx cap open android
```

در Android Studio روی Run بزنید.

## ساخت APK / AAB

در Android Studio:

`Build → Generate Signed Bundle / APK`

- APK: نصب مستقیم
- AAB: انتشار در Google Play

## نکات

- `appId` فعلی: `ir.rokh7.app` (اگر خواستید عوض کنید، قبل از انتشار نهایی باشد)
- برای نوتیفیکیشن / دوربین / آپلود پیشرفته بعداً پلاگین Capacitor اضافه می‌شود
- پوشه `android/` بعد از `cap add android` ساخته می‌شود و معمولاً در git نگه داشته می‌شود
