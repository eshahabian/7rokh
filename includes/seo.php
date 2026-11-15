<?php
declare(strict_types=1);

/** Only editorial/public landing pages are search destinations. */
function casting_seo_pages(): array
{
    return [
        'index.php' => ['پورتال ارتباط هنرمندان با پروژه‌های سینما و تئاتر', 'پورتال ۷ رخ؛ معرفی هنرمندان و ارتباط با پروژه‌های سینما و تئاتر. با خدمات پورتال، شرایط عضویت و راه‌های همکاری آشنا شوید.'],
        'faq.php' => ['سوالات متداول پورتال ۷ رخ', 'پاسخ پرسش‌های رایج درباره ثبت‌نام، پروفایل هنرمندان، اشتراک و خدمات پورتال ۷ رخ.'],
        'rules.php' => ['قوانین و شرایط استفاده از پورتال ۷ رخ', 'قوانین عضویت، استفاده از خدمات و مسئولیت‌های کاربران در پورتال هنرمندان ۷ رخ را بخوانید.'],
        'contact.php' => ['تماس با پورتال ۷ رخ', 'راه ارتباط با مدیران و پشتیبانی پورتال ۷ رخ برای پرسش‌ها و پیگیری خدمات.'],
        'app.php' => ['اپلیکیشن موبایل پورتال ۷ رخ', 'آشنایی با اپلیکیشن موبایل ۷ رخ و راه دسترسی به پورتال هنرمندان از تلفن همراه.'],
    ];
}

function casting_seo_page(): string
{
    return basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
}

function casting_seo_indexable(): bool
{
    return isset(casting_seo_pages()[casting_seo_page()]);
}

function casting_seo_canonical(): string
{
    $origin = rtrim((string) CASTING_MAIN_SITE_URL, '/');
    $page = casting_seo_page();
    return $origin . '/casting-portal/' . ($page === 'index.php' ? '' : $page);
}

function casting_render_seo_head(): void
{
    if (!casting_seo_indexable()) {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
        return;
    }
    [$title, $description] = casting_seo_pages()[casting_seo_page()];
    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $url = $escape(casting_seo_canonical());
    echo '<meta name="description" content="' . $escape($description) . '">' . "\n";
    echo '<link rel="canonical" href="' . $url . '">' . "\n";
    foreach (['og:type' => 'website', 'og:locale' => 'fa_IR', 'og:title' => $title, 'og:description' => $description, 'og:url' => casting_seo_canonical()] as $property => $value) {
        echo '<meta property="' . $property . '" content="' . $escape($value) . '">' . "\n";
    }
}
