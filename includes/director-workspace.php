<?php
declare(strict_types=1);

function casting_director_workspace_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'casting_director_workspace';
}

function casting_director_workspace_install(): void
{
    global $wpdb;
    $table = casting_director_workspace_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        director_id BIGINT UNSIGNED NOT NULL,
        talent_id BIGINT UNSIGNED NOT NULL,
        notes TEXT NULL,
        is_highlight TINYINT(1) NOT NULL DEFAULT 0,
        highlighted_sections TEXT NULL,
        assignment_type VARCHAR(32) NOT NULL DEFAULT '',
        assignment_title VARCHAR(191) NOT NULL DEFAULT '',
        assignment_text TEXT NULL,
        assignment_sent_at DATETIME NULL,
        first_viewed_at DATETIME NULL,
        last_viewed_at DATETIME NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY director_talent (director_id, talent_id),
        KEY director_id (director_id),
        KEY director_highlight (director_id, is_highlight)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('casting_director_workspace_db_version', '1');
}

function casting_director_workspace_ensure_table(): void
{
    if ((string) get_option('casting_director_workspace_db_version', '') !== '1') {
        casting_director_workspace_install();
    }
}

function casting_user_is_director_role(int $user_id): bool
{
    return casting_get_user_role($user_id) === 'director';
}

function casting_director_can_manage_talent(int $director_id, int $talent_id): bool
{
    if ($director_id <= 0 || $talent_id <= 0 || $director_id === $talent_id) {
        return false;
    }
    if (!casting_user_is_director_role($director_id)) {
        return false;
    }
    if (casting_get_user_role($talent_id) !== 'talent') {
        return false;
    }
    if (function_exists('casting_users_block_each_other') && casting_users_block_each_other($director_id, $talent_id)) {
        return false;
    }

    return true;
}

/**
 * @return array<string, string>
 */
function casting_director_workspace_section_labels(): array
{
    return [
        'portraits'  => 'عکس‌ها',
        'info'       => 'اطلاعات پایه',
        'activities' => 'نوع فعالیت',
        'bio'        => 'درباره',
        'skills'     => 'مهارت‌ها',
    ];
}

/**
 * @return array<string, mixed>
 */
function casting_director_workspace_defaults(): array
{
    return [
        'notes'                => '',
        'is_highlight'         => false,
        'highlighted_sections' => [],
        'assignment_type'      => '',
        'assignment_title'     => '',
        'assignment_text'      => '',
        'assignment_sent_at'   => '',
        'first_viewed_at'      => '',
        'last_viewed_at'       => '',
        'viewed'               => false,
    ];
}

/**
 * @param mixed $raw
 * @return list<string>
 */
function casting_director_normalize_highlighted_sections($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $labels = casting_director_workspace_section_labels();
    $out = [];
    foreach ($raw as $key) {
        $key = sanitize_key((string) $key);
        if ($key !== '' && isset($labels[$key]) && !in_array($key, $out, true)) {
            $out[] = $key;
        }
    }
    return $out;
}

/**
 * @param array<string, mixed>|null $row
 * @return array<string, mixed>
 */
function casting_director_workspace_from_row(?array $row): array
{
    $defaults = casting_director_workspace_defaults();
    if (!$row) {
        return $defaults;
    }

    $sections = [];
    if (!empty($row['highlighted_sections'])) {
        $decoded = json_decode((string) $row['highlighted_sections'], true);
        $sections = casting_director_normalize_highlighted_sections($decoded);
    }

    return [
        'notes'                => (string) ($row['notes'] ?? ''),
        'is_highlight'         => !empty($row['is_highlight']),
        'highlighted_sections' => $sections,
        'assignment_type'      => (string) ($row['assignment_type'] ?? ''),
        'assignment_title'     => (string) ($row['assignment_title'] ?? ''),
        'assignment_text'      => (string) ($row['assignment_text'] ?? ''),
        'assignment_sent_at'   => (string) ($row['assignment_sent_at'] ?? ''),
        'first_viewed_at'      => (string) ($row['first_viewed_at'] ?? ''),
        'last_viewed_at'       => (string) ($row['last_viewed_at'] ?? ''),
        'viewed'               => !empty($row['first_viewed_at']),
    ];
}

function casting_director_get_workspace(int $director_id, int $talent_id): array
{
    casting_director_workspace_ensure_table();
    if (!casting_director_can_manage_talent($director_id, $talent_id)) {
        return casting_director_workspace_defaults();
    }

    global $wpdb;
    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM ' . casting_director_workspace_table() . ' WHERE director_id = %d AND talent_id = %d LIMIT 1',
            $director_id,
            $talent_id
        ),
        ARRAY_A
    );

    return casting_director_workspace_from_row(is_array($row) ? $row : null);
}

/**
 * @param list<int> $talent_ids
 * @return array<int, array{viewed:bool,is_highlight:bool}>
 */
function casting_director_workspace_flags_for_talents(int $director_id, array $talent_ids): array
{
    casting_director_workspace_ensure_table();
    $out = [];
    $talent_ids = array_values(array_unique(array_filter(array_map('intval', $talent_ids))));
    if (!casting_user_is_director_role($director_id) || $talent_ids === []) {
        return $out;
    }

    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($talent_ids), '%d'));
    $sql = 'SELECT talent_id, first_viewed_at, is_highlight FROM ' . casting_director_workspace_table()
        . ' WHERE director_id = %d AND talent_id IN (' . $placeholders . ')';
    $params = array_merge([$director_id], $talent_ids);
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    if (!is_array($rows)) {
        return $out;
    }

    foreach ($rows as $row) {
        $tid = (int) ($row['talent_id'] ?? 0);
        if ($tid <= 0) {
            continue;
        }
        $out[$tid] = [
            'viewed'        => !empty($row['first_viewed_at']),
            'is_highlight'  => !empty($row['is_highlight']),
        ];
    }

    return $out;
}

/**
 * @return list<array{talent_id:int,name:string,photo_url:string,city:string}>
 */
function casting_director_list_highlighted_talents(int $director_id): array
{
    casting_director_workspace_ensure_table();
    if (!casting_user_is_director_role($director_id)) {
        return [];
    }

    global $wpdb;
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT talent_id FROM ' . casting_director_workspace_table()
            . ' WHERE director_id = %d AND is_highlight = 1 ORDER BY updated_at DESC, talent_id DESC',
            $director_id
        ),
        ARRAY_A
    );
    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $talent_id = (int) ($row['talent_id'] ?? 0);
        if ($talent_id <= 0 || !casting_director_can_manage_talent($director_id, $talent_id)) {
            continue;
        }
        $talent = get_user_by('id', $talent_id);
        if (!$talent) {
            continue;
        }
        $profile = casting_get_profile($talent_id);
        if (!$profile['visible']) {
            continue;
        }
        $out[] = [
            'talent_id'  => $talent_id,
            'name'       => (string) $talent->display_name,
            'photo_url'  => (string) ($profile['photo_url'] ?? ''),
            'city'       => (string) ($profile['city'] ?? ''),
        ];
    }

    return $out;
}

function casting_director_record_talent_view(int $director_id, int $talent_id): void
{
    casting_director_workspace_ensure_table();
    if (!casting_director_can_manage_talent($director_id, $talent_id)) {
        return;
    }

    global $wpdb;
    $table = casting_director_workspace_table();
    $now = current_time('mysql');
    $existing = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT id FROM ' . $table . ' WHERE director_id = %d AND talent_id = %d LIMIT 1',
            $director_id,
            $talent_id
        )
    );

    if ($existing) {
        $wpdb->update(
            $table,
            [
                'last_viewed_at' => $now,
                'updated_at'     => $now,
            ],
            [
                'director_id' => $director_id,
                'talent_id'   => $talent_id,
            ],
            ['%s', '%s'],
            ['%d', '%d']
        );
        return;
    }

    $wpdb->insert(
        $table,
        [
            'director_id'     => $director_id,
            'talent_id'       => $talent_id,
            'first_viewed_at' => $now,
            'last_viewed_at'  => $now,
            'updated_at'      => $now,
        ],
        ['%d', '%d', '%s', '%s', '%s']
    );
}

/**
 * @param array<string, mixed> $data
 * @return array{ok:bool,error?:string,workspace?:array<string,mixed>}
 */
function casting_director_save_workspace(int $director_id, int $talent_id, array $data): array
{
    casting_director_workspace_ensure_table();
    if (!casting_director_can_manage_talent($director_id, $talent_id)) {
        return ['ok' => false, 'error' => 'دسترسی مجاز نیست.'];
    }

    $notes = sanitize_textarea_field((string) ($data['notes'] ?? ''));
    if (casting_strlen($notes) > 5000) {
        return ['ok' => false, 'error' => 'یادداشت خیلی بلند است.'];
    }

    $is_highlight = !empty($data['is_highlight']);
    $sections = casting_director_normalize_highlighted_sections($data['highlighted_sections'] ?? []);
    $assignment_type = sanitize_key((string) ($data['assignment_type'] ?? ''));
    if (!in_array($assignment_type, ['', 'read_text', 'perform_scene'], true)) {
        $assignment_type = '';
    }
    $assignment_title = sanitize_text_field((string) ($data['assignment_title'] ?? ''));
    $assignment_text = sanitize_textarea_field((string) ($data['assignment_text'] ?? ''));
    if (casting_strlen($assignment_title) > 191) {
        return ['ok' => false, 'error' => 'عنوان تکلیف خیلی بلند است.'];
    }
    if (casting_strlen($assignment_text) > 5000) {
        return ['ok' => false, 'error' => 'متن تکلیف خیلی بلند است.'];
    }

    global $wpdb;
    $table = casting_director_workspace_table();
    $now = current_time('mysql');
    $payload = [
        'notes'                => $notes,
        'is_highlight'         => $is_highlight ? 1 : 0,
        'highlighted_sections' => wp_json_encode($sections, JSON_UNESCAPED_UNICODE),
        'assignment_type'      => $assignment_type,
        'assignment_title'     => $assignment_title,
        'assignment_text'      => $assignment_text,
        'updated_at'           => $now,
    ];

    $existing = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT id FROM ' . $table . ' WHERE director_id = %d AND talent_id = %d LIMIT 1',
            $director_id,
            $talent_id
        )
    );

    if ($existing) {
        $wpdb->update(
            $table,
            $payload,
            [
                'director_id' => $director_id,
                'talent_id'   => $talent_id,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s'],
            ['%d', '%d']
        );
    } else {
        $payload['director_id'] = $director_id;
        $payload['talent_id'] = $talent_id;
        $payload['first_viewed_at'] = $now;
        $payload['last_viewed_at'] = $now;
        $wpdb->insert(
            $table,
            $payload,
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
        );
    }

    return ['ok' => true, 'workspace' => casting_director_get_workspace($director_id, $talent_id)];
}

/**
 * @return array{ok:bool,error?:string,workspace?:array<string,mixed>}
 */
function casting_director_send_assignment(int $director_id, int $talent_id): array
{
    $workspace = casting_director_get_workspace($director_id, $talent_id);
    $type = (string) ($workspace['assignment_type'] ?? '');
    $text = trim((string) ($workspace['assignment_text'] ?? ''));
    $title = trim((string) ($workspace['assignment_title'] ?? ''));
    if (!in_array($type, ['read_text', 'perform_scene'], true)) {
        return ['ok' => false, 'error' => 'نوع تکلیف را انتخاب کنید.'];
    }
    if ($text === '') {
        return ['ok' => false, 'error' => 'متن تکلیف را بنویسید.'];
    }

    $director = get_user_by('id', $director_id);
    $talent = get_user_by('id', $talent_id);
    if (!$director || !$talent) {
        return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
    }

    $brief = [
        'id'            => uniqid('brief_', true),
        'director_id'   => $director_id,
        'director_name' => (string) $director->display_name,
        'type'          => $type,
        'title'         => $title,
        'text'          => $text,
        'sent_at'       => current_time('mysql'),
        'status'        => 'pending',
    ];

    $inbox = get_user_meta($talent_id, 'casting_talent_briefs', true);
    if (!is_array($inbox)) {
        $inbox = [];
    }
    array_unshift($inbox, $brief);
    update_user_meta($talent_id, 'casting_talent_briefs', array_slice($inbox, 0, 100));

    global $wpdb;
    $wpdb->update(
        casting_director_workspace_table(),
        [
            'assignment_sent_at' => $brief['sent_at'],
            'updated_at'         => current_time('mysql'),
        ],
        [
            'director_id' => $director_id,
            'talent_id'   => $talent_id,
        ],
        ['%s', '%s'],
        ['%d', '%d']
    );

    if (!function_exists('casting_send_mail')) {
        require_once __DIR__ . '/mail.php';
    }
    $type_label = $type === 'perform_scene' ? 'اجرای صحنه' : 'خواندن متن';
    $subject = sprintf('[%s] تکلیف جدید از %s', casting_brand(), $director->display_name);
    $body = "سلام " . $talent->display_name . "\n\n"
        . "کارگردان «" . $director->display_name . "» یک تکلیف برای شما فرستاده است.\n"
        . 'نوع: ' . $type_label . "\n";
    if ($title !== '') {
        $body .= 'عنوان: ' . $title . "\n";
    }
    $body .= "\n" . $text . "\n\n"
        . 'برای مشاهده در پورتال وارد شوید: ' . casting_url('panel.php') . "\n";
    casting_send_mail((string) $talent->user_email, $subject, $body);

    return ['ok' => true, 'workspace' => casting_director_get_workspace($director_id, $talent_id)];
}

function casting_director_section_class(array $workspace, string $section_key): string
{
    $sections = $workspace['highlighted_sections'] ?? [];
    if (!is_array($sections) || !in_array($section_key, $sections, true)) {
        return '';
    }

    return ' director-section-highlight';
}

function casting_render_director_viewed_badge(bool $viewed, string $extra_class = ''): void
{
    if (!$viewed) {
        return;
    }
    ?>
    <span class="director-viewed-badge<?= $extra_class !== '' ? ' ' . casting_e(trim($extra_class)) : '' ?>" title="این پروفایل را دیده‌اید" aria-label="دیده شده">
      <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5C21.27 7.61 17 4.5 12 4.5Zm0 12.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-2.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
      </svg>
    </span>
    <?php
}

function casting_render_director_talent_workspace_panel(int $director_id, int $talent_id, array $workspace): void
{
    if (!casting_director_can_manage_talent($director_id, $talent_id)) {
        return;
    }

    $section_labels = casting_director_workspace_section_labels();
    $assignment_types = [
        'read_text'     => 'خواندن متن',
        'perform_scene' => 'اجرای صحنه / تکه نمایش',
    ];
    ?>
    <div class="bio-block director-workspace" id="director-workspace">
      <h3>یادداشت و انتخاب شما</h3>
      <p class="field-hint">این بخش فقط برای شماست؛ سایر کارگردان‌ها و خود بازیگر آن را نمی‌بینند.</p>

      <form class="form" method="post" action="member.php?id=<?= $talent_id ?>#director-workspace">
        <?php wp_nonce_field('casting_director_workspace_' . $talent_id); ?>
        <input type="hidden" name="director_workspace" value="1">

        <label class="check-row">
          <input type="checkbox" name="is_highlight" value="1" <?= !empty($workspace['is_highlight']) ? 'checked' : '' ?>>
          <span>هایلایت — این بازیگر را در لیست جستجو برجسته کن</span>
        </label>

        <div class="field">
          <label for="director_notes">یادداشت خصوصی</label>
          <textarea id="director_notes" name="notes" rows="4" maxlength="5000" placeholder="نظر، نکته، یا یادآوری درباره این بازیگر…"><?= casting_e((string) ($workspace['notes'] ?? '')) ?></textarea>
        </div>

        <fieldset class="field">
          <legend>هایلایت بخش‌های پروفایل</legend>
          <p class="field-hint">بخش‌هایی که برایتان مهم‌تر است را علامت بزنید تا در همین صفحه برجسته شوند.</p>
          <div class="director-section-picks">
            <?php foreach ($section_labels as $key => $label) : ?>
              <label class="check-row">
                <input
                  type="checkbox"
                  name="highlighted_sections[]"
                  value="<?= casting_e($key) ?>"
                  <?= in_array($key, $workspace['highlighted_sections'] ?? [], true) ? 'checked' : '' ?>
                >
                <span><?= casting_e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <div class="director-assignment">
          <h4>تکلیف / سناریوی تست</h4>
          <p class="field-hint">متنی برای بازیگر بفرستید — مثلاً بخواند یا یک صحنه را اجرا کند.</p>
          <div class="form-grid">
            <div class="field">
              <label for="assignment_type">نوع تکلیف</label>
              <select id="assignment_type" name="assignment_type">
                <option value="">انتخاب کنید</option>
                <?php foreach ($assignment_types as $key => $label) : ?>
                  <option value="<?= casting_e($key) ?>" <?= ($workspace['assignment_type'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="assignment_title">عنوان (اختیاری)</label>
              <input id="assignment_title" name="assignment_title" type="text" maxlength="191" value="<?= casting_e((string) ($workspace['assignment_title'] ?? '')) ?>">
            </div>
          </div>
          <div class="field">
            <label for="assignment_text">متن تکلیف</label>
            <textarea id="assignment_text" name="assignment_text" rows="5" maxlength="5000" placeholder="متن برای خواندن، یا توضیح صحنه‌ای که باید اجرا شود…"><?= casting_e((string) ($workspace['assignment_text'] ?? '')) ?></textarea>
          </div>
          <?php if (!empty($workspace['assignment_sent_at'])) : ?>
            <p class="field-hint">آخرین ارسال: <?= casting_e((string) $workspace['assignment_sent_at']) ?></p>
          <?php endif; ?>
        </div>

        <div class="cta-row">
          <button class="btn btn-primary" type="submit" name="director_action" value="save">ذخیره یادداشت</button>
          <button class="btn btn-ghost" type="submit" name="director_action" value="send_assignment">ارسال تکلیف به بازیگر</button>
        </div>
      </form>
    </div>
    <?php
}
