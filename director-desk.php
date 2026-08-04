<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/director-desk.php';

$user = casting_require_casting_user();
$director_id = (int) $user->ID;

if (!casting_user_is_director_role($director_id)) {
    casting_set_flash('error', 'این بخش فقط برای کارگردان‌هاست.');
    casting_redirect('home.php');
}

$project_id = max(0, (int) ($_GET['project'] ?? 0));
$role_id = max(0, (int) ($_GET['role'] ?? 0));
$opp_id = max(0, (int) ($_GET['opp'] ?? 0));
$app_folder = sanitize_key((string) ($_GET['folder'] ?? 'pending'));
$error = '';

require_once __DIR__ . '/includes/opportunities.php';
casting_opportunities_ensure_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'casting_director_desk_page')) {
        $error = 'درخواست نامعتبر است.';
    } else {
        $action = (string) ($_POST['desk_action'] ?? '');
        if ($action === 'create_project') {
            $result = casting_director_create_project(
                $director_id,
                (string) ($_POST['project_title'] ?? ''),
                (string) ($_POST['project_type'] ?? 'film')
            );
            if (!$result['ok']) {
                $error = $result['error'] ?? 'خطا';
            } else {
                casting_set_flash('success', 'پروژه «' . sanitize_text_field((string) ($_POST['project_title'] ?? '')) . '» اضافه شد.');
                casting_redirect('director-desk.php');
            }
        } elseif ($action === 'save_project' && $project_id > 0) {
            $result = casting_director_save_project($director_id, $project_id, $_POST);
            if (!$result['ok']) {
                $error = $result['error'] ?? 'خطا';
            } else {
                casting_set_flash('success', 'اطلاعات پروژه ذخیره شد.');
                casting_redirect('director-desk.php?project=' . $project_id);
            }
        } elseif ($action === 'create_role' && $project_id > 0) {
            $result = casting_director_create_role(
                $director_id,
                $project_id,
                (string) ($_POST['role_title'] ?? ''),
                (string) ($_POST['role_description'] ?? '')
            );
            if (!$result['ok']) {
                $error = $result['error'] ?? 'خطا';
            } else {
                casting_set_flash('success', 'نقش اضافه شد.');
                casting_redirect('director-desk.php?project=' . $project_id . '&role=' . (int) ($result['role_id'] ?? 0));
            }
        } elseif ($action === 'delete_project' && $project_id > 0) {
            casting_director_delete_project($director_id, $project_id);
            casting_set_flash('success', 'پروژه حذف شد.');
            casting_redirect('director-desk.php');
        } elseif ($action === 'delete_role' && $role_id > 0) {
            $role = casting_director_get_role($director_id, $role_id);
            $pid = $role ? (int) $role['project_id'] : 0;
            casting_director_delete_role($director_id, $role_id);
            casting_set_flash('success', 'نقش حذف شد.');
            casting_redirect('director-desk.php' . ($pid > 0 ? '?project=' . $pid : ''));
        } elseif ($action === 'save_role_talent' && $role_id > 0) {
            $talent_id = (int) ($_POST['talent_id'] ?? 0);
            $result = casting_director_save_role_talent($director_id, $role_id, $talent_id, [
                'ratings' => is_array($_POST['ratings'] ?? null) ? $_POST['ratings'] : [],
                'notes'   => (string) ($_POST['role_notes'] ?? ''),
                'status'  => (string) ($_POST['status'] ?? 'candidate'),
            ]);
            if (!$result['ok']) {
                $error = $result['error'] ?? 'خطا';
            } else {
                casting_set_flash('success', 'امتیاز ذخیره شد.');
                casting_redirect('director-desk.php?project=' . $project_id . '&role=' . $role_id);
            }
        } elseif ($action === 'remove_role_talent' && $role_id > 0) {
            $talent_id = (int) ($_POST['talent_id'] ?? 0);
            casting_director_remove_role_talent($director_id, $role_id, $talent_id);
            casting_set_flash('success', 'بازیگر از نقش حذف شد.');
            casting_redirect('director-desk.php?project=' . $project_id . '&role=' . $role_id);
        } elseif ($action === 'send_casting_call' && $project_id > 0) {
            $call_filters = casting_director_parse_call_filters($_POST);
            $publish_public = !empty($_POST['publish_public']);
            $call_role_id = max(0, (int) ($_POST['call_role_id'] ?? 0));
            $result = casting_director_send_casting_call(
                $director_id,
                $project_id,
                $call_filters,
                (string) ($_POST['call_message'] ?? ''),
                $publish_public,
                $call_role_id
            );
            if (!$result['ok']) {
                $error = $result['error'] ?? 'ارسال فراخوان ناموفق بود.';
            } else {
                $msg = 'فراخوان برای ' . (int) ($result['sent'] ?? 0) . ' عضو ارسال شد';
                if ((int) ($result['matched'] ?? 0) > (int) ($result['sent'] ?? 0)) {
                    $msg .= ' (از ' . (int) ($result['matched'] ?? 0) . ' نفر منطبق)';
                }
                if (!empty($result['opportunity_id'])) {
                    $msg .= ' و در فید عمومی فرصت‌ها منتشر شد.';
                } else {
                    $msg .= '.';
                }
                casting_set_flash('success', $msg);
                casting_redirect('director-desk.php?project=' . $project_id);
            }
        } elseif ($action === 'close_opportunity' && $project_id > 0) {
            require_once __DIR__ . '/includes/opportunities.php';
            $oid = max(0, (int) ($_POST['opportunity_id'] ?? 0));
            $result = casting_opportunity_close($director_id, $oid);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', 'فراخوان بسته شد.');
                casting_redirect('director-desk.php?project=' . $project_id);
            }
        } elseif ($action === 'set_application_status' && $project_id > 0) {
            require_once __DIR__ . '/includes/opportunities.php';
            $app_id = max(0, (int) ($_POST['application_id'] ?? 0));
            $status = sanitize_key((string) ($_POST['app_status'] ?? ''));
            $result = casting_opportunity_set_application_status($director_id, $app_id, $status);
            $opp_id = max(0, (int) ($_POST['opportunity_id'] ?? 0));
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', 'وضعیت اپلای به‌روز شد.');
                $redir = 'director-desk.php?project=' . $project_id;
                if ($opp_id > 0) {
                    $redir .= '&opp=' . $opp_id . '&folder=' . rawurlencode($status);
                }
                casting_redirect($redir);
            }
        } elseif ($action === 'bulk_application_status' && $project_id > 0) {
            require_once __DIR__ . '/includes/opportunities.php';
            $opp_id = max(0, (int) ($_POST['opportunity_id'] ?? 0));
            $status = sanitize_key((string) ($_POST['app_status'] ?? ''));
            $ids_raw = $_POST['application_ids'] ?? [];
            $ids = [];
            if (is_array($ids_raw)) {
                foreach ($ids_raw as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            }
            $result = casting_opportunity_bulk_set_application_status($director_id, $ids, $status);
            if (!$result['ok']) {
                $error = $result['error'];
            } else {
                casting_set_flash('success', (int) ($result['updated'] ?? 0) . ' متقاضی به‌روز شد.');
                casting_redirect(
                    'director-desk.php?project=' . $project_id
                    . ($opp_id > 0 ? '&opp=' . $opp_id : '')
                    . '&folder=' . rawurlencode($status)
                );
            }
        }
    }
}

$projects = casting_director_list_projects($director_id);
if ($project_id > 0 && !casting_director_get_project($director_id, $project_id)) {
    $project_id = 0;
    $role_id = 0;
}

$roles = $project_id > 0 ? casting_director_list_roles($director_id, $project_id) : [];
if ($role_id > 0 && !casting_director_get_role($director_id, $role_id)) {
    $role_id = 0;
}

$role_talents = $role_id > 0 ? casting_director_list_role_talents($director_id, $role_id) : [];
$active_project = $project_id > 0 ? casting_director_get_project($director_id, $project_id) : null;
$active_role = $role_id > 0 ? casting_director_get_role($director_id, $role_id) : null;
$active_opp = null;
$opp_applicants = [];
$opp_folder_counts = [];
$opp_folder_labels = casting_opportunity_application_folder_labels();
if (!isset($opp_folder_labels[$app_folder])) {
    $app_folder = 'pending';
}
if ($opp_id > 0) {
    $active_opp = casting_opportunity_get($opp_id);
    if (!$active_opp || (int) ($active_opp['director_id'] ?? 0) !== $director_id || (int) ($active_opp['project_id'] ?? 0) !== $project_id) {
        $opp_id = 0;
        $active_opp = null;
    } else {
        $opp_applicants = casting_opportunity_enrich_applicants(
            casting_opportunity_list_applicants($opp_id, 200)
        );
        $opp_folder_counts = casting_opportunity_application_folder_counts($opp_applicants);
    }
}
$app_status_labels = casting_opportunity_application_status_labels();
$project_types = casting_director_project_type_labels();
$status_labels = casting_director_role_talent_status_labels();
$production_statuses = casting_director_production_status_labels();
$project_stats = $active_project ? casting_director_project_stats($director_id, $project_id) : ['roles' => 0, 'talents' => 0];

casting_render_panel_start('پروژه‌ها', 'desk');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card director-desk-page">
  <?php if ($project_id <= 0) : ?>
    <h1>پروژه‌ها</h1>
    <p class="lede">فیلم، سریال یا تئاتر خود را بسازید. هر پروژه جداگانه مدیریت می‌شود.</p>

    <form class="form director-project-create" method="post">
      <?php wp_nonce_field('casting_director_desk_page'); ?>
      <input type="hidden" name="desk_action" value="create_project">
      <div class="form-grid">
        <div class="field">
          <label for="project_title">نام پروژه</label>
          <input id="project_title" name="project_title" type="text" required maxlength="191" placeholder="مثلاً نام فیلم یا نمایش">
        </div>
        <div class="field">
          <label for="project_type">نوع</label>
          <select id="project_type" name="project_type">
            <?php foreach ($project_types as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">افزودن پروژه</button>
    </form>

    <?php if ($projects) : ?>
      <div class="director-project-lines" aria-label="فهرست پروژه‌ها">
        <?php foreach ($projects as $index => $project) :
            $pid = (int) $project['id'];
            $type = $project_types[(string) ($project['project_type'] ?? 'film')] ?? '';
            $stats = casting_director_project_stats($director_id, $pid);
            $project_new = casting_director_new_project_response_count($director_id, $pid);
            ?>
          <a class="director-project-line" href="director-desk.php?project=<?= $pid ?>">
            <span class="director-project-line-start">
              <span class="director-project-line-num"><?= (int) ($index + 1) ?></span>
              <span class="director-project-line-title"><?= casting_e((string) $project['title']) ?></span>
              <?php if ($project_new > 0) : ?>
                <span class="nav-badge" aria-label="<?= (int) $project_new ?> پذیرش جدید"><?= (int) $project_new ?></span>
              <?php endif; ?>
            </span>
            <span class="director-project-line-meta">
              <?= casting_e($type) ?>
              <?php if ((int) ($project['actors_needed'] ?? 0) > 0) : ?>
                · <?= (int) $project['actors_needed'] ?> بازیگر
              <?php endif; ?>
              <?php if ($stats['roles'] > 0) : ?>
                · <?= (int) $stats['roles'] ?> نقش
              <?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else : ?>
      <p class="empty-state">هنوز پروژه‌ای ندارید — فرم بالا را پر کنید.</p>
    <?php endif; ?>

  <?php elseif ($opp_id > 0 && $active_opp) :
      $folder_apps = array_values(array_filter(
          $opp_applicants,
          static fn(array $app): bool => (string) ($app['status'] ?? '') === $app_folder
      ));
      ?>
    <a class="back-link" href="director-desk.php?project=<?= $project_id ?>">← <?= casting_e((string) ($active_project['title'] ?? 'پروژه')) ?></a>
    <h1>مدیریت متقاضیان</h1>
    <p class="meta">
      <?= casting_e((string) ($active_opp['title'] ?? '')) ?>
      <?php if (!empty($active_opp['role_title'])) : ?> · <?= casting_e((string) $active_opp['role_title']) ?><?php endif; ?>
      · <?= (string) ($active_opp['status'] ?? '') === 'open' ? 'باز' : 'بسته' ?>
      · <?= (int) ($opp_folder_counts['all'] ?? 0) ?> اپلای
    </p>
    <?php if (trim((string) ($active_opp['message'] ?? '')) !== '') : ?>
      <p class="lede"><?= nl2br(casting_e((string) $active_opp['message'])) ?></p>
    <?php endif; ?>

    <nav class="app-manager-folders" aria-label="پوشه‌های متقاضیان">
      <?php foreach ($opp_folder_labels as $folder_key => $folder_label) :
          $count = (int) ($opp_folder_counts[$folder_key] ?? 0);
          $href = 'director-desk.php?project=' . $project_id . '&opp=' . $opp_id . '&folder=' . rawurlencode($folder_key);
          ?>
        <a class="app-manager-folder<?= $app_folder === $folder_key ? ' is-active' : '' ?>" href="<?= casting_e($href) ?>">
          <span><?= casting_e($folder_label) ?></span>
          <strong><?= $count ?></strong>
        </a>
      <?php endforeach; ?>
    </nav>

    <?php if ($folder_apps === []) : ?>
      <p class="empty-state">در این پوشه متقاضی نیست.</p>
    <?php else : ?>
      <form id="app-manager-bulk-form" method="post" action="director-desk.php?project=<?= $project_id ?>&amp;opp=<?= $opp_id ?>&amp;folder=<?= casting_e($app_folder) ?>" class="app-manager-bulk-form">
        <?php wp_nonce_field('casting_director_desk_page'); ?>
        <input type="hidden" name="desk_action" value="bulk_application_status">
        <input type="hidden" name="opportunity_id" value="<?= $opp_id ?>">
        <div class="app-manager-bulk">
          <label class="app-manager-select-all">
            <input type="checkbox" data-app-select-all>
            انتخاب همه در این پوشه
          </label>
          <div class="app-manager-bulk-actions">
            <button class="btn btn-ghost btn-sm" type="submit" name="app_status" value="pending">به بررسی</button>
            <button class="btn btn-ghost btn-sm" type="submit" name="app_status" value="shortlisted">فهرست کوتاه</button>
            <button class="btn btn-primary btn-sm" type="submit" name="app_status" value="accepted">پذیرش</button>
            <button class="btn btn-ghost btn-sm" type="submit" name="app_status" value="rejected">رد</button>
          </div>
        </div>
      </form>

      <div class="app-manager-grid">
        <?php foreach ($folder_apps as $app) :
            $tid = (int) ($app['talent_id'] ?? 0);
            $photo = (string) ($app['photo_url'] ?? '');
            $name = (string) ($app['display_name'] ?? '');
            $age = (int) ($app['age'] ?? 0);
            $city = (string) ($app['city'] ?? '');
            $note = trim((string) ($app['note'] ?? ''));
            $meta_bits = [];
            if ($age > 0) {
                $meta_bits[] = $age . ' سال';
            }
            if ($city !== '') {
                $meta_bits[] = $city;
            }
            ?>
          <article class="app-manager-card">
            <label class="app-manager-check">
              <input form="app-manager-bulk-form" type="checkbox" name="application_ids[]" value="<?= (int) $app['id'] ?>" data-app-select>
              <span class="sr-only">انتخاب <?= casting_e($name) ?></span>
            </label>
            <button type="button" class="app-manager-photo" data-member-preview="<?= $tid ?>" aria-label="پروفایل <?= casting_e($name) ?>">
              <?php if ($photo !== '') : ?>
                <img src="<?= casting_e($photo) ?>" alt="" loading="lazy">
              <?php else : ?>
                <span class="photo-placeholder">بدون عکس</span>
              <?php endif; ?>
            </button>
            <div class="app-manager-card-body">
              <h3>
                <button type="button" class="link-button" data-member-preview="<?= $tid ?>"><?= casting_e($name) ?></button>
              </h3>
              <p class="meta"><?= casting_e($meta_bits !== [] ? implode(' · ', $meta_bits) : '—') ?></p>
              <?php
              $note_limit = 48;
              $note_len = $note === '' ? 0 : (function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') : strlen($note));
              $note_long = $note_len > $note_limit;
              $note_preview = $note === ''
                  ? ''
                  : ($note_long
                      ? ((function_exists('mb_substr') ? mb_substr($note, 0, $note_limit, 'UTF-8') : substr($note, 0, $note_limit)) . '…')
                      : $note);
              ?>
              <div class="app-manager-note-slot">
                <?php if ($note !== '') : ?>
                  <p class="app-manager-note"><?= casting_e($note_preview) ?></p>
                  <?php if ($note_long) : ?>
                    <button
                      type="button"
                      class="link-button app-manager-note-more"
                      data-app-note-open
                      data-app-note-title="<?= casting_e($name) ?>"
                      data-app-note-body="<?= casting_e($note) ?>"
                    >ادامه</button>
                  <?php endif; ?>
                <?php else : ?>
                  <p class="app-manager-note app-manager-note--empty">بدون یادداشت</p>
                <?php endif; ?>
              </div>
              <form method="post" action="director-desk.php?project=<?= $project_id ?>&amp;opp=<?= $opp_id ?>&amp;folder=<?= casting_e($app_folder) ?>" class="app-manager-card-actions">
                <?php wp_nonce_field('casting_director_desk_page'); ?>
                <input type="hidden" name="desk_action" value="set_application_status">
                <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
                <input type="hidden" name="opportunity_id" value="<?= $opp_id ?>">
                <button class="btn btn-ghost btn-sm" type="submit" name="app_status" value="shortlisted">کوتاه</button>
                <button class="btn btn-primary btn-sm" type="submit" name="app_status" value="accepted">پذیرش</button>
                <button class="btn btn-ghost btn-sm" type="submit" name="app_status" value="rejected">رد</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="app-note-lightbox" data-app-note-lightbox aria-hidden="true">
      <div class="app-note-lightbox-panel" role="dialog" aria-modal="true" aria-labelledby="app-note-lightbox-title">
        <div class="app-note-lightbox-head">
          <h2 class="app-note-lightbox-title" id="app-note-lightbox-title" data-app-note-lightbox-title>یادداشت متقاضی</h2>
          <button type="button" class="btn btn-ghost btn-sm" data-app-note-lightbox-close>بستن</button>
        </div>
        <div class="app-note-lightbox-body" data-app-note-lightbox-body></div>
      </div>
    </div>

  <?php elseif ($role_id <= 0 || !$active_role) : ?>
    <a class="back-link" href="director-desk.php">← همه پروژه‌ها</a>
    <h1><?= casting_e((string) ($active_project['title'] ?? '')) ?></h1>
    <p class="meta"><?= casting_e($project_types[(string) ($active_project['project_type'] ?? 'film')] ?? '') ?></p>

    <form class="form director-project-spec" method="post">
      <?php wp_nonce_field('casting_director_desk_page'); ?>
      <input type="hidden" name="desk_action" value="save_project">
      <h2 class="panel-section-title">مشخصات تولید</h2>
      <p class="field-hint">اطلاعات مورد نیاز برای ساخت این اثر — بعداً قابل ویرایش است.</p>

      <div class="form-grid">
        <div class="field">
          <label for="proj_title">نام پروژه</label>
          <input id="proj_title" name="title" type="text" required maxlength="191" value="<?= casting_e((string) ($active_project['title'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="proj_type">نوع</label>
          <select id="proj_type" name="project_type">
            <?php foreach ($project_types as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= ($active_project['project_type'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="proj_status">وضعیت پروژه</label>
          <select id="proj_status" name="production_status">
            <?php foreach ($production_statuses as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>" <?= ($active_project['production_status'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="proj_actors">تعداد بازیگر مورد نیاز</label>
          <input id="proj_actors" name="actors_needed" type="number" min="0" max="999" value="<?= (int) ($active_project['actors_needed'] ?? 0) ?>">
        </div>
        <div class="field">
          <label for="proj_supporting">نقش‌های فرعی / سیاهپوست</label>
          <input id="proj_supporting" name="supporting_needed" type="number" min="0" max="999" value="<?= (int) ($active_project['supporting_needed'] ?? 0) ?>">
        </div>
        <div class="field">
          <label for="proj_genre">ژانر</label>
          <input id="proj_genre" name="genre" type="text" maxlength="64" value="<?= casting_e((string) ($active_project['genre'] ?? '')) ?>" placeholder="درام، کمدی، …">
        </div>
        <div class="field">
          <label for="proj_location">محل فیلمبرداری / اجرا</label>
          <input id="proj_location" name="location" type="text" maxlength="191" value="<?= casting_e((string) ($active_project['location'] ?? '')) ?>" placeholder="شهر یا استودیو">
        </div>
        <div class="field">
          <label for="proj_period">بازه زمان</label>
          <input id="proj_period" name="shoot_period" type="text" maxlength="191" value="<?= casting_e((string) ($active_project['shoot_period'] ?? '')) ?>" placeholder="مثلاً تابستان ۱۴۰۵">
        </div>
        <div class="field">
          <label for="proj_duration">مدت (فیلم / اجرا)</label>
          <input id="proj_duration" name="duration_label" type="text" maxlength="64" value="<?= casting_e((string) ($active_project['duration_label'] ?? '')) ?>" placeholder="۹۰ دقیقه / ۲ ساعت">
        </div>
      </div>
      <div class="field">
        <label for="proj_synopsis">خلاصه داستان</label>
        <textarea id="proj_synopsis" name="synopsis" rows="3" maxlength="5000"><?= casting_e((string) ($active_project['synopsis'] ?? '')) ?></textarea>
      </div>
      <div class="field">
        <label for="proj_notes">یادداشت داخلی (اختیاری)</label>
        <textarea id="proj_notes" name="notes" rows="2" maxlength="3000"><?= casting_e((string) ($active_project['notes'] ?? '')) ?></textarea>
      </div>
      <div class="cta-row">
        <button class="btn btn-primary" type="submit">ذخیره مشخصات</button>
        <button class="btn btn-ghost" type="submit" formaction="director-desk.php?project=<?= $project_id ?>" name="desk_action" value="delete_project" onclick="return confirm('کل پروژه حذف شود؟');">حذف پروژه</button>
      </div>
    </form>

    <div class="director-project-roles">
      <div class="director-project-roles-head">
        <h2 class="panel-section-title">نقش‌ها و کستینگ</h2>
        <a class="btn btn-ghost btn-sm" href="search-users.php">جستجوی بازیگر</a>
      </div>
      <p class="field-hint">
        <?= (int) $project_stats['roles'] ?> نقش تعریف‌شده
        <?php if ((int) ($active_project['actors_needed'] ?? 0) > 0) : ?>
          · هدف: <?= (int) $active_project['actors_needed'] ?> بازیگر
        <?php endif; ?>
        · <?= (int) $project_stats['talents'] ?> نامزد ثبت‌شده
      </p>

      <?php if ($roles) : ?>
        <div class="director-project-lines director-project-lines--roles">
          <?php foreach ($roles as $index => $role) :
              $rid = (int) $role['id'];
              ?>
            <a class="director-project-line" href="director-desk.php?project=<?= $project_id ?>&role=<?= $rid ?>">
              <span class="director-project-line-start">
                <span class="director-project-line-num"><?= (int) ($index + 1) ?></span>
                <span class="director-project-line-title"><?= casting_e((string) $role['title']) ?></span>
              </span>
              <?php if (($role['description'] ?? '') !== '') : ?>
                <span class="director-project-line-meta"><?= casting_e((string) $role['description']) ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="form director-desk-mini-form" method="post">
        <?php wp_nonce_field('casting_director_desk_page'); ?>
        <input type="hidden" name="desk_action" value="create_role">
        <div class="form-grid">
          <div class="field">
            <label for="role_title">نقش جدید</label>
            <input id="role_title" name="role_title" type="text" required maxlength="191" placeholder="مثلاً نقش اول">
          </div>
          <div class="field">
            <label for="role_description">توضیح نقش</label>
            <input id="role_description" name="role_description" type="text" maxlength="500" placeholder="سن، ویژگی، …">
          </div>
        </div>
        <button class="btn btn-primary btn-sm" type="submit">افزودن نقش</button>
      </form>
    </div>

    <?php casting_render_director_casting_call_form($project_id, [], '', $director_id); ?>

  <?php else : ?>
    <a class="back-link" href="director-desk.php?project=<?= $project_id ?>">← <?= casting_e((string) ($active_project['title'] ?? 'پروژه')) ?></a>
    <h1><?= casting_e((string) $active_role['title']) ?></h1>
    <?php if (($active_role['description'] ?? '') !== '') : ?>
      <p class="meta"><?= casting_e((string) $active_role['description']) ?></p>
    <?php endif; ?>
    <p class="field-hint">بازیگران بر اساس امتیاز شما مرتب می‌شوند — بالاترین در بالا.</p>

    <div class="cta-row">
      <a class="btn btn-ghost btn-sm" href="search-users.php">افزودن از جستجو</a>
      <form method="post" onsubmit="return confirm('این نقش حذف شود؟');">
        <?php wp_nonce_field('casting_director_desk_page'); ?>
        <input type="hidden" name="desk_action" value="delete_role">
        <button class="btn btn-ghost btn-sm" type="submit">حذف نقش</button>
      </form>
    </div>

    <?php if (!$role_talents) : ?>
      <p class="empty-state">هنوز بازیگری برای این نقش ثبت نشده. از جستجو پروفایل را باز کنید و به این نقش اضافه کنید.</p>
    <?php else : ?>
      <div class="director-desk-rank-list">
        <?php foreach ($role_talents as $index => $row) :
            $talent_id = (int) $row['talent_id'];
            $rank = $index + 1;
            ?>
          <article class="director-desk-rank-card">
            <div class="director-desk-rank-head">
              <span class="director-desk-rank-num">#<?= $rank ?></span>
              <a class="director-desk-rank-photo" href="member.php?id=<?= $talent_id ?>&role=<?= $role_id ?>#director-desk">
                <?php if (($row['photo_url'] ?? '') !== '') : ?>
                  <img src="<?= casting_e((string) $row['photo_url']) ?>" alt="">
                <?php else : ?>
                  <span class="photo-placeholder">?</span>
                <?php endif; ?>
              </a>
              <div class="director-desk-rank-meta">
                <h3><a href="member.php?id=<?= $talent_id ?>&role=<?= $role_id ?>#director-desk"><?= casting_e((string) $row['talent_name']) ?></a></h3>
                <p class="meta">
                  امتیاز: <strong class="director-score-value"><?= casting_e(casting_director_format_score((float) $row['score_avg'])) ?></strong>/10
                  · <?= casting_e($status_labels[$row['status']] ?? $row['status']) ?>
                </p>
              </div>
            </div>
            <form class="form" method="post">
              <?php wp_nonce_field('casting_director_desk_page'); ?>
              <input type="hidden" name="desk_action" value="save_role_talent">
              <input type="hidden" name="talent_id" value="<?= $talent_id ?>">
              <?php casting_render_director_rating_fields('rank_' . $talent_id, $row['ratings']); ?>
              <div class="form-grid">
                <div class="field">
                  <label for="status_<?= $talent_id ?>">وضعیت</label>
                  <select id="status_<?= $talent_id ?>" name="status">
                    <?php foreach ($status_labels as $key => $label) : ?>
                      <option value="<?= casting_e($key) ?>" <?= ($row['status'] ?? '') === $key ? 'selected' : '' ?>><?= casting_e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="notes_<?= $talent_id ?>">یادداشت</label>
                  <input id="notes_<?= $talent_id ?>" name="role_notes" type="text" maxlength="3000" value="<?= casting_e((string) ($row['notes'] ?? '')) ?>">
                </div>
              </div>
              <div class="cta-row">
                <button class="btn btn-primary btn-sm" type="submit">ذخیره امتیاز</button>
                <button class="btn btn-ghost btn-sm" type="submit" formaction="director-desk.php?project=<?= $project_id ?>&role=<?= $role_id ?>" name="desk_action" value="remove_role_talent" onclick="return confirm('حذف از این نقش؟');">حذف</button>
              </div>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php casting_render_panel_end(); ?>
