<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'teacher']);
ensure_assignments_table();
ensure_school_structure_tables();
$page_title = 'Giao đề thi';

$current = current_user();
$isAdmin = $current['role'] === 'admin';
$recipientRole = $isAdmin ? 'teacher' : 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $assignmentId = (int)$_POST['delete'];
        $deleteSql = $isAdmin
            ? 'DELETE FROM exam_assignments WHERE id=?'
            : 'DELETE FROM exam_assignments WHERE id=? AND created_by=?';
        $deleteParams = $isAdmin ? [$assignmentId] : [$assignmentId, $current['id']];
        db()->prepare($deleteSql)->execute($deleteParams);
        log_activity('delete', 'assignment', $assignmentId, 'Đã xóa bài kiểm tra đã giao #' . $assignmentId);
        flash('Đã xóa bài kiểm tra đã giao');
        redirect('assignments.php');
    }

    $examId = (int)post('exam_id');
    $recipients = $_POST['recipients'] ?? [];
    $classRecipients = $_POST['class_recipients'] ?? [];
    $manualTarget = trim(post('manual_target'));
    $created = 0;

    if ($examId <= 0) {
        flash('Vui lòng chọn một đề thi để giao.', 'error');
        redirect('assignments.php');
    }

    if (!$isAdmin) {
        $st = db()->prepare('SELECT e.id
            FROM exams e
            LEFT JOIN exam_assignments a ON a.exam_id=e.id AND a.target_user_id=?
            WHERE e.id=? AND (e.created_by=? OR a.id IS NOT NULL)
            LIMIT 1');
        $st->execute([$current['id'], $examId, $current['id']]);
        if (!$st->fetchColumn()) {
            flash('Bạn chỉ có thể giao lại đề được admin giao cho mình hoặc đề do bạn tạo.', 'error');
            redirect('assignments.php');
        }
    }

    $insert = db()->prepare('INSERT INTO exam_assignments(exam_id,target,target_role,target_user_id,target_class_id,start_at,due_at,show_score,show_answers,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    if ($isAdmin) {
        foreach ($recipients as $recipient) {
            if (!preg_match('/^' . $recipientRole . ':(\d+)$/', (string)$recipient, $m)) continue;
            $userId = (int)$m[1];
            $st = db()->prepare('SELECT name FROM users WHERE id=? AND role=? AND status="active"');
            $st->execute([$userId, $recipientRole]);
            $name = $st->fetchColumn();
            if (!$name) continue;
            $insert->execute([$examId, $name, $recipientRole, $userId, null, post('start_at') ?: null, post('due_at') ?: null, isset($_POST['show_score']) ? 1 : 0, isset($_POST['show_answers']) ? 1 : 0, post('status', 'open'), $current['id']]);
            $created++;
        }
    } else {
        foreach ($classRecipients as $classId) {
            $classId = (int)$classId;
            $st = db()->prepare('SELECT c.name,g.name grade_name,s.name school_name FROM school_classes c JOIN school_grades g ON g.id=c.grade_id JOIN schools s ON s.id=g.school_id WHERE c.id=?');
            $st->execute([$classId]);
            $class = $st->fetch();
            if (!$class) continue;
            $target = $class['school_name'] . ' - ' . $class['grade_name'] . ' - ' . $class['name'];
            $insert->execute([$examId, $target, 'class', null, $classId, post('start_at') ?: null, post('due_at') ?: null, isset($_POST['show_score']) ? 1 : 0, isset($_POST['show_answers']) ? 1 : 0, post('status', 'open'), $current['id']]);
            $created++;
        }
    }

    if ($created === 0) {
        $message = $isAdmin
            ? 'Vui lòng chọn ít nhất một giáo viên nhận đề.'
            : 'Vui lòng chọn ít nhất một học sinh hoặc nhập lớp/nhóm nhận đề.';
        flash($message, 'error');
        redirect('assignments.php');
    }

    db()->prepare('UPDATE exams SET status="published" WHERE id=?')->execute([$examId]);
    log_activity('create', 'assignment', $examId, ($isAdmin ? 'Admin đã giao đề thi cho giáo viên' : 'Giáo viên đã giao đề thi xuống học sinh') . ' (' . $created . ' lượt nhận)');
    flash($isAdmin ? 'Đã giao đề thi cho giáo viên' : 'Đã giao đề thi xuống học sinh');
    redirect('assignments.php?exam_id=' . $examId);
}

$subjects = fetch_subjects();
$q = trim($_GET['q'] ?? '');
$subjectId = (int)($_GET['subject_id'] ?? 0);
$status = trim($_GET['status'] ?? '');
$selectedExamId = (int)($_GET['exam_id'] ?? 0);

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(e.code LIKE ? OR e.title LIKE ? OR s.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($subjectId > 0) {
    $where[] = 'e.subject_id=?';
    $params[] = $subjectId;
}
if (in_array($status, ['draft', 'published', 'closed'], true)) {
    $where[] = 'e.status=?';
    $params[] = $status;
}
if (!$isAdmin) {
    $where[] = '(e.created_by=? OR EXISTS (SELECT 1 FROM exam_assignments ax WHERE ax.exam_id=e.id AND ax.target_user_id=?))';
    array_push($params, $current['id'], $current['id']);
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
$exams = $st->fetchAll();

if ($selectedExamId <= 0 && $exams) {
    $selectedExamId = (int)$exams[0]['id'];
}

$recipients = [];
if ($isAdmin) {
    $recipientStmt = db()->prepare("SELECT id,name,email FROM users WHERE role=? AND status='active' ORDER BY name");
    $recipientStmt->execute([$recipientRole]);
    $recipients = $recipientStmt->fetchAll();
}
$classes = $isAdmin ? [] : fetch_school_classes();

$rowsSql = 'SELECT a.*, e.code, e.title, e.duration, s.name subject_name, u.name creator,
        tu.name target_user_name, tu.email target_user_email, c.name target_class_name, g.name target_grade_name, sc.name target_school_name,
        (SELECT COUNT(*) FROM attempts at WHERE at.exam_id=a.exam_id AND at.status="submitted") submitted_count
        FROM exam_assignments a
        JOIN exams e ON e.id=a.exam_id
        JOIN subjects s ON s.id=e.subject_id
        LEFT JOIN users u ON u.id=a.created_by
        LEFT JOIN users tu ON tu.id=a.target_user_id
        LEFT JOIN school_classes c ON c.id=a.target_class_id
        LEFT JOIN school_grades g ON g.id=c.grade_id
        LEFT JOIN schools sc ON sc.id=g.school_id';
$rowsParams = [];
if (!$isAdmin) {
    $rowsSql .= ' WHERE a.created_by=? OR a.target_user_id=?';
    $rowsParams = [$current['id'], $current['id']];
}
$rowsSql .= ' ORDER BY a.created_at DESC';
$st = db()->prepare($rowsSql);
$st->execute($rowsParams);
$rows = $st->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
  <div>
    <h1>Giao đề thi</h1>
    <p><?= $isAdmin
        ? 'Admin chọn đề đã soạn rồi giao cho giáo viên phụ trách. Giáo viên sẽ tiếp tục giao xuống học sinh.'
        : 'Giáo viên chọn đề được admin giao hoặc đề mình tạo, sau đó giao xuống học sinh/lớp học.' ?></p>
  </div>
  <div class="heading-actions">
    <a class="btn ghost" href="create-exam.php"><span class="material-symbols-outlined">add_circle</span> Tạo đề mới</a>
    <a class="btn ghost" href="exams.php"><span class="material-symbols-outlined">assignment</span> Quản lý đề</a>
  </div>
</div>

<form class="assignment-layout" method="post">
  <section class="card assignment-picker">
    <div class="section-head">
      <div>
        <h2>Chọn đề đã soạn</h2>
        <p class="muted"><?= $isAdmin ? 'Danh sách đề trong hệ thống để giao cho giáo viên.' : 'Chỉ hiện đề bạn tạo hoặc đề admin đã giao cho bạn.' ?></p>
      </div>
    </div>

    <div class="assignment-filter">
      <input name="q" form="exam-filter-form" value="<?= e($q) ?>" placeholder="Tìm mã đề, tên đề, môn học...">
      <select name="subject_id" form="exam-filter-form">
        <option value="">Tất cả môn</option>
        <?php foreach ($subjects as $subject): ?>
          <option value="<?= e($subject['id']) ?>" <?= $subjectId === (int)$subject['id'] ? 'selected' : '' ?>><?= e($subject['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" form="exam-filter-form">
        <option value="">Tất cả trạng thái</option>
        <?php foreach (['draft' => 'Bản nháp', 'published' => 'Đã công bố', 'closed' => 'Đã đóng'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" form="exam-filter-form">Lọc</button>
    </div>

    <div class="exam-choice-list">
      <?php foreach ($exams as $exam): ?>
        <label class="exam-choice <?= $selectedExamId === (int)$exam['id'] ? 'selected' : '' ?>">
          <input type="radio" name="exam_id" value="<?= e($exam['id']) ?>" <?= $selectedExamId === (int)$exam['id'] ? 'checked' : '' ?> required>
          <span class="exam-choice-main">
            <strong><?= e($exam['code']) ?> - <?= e($exam['title']) ?></strong>
            <small><?= e($exam['subject_name']) ?> · <?= e($exam['duration']) ?> phút · <?= e($exam['total_questions']) ?> câu</small>
          </span>
          <span class="status-pill <?= e($exam['status']) ?>"><?= e(status_label($exam['status'])) ?></span>
        </label>
      <?php endforeach; ?>
      <?php if (!$exams): ?>
        <div class="empty-state">
          <span class="material-symbols-outlined">assignment</span>
          <strong>Chưa tìm thấy đề thi</strong>
          <p class="muted"><?= $isAdmin ? 'Hãy tạo đề mới từ ngân hàng câu hỏi.' : 'Bạn chưa có đề nào được admin giao hoặc đề do bạn tạo.' ?></p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="card assignment-recipients">
    <h2><?= $isAdmin ? 'Giao cho giáo viên' : 'Giao xuống học sinh' ?></h2>
    <div class="recipient-panel recipient-panel-full">
      <h3><?= $isAdmin ? 'Giáo viên nhận đề' : 'Học sinh nhận đề' ?></h3>
      <div class="recipient-list">
        <?php if ($isAdmin): ?>
          <?php foreach ($recipients as $recipient): ?>
            <label class="recipient-row">
              <input type="checkbox" name="recipients[]" value="<?= e($recipientRole) ?>:<?= e($recipient['id']) ?>">
              <span><strong><?= e($recipient['name']) ?></strong><small><?= e($recipient['email']) ?></small></span>
            </label>
          <?php endforeach; ?>
          <?php if (!$recipients): ?><p class="muted">Chua co giao vien active.</p><?php endif; ?>
        <?php else: ?>
          <?php foreach ($classes as $class): ?>
            <label class="recipient-row">
              <input type="checkbox" name="class_recipients[]" value="<?= e($class['id']) ?>">
              <span><strong><?= e($class['school_name'] . ' - ' . $class['grade_name'] . ' - ' . $class['name']) ?></strong><small><?= e($class['student_count']) ?> hoc sinh</small></span>
            </label>
          <?php endforeach; ?>
          <?php if (!$classes): ?><p class="muted">Chua co lop nao. Hay tao Truong - Khoi - Lop trong Quan ly tai khoan.</p><?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>Thời gian bắt đầu</label>
        <input type="datetime-local" name="start_at">
      </div>
      <div>
        <label>Hạn nộp</label>
        <input type="datetime-local" name="due_at">
      </div>
    </div>
    <label>Trạng thái</label>
    <select name="status">
      <option value="open">Đang mở</option>
      <option value="scheduled">Lên lịch</option>
      <option value="closed">Đã đóng</option>
    </select>
    <label class="check-row"><input type="checkbox" name="show_score" checked> Cho phép xem điểm sau khi nộp</label>
    <label class="check-row"><input type="checkbox" name="show_answers"> Cho phép xem đáp án</label>
    <button class="btn primary"><span class="material-symbols-outlined">send</span> <?= $isAdmin ? 'Giao cho giáo viên' : 'Giao cho học sinh' ?></button>
  </section>
</form>
<form id="exam-filter-form" method="get"></form>

<div class="card assigned-table">
  <h2><?= $isAdmin ? 'Đề đã giao cho giáo viên' : 'Đề được nhận và đã giao xuống' ?></h2>
  <div class="table-scroll">
    <table class="table">
      <tr>
        <th>ID</th>
        <th>Đề thi</th>
        <th>Người nhận</th>
        <th>Người giao</th>
        <th>Bắt đầu</th>
        <th>Hạn nộp</th>
        <th>Đã làm</th>
        <th>Trạng thái</th>
        <th></th>
      </tr>
      <?php foreach ($rows as $r): $rowStatus = assignment_status($r); $targetName = ($r['target_role'] ?? '') === 'class' ? trim(($r['target_school_name'] ?? '') . ' - ' . ($r['target_grade_name'] ?? '') . ' - ' . ($r['target_class_name'] ?? ''), ' -') : ($r['target_user_name'] ?: $r['target']); ?>
        <tr>
          <td><?= e($r['id']) ?></td>
          <td><strong><?= e($r['code']) ?></strong><br><span class="muted"><?= e($r['title']) ?></span></td>
          <td>
            <strong><?= e($targetName) ?></strong>
            <br><span class="muted"><?= e($r['target_role'] ?? 'group') ?><?= !empty($r['target_user_email']) ? ' · ' . e($r['target_user_email']) : '' ?></span>
          </td>
          <td><?= e($r['creator'] ?? '') ?></td>
          <td><?= e($r['start_at']) ?></td>
          <td><?= e($r['due_at']) ?></td>
          <td><?= e($r['submitted_count']) ?></td>
          <td><span class="status-pill <?= e($rowStatus) ?>"><?= e(status_label($rowStatus)) ?></span></td>
          <td class="actions">
            <a class="btn ghost" href="exam-view.php?id=<?= e($r['exam_id']) ?>">Xem đề</a>
            <?php if ($isAdmin || (int)$r['created_by'] === (int)$current['id']): ?>
              <form method="post"><button class="btn danger" name="delete" value="<?= e($r['id']) ?>" data-confirm="Xóa bài đã giao?">Xóa</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="9" class="muted">Chưa có đề thi nào trong luồng giao.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
