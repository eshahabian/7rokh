<?php
declare(strict_types=1);

function casting_news_placements(): array
{
    return ['headline' => 'تیتر اصلی', 'editor' => 'پیشنهاد سردبیر', 'featured' => 'محتوای ویژه'];
}

/** Approval creates a recoverable draft before making any content public. */
function casting_news_publish(int $reviewer, string $owner, string $id, array $input): int
{
    if (!casting_news_reviewer($reviewer)) { throw new RuntimeException('اجازه انتشار خبر را ندارید.'); }
    if (!preg_match('/^[1-9][0-9]*$/D', $owner) || !preg_match('/^[a-f0-9]{32}$/D', $id)) {
        throw new RuntimeException('شناسه خبر نامعتبر است.');
    }
    $base = casting_news_dir() . '/' . $owner . '/' . $id;
    $lock = fopen($base . '.lock', 'c+b');
    if (!$lock || !flock($lock, LOCK_EX)) { throw new RuntimeException('قفل خبر در دسترس نیست.'); }
    try {
        $row = is_file($base . '.json') ? json_decode((string) file_get_contents($base . '.json'), true) : null;
        if (!$row || empty($row['done']) || casting_news_state($row) === 'deleted') { throw new RuntimeException('خبر قابل انتشار نیست.'); }
        $key = $owner . ':' . $id;
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_casting_news_source', 'meta_value' => $key, 'numberposts' => 1]);
        $post_id = $posts ? (int) $posts[0]->ID : 0;
        if ($post_id && get_post_status($post_id) === 'publish') { return $post_id; }
        $category = (int) ($input['category'] ?? 0);
        if (!$category || !term_exists($category, 'category')) { throw new RuntimeException('دسته انتشار را انتخاب کنید.'); }
        $placements = array_values(array_unique(array_map('strval', (array) ($input['placements'] ?? []))));
        if (array_diff($placements, array_keys(casting_news_placements()))) { throw new RuntimeException('جایگاه نامعتبر است.'); }
        $title = sanitize_text_field((string) ($input['publish_title'] ?? ''));
        $lead = sanitize_textarea_field((string) ($input['publish_lead'] ?? ''));
        $body = sanitize_textarea_field((string) ($input['publish_body'] ?? ''));
        if ($title === '' || $lead === '' || $body === '') { throw new RuntimeException('عنوان، لید و متن الزامی است.'); }
        $tags = [];
        foreach ($placements as $placement) {
            $name = casting_news_placements()[$placement];
            $term = term_exists($name, 'post_tag');
            if (!$term) { $term = wp_insert_term($name, 'post_tag'); }
            if (is_wp_error($term)) { throw new RuntimeException($term->get_error_message()); }
            $tags[] = (int) (is_array($term) ? $term['term_id'] : $term);
        }
        $data = ['post_title' => $title, 'post_excerpt' => $lead, 'post_content' => '<p><strong>' . esc_html($lead) . '</strong></p>' . wpautop(esc_html($body)), 'post_category' => [$category], 'tags_input' => $tags];
        if (!$post_id) {
            $post_id = wp_insert_post($data + ['post_status' => 'draft', 'post_type' => 'post', 'post_author' => $reviewer, 'meta_input' => ['_casting_news_source' => $key, '_casting_news_reporter' => (string) ($row['reporter'] ?? '')]], true);
        } else { $post_id = wp_update_post($data + ['ID' => $post_id], true); }
        if (is_wp_error($post_id)) { throw new RuntimeException($post_id->get_error_message()); }
        // Use a separately chosen, validated WordPress image. Arbitrary private originals stay private.
        if (!empty($_FILES['publish_image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $file = $_FILES['publish_image'];
            $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
            if (!in_array($check['type'], ['image/jpeg', 'image/png', 'image/webp'], true) || !wp_getimagesize($file['tmp_name'])) { throw new RuntimeException('تصویر شاخص باید JPEG، PNG یا WebP معتبر باشد.'); }
            $media = media_handle_upload('publish_image', $post_id);
            if (is_wp_error($media)) { throw new RuntimeException($media->get_error_message()); }
            set_post_thumbnail($post_id, $media);
        }
        if ($placements && !has_post_thumbnail($post_id)) { throw new RuntimeException('برای جایگاه‌های صفحه اصلی تصویر شاخص انتخاب کنید.'); }
        update_post_meta($post_id, '_casting_news_approved_by', $reviewer);
        update_post_meta($post_id, '_casting_news_approved_at', gmdate('c'));
        $result = wp_update_post(['ID' => $post_id, 'post_status' => 'publish'], true);
        if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
        $row['wp_post_id'] = $post_id;
        $row['published_by'] = $reviewer;
        $row['published_at'] = gmdate('c');
        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($base . '.json.tmp', $json) !== strlen($json) || !rename($base . '.json.tmp', $base . '.json')) { throw new RuntimeException('خبر منتشر شد؛ ذخیره وضعیت پورتال ناموفق بود. ارسال مجدد نوشته تکراری نمی‌سازد.'); }
        return (int) $post_id;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function casting_news_render_publish(array $row): void
{
    $posts = get_posts(['post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_casting_news_source', 'meta_value' => $row['uid'] . ':' . $row['id'], 'numberposts' => 1]);
    if ($posts && get_post_status($posts[0]->ID) === 'publish') {
        echo '<p>منتشرشده — <a target="_blank" rel="noopener" href="' . esc_url(get_permalink($posts[0]->ID)) . '">مشاهده در سایت</a></p>';
        return;
    }
    if (casting_news_state($row) === 'deleted') { return; }
    $categories = get_categories(['hide_empty' => false]);
    ?>
    <details><summary>بررسی و انتشار در سایت</summary>
    <form method="post" enctype="multipart/form-data" class="form news-publish-form" action="<?= casting_e(casting_url('reporters.php')) ?>">
      <?php wp_nonce_field('casting_news_publish'); ?>
      <input type="hidden" name="news_publish" value="1">
      <input type="hidden" name="owner" value="<?= (int) $row['uid'] ?>">
      <input type="hidden" name="id" value="<?= casting_e($row['id']) ?>">
      <label>عنوان<input required name="publish_title" value="<?= casting_e($row['title']) ?>"></label>
      <label>لید<textarea required name="publish_lead" rows="3"><?= casting_e($row['lead']) ?></textarea></label>
      <label>متن<textarea required name="publish_body" rows="10"><?= casting_e($row['body']) ?></textarea></label>
      <label>دسته انتشار<select required name="category"><option value="">انتخاب کنید</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category->term_id ?>"><?= casting_e($category->name) ?></option><?php endforeach; ?></select></label>
      <fieldset><legend>جایگاه‌های صفحه اصلی (اختیاری)</legend><?php foreach (casting_news_placements() as $key => $label): ?><label><input type="checkbox" name="placements[]" value="<?= $key ?>"> <?= casting_e($label) ?></label><?php endforeach; ?></fieldset>
      <p>بدون انتخاب جایگاه، انتشار عادی در دسته انجام می‌شود. تیتر اصلی و محتوای ویژه نیازمند تنظیم اولیه فیلتر قالب هستند.</p>
      <label>تصویر شاخص<input type="file" name="publish_image" accept="image/jpeg,image/png,image/webp"></label>
      <p>فایل اصلی خبرنگار خصوصی می‌ماند. فقط تصویر شاخص انتخاب‌شده عمومی می‌شود.</p>
      <button class="btn btn-ghost news-preview-button" type="button">پیش‌نمایش متن و محل انتشار</button>
      <div class="news-publish-preview" hidden style="white-space:pre-wrap;overflow-wrap:anywhere" aria-live="polite"></div>
      <label><input type="checkbox" required> متن و محل انتشار را بررسی کردم و انتشار عمومی را تأیید می‌کنم.</label>
      <button class="btn btn-primary" type="submit">تأیید و انتشار در سایت</button>
    </form></details>
    <?php
}
