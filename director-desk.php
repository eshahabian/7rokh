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
    casting_redirect('panel.php');
}

$project_id = max(0, (int) ($_GET['project'] ?? 0));
$role_id = max(0, (int) ($_GET['role'] ?? 0));
$error = '';

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
                casting_set_flash('success', 'پروژه اضافه شد.');
                casting_redirect('director-desk.php?project=' . (int) ($result['project_id'] ?? 0));
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
        <?php foreach ($projects as $project) :
            $pid = (int) $project['id'];
            $type = $project_types[(string) ($project['project_type'] ?? 'film')] ?? '';
            $stats = casting_director_project_stats($director_id, $pid);
            ?>
          <a class="director-project-line" href="director-desk.php?project=<?= $pid ?>">
            <span class="director-project-line-title"><?= casting_e((string) $project['title']) ?></span>
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
          <?php foreach ($roles as $role) :
              $rid = (int) $role['id'];
              ?>
            <a class="director-project-line" href="director-desk.php?project=<?= $project_id ?>&role=<?= $rid ?>">
              <span class="director-project-line-title"><?= casting_e((string) $role['title']) ?></span>
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
