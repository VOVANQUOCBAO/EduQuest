<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'teacher']);
ensure_school_structure_tables();

$current = current_user();
$isAdmin = ($current['role'] ?? '') === 'admin';
$page_title = 'Quan ly tai khoan';

if (!$isAdmin && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete']))) {
    http_response_code(403);
    exit('Ban khong co quyen thay doi tai khoan.');
}

if ($isAdmin && isset($_GET['delete'])) {
    $userId = (int)$_GET['delete'];
    $st = db()->prepare('SELECT name,email FROM users WHERE id=?');
    $st->execute([$userId]);
    $deletedUser = $st->fetch();
    db()->prepare('DELETE FROM users WHERE id<>? AND id=?')->execute([current_user()['id'], $userId]);
    log_activity('delete', 'user', $userId, 'Đã xóa tài khoản: ' . (($deletedUser['name'] ?? '') ?: ('#' . $userId)));
    flash('Da xoa tai khoan');
    redirect('users.php');
}

if ($isAdmin && isset($_GET['delete_school'])) {
    $schoolId = (int)$_GET['delete_school'];
    db()->prepare('UPDATE users SET class_id=NULL WHERE class_id IN (
        SELECT c.id FROM school_classes c JOIN school_grades g ON g.id=c.grade_id WHERE g.school_id=?
    )')->execute([$schoolId]);
    db()->prepare('DELETE FROM schools WHERE id=?')->execute([$schoolId]);
    log_activity('delete', 'school', $schoolId, 'Đã xóa trường #' . $schoolId);
    flash('Da xoa truong');
    redirect('users.php');
}

if ($isAdmin && isset($_GET['delete_grade'])) {
    $gradeId = (int)$_GET['delete_grade'];
    db()->prepare('UPDATE users SET class_id=NULL WHERE class_id IN (SELECT id FROM school_classes WHERE grade_id=?)')->execute([$gradeId]);
    db()->prepare('DELETE FROM school_grades WHERE id=?')->execute([$gradeId]);
    log_activity('delete', 'grade', $gradeId, 'Đã xóa khối #' . $gradeId);
    flash('Da xoa khoi');
    redirect('users.php');
}

if ($isAdmin && isset($_GET['delete_class'])) {
    $classId = (int)$_GET['delete_class'];
    db()->prepare('UPDATE users SET class_id=NULL WHERE class_id=?')->execute([$classId]);
    db()->prepare('DELETE FROM school_classes WHERE id=?')->execute([$classId]);
    log_activity('delete', 'class', $classId, 'Đã xóa lớp #' . $classId);
    flash('Da xoa lop');
    redirect('users.php');
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'school') {
        $name = post('school_name');
        $address = post('address');
        if ($name !== '') {
            db()->prepare('INSERT INTO schools(name,address) VALUES(?,?)')->execute([$name, $address]);
            log_activity('create', 'school', (int)db()->lastInsertId(), 'Đã tạo trường: ' . $name);
        }
        flash('Da tao truong');
        redirect('users.php');
    }

    if ($action === 'grade') {
        $schoolId = (int)post('school_id');
        $name = post('grade_name');
        if ($schoolId > 0 && $name !== '') {
            db()->prepare('INSERT INTO school_grades(school_id,name) VALUES(?,?)')->execute([$schoolId, $name]);
            log_activity('create', 'grade', (int)db()->lastInsertId(), 'Đã tạo khối: ' . $name);
        }
        flash('Da tao khoi');
        redirect('users.php');
    }

    if ($action === 'class') {
        $gradeId = (int)post('grade_id');
        $name = post('class_name');
        if ($gradeId > 0 && $name !== '') {
            db()->prepare('INSERT INTO school_classes(grade_id,name) VALUES(?,?)')->execute([$gradeId, $name]);
            log_activity('create', 'class', (int)db()->lastInsertId(), 'Đã tạo lớp: ' . $name);
        }
        flash('Da tao lop');
        redirect('users.php');
    }

    if ($action === 'user') {
        $id = (int)($_POST['id'] ?? 0);
        $name = post('name');
        $email = post('email');
        $role = in_array(post('role', 'student'), ['admin', 'teacher', 'student'], true) ? post('role') : 'student';
        $status = in_array(post('status', 'active'), ['active', 'locked'], true) ? post('status') : 'active';
        $password = trim($_POST['password'] ?? '');
        $classId = $role === 'student' ? (int)post('class_id') : 0;
        $classId = $classId > 0 ? $classId : null;

        if ($id) {
            db()->prepare('UPDATE users SET name=?,email=?,role=?,status=?,class_id=? WHERE id=?')->execute([$name, $email, $role, $status, $classId, $id]);
            if ($password !== '') {
                db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            log_activity('update', 'user', $id, 'Đã cập nhật tài khoản: ' . $name);
            flash('Da cap nhat tai khoan');
        } else {
            db()->prepare('INSERT INTO users(name,email,password,role,status,class_id) VALUES(?,?,?,?,?,?)')
                ->execute([$name, $email, password_hash($password !== '' ? $password : '123456', PASSWORD_DEFAULT), $role, $status, $classId]);
            log_activity('create', 'user', (int)db()->lastInsertId(), 'Đã thêm tài khoản: ' . $name);
            flash('Da them tai khoan');
        }
        redirect('users.php');
    }
}

$edit = null;
if ($isAdmin && isset($_GET['edit'])) {
    $st = db()->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}

$passwordValue = '';
$passwordNote = '';
if ($edit) {
    $info = password_get_info((string)$edit['password']);
    if (($info['algo'] ?? 0) === 0) {
        $passwordValue = (string)$edit['password'];
        $passwordNote = 'Mat khau hien tai dang luu dang co the xem.';
    } else {
        $passwordNote = 'Mat khau da ma hoa. Nhap mat khau moi neu muon doi.';
    }
}

$tree = school_tree();
$schools = db()->query('SELECT * FROM schools ORDER BY name')->fetchAll();
$grades = db()->query('SELECT g.*,s.name school_name FROM school_grades g JOIN schools s ON s.id=g.school_id ORDER BY s.name,g.name')->fetchAll();
$classes = db()->query('SELECT c.*,g.name grade_name,s.name school_name FROM school_classes c JOIN school_grades g ON g.id=c.grade_id JOIN schools s ON s.id=g.school_id ORDER BY s.name,g.name,c.name')->fetchAll();
$users = db()->query('SELECT u.*,c.name class_name,g.name grade_name,s.name school_name
    FROM users u
    LEFT JOIN school_classes c ON c.id=u.class_id
    LEFT JOIN school_grades g ON g.id=c.grade_id
    LEFT JOIN schools s ON s.id=g.school_id
    ORDER BY u.created_at DESC')->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
  <div>
    <h1>Quản lý tài khoản</h1>
    <p>Quản lý Trường, Khối, Lớp và tài khoản học sinh theo từng lớp.</p>
  </div>
</div>

<?php if ($isAdmin): ?>
<div class="grid grid-3">
  <div class="card">
    <h2>Tạo trường</h2>
    <form method="post">
      <input type="hidden" name="action" value="school">
      <label>Tên trường</label><input name="school_name" required placeholder="Ví dụ: THPT EduQuest">
      <label>Địa chỉ</label><input name="address" placeholder="Không bắt buộc">
      <br><br><button class="btn primary">Tạo trường</button>
    </form>
  </div>
  <div class="card">
    <h2>Tạo khối</h2>
    <form method="post">
      <input type="hidden" name="action" value="grade">
      <label>Trường</label>
      <select name="school_id" required>
        <?php foreach ($schools as $s): ?><option value="<?= e($s['id']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
      <label>Tên khối</label><input name="grade_name" required placeholder="Ví dụ: Khối 10">
      <br><br><button class="btn primary">Tạo khối</button>
    </form>
  </div>
  <div class="card">
    <h2>Tạo lớp</h2>
    <form method="post">
      <input type="hidden" name="action" value="class">
      <label>Khối</label>
      <select name="grade_id" required>
        <?php foreach ($grades as $g): ?><option value="<?= e($g['id']) ?>"><?= e($g['school_name'] . ' - ' . $g['name']) ?></option><?php endforeach; ?>
      </select>
      <label>Tên lớp</label><input name="class_name" required placeholder="Ví dụ: 10A1">
      <br><br><button class="btn primary">Tạo lớp</button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:24px">
  <h2><?= $edit ? 'Sửa' : 'Thêm' ?> tài khoản</h2>
  <form method="post">
    <input type="hidden" name="action" value="user">
    <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
    <div class="form-row">
      <div><label>Họ tên</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
      <div><label>Email</label><input name="email" type="email" required value="<?= e($edit['email'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div>
        <label>Mật khẩu <?= $edit ? '(để trống nếu không đổi)' : '' ?></label>
        <div class="input-icon password-field">
          <span class="material-symbols-outlined">key</span>
          <input name="password" type="password" value="<?= e($passwordValue) ?>" <?= $edit ? '' : 'required' ?>>
          <button class="password-toggle" type="button" data-password-toggle title="Ẩn/hiện mật khẩu"><span class="material-symbols-outlined">visibility</span></button>
        </div>
        <?php if ($passwordNote): ?><p class="muted password-note"><?= e($passwordNote) ?></p><?php endif; ?>
      </div>
      <div>
        <label>Vai trò</label>
        <select name="role">
          <?php foreach (['admin' => 'Admin', 'teacher' => 'Giáo viên', 'student' => 'Học sinh'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($edit['role'] ?? 'student') === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div>
        <label>Lớp học sinh</label>
        <select name="class_id">
          <option value="">Chưa gán lớp / không phải học sinh</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= (int)($edit['class_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['school_name'] . ' - ' . $c['grade_name'] . ' - ' . $c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Trạng thái</label>
        <select name="status">
          <option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>active</option>
          <option value="locked" <?= ($edit['status'] ?? '') === 'locked' ? 'selected' : '' ?>>locked</option>
        </select>
      </div>
    </div>
    <button class="btn primary">Lưu</button>
    <?php if ($edit): ?><a class="btn ghost" href="users.php">Hủy</a><?php endif; ?>
  </form>
</div>
<?php endif; ?>

<div class="grid grid-2" style="margin-top:24px">
  <div class="card">
    <h2>Cây trường - khối - lớp</h2>
    <?php if (!$tree): ?><p class="muted">Chưa có trường nào.</p><?php endif; ?>
    <?php foreach ($tree as $school): ?>
      <div class="question-edit-card">
        <div class="section-head">
          <div><h3><?= e($school['name']) ?></h3><p class="muted"><?= e($school['address'] ?? '') ?></p></div>
          <?php if ($isAdmin): ?><a class="btn danger" data-confirm="Xóa trường này?" href="?delete_school=<?= e($school['id']) ?>">Xóa</a><?php endif; ?>
        </div>
        <?php foreach ($school['grades'] as $grade): ?>
          <details open>
            <summary><strong><?= e($grade['name']) ?></strong> <?php if ($isAdmin): ?><a class="btn ghost" href="?delete_grade=<?= e($grade['id']) ?>" data-confirm="Xóa khối này?">Xóa</a><?php endif; ?></summary>
            <?php foreach ($grade['classes'] as $class): ?>
              <div class="question" style="margin-left:16px">
                <div class="section-head">
                  <strong><?= e($class['name']) ?></strong>
                  <?php if ($isAdmin): ?><a class="btn ghost" href="?delete_class=<?= e($class['id']) ?>" data-confirm="Xóa lớp này? Học sinh sẽ được bỏ gán lớp.">Xóa</a><?php endif; ?>
                </div>
                <?php if (!$class['students']): ?><p class="muted">Chưa có học sinh trong lớp.</p><?php endif; ?>
                <?php foreach ($class['students'] as $student): ?>
                  <p class="choice"><?= e($student['name']) ?> · <?= e($student['email']) ?> · <?= e($student['status']) ?></p>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>Danh sách tài khoản</h2>
    <table class="table">
      <tr><th>Tên</th><th>Email</th><th>Role</th><th>Lớp</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['role']) ?></td>
          <td><?= e(trim(($u['school_name'] ? $u['school_name'] . ' - ' : '') . ($u['grade_name'] ? $u['grade_name'] . ' - ' : '') . ($u['class_name'] ?? '')) ?: '-') ?></td>
          <?php if ($isAdmin): ?>
            <td class="actions">
              <a class="btn ghost" href="?edit=<?= e($u['id']) ?>">Sửa</a>
              <a class="btn danger" data-confirm="Xóa tài khoản?" href="?delete=<?= e($u['id']) ?>">Xóa</a>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
