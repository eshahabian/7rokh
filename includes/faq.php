<?php
declare(strict_types=1);

/**
 * @return list<array{question:string,answer:string}>
 */
function casting_faq_items(): array
{
    return [
        [
            'question' => 'هفت رخ چیست؟',
            'answer'   => 'هفت رخ پورتال تخصصی سینما و تئاتر است؛ بستری برای معرفی هنرمندان و برقراری ارتباط حرفه‌ای میان بازیگران، عوامل تولید، کارگردان‌ها و تهیه‌کنندگان.',
        ],
        [
            'question' => 'آیا استفاده از هفت رخ رایگان است؟',
            'answer'   => 'عضویت و استفاده از امکانات اصلی پورتال رایگان است. برای اولویت در جستجو و حساب کاربری ویژه می‌توانید اشتراک ماهانه تهیه کنید.',
        ],
        [
            'question' => 'برای استفاده از سایت باید ثبت‌نام کنم؟',
            'answer'   => 'برای ساخت پروفایل، جستجوی کاربران، ارسال پیام و استفاده از پنل کاربری باید ثبت‌نام کنید و وارد حساب خود شوید.',
        ],
        [
            'question' => 'چه کسانی می‌توانند در هفت رخ عضو شوند؟',
            'answer'   => 'هنرمندان (بازیگران و عوامل فنی و هنری) و کارفرمایان حوزه سینما و تئاتر مانند کارگردان و تهیه‌کننده می‌توانند عضو شوند و پروفایل تخصصی خود را ثبت کنند.',
        ],
        [
            'question' => 'حساب کاربری ویژه چه مزیتی دارد؟',
            'answer'   => 'با فعال‌سازی حساب ویژه، پروفایل شما در نتایج جستجو در اولویت نمایش داده می‌شود. پس از ثبت فیش و تأیید توسط مدیر، اشتراک یک‌ماهه فعال می‌شود.',
        ],
        [
            'question' => 'آیا اطلاعات و مکالمات من محرمانه هستند؟',
            'answer'   => 'بله. اطلاعات پروفایل و پیام‌های خصوصی شما در چارچوب پورتال نگهداری می‌شود و حریم خصوصی کاربران برای ما اهمیت دارد.',
        ],
        [
            'question' => 'چگونه مشکلات سایت را گزارش کنم؟',
            'answer'   => 'از بخش «تماس با ما» در پنل می‌توانید برای مدیر سایت یا مدیر هفت رخ پیام بگذارید. پیام‌ها داخل همان پنل کاربری ذخیره و نمایش داده می‌شوند.',
        ],
        [
            'question' => 'آیا می‌توانم پیام‌هایم را مشاهده کنم؟',
            'answer'   => 'بله. پس از ورود، از بخش «پیام کاربران» در پنل می‌توانید گفتگوهای خود را با سایر اعضا ببینید و ادامه دهید.',
        ],
        [
            'question' => 'آیا هفت رخ روی موبایل هم قابل استفاده است؟',
            'answer'   => 'بله، سایت به صورت واکنش‌گرا طراحی شده و روی موبایل، تبلت و کامپیوتر قابل استفاده است.',
        ],
    ];
}

function casting_render_faq_json_ld(): void
{
    $entities = [];
    foreach (casting_faq_items() as $item) {
        $entities[] = [
            '@type'          => 'Question',
            'name'           => $item['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $item['answer'],
            ],
        ];
    }

    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $entities,
    ];

    $json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return;
    }
    ?>
<script type="application/ld+json"><?= $json ?></script>
    <?php
}

function casting_render_faq_accordion(): void
{
    $items = casting_faq_items();
    ?>
  <div class="faq-accordion" data-faq-accordion>
    <?php foreach ($items as $i => $item) : ?>
      <details class="faq-item">
        <summary class="faq-question">
          <span class="faq-question-text"><?= casting_e($item['question']) ?></span>
          <span class="faq-icon" aria-hidden="true"></span>
        </summary>
        <div class="faq-answer">
          <p><?= casting_e($item['answer']) ?></p>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
    <?php
}
