<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/panel.php';
require_once __DIR__ . '/includes/director-desk.php';

$user = casting_require_casting_user();
$director_id = (int) $user->ID;

if (!casting_user_is_director($director_id)) {
    casting_set_flash('error', 'میز کار فقط برای کارگردان‌هاست.');
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
                (string) ($_POST['project_type'] ?? 'film'),
                (string) ($_POST['project_notes'] ?? '')
            );
            if (!$result['ok']) {
                $error = $result['error'] ?? 'خطا';
            } else {
                casting_set_flash('success', 'پروژه ساخته شد.');
                casting_redirect('director-desk.php?project=' . (int) ($result['project_id'] ?? 0));
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

casting_render_panel_start('میز کار casting', 'desk');
if ($error !== '') {
    echo '<div class="flash flash-error" role="alert">' . casting_e($error) . '</div>';
}
casting_render_flash();
?>
<section class="dash-card director-desk-page">
  <div class="director-desk-head">
    <div>
      <h1>میز کار حرفه‌ای</h1>
      <p class="lede">پروژه‌ها و نقش‌ها را جدا مدیریت کنید. به هر بازیگر برای دیالوگ، ایفای نقش و سایر معیارها امتیاز بدهید — در هر نقش، امتیاز بالاتر = رتبه بالاتر.</p>
    </div>
    <a class="btn btn-ghost" href="search-users.php">جستجوی بازیگر</a>
  </div>

  <div class="director-desk-layout">
    <aside class="director-desk-column">
      <h2 class="director-desk-column-title">پروژه‌ها / فیلم‌ها</h2>
      <?php if ($projects) : ?>
        <ul class="director-desk-list">
          <?php foreach ($projects as $project) :
              $pid = (int) $project['id'];
              $type = $project_types[(string) ($project['project_type'] ?? 'film')] ?? '';
              ?>
            <li>
              <a class="director-desk-list-link<?= $pid === $project_id ? ' is-active' : '' ?>" href="director-desk.php?project=<?= $pid ?>">
                <span class="director-desk-list-title"><?= casting_e((string) $project['title']) ?></span>
                <span class="meta"><?= casting_e($type) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else : ?>
        <p class="empty-state">هنوز پروژه‌ای ندارید.</p>
      <?php endif; ?>

      <form class="form director-desk-mini-form" method="post">
        <?php wp_nonce_field('casting_director_desk_page'); ?>
        <input type="hidden" name="desk_action" value="create_project">
        <div class="field">
          <label for="project_title">پروژه جدید</label>
          <input id="project_title" name="project_title" type="text" required maxlength="191" placeholder="نام فیلم یا پروژه">
        </div>
        <div class="field">
          <label for="project_type">نوع</label>
          <select id="project_type" name="project_type">
            <?php foreach ($project_types as $key => $label) : ?>
              <option value="<?= casting_e($key) ?>"><?= casting_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary btn-sm" type="submit">افزودن پروژه</button>
      </form>
    </aside>

    <aside class="director-desk-column">
      <h2 class="director-desk-column-title">نقش‌ها</h2>
      <?php if ($project_id <= 0) : ?>
        <p class="field-hint">یک پروژه را انتخاب کنید.</p>
      <?php else : ?>
        <?php if ($roles) : ?>
          <ul class="director-desk-list">
            <?php foreach ($roles as $role) :
                $rid = (int) $role['id'];
                ?>
              <li>
                <a class="director-desk-list-link<?= $rid === $role_id ? ' is-active' : '' ?>" href="director-desk.php?project=<?= $project_id ?>&role=<?= $rid ?>">
                  <span class="director-desk-list-title"><?= casting_e((string) $role['title']) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else : ?>
          <p class="empty-state">برای این پروژه نقشی تعریف نشده.</p>
        <?php endif; ?>

        <form class="form director-desk-mini-form" method="post">
          <?php wp_nonce_field('casting_director_desk_page'); ?>
          <input type="hidden" name="desk_action" value="create_role">
          <div class="field">
            <label for="role_title">نقش جدید</label>
            <input id="role_title" name="role_title" type="text" required maxlength="191" placeholder="مثلاً نقش اول / مادر">
          </div>
          <div class="field">
            <label for="role_description">توضیح (اختیاری)</label>
            <input id="role_description" name="role_description" type="text" maxlength="500" placeholder="سن، ویژگی، …">
          </div>
          <button class="btn btn-primary btn-sm" type="submit">افزودن نقش</button>
        </form>

        <?php if ($active_project) : ?>
          <form class="form" method="post" onsubmit="return confirm('کل پروژه و نقش‌هایش حذف شود؟');">
            <?php wp_nonce_field('casting_director_desk_page'); ?>
            <input type="hidden" name="desk_action" value="delete_project">
            <button class="btn btn-ghost btn-sm" type="submit">حذف پروژه</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </aside>

    <div class="director-desk-main">
      <?php if ($role_id <= 0 || !$active_role) : ?>
        <p class="empty-state">یک نقش را انتخاب کنید تا لیست بازیگران و امتیازها را ببینید.</p>
      <?php else : ?>
        <div class="director-desk-main-head">
          <div>
            <h2><?= casting_e((string) $active_role['title']) ?></h2>
            <?php if (($active_role['description'] ?? '') !== '') : ?>
              <p class="meta"><?= casting_e((string) $active_role['description']) ?></p>
            <?php endif; ?>
            <p class="field-hint">مرتب‌سازی: بالاترین امتیاز در ابتدای لیست</p>
          </div>
          <div class="cta-row">
            <a class="btn btn-ghost btn-sm" href="search-users.php">افزودن از جستجو</a>
            <form method="post" onsubmit="return confirm('این نقش حذف شود؟');">
              <?php wp_nonce_field('casting_director_desk_page'); ?>
              <input type="hidden" name="desk_action" value="delete_role">
              <button class="btn btn-ghost btn-sm" type="submit">حذف نقش</button>
            </form>
          </div>
        </div>

        <?php if (!$role_talents) : ?>
          <p class="empty-state">هنوز بازیگری به این نقش اضافه نشده. از جستجو پروفایل را باز کنید و «افزودن به نقش» بزنید.</p>
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
                      <?php if (($row['city'] ?? '') !== '') : ?> · <?= casting_e((string) $row['city']) ?><?php endif; ?>
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
                      <label for="notes_<?= $talent_id ?>">یادداشت نقش</label>
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
    </div>
  </div>
</section>
<?php casting_render_panel_end(); ?>
