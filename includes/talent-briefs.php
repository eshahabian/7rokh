<?php
declare(strict_types=1);

require_once __DIR__ . '/director-workspace.php';

/**
 * @return array<string, string>
 */
function casting_talent_brief_type_labels(): array
{
    return [
        'read_text'     => 'خواندن متن',
        'perform_scene' => 'اجرای صحنه / تکه نمایش',
    ];
}

/**
 * @param array<string, mixed> $brief
 * @return array{audio:bool,video:bool,text:bool}
 */
function casting_talent_brief_requirements(array $brief): array
{
    $type = (string) ($brief['type'] ?? '');
    $also_text = !empty($brief['also_require_text']);
    $also_audio = !empty($brief['also_require_audio']);

    return [
        'audio' => $type === 'read_text' || ($type === 'perform_scene' && $also_audio),
        'video' => $type === 'perform_scene',
        'text'  => $also_text,
    ];
}

/**
 * @param array<string, mixed> $brief
 */
function casting_talent_brief_requirements_summary(array $brief): string
{
    $reqs = casting_talent_brief_requirements($brief);
    $parts = [];
    if ($reqs['audio']) {
        $parts[] = 'فایل صوتی';
    }
    if ($reqs['video']) {
        $parts[] = 'فایل ویدیو';
    }
    if ($reqs['text']) {
        $parts[] = 'پاسخ متنی';
    }

    return $parts !== [] ? implode(' + ', $parts) : '—';
}

/**
 * @param mixed $raw
 * @return list<array<string, mixed>>
 */
function casting_talent_normalize_briefs($raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $brief) {
        if (!is_array($brief) || empty($brief['id'])) {
            continue;
        }
        $response = is_array($brief['response'] ?? null) ? $brief['response'] : [];
        $out[] = [
            'id'                 => (string) $brief['id'],
            'director_id'        => (int) ($brief['director_id'] ?? 0),
            'director_name'      => (string) ($brief['director_name'] ?? ''),
            'type'               => (string) ($brief['type'] ?? ''),
            'title'              => (string) ($brief['title'] ?? ''),
            'text'               => (string) ($brief['text'] ?? ''),
            'also_require_text'  => !empty($brief['also_require_text']),
            'also_require_audio' => !empty($brief['also_require_audio']),
            'sent_at'            => (string) ($brief['sent_at'] ?? ''),
            'status'             => (string) ($brief['status'] ?? 'pending'),
            'response'           => [
                'audio_id'     => (int) ($response['audio_id'] ?? 0),
                'video_id'     => (int) ($response['video_id'] ?? 0),
                'text'         => (string) ($response['text'] ?? ''),
                'submitted_at' => (string) ($response['submitted_at'] ?? ''),
            ],
        ];
    }

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function casting_talent_get_briefs(int $talent_id): array
{
    if ($talent_id <= 0) {
        return [];
    }

    return casting_talent_normalize_briefs(get_user_meta($talent_id, 'casting_talent_briefs', true));
}

/**
 * @return list<array<string, mixed>>
 */
function casting_director_talent_briefs(int $director_id, int $talent_id): array
{
    $out = [];
    foreach (casting_talent_get_briefs($talent_id) as $brief) {
        if ((int) ($brief['director_id'] ?? 0) === $director_id) {
            $out[] = $brief;
        }
    }

    return $out;
}

function casting_talent_pending_brief_count(int $talent_id): int
{
    $count = 0;
    foreach (casting_talent_get_briefs($talent_id) as $brief) {
        if (($brief['status'] ?? '') === 'pending') {
            $count++;
        }
    }

    return $count;
}

/**
 * @return array<string, mixed>|null
 */
function casting_talent_find_brief(int $talent_id, string $brief_id): ?array
{
    $brief_id = trim($brief_id);
    if ($brief_id === '') {
        return null;
    }
    foreach (casting_talent_get_briefs($talent_id) as $brief) {
        if (($brief['id'] ?? '') === $brief_id) {
            return $brief;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $patch
 */
function casting_talent_update_brief(int $talent_id, string $brief_id, array $patch): bool
{
    $briefs = casting_talent_get_briefs($talent_id);
    $changed = false;
    foreach ($briefs as $i => $brief) {
        if (($brief['id'] ?? '') !== $brief_id) {
            continue;
        }
        $briefs[$i] = array_merge($brief, $patch);
        $changed = true;
        break;
    }
    if (!$changed) {
        return false;
    }

    update_user_meta($talent_id, 'casting_talent_briefs', array_slice($briefs, 0, 100));

    return true;
}

function casting_brief_attachment_url(int $attachment_id): string
{
    if ($attachment_id <= 0) {
        return '';
    }
    $url = wp_get_attachment_url($attachment_id);

    return is_string($url) ? $url : '';
}

/**
 * @return array{ok:bool,error?:string,attachment_id?:int,skipped?:bool}
 */
function casting_handle_brief_media_upload(int $user_id, string $field_name, string $kind): array
{
    if (empty($_FILES[$field_name]['name'])) {
        return ['ok' => true, 'skipped' => true];
    }

    if (!function_exists('casting_require_media_includes')) {
        require_once __DIR__ . '/profile.php';
    }
    casting_require_media_includes();

    $file = $_FILES[$field_name];
    $name = strtolower((string) ($file['name'] ?? ''));
    $ftype = (string) ($file['type'] ?? '');

    if ($kind === 'audio') {
        $allowed = ['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/webm', 'audio/ogg'];
        $ext_ok = preg_match('/\.(mp3|m4a|wav|webm|ogg|aac)$/', $name) === 1;
        $max = 25 * 1024 * 1024;
        $type_err = 'فقط فایل صوتی MP3، M4A، WAV، WebM یا OGG مجاز است.';
    } else {
        $allowed = ['video/mp4', 'video/webm', 'video/quicktime'];
        $ext_ok = preg_match('/\.(mp4|webm|mov)$/', $name) === 1;
        $max = 40 * 1024 * 1024;
        $type_err = 'فقط ویدیو MP4، WebM یا MOV مجاز است.';
    }

    if (!in_array($ftype, $allowed, true) && !$ext_ok) {
        return ['ok' => false, 'error' => $type_err];
    }
    if ((int) ($file['size'] ?? 0) > $max) {
        return ['ok' => false, 'error' => $kind === 'audio' ? 'حجم فایل صوتی حداکثر ۲۵ مگابایت باشد.' : 'حجم ویدیو حداکثر ۴۰ مگابایت باشد.'];
    }

    casting_enable_user_upload_dir($user_id);
    $attachment_id = media_handle_upload($field_name, 0);
    casting_disable_user_upload_dir();

    if (is_wp_error($attachment_id)) {
        return ['ok' => false, 'error' => 'آپلود ناموفق بود: ' . $attachment_id->get_error_message()];
    }

    return ['ok' => true, 'attachment_id' => (int) $attachment_id];
}

/**
 * @return array{ok:bool,error?:string}
 */
function casting_talent_submit_brief_response(int $talent_id, string $brief_id): array
{
    $brief = casting_talent_find_brief($talent_id, $brief_id);
    if (!$brief) {
        return ['ok' => false, 'error' => 'تکلیف پیدا نشد.'];
    }
    if (($brief['status'] ?? '') === 'submitted') {
        return ['ok' => false, 'error' => 'این تکلیف قبلاً ارسال شده است.'];
    }

    $reqs = casting_talent_brief_requirements($brief);
    $response = is_array($brief['response'] ?? null) ? $brief['response'] : [];
    $audio_id = (int) ($response['audio_id'] ?? 0);
    $video_id = (int) ($response['video_id'] ?? 0);
    $text = sanitize_textarea_field((string) ($_POST['response_text'] ?? ''));

    if ($reqs['audio']) {
        $audio = casting_handle_brief_media_upload($talent_id, 'brief_audio', 'audio');
        if (!$audio['ok']) {
            return ['ok' => false, 'error' => $audio['error'] ?? 'آپلود صوت ناموفق بود.'];
        }
        if (!empty($audio['attachment_id'])) {
            $audio_id = (int) $audio['attachment_id'];
        }
        if ($audio_id <= 0) {
            return ['ok' => false, 'error' => 'فایل صوتی الزامی است.'];
        }
    }

    if ($reqs['video']) {
        $video = casting_handle_brief_media_upload($talent_id, 'brief_video', 'video');
        if (!$video['ok']) {
            return ['ok' => false, 'error' => $video['error'] ?? 'آپلود ویدیو ناموفق بود.'];
        }
        if (!empty($video['attachment_id'])) {
            $video_id = (int) $video['attachment_id'];
        }
        if ($video_id <= 0) {
            return ['ok' => false, 'error' => 'فایل ویدیو الزامی است.'];
        }
    }

    if ($reqs['text']) {
        if (trim($text) === '') {
            return ['ok' => false, 'error' => 'پاسخ متنی الزامی است.'];
        }
        if (casting_strlen($text) > 5000) {
            return ['ok' => false, 'error' => 'پاسخ متنی خیلی بلند است.'];
        }
    } else {
        $text = '';
    }

    $updated = casting_talent_update_brief($talent_id, $brief_id, [
        'status'   => 'submitted',
        'response' => [
            'audio_id'     => $audio_id,
            'video_id'     => $video_id,
            'text'         => $text,
            'submitted_at' => current_time('mysql'),
        ],
    ]);
    if (!$updated) {
        return ['ok' => false, 'error' => 'ذخیره پاسخ ناموفق بود.'];
    }

    $director_id = (int) ($brief['director_id'] ?? 0);
    $director = $director_id > 0 ? get_user_by('id', $director_id) : false;
    $talent = get_user_by('id', $talent_id);
    if ($director && $talent && function_exists('casting_send_mail')) {
        if (!function_exists('casting_send_mail')) {
            require_once __DIR__ . '/mail.php';
        }
        $type_label = casting_talent_brief_type_labels()[$brief['type'] ?? ''] ?? 'تکلیف';
        $subject = sprintf('[%s] پاسخ تکلیف از %s', casting_brand(), $talent->display_name);
        $body = "سلام " . $director->display_name . "\n\n"
            . "بازیگر «" . $talent->display_name . "» پاسخ تکلیف («" . $type_label . "») را ارسال کرد.\n\n"
            . 'مشاهده پروفایل: ' . casting_url('member.php?id=' . $talent_id . '#director-workspace') . "\n";
        casting_send_mail((string) $director->user_email, $subject, $body);
    }

    return ['ok' => true];
}

function casting_render_talent_brief_card(array $brief, bool $show_form = true): void
{
    $type_labels = casting_talent_brief_type_labels();
    $type_label = $type_labels[$brief['type'] ?? ''] ?? (string) ($brief['type'] ?? '');
    $reqs = casting_talent_brief_requirements($brief);
    $req_summary = casting_talent_brief_requirements_summary($brief);
    $status = (string) ($brief['status'] ?? 'pending');
    $response = is_array($brief['response'] ?? null) ? $brief['response'] : [];
    $brief_id = (string) ($brief['id'] ?? '');
    ?>
<article class="brief-card<?= $status === 'submitted' ? ' brief-card--done' : ' brief-card--pending' ?>">
  <header class="brief-card-head">
    <div>
      <h3><?= casting_e($type_label) ?><?php if (($brief['title'] ?? '') !== '') : ?> — <?= casting_e((string) $brief['title']) ?><?php endif; ?></h3>
      <p class="meta">از <?= casting_e((string) ($brief['director_name'] ?? '')) ?> · <?= casting_e((string) ($brief['sent_at'] ?? '')) ?></p>
    </div>
    <span class="chip"><?= $status === 'submitted' ? 'ارسال شده' : 'در انتظار پاسخ' ?></span>
  </header>

  <div class="brief-card-body">
    <p class="brief-requirements"><strong>ارسال لازم:</strong> <?= casting_e($req_summary) ?></p>
    <div class="brief-text"><?= nl2br(casting_e((string) ($brief['text'] ?? ''))) ?></div>

    <?php if ($status === 'submitted') : ?>
      <div class="brief-response">
        <?php if (!empty($response['audio_id'])) : ?>
          <?php $audio_url = casting_brief_attachment_url((int) $response['audio_id']); ?>
          <?php if ($audio_url !== '') : ?>
            <p><strong>فایل صوتی:</strong></p>
            <audio controls preload="metadata" src="<?= casting_e($audio_url) ?>"></audio>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($response['video_id'])) : ?>
          <?php $video_url = casting_brief_attachment_url((int) $response['video_id']); ?>
          <?php if ($video_url !== '') : ?>
            <p><strong>فایل ویدیو:</strong></p>
            <video controls preload="metadata" src="<?= casting_e($video_url) ?>"></video>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (($response['text'] ?? '') !== '') : ?>
          <p><strong>پاسخ متنی:</strong></p>
          <p><?= nl2br(casting_e((string) $response['text'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($response['submitted_at'])) : ?>
          <p class="field-hint">ارسال پاسخ: <?= casting_e((string) $response['submitted_at']) ?></p>
        <?php endif; ?>
      </div>
    <?php elseif ($show_form && $brief_id !== '') : ?>
      <form class="form brief-response-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('casting_brief_' . $brief_id); ?>
        <input type="hidden" name="brief_id" value="<?= casting_e($brief_id) ?>">
        <input type="hidden" name="brief_action" value="submit">

        <?php if ($reqs['audio']) : ?>
          <div class="field">
            <label for="brief_audio_<?= casting_e($brief_id) ?>">فایل صوتی <span class="req-mark">*</span></label>
            <input id="brief_audio_<?= casting_e($brief_id) ?>" name="brief_audio" type="file" accept="audio/*,.mp3,.m4a,.wav,.webm,.ogg" required>
            <p class="field-hint">MP3 / M4A / WAV / WebM / OGG — حداکثر ۲۵ مگابایت</p>
          </div>
        <?php endif; ?>

        <?php if ($reqs['video']) : ?>
          <div class="field">
            <label for="brief_video_<?= casting_e($brief_id) ?>">فایل ویدیو <span class="req-mark">*</span></label>
            <input id="brief_video_<?= casting_e($brief_id) ?>" name="brief_video" type="file" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov" required>
            <p class="field-hint">MP4 / WebM / MOV — حداکثر ۴۰ مگابایت</p>
          </div>
        <?php endif; ?>

        <?php if ($reqs['text']) : ?>
          <div class="field">
            <label for="response_text_<?= casting_e($brief_id) ?>">پاسخ متنی <span class="req-mark">*</span></label>
            <textarea id="response_text_<?= casting_e($brief_id) ?>" name="response_text" rows="4" maxlength="5000" required placeholder="توضیح یا پاسخ متنی…"></textarea>
          </div>
        <?php endif; ?>

        <button class="btn btn-primary" type="submit">ارسال پاسخ تکلیف</button>
      </form>
    <?php endif; ?>
  </div>
</article>
    <?php
}

function casting_render_director_brief_responses(int $director_id, int $talent_id): void
{
    $briefs = casting_director_talent_briefs($director_id, $talent_id);
    if ($briefs === []) {
        return;
    }
    ?>
    <div class="director-brief-history">
      <h4>تکالیف ارسال‌شده به این بازیگر</h4>
      <?php foreach (array_slice($briefs, 0, 10) as $brief) : ?>
        <?php casting_render_talent_brief_card($brief, false); ?>
      <?php endforeach; ?>
    </div>
    <?php
}
