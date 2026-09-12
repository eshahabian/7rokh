<?php
declare(strict_types=1);

/**
 * نرمال‌سازی عکس آپلودشده: تبدیل فرمت، ریسایز، و در صورت نیاز کراپ برای پروفایل‌های مختلف سایت.
 */

/**
 * @return array<string, array{
 *   max_width:int,
 *   max_height:int,
 *   crop:bool,
 *   quality:int,
 *   upscale:bool,
 *   label:string
 * }>
 */
function casting_image_process_profiles(): array
{
    return [
        'portrait'  => [
            'max_width'  => 1200,
            'max_height' => 1600,
            'crop'       => true,
            'quality'    => 85,
            'upscale'    => false,
            'label'      => 'عکس پروفایل',
        ],
        'gallery'   => [
            'max_width'  => 1920,
            'max_height' => 1920,
            'crop'       => false,
            'quality'    => 85,
            'upscale'    => false,
            'label'      => 'گالری',
        ],
        'chat'      => [
            'max_width'  => 1600,
            'max_height' => 1600,
            'crop'       => false,
            'quality'    => 82,
            'upscale'    => false,
            'label'      => 'چت',
        ],
        'cover'     => [
            'max_width'  => 1920,
            'max_height' => 1080,
            'crop'       => false,
            'quality'    => 85,
            'upscale'    => false,
            'label'      => 'کاور',
        ],
        'ad_poster' => [
            'max_width'  => 1920,
            'max_height' => 810,
            'crop'       => true,
            'quality'    => 85,
            'upscale'    => true,
            'label'      => 'پوستر تبلیغ',
        ],
        'receipt'   => [
            'max_width'  => 1600,
            'max_height' => 1600,
            'crop'       => false,
            'quality'    => 80,
            'upscale'    => false,
            'label'      => 'فیش',
        ],
        'general'   => [
            'max_width'  => 1920,
            'max_height' => 1920,
            'crop'       => false,
            'quality'    => 85,
            'upscale'    => false,
            'label'      => 'عکس',
        ],
    ];
}

/**
 * @return list<string>
 */
function casting_image_process_allowed_mimes(): array
{
    return ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
}

/**
 * آیا مسیر فایل تصویر قابل‌پردازش است؟
 */
function casting_image_process_is_readable(string $path): bool
{
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        return false;
    }
    $info = @getimagesize($path);

    return is_array($info) && (int) ($info[0] ?? 0) > 0 && (int) ($info[1] ?? 0) > 0;
}

/**
 * خروجی ترجیحی: WebP اگر ادیتور پشتیبانی کند، وگرنه JPEG.
 *
 * @return array{mime:string,ext:string}
 */
function casting_image_process_output_format(WP_Image_Editor $editor): array
{
    if (method_exists($editor, 'supports_mime_type') && $editor->supports_mime_type('image/webp')) {
        return ['mime' => 'image/webp', 'ext' => 'webp'];
    }

    return ['mime' => 'image/jpeg', 'ext' => 'jpg'];
}

/**
 * کراپ وسط به نسبت هدف، سپس ریسایز به ابعاد دقیق پروفایل (برای crop=true).
 * بدون کراپ: فقط fit داخل max_width×max_height.
 *
 * @param array{max_width:int,max_height:int,crop:bool,quality:int,upscale:bool} $profile
 * @return true|\WP_Error
 */
function casting_image_process_apply_geometry(WP_Image_Editor $editor, array $profile)
{
    $size = $editor->get_size();
    $src_w = (int) ($size['width'] ?? 0);
    $src_h = (int) ($size['height'] ?? 0);
    if ($src_w < 1 || $src_h < 1) {
        return new WP_Error('casting_image_size', 'ابعاد تصویر خوانده نشد.');
    }

    $max_w = max(1, (int) $profile['max_width']);
    $max_h = max(1, (int) $profile['max_height']);
    $crop = !empty($profile['crop']);
    $upscale = !empty($profile['upscale']);

    if ($crop) {
        $target_ratio = $max_w / $max_h;
        $src_ratio = $src_w / $src_h;
        if ($src_ratio > $target_ratio) {
            $crop_h = $src_h;
            $crop_w = (int) round($src_h * $target_ratio);
        } else {
            $crop_w = $src_w;
            $crop_h = (int) round($src_w / $target_ratio);
        }
        $crop_w = max(1, min($src_w, $crop_w));
        $crop_h = max(1, min($src_h, $crop_h));
        $src_x = (int) max(0, floor(($src_w - $crop_w) / 2));
        $src_y = (int) max(0, floor(($src_h - $crop_h) / 2));

        $cropped = $editor->crop($src_x, $src_y, $crop_w, $crop_h);
        if (is_wp_error($cropped)) {
            return $cropped;
        }

        if ($crop_w !== $max_w || $crop_h !== $max_h) {
            if ($upscale || $crop_w > $max_w || $crop_h > $max_h) {
                $resized = $editor->resize($max_w, $max_h, false);
                if (is_wp_error($resized)) {
                    return $resized;
                }
            }
        }

        return true;
    }

    if ($src_w <= $max_w && $src_h <= $max_h && !$upscale) {
        return true;
    }

    $resized = $editor->resize($max_w, $max_h, false);
    if (is_wp_error($resized)) {
        return $resized;
    }

    return true;
}

/**
 * پردازش فایل روی دیسک (مسیر ثابت) و جایگزینی با نسخهٔ بهینه‌شده.
 *
 * @return array{ok:bool,error:string,path:string,mime:string,ext:string,width:int,height:int,bytes:int}
 */
function casting_image_process_file(string $path, string $profile_key = 'general'): array
{
    $profiles = casting_image_process_profiles();
    if (!isset($profiles[$profile_key])) {
        $profile_key = 'general';
    }
    $profile = $profiles[$profile_key];

    if (!casting_image_process_is_readable($path)) {
        return [
            'ok'     => false,
            'error'  => 'فایل تصویر معتبر نیست یا خوانده نشد.',
            'path'   => $path,
            'mime'   => '',
            'ext'    => '',
            'width'  => 0,
            'height' => 0,
            'bytes'  => 0,
        ];
    }

    $info = @getimagesize($path);
    $mime_in = strtolower((string) ($info['mime'] ?? ''));
    if ($mime_in !== '' && !in_array($mime_in, casting_image_process_allowed_mimes(), true)) {
        return [
            'ok'     => false,
            'error'  => 'فرمت تصویر پشتیبانی نمی‌شود. JPG، PNG، WebP یا GIF بفرستید.',
            'path'   => $path,
            'mime'   => $mime_in,
            'ext'    => '',
            'width'  => 0,
            'height' => 0,
            'bytes'  => 0,
        ];
    }

    if (!function_exists('wp_get_image_editor')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $editor = wp_get_image_editor($path);
    if (is_wp_error($editor)) {
        return [
            'ok'     => false,
            'error'  => 'پردازش تصویر روی سرور ممکن نیست. فرمت دیگری امتحان کنید.',
            'path'   => $path,
            'mime'   => $mime_in,
            'ext'    => '',
            'width'  => 0,
            'height' => 0,
            'bytes'  => 0,
        ];
    }

    $geo = casting_image_process_apply_geometry($editor, $profile);
    if (is_wp_error($geo)) {
        return [
            'ok'     => false,
            'error'  => 'تنظیم ابعاد تصویر ناموفق بود.',
            'path'   => $path,
            'mime'   => $mime_in,
            'ext'    => '',
            'width'  => 0,
            'height' => 0,
            'bytes'  => 0,
        ];
    }

    $out = casting_image_process_output_format($editor);
    $editor->set_quality((int) $profile['quality']);

    $tmp_out = $path . '.casting.' . $out['ext'];
    $saved = $editor->save($tmp_out, $out['mime']);
    if (is_wp_error($saved) || empty($saved['path']) || !is_file((string) $saved['path'])) {
        if (is_file($tmp_out)) {
            @unlink($tmp_out);
        }

        return [
            'ok'     => false,
            'error'  => 'ذخیره تصویر بهینه‌شده ناموفق بود.',
            'path'   => $path,
            'mime'   => $out['mime'],
            'ext'    => $out['ext'],
            'width'  => 0,
            'height' => 0,
            'bytes'  => 0,
        ];
    }

    $saved_path = (string) $saved['path'];
    $bytes = (int) filesize($saved_path);
    if ($bytes < 1) {
        @unlink($saved_path);

        return [
            'ok'     => false,
            'error'  => 'خروجی تصویر خالی بود.',
            'path'   => $path,
            'mime'   => $out['mime'],
            'ext'    => $out['ext'],
            'width'  => 0,
            'height' => 0,
            'bytes'  => 0,
        ];
    }

    // جایگزینی روی همان مسیر موقت آپلود تا is_uploaded_file برقرار بماند.
    $replaced = false;
    if (@copy($saved_path, $path)) {
        $replaced = true;
    } elseif (@rename($saved_path, $path)) {
        $replaced = true;
        $saved_path = '';
    }
    if ($saved_path !== '' && is_file($saved_path)) {
        @unlink($saved_path);
    }
    if (!$replaced) {
        return [
            'ok'     => false,
            'error'  => 'جایگزینی فایل بهینه‌شده ناموفق بود.',
            'path'   => $path,
            'mime'   => $out['mime'],
            'ext'    => $out['ext'],
            'width'  => 0,
            'height' => 0,
            'bytes'  => 0,
        ];
    }

    clearstatcache(true, $path);
    $final_info = @getimagesize($path);
    $width = is_array($final_info) ? (int) ($final_info[0] ?? 0) : (int) ($saved['width'] ?? 0);
    $height = is_array($final_info) ? (int) ($final_info[1] ?? 0) : (int) ($saved['height'] ?? 0);
    $final_mime = is_array($final_info) && !empty($final_info['mime'])
        ? strtolower((string) $final_info['mime'])
        : $out['mime'];
    $final_ext = $final_mime === 'image/webp' ? 'webp' : 'jpg';

    return [
        'ok'     => true,
        'error'  => '',
        'path'   => $path,
        'mime'   => $final_mime,
        'ext'    => $final_ext,
        'width'  => $width,
        'height' => $height,
        'bytes'  => (int) filesize($path),
    ];
}

/**
 * نرمال‌سازی یک ورودی $_FILES برای آپلود تصویر.
 *
 * @param array<string, mixed> $file
 * @return array{ok:bool,error:string,width:int,height:int,mime:string}
 */
function casting_prepare_uploaded_image(array &$file, string $profile_key = 'general'): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        $msg = function_exists('casting_upload_php_error_message')
            ? casting_upload_php_error_message($error, 'image')
            : 'آپلود فایل ناموفق بود.';

        return ['ok' => false, 'error' => $msg, 'width' => 0, 'height' => 0, 'mime' => ''];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || (!is_uploaded_file($tmp) && !is_file($tmp))) {
        return ['ok' => false, 'error' => 'آپلود نامعتبر است.', 'width' => 0, 'height' => 0, 'mime' => ''];
    }

    $result = casting_image_process_file($tmp, $profile_key);
    if (!$result['ok']) {
        return [
            'ok'     => false,
            'error'  => $result['error'],
            'width'  => 0,
            'height' => 0,
            'mime'   => '',
        ];
    }

    $base = pathinfo((string) ($file['name'] ?? 'photo'), PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $base) ?: 'photo';
    $file['name'] = $base . '.' . $result['ext'];
    $file['type'] = $result['mime'];
    $file['size'] = $result['bytes'];
    $file['tmp_name'] = $tmp;

    return [
        'ok'     => true,
        'error'  => '',
        'width'  => $result['width'],
        'height' => $result['height'],
        'mime'   => $result['mime'],
    ];
}
