<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'teacher']);
ensure_exam_points_columns();
$page_title = 'Quản lý đề thi';

$current = current_user();
$isAdmin = $current['role'] === 'admin';

function can_manage_exam(array $exam, array $current, bool $isAdmin): bool
{
    return $isAdmin || (int)($exam['created_by'] ?? 0) === (int)$current['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exam'])) {
    $id = (int)post('id');
    $st = db()->prepare('SELECT * FROM exams WHERE id=?');
    $st->execute([$id]);
    $exam = $st->fetch();
    if (!$exam || !can_manage_exam($exam, $current, $isAdmin)) {
        flash('Bạn không có quyền sửa đề thi này.', 'error');
        redirect('exams.php');
    }

    $title = post('title');
    $code = post('code');
    $school = post('school_name', 'Trường THPT');
    $subjectId = (int)post('subject_id');
    $duration = max(1, (int)post('duration', 45));
    $totalPoints = max(0.25, (float)post('total_points', 10));
    $status = in_array(post('status', 'draft'), ['draft', 'published', 'closed'], true) ? post('status') : 'draft';

    if ($title === '' || $code === '' || $subjectId <= 0) {
        flash('Vui lòng nhập đủ tên đề, mã đề và môn học.', 'error');
        redirect('exams.php?edit=' . $id);
    }

    db()->prepare('UPDATE exams SET code=?, title=?, school_name=?, subject_id=?, duration=?, total_points=?, status=? WHERE id=?')
        ->execute([$code, $title, $school, $subjectId, $duration, $totalPoints, $status, $id]);
    $st = db()->prepare('SELECT id FROM exam_questions WHERE exam_id=? ORDER BY position');
    $st->execute([$id]);
    $examQuestionIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    $points = distribute_exam_points($totalPoints, count($examQuestionIds));
    $updatePoint = db()->prepare('UPDATE exam_questions SET points=? WHERE id=?');
    foreach ($examQuestionIds as $index => $examQuestionId) {
        $updatePoint->execute([$points[$index] ?? 0, $examQuestionId]);
    }
    log_activity('update', 'exam', $id, 'Đã cập nhật đề thi: ' . $title);
    flash('Đã cập nhật thông tin đề thi');
    redirect('exams.php');
}

if (isset($_GET['publish'])) {
    $id = (int)$_GET['publish'];
    db()->prepare('UPDATE exams SET status="published" WHERE id=?')->execute([$id]);
    log_activity('publish', 'exam', $id, 'Đã công bố đề thi #' . $id);
    flash('Đã công bố đề');
    redirect('exams.php');
}
if (isset($_GET['close'])) {
    $id = (int)$_GET['close'];
    db()->prepare('UPDATE exams SET status="closed" WHERE id=?')->execute([$id]);
    log_activity('close', 'exam', $id, 'Đã đóng đề thi #' . $id);
    flash('Đã đóng đề');
    redirect('exams.php');
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $st = db()->prepare('SELECT * FROM exams WHERE id=?');
    $st->execute([$id]);
    $exam = $st->fetch();
    if (!$exam || !can_manage_exam($exam, $current, $isAdmin)) {
        flash('Bạn không có quyền xóa đề thi này.', 'error');
        redirect('exams.php');
    }
    db()->prepare('DELETE FROM exams WHERE id=?')->execute([$id]);
    log_activity('delete', 'exam', $id, 'Đã xóa đề thi: ' . ($exam['title'] ?? ('#' . $id)));
    flash('Đã xóa đề');
    redirect('exams.php');
}

$subjects = fetch_subjects();
$edit = null;
if (isset($_GET['edit'])) {
    $st = db()->prepare('SELECT * FROM exams WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
    if ($edit && !can_manage_exam($edit, $current, $isAdmin)) {
        flash('Bạn không có quyền sửa đề thi này.', 'error');
        redirect('exams.php');
    }
}

$where = [];
$params = [];
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $where[] = '(e.title LIKE ? OR e.code LIKE ? OR s.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if (($_GET['subject_id'] ?? '') !== '') {
    $where[] = 'e.subject_id=?';
    $params[] = (int)$_GET['subject_id'];
}
if (in_array($_GET['status'] ?? '', ['draft', 'published', 'closed'], true)) {
    $where[] = 'e.status=?';
    $params[] = $_GET['status'];
}

$sql = 'SELECT e.*, s.name subject_name, u.name creator, COUNT(eq.id) total_questions
        FROM exams e
        JOIN subjects s ON s.id=e.subject_id
        LEFT JOIN users u ON u.id=e.created_by
        LEFT JOIN exam_questions eq ON eq.exam_id=e.id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' GROUP BY e.id
          ORDER BY e.created_at DESC';
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
  <div>
    <h1>Quản lý đề thi</h1>
    <p>Thêm, sửa, xóa, đổi tên đề và lọc theo môn để phân biệt các đề đã soạn.</p>
  </div>
  <div class="heading-actions">
    <a class="btn primary" href="create-exam.php"><span class="material-symbols-outlined">add_circle</span> Thêm đề mới</a>
  </div>
</div>

<div class="core-workflow">
  <a class="workflow-step" href="questions.php"><span class="material-symbols-outlined">database</span><strong>Ngân hàng câu hỏi</strong><small>Lưu bộ câu hỏi</small></a>
  <a class="workflow-step" href="create-exam.php"><span class="material-symbols-outlined">edit_note</span><strong>Tạo đề thi</strong><small>Tạo đề từ ngân hàng</small></a>
  <a class="workflow-step active" href="exams.php"><span class="material-symbols-outlined">assignment</span><strong>Quản lý đề thi</strong><small>Sửa, xuất và giao đề</small></a>
</div>

<?php if ($edit): ?>
  <div class="card">
    <h2>Sửa thông tin đề thi</h2>
    <form method="post">
      <input type="hidden" name="id" value="<?= e($edit['id']) ?>">
      <div class="form-row">
        <div>
          <label>Tên đề thi</label>
          <input name="title" required value="<?= e($edit['title']) ?>">
        </div>
        <div>
          <label>Mã đề</label>
          <input name="code" required value="<?= e($edit['code']) ?>">
        </div>
      </div>
      <div class="form-row">
        <div>
          <label>Tên trường</label>
          <input name="school_name" value="<?= e($edit['school_name']) ?>">
        </div>
        <div>
          <label>Môn học</label>
          <select name="subject_id" required>
            <?php foreach ($subjects as $s): ?>
              <option value="<?= e($s['id']) ?>" <?= (int)$edit['subject_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div>
          <label>Thời gian làm bài</label>
          <input type="number" min="1" name="duration" value="<?= e($edit['duration']) ?>">
        </div>
        <div>
          <label>T&#7893;ng &#273;i&#7875;m</label>
          <input type="number" min="0.25" step="0.25" name="total_points" value="<?= e($edit['total_points'] ?? 10) ?>">
        </div>
        <div>
          <label>Trạng thái</label>
          <select name="status">
            <?php foreach (['draft' => 'Bản nháp', 'published' => 'Đã công bố', 'closed' => 'Đã đóng'] as $k => $v): ?>
              <option value="<?= $k ?>" <?= $edit['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="actions">
        <button class="btn primary" name="save_exam" value="1"><span class="material-symbols-outlined">save</span> Lưu thay đổi</button>
        <a class="btn ghost" href="exams.php">Hủy</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <div class="section-head">
    <div>
      <h2>Danh sách đề thi</h2>
      <p class="muted">Tìm đề theo tên, mã đề hoặc lọc theo môn học trước khi giao đề.</p>
    </div>
  </div>
  <form class="actions" style="margin-bottom:16px">
    <input name="q" placeholder="Tìm tên đề, mã đề hoặc môn học" value="<?= e($q) ?>">
    <select name="subject_id">
      <option value="">Tất cả môn</option>
      <?php foreach ($subjects as $s): ?>
        <option value="<?= e($s['id']) ?>" <?= ($_GET['subject_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">Tất cả trạng thái</option>
      <?php foreach (['draft' => 'Bản nháp', 'published' => 'Đã công bố', 'closed' => 'Đã đóng'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($_GET['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn">Lọc</button>
  </form>

  <div class="table-scroll">
    <table class="table">
      <tr>
        <th>Mã đề</th>
        <th>Tên đề thi</th>
        <th>Môn</th>
        <th>Thời gian</th>
        <th>Số câu</th>
        <th>Người tạo</th>
        <th>Trạng thái</th>
        <th>Thao tác</th>
      </tr>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= e($r['code']) ?></strong></td>
          <td><?= e($r['title']) ?></td>
          <td><?= e($r['subject_name']) ?></td>
          <td><?= e($r['duration']) ?> phút</td>
          <td><?= e($r['total_questions']) ?></td>
          <td><?= e($r['creator'] ?? '') ?></td>
          <td><span class="status-pill <?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></td>
          <td class="actions">
            <a class="btn ghost" href="exam-view.php?id=<?= e($r['id']) ?>">Xem</a>
            <a class="btn ghost" href="exam-matrix.php?id=<?= e($r['id']) ?>">Ma trận</a>
            <?php if (can_manage_exam($r, $current, $isAdmin)): ?>
              <a class="btn ghost" href="?edit=<?= e($r['id']) ?>">Sửa</a>
            <?php endif; ?>
            <a class="btn ghost" href="export-exam.php?id=<?= e($r['id']) ?>">Word</a>
            <a class="btn ghost" href="export-answers.php?id=<?= e($r['id']) ?>">Đáp án</a>
            <a class="btn secondary" href="assignments.php?exam_id=<?= e($r['id']) ?>">Giao đề</a>
            <a class="btn secondary" href="?publish=<?= e($r['id']) ?>">Công bố</a>
            <a class="btn" href="?close=<?= e($r['id']) ?>">Đóng</a>
            <?php if (can_manage_exam($r, $current, $isAdmin)): ?>
              <a class="btn danger" data-confirm="Xóa đề này? Các câu trong đề và bài giao liên quan cũng sẽ bị xóa." href="?delete=<?= e($r['id']) ?>">Xóa</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="muted">Không có đề thi phù hợp.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
